<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Helper\ConnectionDriverController;
use App\Http\Controllers\Helper\FinincialCheckController;
use App\Http\Controllers\Helper\RegisterLogController;
use Illuminate\Http\Request;
use Validator;
use DB;
use Auth;
use App\Models\GeneralSetting;
use App\Models\Universities;
use App\Models\MailInvoice;
use App\Models\API_PYMENT;
use App\Models\invoice;
use App\Models\TotalMailInvoice;

class ReciveController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
    private $connectionDriver="";

    public $ministray="";
    public $univ="";
    public $univbranch="";
    public $shortinvoice="";
    public $longinvoice="";
    public $Error=0;

public function culculated(){
	
	$univ_with_finincial=[6,8,11];
	 $driverObject=new ConnectionDriverController();
	 $univ=$driverObject->get_active_univ();
	 foreach($univ as $key=>$value)
	 {
		if(in_array($value->UNID,$univ_with_finincial))
		{
		
		 $driverObject->reconnectDriver( $value->UNIV_DB_NAME);
		 
			 
			 foreach([1,2] as $currancy)
			 {
				
				 	$recourd=TotalMailInvoice::on('mysql1')->where('currancy_id', $currancy)->whereDate('date',date("Y-m-d"))->first();
			if($recourd)
	{
	
		  $apiTotalInvoice=invoice::on('mysql1')->where('PAYMENT_CURRENCY_ID', $currancy)->whereDate('BONDS_DATE',date("Y-m-d"))->count();
	      $apiAmountInvoice=invoice::on('mysql1')->where('PAYMENT_CURRENCY_ID', $currancy)->whereDate('BONDS_DATE',date("Y-m-d"))->sum(DB::raw('REAL_FEE_AMOUNT * EXCHANGE_PRICE'));
		//dd($recourd->Id);
		$_recourd=TotalMailInvoice::on('mysql1')->findOrFail($recourd->Id);
		$_recourd->setConnection('mysql1');
		$_recourd->sar_total_invoice=$apiTotalInvoice;
		$_recourd->sar_total_invoice_price=$apiAmountInvoice;
		$_recourd->save();
		
		//dd(TotalMailInvoice::on('mysql1')->findOrFail($recourd->Id));
		
	}else{
		
		         //  $user = Auth::user();
				   $apiTotalInvoice=invoice::on('mysql1')->where('PAYMENT_CURRENCY_ID', $currancy)->whereDate('BONDS_DATE',date("Y-m-d"))->count();
	               $apiAmountInvoice=invoice::on('mysql1')->where('PAYMENT_CURRENCY_ID', $currancy)->whereDate('BONDS_DATE',date("Y-m-d"))->sum(DB::raw('REAL_FEE_AMOUNT * EXCHANGE_PRICE'));
	
                    $bank_id= 99;

				    $totalInvoice=new TotalMailInvoice();
					$totalInvoice->setConnection('mysql1');
					$totalInvoice->total_invoice=0;
					$totalInvoice->total_invoice_price=0;
					$totalInvoice->sar_total_invoice=$apiTotalInvoice;
					$totalInvoice->sar_total_invoice_price=$apiAmountInvoice;
					$totalInvoice->date=date("Y-m-d");
					$totalInvoice->currancy_id=$currancy;
					$totalInvoice->bank_id=$bank_id;
					$totalInvoice->save();
					//dd($totalInvoice);
	}
			 }
	 }
	 }

	
return 'done'		;
}

