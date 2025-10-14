<?php


namespace App\Http\Controllers\API;

use http\Env\Response;
use Illuminate\Http\Request;
use Validator;
use Collection;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Resources\Post as PostResource;
use App\Models\financialfees;
use App\Models\invoice;
use App\Models\invoicecancel;
use App\Models\invoicedetails;
use App\Models\studentbill;
use App\Models\students;
use App\Models\PaymentUser;
use App\Models\FinancialAccounts;
use App\Models\FinancialBank;
use App\Models\FinancialBankFees;
use App\Models\FinancialAccountFees;
use Auth;
use App\Models\LogFile;
use App\Models\Universities;
use App\Models\CenterUniversities;

use App\Models\API_FEES_PAYMENT;
use App\Models\PaymentLogeFile;
use App\Models\RequestLogeFile;
use App\Models\GeneralSetting;
use App\Models\CENTER_API_PYMENT;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\FinancialApiController as FinancialApiController;
use Exception;

class CenterPaymentController extends Controller
{
    public static function gettoday()
    {
        $date = date('Y-m-d');
        $newdate = strtotime($date);
        $newdate = date('Y-m-d', $newdate);

        return $newdate;
    }
    public  static function initCurlRequest($reqURL)
    {
        $ch = curl_init();
        $headers = array();

        curl_setopt($ch, CURLOPT_URL, $reqURL);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $body = curl_exec($ch);

        //$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // extract header
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        // extract body
        $body = substr($body, $headerSize);

        curl_close($ch);

        return $body;
    }
    public static function checkUserAuth($banckID)
    {

        $banck = DB::connection('center')->table('financial_banks')->where('id', $banckID)->first();

        if ($banck) {
            if ($banck->api_connection == 1 && $banck->is_enable == 1) {
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }
    public static function sendSuccess($result, $message)
    {

        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];
        Log::info("send seccuss to payment " . "  seccuss code (" . $message . ")");

        return json_encode($response);
    }


    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public static function send_error($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,

        ];


        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
            $response['message'] = $error;
        }

        Log::info("send error to paymwnt " . "  ERROR code (" . $error . ")");
        return json_encode($response);
    }

    public static function payInvoice($invoice_id, $bank_id, $data, $database)
    {
        if (self::checkUserAuth($bank_id)) {
            return self::send_error('#203', "غير مخول بسداد لهذه الجامعة");
        }

        DB::purge('center');
        config(['database.connections.center.database' => $database]);
        DB::reconnect('center');

        // Fetch invoice data including related details in one go
        $invoice = DB::connection('center')
            ->table('invoices')
            ->where('invoice_number', $invoice_id)
            ->where('payment_flag', 0)
            ->first();

        if (!$invoice) {
            return self::send_error('#514', "يرجى مراجعة الكلية");
        }

        if (!self::checkInvoiceWithDetails($invoice)) {
            return self::send_error('#506', " ");
        }

        if ($invoice->deadline < self::gettoday()) {
            return self::send_error('#516', " ");
        }

        return self::checkPaymentRequestData($invoice, $data, $bank_id);
    }
    public static function checkInvoiceWithDetails($invoice)
    {
        $totalAmount = DB::connection('center')
            ->table('invoice_details')
            ->where('invoice_id', $invoice->invoice_number)
            ->sum('amount');
         
        return $totalAmount == $invoice->amount;
    }
    public static function checkPaymentRequestData($invoice, $requestData, $bank_id)
    {
        $invoicedate = date('Y-m-d', strtotime($invoice->created_at));
        $date_input = date('Y-m-d', strtotime($requestData['BONDS_DATE']));

        if ($invoicedate > $date_input || $date_input != self::gettoday()) {
            return self::send_error('#518', " ");
        }

        // Check if there's an existing bound_id
        $existingInvoice = DB::connection('center')
            ->table('invoices')
            ->where('bound_id', $requestData['BONDS_ID'])
            ->exists();

        if ($existingInvoice) {
            return self::send_error('#510', " ");
        }

        if ($invoice->amount != $requestData['PAYMENT']) {
            return self::send_error('#503', " ");
        }

        $apiPayment = DB::connection('center')
            ->table('api_payments')
            ->where('bound_id', $requestData['BONDS_ID'])
            ->first();

        if ($apiPayment) {
            if ($apiPayment->payment_flag == 1) {
                return self::send_error('#510', " ");
            }

            DB::connection('center')
                ->table('api_payments')
                ->where('id', $apiPayment->id)
                ->update([
                    'student_id' => $invoice->student_id,
                    'invoice_ident' => $invoice->invoice_number,
                    'bank_id' => $bank_id,
                    'amount' => $requestData['PAYMENT'],
                    'bound_date' => $date_input,
                    'payment_by' => $requestData['PAYMENT_BY'],
                    'actual_payment_date' => Carbon::now(),
                    'payment_flag' => 0,
                ]);

            Log::info("Updated api_payment for invoice_number: {$invoice->invoice_number}");
        } else {
            $apiPayment = DB::connection('center')
                ->table('api_payments')
                ->insertGetId([
                    'student_id' => $invoice->student_id,
                    'invoice_ident' => $invoice->invoice_number,
                    'bank_id' => $bank_id,
                    'amount' => $requestData['PAYMENT'],
                    'bound_id' => $requestData['BONDS_ID'],
                    'bound_date' => $date_input,
                    'payment_by' => $requestData['PAYMENT_BY'],
                    'actual_payment_date' =>Carbon::now(),
                    'payment_flag' => 0,
                ]);

            Log::info("Created api_payment for invoice_number: {$invoice->invoice_number}");
        }

        if (self::realPayments($invoice->invoice_number)) {
            DB::connection('center')
                ->table('api_payments')
                ->where('id', $apiPayment)
                ->update(['payment_flag' => 1]);

            $send_invoice = self::get_invoice($invoice, 0);
            return self::sendSuccess($send_invoice, "#101");
        } else {
            return self::send_error('#514', " ");
        }
    }
   
    public static function realPayments($invoiceNumber)
    {
        if (empty($invoiceNumber)) {
            return false;
        }

        $prefix_invoice = GeneralSetting::on('center')->Api()->select('SETTING_VALUE')->first()->SETTING_VALUE;
        $longinvoice = $prefix_invoice . $invoiceNumber;
        $univ_id = substr(self::getuniv($longinvoice), 0, 2);
       
        $url = "sanaa.cyemen.localhost/getInvoiceResponse/{$invoiceNumber}/{$univ_id}";
            Log::info("==============URL: {$url}");
        try {
            $response = self::initCurlRequest($url);
       
            
            Log::info("Response from API in realPayments function: {$response}");

            $resultMessage = json_decode($response)->payment_state ?? null;
            return $resultMessage === 'success';
        } catch (Exception $e) {
            Log::error("Error in realPayments function: {$e->getMessage()}");
            return false;
        }
    }
   
