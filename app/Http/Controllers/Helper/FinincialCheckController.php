<?php
namespace App\Http\Controllers\Helper;


use App\Models\invoice;
use App\Models\students;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Auth;
use Illuminate\Support\Facades\DB;
use App\Models\GeneralSetting;
use App\Models\Universities;
use App\Models\LogFile;
use App\Http\Controllers\API\FinancialApiController as FinancialApiController;
use App\Models\API_PYMENT;
use App\Models\FinancialBank;
use App\Models\invoicedetails;
use App\Models\RequestLogeFile;
use Illuminate\Support\Facades\Log;

class FinincialCheckController extends Controller
{

    public function isMaster($student){

		try{

		//	dd($student->STUDENT_IDENT);
		$programm=collect(DB::connection('mysql1')->select('select financial_student_accounts.FOR_HIGH_STUDIES from financial_students
inner join financial_student_accounts on financial_student_accounts.ACCOUNT_ID=financial_students.CURENT_ACCOUNT_ID
WHERE financial_students.STUDENT_IDENT=?',[$student->STUDENT_IDENT]))->first();

		if($programm->FOR_HIGH_STUDIES==1)
			return 2; // معرف عند موبايل موني ان 2 دراسات عليا
		elseif($programm->FOR_HIGH_STUDIES==2)
		return 1;//  عند موبايل موني ان 1 دراسات اولية



		}catch(\Exception $e){
            Log::info("Exception IN  isMaster ERROR (" . $e . ")");
		}
	}
    public function check_symble($value, $flage)
    {
        if ($flage == 0) {
            $whiteListed = "\$\@\#\^\|\!\~\=\+\-\_\.\>\<\*\/";
        }
        if ($flage == 1) {
            $whiteListed = "\$\@\#\^\|\!\~\=\+\_\.\>\<\*\/";
        }
        if ($flage == 2) {
            $whiteListed = "\$\@\#\^\|\!\~\=\+\_\>\<\*\/";
        }
        //  echo $flage ."<br/>" ;
        //  echo $value ."<br/>" ;
        //echo $whiteListed ."<br/>";

        return preg_match('/[' . $whiteListed . ']/', $value);
    }
public function checkInput($input){
    foreach ($input as $key => $value) {
        $output = FALSE;
        if ($key == "BONDS_DATE") {
            $output = $this->check_symble($value, 1);
        } else if ($key != "TYPE") {
            $output = $this->check_symble($value, 0);
        }
        return $output;
    }
}

public function checkPaymentInput($input){
    foreach ($input as $key => $value) {
        $output = FALSE;
        if ($key == "BONDS_DATE") {
            $output = $this->check_symble($value, 1);
        } else if ($key == "PAYMENT") {

            $output = $this->check_symble($value, 2);
        } else {
            $output = $this->check_symble($value, 0);
        }
        return $output;
    }
}
public function repearDifferentBettweenApiPymentAndInvoice(){

    $diffrent=collect(DB::connection('mysql1')->select('select ap.API_PAYMENT_ID from financial_invoices as fi INNER JOIN api_payments as ap on fi.INVOICE_IDENT = ap.INVOICE_IDENT and fi.BONDS_ID = ap.BONDS_ID
where ap.PAYMENT_FLAG=0 and fi.PAYMENT_FLAG=1'));
if($diffrent->count()>0)
{
 foreach($diffrent as $value){
     $ApiPyment=API_PYMENT::on('mysql1')->findOrFail($value->API_PAYMENT_ID);
      $ApiPyment->setConnection('mysql1');
     $ApiPyment->PAYMENT_FLAG=1;
      $ApiPyment->save();

 }
}

}

public function CheckFininsial($invoice, $id, $bank_id,$paymentID,$univName,$univID,$type)
{
    $registerLogObject=new RegisterLogController();


    try {
		
		


        $Student =students::on('mysql1')->where('STUDENT_IDENT', $invoice->STUDENT_IDENT)->firstOrFail();

        $valudationResponce = (new FinancialApiController())->getInvoiceFinincialStatus($univID, $Student->STUDENT_IDENT, $Student->FACULTY_IDENT, $id, $bank_id,$paymentID);
		dd($valudationResponce);
             if($valudationResponce=='تم تعليق عملية طلب السداد!'){
                 $this->repearDifferentBettweenApiPymentAndInvoice();
                  $valudationResponce = (new FinancialApiController())->getInvoiceFinincialStatus($univID, $Student['student_id'], $Student['faculty_id'], $id, $bank_id,$paymentID);
             }

            $registerLogObject->registerFinincialResponce($valudationResponce,$invoice->INVOICE_IDENT,1,$univName);


        if (!is_string($valudationResponce) && !is_null($valudationResponce)) {

            if($type=='key')
			{
                return $valudationResponce->payment_key;
			}
			else
			{ return $valudationResponce;}
        } else {

            $registerLogObject->saveError($invoice->INVOICE_IDENT,888,'https://financial/api/route.php=== Responce' . $valudationResponce,88,555,$bank_id,99,$univName);
            return 99;

        }
    } catch (\Exception $e) {
			dd($e);
        $registerLogObject->saveError($invoice->INVOICE_IDENT,9999,'https://financial/api/route.php===' . $e,99,555,$bank_id,99,$univName);
        return 99;

    }
}


    public function checkUserAuth($banckID)
    {

        $banck = FinancialBank::on('mysql1')->where('BANK_ID', $banckID)->first();

        if ($banck->HAS_API_CONNECT == 1 && $banck->IS_ENABLE == 1) {
            return false;
        } else {
            return true;
        }
    }

    public function check_invoice_and_invoice_details($invoice)
    {


        $sum_invoice_details = collect(DB::connection('mysql1')->select('SELECT SUM(REAL_FEE_AMOUNT) as price FROM financial_invoices_details WHERE INVOICE_IDENT = ?', [$invoice->INVOICE_IDENT]))->first()->price;

        // dd($Fees_Cost);





        $INVOICE_COST = $invoice->REAL_FEE_AMOUNT;

        if ($sum_invoice_details === $INVOICE_COST)
           return true;
           else
           return false;
       }

    public function check_student_status($student_ident, $invoice_ident)
    {

        try {
            $resutlt = optional(collect(DB::connection('mysql1')->select(
                'SELECT Financial_GetStudentStatus(?) as msg',
                [
                    $student_ident

                ]
            ))->first())->msg;
            //    dd($resutlt);
            if (!is_null($resutlt)) {
                if ($resutlt == 1) {
                    return true;
                } else {
                    return false;
                }
            }
        } catch (\Exception $e) {
            $log_row = new LogFile;
            $log_row->setConnection('mysql1');
            $log_row->INVOICE_IDENT = $invoice_ident;
            $log_row->API_PAYMENT_ID = 9999999;
            $log_row->FININCIAL_FUNCTION = 'Financial_GetStudentStatus==' . $e;
            $log_row->RETURN_ERROR = 0;
            $log_row->MAIL_RETURN_ERROR = 551;
            $log_row->BANK_ID = 1;
            $log_row->TYPE_ID = 1;
            $log_row->save();


            return $this->sendError('#551', "Server process Internal Error ");
        }
    }
}
