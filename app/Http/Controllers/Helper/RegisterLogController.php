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
use App\Http\Controllers\API\FinancialApiController as FinancialApiController;
use App\Models\FinincialLogeFile;
use App\Models\LogFile;
use App\Models\PaymentLogeFile;
use App\Models\RequestLogeFile;
use Illuminate\Support\Facades\Log;

class RegisterLogController extends Controller
{

   public  $logConnection='mysql1';
    public function registerFinincialResponce($Responce,$invocieID,$type,$univName){

        $log_row = new FinincialLogeFile();
        $log_row->setConnection($this->logConnection);
        $log_row->setTable('api_responce_finincial_api_log'.$univName);
        $log_row->INVOICE_ID = $invocieID;
        $log_row->RESPONCE = json_encode( $Responce);
        $log_row->TYPE = $type;
        $log_row->save();

}
    public function register_RequestLogeFile($INVOICE_IDENT, $TYPE, $Flage,$univName)
    {

        $RequestLogeFile = new RequestLogeFile();
        $RequestLogeFile->setConnection($this->logConnection);
        $RequestLogeFile->setTable('api_request_log_file'.$univName);
        $RequestLogeFile->INVOICE_IDENT = $INVOICE_IDENT;
        $RequestLogeFile->TYPE = $TYPE;
        $RequestLogeFile->Flage = $Flage;
        $RequestLogeFile->save();

    }
    public function update_RequestLogeFile($univName,$error)
    {
        $Request=DB::connection($this->logConnection)->table('api_request_log_file'.$univName)->orderBy('id', 'DESC')->first();
		DB::connection($this->logConnection)->table('api_request_log_file'.$univName)->where('id',$Request->id)->update(['error'=>1]);

    }
    public function saveError($INVOICE_IDENT,$API_PAYMENT_ID,$FININCIAL_FUNCTION,$RETURN_ERROR,$MAIL_RETURN_ERROR,$BANK_ID,$TYPE_ID,$univName){
        $log_row = new LogFile();
        $log_row->setConnection($this->logConnection);
        $log_row->setTable('api_log_file'.$univName);
        $log_row->INVOICE_IDENT =$INVOICE_IDENT;
        $log_row->API_PAYMENT_ID = $API_PAYMENT_ID;
        $log_row->FININCIAL_FUNCTION = $FININCIAL_FUNCTION;
        $log_row->RETURN_ERROR = $RETURN_ERROR;
        $log_row->MAIL_RETURN_ERROR = $MAIL_RETURN_ERROR;
        $log_row->BANK_ID = $BANK_ID;
        $log_row->TYPE_ID = $TYPE_ID;
        $log_row->save();

        Log::info("Exception Whene use " .$FININCIAL_FUNCTION);
    }

    public function update_PaymentLogeFile($univName,$error)
    {
        $PaymentLogeFile = PaymentLogeFile::on('mysql1')->orderBy('id', 'DESC')->first();

        $PaymentLogeFile->Flage = 1;
        $PaymentLogeFile->save();
    }



    public function register_PaymentLogeFile($INVOICE_IDENT, $BONDS_ID, $BONDS_DATE, $PAYMENT_BY, $PAYMENT,$univName)
    {
        $PaymentLogeFile = new PaymentLogeFile;
        $PaymentLogeFile->setConnection($this->logConnection);
		$PaymentLogeFile->setTable('api_payment_log_file'.$univName);
        $PaymentLogeFile->INVOICE_IDENT = $INVOICE_IDENT;
        $PaymentLogeFile->BONDS_ID = $BONDS_ID;
        $PaymentLogeFile->BONDS_DATE = $BONDS_DATE;
        $PaymentLogeFile->PAYMENT_BY = $PAYMENT_BY;
        $PaymentLogeFile->PAYMENT = $PAYMENT;
        $PaymentLogeFile->Flage = 0;

        $PaymentLogeFile->save();
    }
}