public static function Verification($invoice_ident, $bank_id, $is_details, $database)
{
    DB::purge('center');
    config(['database.connections.center.database' => $database]);
    DB::reconnect('center');

    if (self::checkUserAuth($bank_id)) {
        return self::send_error('#203', "غير مخول بسداد لهذه الجامعة");
    }

   

    Log::info("in Verification function for invoice_number ({$invoice_ident})");

    // Fetch invoice and related payment details in one go
    $invoice = DB::connection('center')->table('invoices')
        ->where('invoice_number', $invoice_ident)
        ->where('payment_flag', 1)
        ->first();
        if (!$invoice) {
             return self::sendSuccess("0", "101");
        }else{
                 return self::sendSuccess("1", "101");
        }

}
public static function getInvoiceData($invoice_ident, $bank_id, $is_details, $database)
{
    DB::purge('center');
    config(['database.connections.center.database' => $database]);
    DB::reconnect('center');

    if (self::checkUserAuth($bank_id)) {
        return self::send_error('#203', "غير مخول بسداد لهذه الجامعة");
    }

   

    Log::info("in getInvoiceData function for invoice_number ({$invoice_ident})");

    // Fetch invoice and related payment details in one go
    $invoice = DB::connection('center')->table('invoices')
        ->where('invoice_number', $invoice_ident)
        ->where('payment_flag', 0)
        ->first();
    
    if (!$invoice) {
        return self::send_error('#514', "يرجى مراجعة الكلية");
    }

    if (self::checkApiPayment($invoice) || !self::checkInvoiceWithDetails($invoice)) {
        return self::send_error('#514', "يرجى مراجعة الكلية");
    }

    if ($invoice->deadline < self::gettoday()) {
        return self::send_error('#516', " ");
    }

    $send_invoice = self::get_invoice($invoice, $is_details);

    return self::sendSuccess($send_invoice, "#101");
}

public static function checkApiPayment($invoice)
{
    Log::info("in checkApiPayment function for invoice_number ({$invoice->invoice_number})");

    // Check for existing API payment in one query
    $apiPaymentExists = DB::connection('center')->table('api_payments')
        ->where([
            ['invoice_ident', '=', $invoice->invoice_number],
            ['student_id', '=', $invoice->student_id],
            ['payment_flag', '=', 1],
            ['amount', '=', $invoice->amount]
        ])
        ->exists();

    return $apiPaymentExists;
}