public function totalDyileInvoice(Request $request) {
    $driverObject = new ConnectionDriverController();
    $input = $request->all();

    $validator = Validator::make($input, [
        'AMOUNT' => 'required|numeric|min:1',
        'INVOICE_TOTLE' => 'required|numeric|min:1',
        'UNIV_ID' => 'required|numeric|min:1',
        'CURANCY_ID' => 'required|numeric|min:1',
        'DATE' => 'required|date',
    ]);

    if (!$validator->fails()) {
        $connectionInfo = $driverObject->chooseUniv($input['UNIV_ID']);
        $driverObject->reconnectDriver($connectionInfo['connectionDriver'], $connectionInfo['type']);

        $date = date('Y-m-d', strtotime($input['DATE']));
        $currency = $input['CURANCY_ID'];
        $user = Auth::user();
        $bank_id = $user->bank_id;

        if ($connectionInfo['type'] == 0) {
            $connection = 'mysql1';
            $apiTotalInvoice = Invoice::on('mysql1')
                ->where('PAYMENT_CURRENCY_ID', $currency)
                ->where('bank_id', $bank_id)
                ->where('BONDS_DATE', $date)
                ->count();

            $apiAmountInvoice = Invoice::on('mysql1')
                ->where('PAYMENT_CURRENCY_ID', $currency)
                ->where('bank_id', $bank_id)
                ->where('BONDS_DATE', $date)
                ->sum(DB::raw('REAL_FEE_AMOUNT * EXCHANGE_PRICE'));
        } else {
            $connection = 'center';
            $apiTotalInvoice = DB::connection('center')
                ->table('invoices')
                ->where('payment_currency_id', $currency)
                ->where('bound_date', $date)
                ->count();

            $apiAmountInvoice = DB::connection('center')
                ->table('invoices')
                ->where('payment_currency_id', $currency)
                ->where('bound_date', $date)
                ->sum(DB::raw('amount * exchange_price'));
        }

        // **Check if a record already exists for the given date, currency, and bank ID**
        $totalInvoice = TotalMailInvoice::on($connection)
            ->where('date', $date)
            ->where('currancy_id', $currency)
            ->where('bank_id', $bank_id)
            ->first();

        if ($totalInvoice) {
            // **Update the existing record**
            $totalInvoice->total_invoice = $input['INVOICE_TOTLE'];
            $totalInvoice->total_invoice_price = $input['AMOUNT'];
            $totalInvoice->sar_total_invoice = $apiTotalInvoice;
            $totalInvoice->sar_total_invoice_price = $apiAmountInvoice;
            $totalInvoice->save();
        } else {
            // **Create a new record if it doesn't exist**
            $totalInvoice = new TotalMailInvoice();
            $totalInvoice->setConnection($connection);
            $totalInvoice->total_invoice = $input['INVOICE_TOTLE'];
            $totalInvoice->total_invoice_price = $input['AMOUNT'];
            $totalInvoice->sar_total_invoice = $apiTotalInvoice;
            $totalInvoice->sar_total_invoice_price = $apiAmountInvoice;
            $totalInvoice->date = $date;
            $totalInvoice->currancy_id = $currency;
            $totalInvoice->bank_id = $bank_id;
            $totalInvoice->save();
        }

        if ($input['INVOICE_TOTLE'] == $apiTotalInvoice && $apiAmountInvoice == $input['AMOUNT']) {
            return $this->sendResponse("1", "#200");
        } else {
            return $this->sendResponse("0", "#200");
        }
    } else {
        return $this->sendError('#502', " ");
    }
}

    public function reciveInvoices(Request $request){

        $input = $request->all();
        
        $validator = Validator::make($input, ['data' => 'required|json']);
       
        if (!$validator->fails() && $this->json_validate($request->data)) {

            foreach (json_decode($request->data,true) as $key => $value) {
               
                if($this->checkJsonData($value))
                {
                
                   if( !$this->storeJsonData($value))
                   {
                       $this->Error++;
                   }
                }else{
					    $this->Error++;
				   }


            }
            if($this->Error==0)
            {
                return $this->sendResponse('تم الارسال بنجاح', '#210 ');
            }else{

                return $this->sendError('#590', ' ');
            }

        }else{

                return $this->sendError('#590', ' ');
            }


    }

    public function storeJsonData($value){
        $driverObject=new ConnectionDriverController();
       
try{
        $connectionInfo=$driverObject->redirect_db($value['invoiceId']);

       //dd($connectionInfo);
            $id = $connectionInfo['id'];
            \Log::error($id);
			//	dd( $id);
        if(!is_numeric($id))
        {
            return false;
        }

        ($connectionInfo['type']==0)?$connection='mysql1':$connection='center';

         $driverObject->reconnectDriver($connectionInfo['connectionDriver'],$connectionInfo['type']);


         MailInvoice::on($connection)->updateOrCreate(
            ['INVOICE_IDENT' => $id], // Search criteria
            [
                'PAYMENT' => $value['payment'],
                'BOUND_ID' => $value['boundId'],
                'BOUND_DATE' => $value['boundDate']
            ]
        );



}catch(\Exception  $e){
\Log::error('error in ReciveController line 258: ' . $e->getMessage());
    return false ;
}

return true;
    }
    public function checkJsonData($data){

       $invoiceID= $data['invoiceId'] ?? 0;
       $invoicePayment= $data['payment']?? 0;
       $boundID= $data['boundId'] ?? 0;
       $boundDate= $data['boundDate']  ?? "";
       // dd($data);
       if( $invoiceID!=0 &&  $invoicePayment !=0 &&  $boundID !=0 &&  $boundDate !="")
       {
           return true;
       }
       return false;
    }
    public function is_json($input_line) {
        if(is_string($input_line)){
            preg_match('/^\{(\s+|\n+)*(\"(.*)(\n+|\s+)*)*\}$|^\[(\s+|\n+)*\{(\s+|\n+)*(\"(.*)(\n+|\s+)*)*\}(\s+|\n)*\]$/', $input_line, $output_array);
            if (  isset($output_array) || !empty($output_array)) {
                return true;
            }
        }
        return false;
        }
    public   function json_validate($string) {
        // decode the JSON data

   // dd( $this->is_json($string));
      if($this->is_json($string))
       {
        $result = json_decode($string,TRUE);
        // switch and check possible JSON errors
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $error = ''; // JSON is valid // No error has occurred
                break;
            case JSON_ERROR_DEPTH:
                $error = 'The maximum stack depth has been exceeded.';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Invalid or malformed JSON.';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Control character error, possibly incorrectly encoded.';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON.';
                break;
            // PHP >= 5.3.3
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_RECURSION:
                $error = 'One or more recursive references in the value to be encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_INF_OR_NAN:
                $error = 'One or more NAN or INF values in the value to be encoded.';
                break;
            case JSON_ERROR_UNSUPPORTED_TYPE:
                $error = 'A value of a type that cannot be encoded was given.';
                break;
            default:
                $error = 'Unknown JSON error occured.';
                break;
        }
       
        if ($error !== '') {
            // throw the Exception or exit // or whatever :)
            return false;
        }
        // everything is OK
        return true;
        }else{
          return false;
          }
}

public function getshortinvoice($invoice_ident){


    return   substr($invoice_ident, 5, strlen($invoice_ident));

}
  public function get_active_univ()
    {
        try {

            $univs = Universities::where('IS_IT_ENABLE', 1)->get(['UNIV_DB_NAME', 'UNID', 'MINISTRY_ID', 'BRANSH_ID']);
            //  $univs=Universities::where('IS_IT_ENABLE', 1)->pluck('UNIV_DB_NAME','UNID')->toArray();

            //	if(sizeof($univs)>0) { return $univs;}else{return null; }
            if ($univs->count() > 0) {
                return $univs;
            } else {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }
    }
public function chooseUniv($univ_id){
	$univ_id=substr($univ_id, 0, 2);
	//dd((int)str_replace('0','',substr($univ_id, 0, 2)));
	  $univ_DB=$this->get_active_univ();
	  foreach($univ_DB as  $key => $value)
    {

       if($univ_id==$key)
       {

        $this->connectionDriver=$value;
       }
    }
}




}
