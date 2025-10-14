<?php

namespace App\Services;

use  App\Http\Controllers\API\GraduateStudiesPortalController;
use  App\Http\Controllers\API\CenterPaymentController;
use App\Models\API_PYMENT;
use App\Models\FinancialAccountFees;
use App\Models\FinancialBank;
use App\Models\invoice;
use App\Models\invoicedetails;
use App\Models\PaymentLogeFile;
use App\Models\RequestLogeFile;
use App\Models\studentbill;
use App\Models\students;
use App\Models\FinancialBankFees;
use App\Models\FinancialAccounts;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InvoiceService
{
    protected $connectionDriver;
    protected $paymentType = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->connectionDriver = env('DB_DATABASE_FINANCIAL', 'financial');
    }

    /**
     * Obtener detalles de una factura
     *
     * @param Request $request
     * @param ValidationService $validationService
     * @return array
     */
    public function getInvoiceDetails(Request $request, ValidationService $validationService)
    {
        try {
            $input = $request->all();
            $redirectConnectionService = new RedirectConnectionService();
         
            
        
            
            // Obtener usuario y banco
            $user = Auth::user();
            $bankId = $user->bank_id;
            
            // Redirigir a la base de datos correcta
            $responce = $redirectConnectionService->redirect_db($input['INVOICE_IDENT']);
        
           
            $invoiceId = $responce['id'];
            if (!is_string($invoiceId)) {
                return ['status' => 'error', 'code' => '#504', 'message' => ' '];
            }
          
            if($responce['paymentType']==1)
            {
                
               return CenterPaymentController::getInvoiceData($invoiceId,$bankId,1,$responce['connectionDriver']);

            }else if($responce['paymentType']==2)
            {
                 $result=new GraduateStudiesPortalController();
             
                    return $result->getInvoiceData($invoiceId,$bankId,1);
              
            }
           
             $redirectConnectionService->setupDatabaseConnection($responce['connectionDriver']);
            // Verificar autorización del usuario
            if (!$validationService->checkUserAuthorization($bankId)) {

                return ['status' => 'error', 'code' => '#203', 'message' => 'غير مخول بسداد لهذه الجامعة'];
            }
        
            if (!is_numeric($invoiceId)) {
              
                return $redirectConnectionService->redirect_db($input['INVOICE_IDENT']);
            }
                
            // Registrar solicitud de log
            $this->registerRequestLogFile($invoiceId, 0, 2,$responce['connectionDriver']);
          
          
            
            // Verificar si la factura existe
            if (invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->count() == 0) {
                  
                 $this->updateRequestLogFile($invoiceId,$responce['connectionDriver']);
                return ['status' => 'error', 'code' => '#504', 'message' => ' '];
            }
            
            $invoice = invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->get()->last();
         
            // Verificar estado del estudiante
            if (!$this->checkStudentStatus($invoice->STUDENT_IDENT, $invoiceId)) {
                  $this->updateRequestLogFile($invoiceId,$responce['connectionDriver']);
                return ['status' => 'error', 'code' => '#504', 'message' => ' '];
            }
            
            // Determinar tipo de factura
            $invoiceType = "";
            try {
                $invoiceType = $input['TYPE'];
            } catch (\Throwable $th) {
                // Tipo no especificado
            }
           
            // Verificar si la factura tiene detalles
            if (invoicedetails::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->count() == 0) {
                  $this->updateRequestLogFile($invoiceId,$responce['connectionDriver']);
                return ['status' => 'error', 'code' => '#505', 'message' => ' '];
            }
            
            $invoiceDetails = invoicedetails::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->first();
         
            if ($invoiceType == "") {
                $invoiceType = $invoiceDetails->PAYMENT_FLAG;
            }
            
            $this->registerRequestLogFile($invoiceId, $invoiceType, 2,$responce['connectionDriver']);
             
            // Procesar según el tipo de factura
            if ($invoiceType == 0) {
                return $this->getUnpaidInvoiceDetails($invoiceId, $input,$responce['connectionDriver']);
            } elseif ($invoiceType == 1) {
                return ['status' => 'error', 'code' => '#508', 'message' => ' '];
                // El código para facturas pagadas está comentado en el original
            } else {
                $this->updateRequestLogFile($invoiceId,$responce['connectionDriver']);
                return ['status' => 'error', 'code' => '#508', 'message' => ' '];
            }
            
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles de factura: ' . $e->getMessage());
            return ['status' => 'error', 'code' => '#551', 'message' => 'Server process Internal Error'];
        }
    }
      public function redirect_db($longInvoice)
    {
        $this->longinvoice = $longInvoice;
         $this->paymentType = 0;
        // Check if this is a graduate studies portal invoice
        if (substr((string)$longInvoice, 0, 5) === '10001') {
            $this->paymentType = 2;
            return substr($longInvoice, 5, strlen($longInvoice));
        }
        
        $minstry = $this->getministry($this->longinvoice);
        $univ = $this->getuniv($this->longinvoice);
        $univ_sub = $this->getunivbransh($this->longinvoice);
        $id = $this->getshortinvoice($this->longinvoice);
       


        // Determine if this is a center payment
        $type = 0;
        if ((int)$univ_sub > 50 && (int)$univ_sub < 60) {
            Log::error("type" . $type);
            $type = 1;
        }
        
        
        $univ_DB = $this->get_active_univ($type);
        
        $univ_id = (int)substr($univ, 0, 2);
        $this->univ = $univ_id;

        try {
            foreach ($univ_DB as $value) {
                if ($type == 0) {
                    // Regular university payment
                    if (($univ_id == $value->UNID) && ($minstry == $value->MINISTRY_ID) && ($univ_sub == $value->BRANSH_ID)) {
                        $this->connectionDriver = $value->UNIV_DB_NAME;
                    }
                } else {
                    // Center payment
                    if ($univ_id == $value->id) {
                        $this->connectionDriver = $value->DATABASE_NAME;
                        $this->paymentType = 1;
                    }
                }
            }
             
        } catch (\Exception $e) {
            Log::error("type" . $e->getMessage());
            return $this->sendError('#504', " ");
        }
        
        $request_prefix = $minstry . $univ;
        Log::error(" this->connectionDriver" . $this->connectionDriver);
        Log::error(" this->paymentType" . $this->paymentType);
        
        if ($type == 0) {
            // Regular university payment validation
            Log::error("---type0");
            Log::error("============" . $request_prefix);
            Log::error("============" . $this->get_invoice_prifex());
            
            if (!($this->get_invoice_prifex() == $request_prefix)) {
                return $this->sendError('#504', " ");
            }
            return $id;
        } else {
            // Center payment validation
            Log::error("---type1");
            
            if (!($this->get_center_invoice_prifex() == $request_prefix)) {
                return $this->sendError('#504', " ");
            }
            return $id;
        }
    }

    /**
     * Obtener detalles de una factura no pagada
     *
     * @param string $invoiceId
     * @param array $input
     * @return array
     */
    protected function getUnpaidInvoiceDetails(string $invoiceId, array $input,$database): array
    {

        // Verificar si la factura tiene detalles no pagados
        if (invoicedetails::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->where('PAYMENT_FLAG', 0)->count() == 0) {
             $this->updateRequestLogFile($invoiceId,$database);
            return ['status' => 'error', 'code' => '#513', 'message' => ' '];
        }
         
        if (API_PYMENT::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->where('PAYMENT_FLAG', 99)->count() > 0) {
           // $this->updateRequestLogFile();
            return ['status' => 'error', 'code' => '#551', 'message' => 'Server process Internal Error'];
        }
     
        // Verificar si la factura no ha expirado
        if (invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->whereDate('DEADLINE', '>=', $this->getToday())->count() == 0) {
           $this->updateRequestLogFile($invoiceId,$database);
            return ['status' => 'error', 'code' => '#516', 'message' => ' '];
        }
        
        // Obtener detalles de la factura
        $invoiceDetail = invoicedetails::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->where('PAYMENT_FLAG', 0)->get();
        $sendInvoiceDetail = collect();
        
        // Obtener información del estudiante
        $studentIdent = invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->first()->STUDENT_IDENT;
        $studentFaculty = students::on('mysql1')->where('STUDENT_IDENT', $studentIdent)->first()->FACULTY_IDENT;
        
        // Procesar cada detalle de la factura
        foreach ($invoiceDetail as $value) {
            $studentBill = studentbill::on('mysql1')->where('BILL_ID', $value->BILL_ID)->first();
            $fee = $studentBill->fees;
            $feeDetails = $studentBill->feesDetails;
            
            $rosumJSON = FinancialAccountFees::on('mysql1')
                ->where('FEES_ID', $studentBill->FEES_ID)
                ->where('FACULTY_IDENT', $studentFaculty)
                ->first();
            
            $user = Auth::user();
            $bankId = $user->bank_id;
            $rosumJSON = (empty($rosumJSON->ACCOUNTS_JSON)) ? "" : $rosumJSON->ACCOUNTS_JSON;
            
            $accountJson = $this->getBankDistributed($rosumJSON, $bankId, $studentBill->PAYMENT_CURRENCY_ID);
            
            $sendInvoiceDetail->push([
                'invoice_id' => $input['INVOICE_IDENT'],
                'fee_name' => $fee->FEES_NAME,
                'fee_CURRENCY' => $feeDetails->CURRENCY_ID,
                'exchang' => $value->EXCHANGE_PRICE,
                'fee_cost' => ($value->REAL_FEE_AMOUNT) * ($value->EXCHANGE_PRICE),
                'level' => $studentBill->S_LEVEL,
                'main_category_id' => $fee->FMF_IDENT,
                'sub_category_id' => $fee->FEES_CODE,
                'fee_state' => $value->PAYMENT_FLAG,
                'fee_date' => $value->RECORDED_ON,
                'fee_account_distribution' => $accountJson,
            ]);
        }
        
        return ['status' => 'success', 'code' => '#102', 'data' => $sendInvoiceDetail->toArray()];
    }
    
    /**
     * Configurar la conexión a la base de datos
     */
  
    
    
 
    
    /**
     * Obtener la fecha actual en formato Y-m-d
     *
     * @return string
     */
    public function getToday(): string
    {
        $date = date('Y-m-d');
        $newdate = strtotime($date);
        $newdate = date('Y-m-d', $newdate);
        
        return $newdate;
    }
    
    /**
     * Verificar el estado del estudiante
     *
     * @param string $studentIdent
     * @param string $invoiceIdent
     * @return bool
     */
    public function checkStudentStatus(string $studentIdent, string $invoiceIdent): bool
    {
        try {
            $result = optional(collect(DB::connection('mysql1')->select(
                'SELECT Financial_GetStudentStatus(?) as msg',
                [
                    $studentIdent
                ]
            ))->first())->msg;
            
            if (!is_null($result)) {
                if ($result == 1) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Error al verificar estado del estudiante: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener la distribución bancaria
     *
     * @param string $rosumJSON
     * @param int $bankId
     * @param int $currencyId
     * @return array
     */
        public function getBankDistributed($json, $bankID, $pyment_currency)
    {


    
        //========= when distributed is off one account ============
        $accountsAfterFilter = collect();
        $account = FinancialBankFees::on('mysql1')->where('BANK_ID', '=', $bankID)->where('ACCOUNT_ID', '=', "0")->where('IS_ENABLE', 1)->get();


        if ($account->count() > 0) {
            $accountsAfterFilter = $this->getBankAccount($bankID, $pyment_currency);
        } else if (FinancialBankFees::on('mysql1')->where('BANK_ID', '=', $bankID)->where('ACCOUNT_ID', '!=', "0")->where('IS_ENABLE', 1)->count() > 0) {
            $accountsAfterFilter = $this->getRusomeAccount($json, $bankID, $pyment_currency);
        } else if (FinancialBankFees::on('mysql1')->where('BANK_ID', '=', $bankID)->count() == 0) {

            $accountsAfterFilter->push(['bankAccount' => "", 'bankAccountCurrency' => "", 'accountPercent' => '100']);
        }
        return $accountsAfterFilter;
    }



     public function getRusomeAccount($json, $bankID, $pyment_currency)
    {

        $collection = collect();

        foreach (json_decode($json) as $key => $value) {

            $bankAccount = FinancialBankFees::on('mysql1')->where('ACCOUNT_ID', $value->id)->where('BANK_ID', $bankID)->where('IS_ENABLE', 1)->where('ACCOUNT_ID', $value->id)->get();

            if ($bankAccount->count() > 0) {
               
               $bankAccount = $bankAccount->first();
           $collection->push(['bankAccount' => $bankAccount->ACCOUNT_NUMBER, 'bankAccountCurrency' => $bankAccount->CURRENCY_ID, 'accountPercent' => $value->value]);
            }
        }

        return  $collection;
    }

     public function  getBankAccount($bankID, $pyment_currency)
    {
        $collection = collect();
        $account = FinancialBankFees::on('mysql1')->where('CURRENCY_ID', $pyment_currency)->where('BANK_ID', '=', $bankID)->where('ACCOUNT_ID', '=', "0")->where('IS_ENABLE', 1)->get();

        foreach ($account as $value) {

            $collection->push(['bankAccount' => $value->ACCOUNT_NUMBER, 'bankAccountCurrency' => $value->CURRENCY_ID, 'accountPercent' => '100']);
        }

        return  $collection;
    }
    /**
     * Registrar archivo de log de solicitud
     *
     * @param string $invoiceIdent
     * @param int $type
     * @param int $flag
     * @return void
     */
    public function registerRequestLogFile(string $invoiceIdent, int $TYPE, int $Flage,$database): void
    {
         DB::connection('logConnection')->table('api_request_log_file'.$database)->insert([
                'INVOICE_IDENT' => $invoiceIdent,
                'TYPE'          => $TYPE,
                'Flage'         => $Flage,
                'created_at'    =>  Carbon::now(),
                'updated_at'    =>  Carbon::now(),
            ]);
      
    }
    
    /**
     * Actualizar archivo de log de solicitud
     *
     * @return void
     */
    public function updateRequestLogFile($INVOICE_IDENT,$database): void
    {
         DB::connection('logConnection')
            ->table('api_request_log_file'.$database)
            ->where('INVOICE_IDENT', $INVOICE_IDENT)
            ->limit(1)
            ->update([
                'error'      => 1,
                'updated_at' =>  Carbon::now(),
            ]);
    }
}