public static function get_invoice($invoice, $is_details)
{
    $student = DB::connection('center')->table('students')
        ->where('id', $invoice->student_id)
        ->first();

    $prefix_invoice = GeneralSetting::on('center')->Api()->select('SETTING_VALUE')->first()->SETTING_VALUE;
    $longinvoice = $prefix_invoice . $invoice->invoice_number;
    Log::info("in get_invoice function for invoice_number ({$invoice->invoice_number}), details = {$is_details}");

    $invoiceDetailsQuery = DB::connection('center')->table('invoice_details')
        ->select(
            'fee_details.id AS sub_category_id',
            'fee_details.amount AS fee_cost',
            'invoice_details.session_id',
            'invoice_details.invoice_id',
            'fees.fee_status',
            'fees.created_at AS fee_date',
            'courses.id AS course_id',
            'courses.course_name',
            'school_classes.id AS class_id',
            'school_classes.class_name',
            'fees.id AS main_category_id',
            'fees.fee_name AS fee_name'
        )
        ->leftJoin('fee_details', 'fee_details.id', '=', 'invoice_details.fee_detail_id')
        ->leftJoin('fees', 'fees.id', '=', 'fee_details.fee_id')
        ->leftJoin('courses', 'courses.id', '=', 'fee_details.course_id')
        ->leftJoin('school_classes', 'school_classes.id', '=', 'courses.class_id')
        ->where('invoice_details.invoice_id', $invoice->invoice_number);

    if ($is_details) {
        $invoiceDetails = $invoiceDetailsQuery->get();
        $details = [];
        foreach ($invoiceDetails as $detail) {
            $details[] = [
                'invoice_id' => $longinvoice,
                'fee_name' => $detail->fee_name,
                'fee_CURRENCY' => $detail->currency ?? 1,
                'exchang' => $detail->exchang ?? 1,
                'fee_cost' => round($detail->fee_cost, 3),
                'level' => 1,
                'main_category_id' => $detail->main_category_id,
                'sub_category_id' => $detail->sub_category_id,
                'fee_state' => $detail->fee_status,
                'fee_date' => $detail->fee_date,
                'fee_account_distribution' => []
            ];
        }
       
        return $details;
    } else {
        $invoiceDetail = $invoiceDetailsQuery->first();
        return [
            'invoice_id' => $longinvoice,
            'student_name' => $student->full_name,
            'student_id' => $invoice->student_id,
            'education_type' => 3,
            'invoice_cost' => round($invoice->amount, 3),
            'invoice_payment' => round($invoice->payment_flag == 0 ? 0 : $invoice->amount, 3),
            'invoice_state' => $invoice->payment_flag,
            'user_id' => $invoice->payment_by,
            'currancy' => 1,
            'bound_id' => $invoice->bound_id,
            'university_id' => self::getuniv($longinvoice),
            'university_name' => self::getunivName($longinvoice),
            'faculty_id' => self::getFaculty($invoice->class_id)[0] ?? null,
            'faculty_name' => self::getFaculty($invoice->class_id)[1] ?? null,
            'program_id' =>$invoice->class_id ?? null,
            'program_name' =>self::getProgram($invoice->class_id) ?? null,
            'year' => date('Y'),
            'bound_date' => $invoice->bound_date,
            'payment_date' => $invoice->actual_payment_date
        ];
    }
}

    public static function getuniv($invoice_ident)
    {
        return substr($invoice_ident, 1, 4);
    }
    public static function getunivName($invoice_ident)
    {
        //$minstryID = self::getministry($invoice_ident);
        //$branshID = self::getunivbransh($invoice_ident);
        $univID = substr($invoice_ident, 1, 4);
        $id = substr($univID, 0, 2);

        //dd(DB::connection('center')->table('universities')->where('id', $id)->first());
        // dd(Universities::where('UNID',$id)->where('BRANSH_ID','=',$branshID)->first());

        $name = DB::connection('center')->table('universities')->where('id', $id)->first()->unv_name;

        return $name;
    }

    public static function getFaculty($id){
        if(!$id)
        return null;

        $center = DB::connection('center')->table('centers')
        ->join('school_classes', 'school_classes.center_id', '=', 'centers.id')
        ->where('school_classes.id', $id)->first();
        
    
       return  [$center->id,$center->center_name];

    
    }
    public static function getProgram($id){

        if(!$id)
        return null;
//dd(DB::connection('center')->table('courses')->select('course_name')->where('id',$id)->first());
return  DB::connection('center')->table('school_classes')->select('class_name')->where('id',$id)->first()->class_name;

    }
}
