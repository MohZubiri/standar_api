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

use App\Models\FinancialBank;

use Auth;
use App\Models\LogFile;
use App\Models\Universities;
use App\Models\API_FEES_PAYMENT;
use App\Models\PaymentLogeFile;
use App\Models\RequestLogeFile;
use App\Models\GeneralSetting;
use App\Models\API_PYMENT;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\FinancialApiController as FinancialApiController;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\RedirectConnectionService;
use App\Services\ValidationService;
use App\Services\ValidationService2;
use  App\Http\Controllers\API\GraduateStudiesPortalController;
use  App\Http\Controllers\API\CenterPaymentController;
class PostController extends Controller
{



    private  $univ_DB = [
        6  => '_sar_20',
        9 => '_sar_19'

    ];

    private $connectionDriver = "";

    public $ministray = "";
    public $univ = "";
    public $univbranch = "";
    public $shortinvoice = "";
    public $longinvoice = "";
    public $key = "";







   
  /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function gettoday()
    {
        $date = date('Y-m-d');
        $newdate = strtotime($date);
        $newdate = date('Y-m-d', $newdate);

        return $newdate;
    }
   


   /**
     * =============================
     * this function to temp payment
     * =============================
     */

   
    public function checkUserAuth($banckID)
    {
        $banck = FinancialBank::on('mysql1')->where('BANK_ID', $banckID)->first();

        if ($banck->HAS_API_CONNECT == 1 && $banck->IS_ENABLE == 1) {
            return false;
        } else {
            return true;
        }
    }


 
    public function show_invoice_details(Request $request, ValidationService $validationService, InvoiceService $invoiceService)
    {
        // Validar la solicitud usando ValidationService
        $validationResult = $validationService->validateInvoiceDetailsRequest($request);
        
        if ($validationResult['status'] === 'error') {
            return $this->sendError($validationResult['code'], $validationResult['message']);
        }
      
        // Obtener detalles usando InvoiceService
        $detailsResult = $invoiceService->getInvoiceDetails($request, $validationService);
 
        if (isset($detailsResult['status']) && $detailsResult['status'] === 'error') {
            return $this->sendError($detailsResult['code'], $detailsResult['message']);
        }

        // Llamar a showinvoice para obtener el resumen principal
        // Nota: showinvoice requiere RedirectConnectionService, lo instanciamos aquí
        $summaryResponse = $this->showinvoice($request, $validationService, new RedirectConnectionService());

        // Normalizar el resumen a un arreglo
        $summaryData = $summaryResponse;
        if ($summaryResponse instanceof \Illuminate\Http\JsonResponse) {
            $summaryData = $summaryResponse->getData(true);
        }

        // Extraer datos de detalles
        $detailsData = $detailsResult;
        if (isset($detailsResult['status']) && ($detailsResult['status'] === 'seccess' || $detailsResult['status'] === 'success')) {
            $detailsData = $detailsResult['data'] ?? $detailsResult;
        }

        // Construir respuesta combinada: { result: <showinvoice>, result.ditails: <details> }
        if (is_array($summaryData)) {
            $combined = ['result' => $summaryData];
            $combined['result']['ditails'] = $detailsData; // mantener la clave solicitada 'ditails'
            return response()->json($combined);
        }

        // Si por alguna razón el resumen no es un arreglo, devolver solo los detalles como respaldo
        if (isset($detailsResult['status']) && ($detailsResult['status'] === 'seccess' || $detailsResult['status'] === 'success')) {
            return $this->sendResponse($detailsData, $detailsResult['code'] ?? '#101');
        }

        return $detailsResult;
    }

  

    
    /**
     * Process invoice payment
     *
     * @param Request $request
     * @param ValidationService $validationService
     * @param PaymentService $paymentService
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, ValidationService $validationService, PaymentService $paymentService)
    {
        try {
         
            // Validate request data
            $validationResult = $validationService->validatePaymentRequest($request);
            
            if ($validationResult['status'] === 'error') {
                return $this->sendError($validationResult['code'], $validationResult['message']);
            }
            
            $input = $validationResult['data'];
            $redirectConnectionService= new RedirectConnectionService();
            // Get invoice ID from request and set payment type
            $responce=$redirectConnectionService->redirect_db($input['INVOICE_IDENT']);
            $invoiceId = $responce['id'];
         
            if ((int)$invoiceId === 0) {
                return $this->sendError('#504', 'Invoice not found');
            }
            
            if (!is_numeric($invoiceId)) {
                $responce=$redirectConnectionService->redirect_db($input['INVOICE_IDENT']);
            }
              $user = Auth::user();
            $bankId = $user->bank_id;
          
               if ($responce['paymentType'] == 1) {
                Log::info("Processing center payment for invoice: {$invoiceId}");
               
                return app('App\Http\Controllers\API\CenterPaymentController')->payInvoice(
                    $invoiceId,
                    $bankId,
                    $input,
                   $responce['connectionDriver']
                );
            }
            // Graduate studies portal payment
            else if ($responce['paymentType'] == 2) {
                Log::info("Processing graduate studies portal payment for invoice: {$invoiceId}");
                // Use GraduateStudiesPortalController for graduate studies portal payments

                 
                return app('App\Http\Controllers\API\GraduateStudiesPortalController')->payInvoice(
                    $invoiceId,
                    $bankId,
                    $input
                );
            }
            // Setup database connection
            DB::purge('mysql1');
            config(['database.connections.mysql1.database' =>  $responce['connectionDriver']]);
            DB::reconnect('mysql1');
            
            Log::info("Payment type: {$responce['paymentType']}");
          
            Log::info("Processing regular university payment for invoice: {$invoiceId}");
           
        
            if ($this->checkUserAuth($bankId)) {
                return $this->sendError('#203', 'غير مخول بسداد لهذه الجامعة');
            }
            
         
            $invoice = invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->get()->last();
     
           
            $this->key = $this->CheckFininsial($invoice, $invoiceId, $bankId, 1, $responce);
         
            if ($this->key == 99) {
                return $this->sendError('#514', "الحافظة غير قابلة لسداد في الوقت الحالي");
            }
         
            // Prepare payment data
            $paymentData = [
                'invoice_id' => $invoiceId,
                'bonds_id' => $input['BONDS_ID'],
                'bonds_date' => $input['BONDS_DATE'],
                'payment_by' => $input['PAYMENT_BY'],
                'payment_amount' => $input['PAYMENT'],
                'key' => $this->key
            ];
          
          $invoice_info=$this->get_invoice($invoice,$responce);

             $paymentService->registerPaymentLogFile($paymentData['invoice_id'], $input['BONDS_ID'], $input['BONDS_DATE'], $input['PAYMENT_BY'], $input['PAYMENT'],$responce['connectionDriver']);
         
             // Process payment
            $result = $paymentService->processPayment($invoice_info, $responce, $bankId,$paymentData);

            // Update payment log
            $paymentService->updatePaymentLogFile($paymentData['invoice_id'],$responce['connectionDriver']);
         
            // Return response based on result
            if ($result['status'] === 'success') {
                
                $sendInvoice =$invoice_info;
                $sendInvoice['api_payment_id'] = $result['api_payment_id'] ?? null;
                return $this->sendResponse($sendInvoice, $result['code']);
            } else {
                return $this->sendError($result['code'], $result['message']);
            }
            
        } catch (\Exception $e) {
            // Log the exception
            Log::error('Payment processing error: ' . $e->getMessage());
            
            $this->logFile($responce['connectionDriver'],$invoiceId, $bankId);

            
            return $this->sendError('#551', 'Server process Internal Error');
        }
    }
    /**
     * function to generat internal payment_user
     */
   
    public function logFile($database,$invoice_id,$bank_id,$FININCIL_FUNCTION=null){

          DB::connection('logConnection')->table('api_log_file'.$database)->insert([
                    'INVOICE_IDENT'      => $invoice_id,
                    'API_PAYMENT_ID'     => 0,
                    'FININCIAL_FUNCTION' => $FININCIL_FUNCTION??'store method exception',
                    'RETURN_ERROR'       => 999,
                    'MAIL_RETURN_ERROR'  => 551,
                    'BANK_ID'            => $bank_id,
                    'TYPE_ID'            => 1,
                    'created_at'         =>  Carbon::now(),
                    'updated_at'         =>  Carbon::now(),
                ]);
    }
    
    

    public function invoiceVerification(Request $request, ValidationService $validationService,RedirectConnectionService $redirectConnectionService)
    {

       
            $validationResult = $validationService->validateShowInvoiceRequest($request,$validationService);
            
             if (!$validationResult['success']) {
                return $this->sendError($validationResult['code'], $validationResult['message']);
            }
            
           $input = $request->all();
          //  dd($redirectConnectionService->redirect_db($input['INVOICE_IDENT']));
            $responce=$redirectConnectionService->redirect_db($input['INVOICE_IDENT']);
 
            if(!isset($responce["id"]))
                return  $responce;
           
            $invoiceId = trim($responce['id']);
          
      
            // Validate invoice ID
            if (!is_string($invoiceId) || !is_numeric($invoiceId)) {
                return $this->sendError('#504', "Invalid invoice ID");
            }
         
            // Check user authorization
            $user = Auth::user();
            $bankId = $user->bank_id;
           
              if($responce['paymentType']==1)
                {
                    $result= CenterPaymentController::Verification($invoiceId,$bankId,0,$responce['connectionDriver']);
                    return $result;
                     
                }elseif($responce['paymentType']==2)
                {
                  
                    $result=new GraduateStudiesPortalController();
                    $result= $result->Verification($invoiceId,$bankId,0);
                
                    return $result;
                }

      

         

            if (invoice::on('mysql1')->where('INVOICE_IDENT', $id)->where('BANK_ID', $bankId)->count() > 0) {
                return $this->sendResponse("1", "101");
            } else {
                return $this->sendResponse("0", "101");
            }
        }
    


    // =========== function to check all fininssial issue is correct ======================
    public function CheckFininsial($invoice, $id, $bank_id,$paymentID,$genralPrameter)
    {

        try {
          
           
       
            $Student = $this->get_invoice($invoice,$genralPrameter);
         
            $valudationResponce = (new FinancialApiController())->getInvoiceFinincialStatus( $genralPrameter['univ_id'], $Student['student_id'], $Student['faculty_id'], $id, $bank_id,$paymentID);
       

                // Debug line to check validation response type - commented out
                // echo "ccc"; dd(is_string($valudationResponce));
            if (!is_string($valudationResponce) && !is_null($valudationResponce)) {

                return $valudationResponce->key;

            }else{
              Log::info("Exception Whene use 'https://financial/api/route.php'" . $valudationResponce);
              DB::connection('logConnection')->table('api_log_file'.$genralPrameter['connectionDriver'])->insert([
                    'INVOICE_IDENT'      =>  $genralPrameter['univ_id'],
                    'API_PAYMENT_ID'     => 888,
                    'FININCIAL_FUNCTION' => 'https://financial/api/route.php=== Responce'.$valudationResponce,
                    'RETURN_ERROR'       => 88,
                    'MAIL_RETURN_ERROR'  => 555,
                    'BANK_ID'            => $bank_id,
                    'TYPE_ID'            => 99,
                    'created_at'         =>  Carbon::now(),
                    'updated_at'         =>  Carbon::now(),
                ]);
             

			  return 99;
            }
        } catch (\Exception $e) {

            Log::info("Exception Whene use 'https://financial/api/route.php'" . "  ERROR (" . $e . ")");

               DB::connection('logConnection')->table('api_log_file'.$genralPrameter['connectionDriver'])->insert([
                    'INVOICE_IDENT'      =>  $genralPrameter['univ_id'],
                    'API_PAYMENT_ID'     => 9999,
                    'FININCIAL_FUNCTION' => 'https://financial/api/route.php=== '.$e,
                    'RETURN_ERROR'       => 99,
                    'MAIL_RETURN_ERROR'  => 555,
                    'BANK_ID'            => $bank_id,
                    'TYPE_ID'            => 99,
                    'created_at'         =>  Carbon::now(),
                    'updated_at'         =>  Carbon::now(),
                ]);
          
        }
    }

    /**
     * Show invoice details based on invoice ID and type
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function showinvoice(Request $request, ValidationService $validationService,RedirectConnectionService $redirectConnectionService)
    {
        try {
             $invoiceId='';
            // Validate request data
            $validationResult = $validationService->validateShowInvoiceRequest($request,$validationService);
            if (!$validationResult['success']) {
                return $this->sendError($validationResult['code'], $validationResult['message']);
            }
      
            $input = $request->all();
          //  dd($redirectConnectionService->redirect_db($input['INVOICE_IDENT']));
            $responce=$redirectConnectionService->redirect_db($input['INVOICE_IDENT']);
 
            if(!isset($responce["id"]))
                return  $responce;
           
            $invoiceId = trim($responce['id']);
          
      
            // Validate invoice ID
            if (!is_string($invoiceId) || !is_numeric($invoiceId)) {
                return $this->sendError('#504', "Invalid invoice ID");
            }
         
            // Check user authorization
            $user = Auth::user();
            $bankId = $user->bank_id;
           
              if($responce['paymentType']==1)
                {
                    $result= CenterPaymentController::getInvoiceData($invoiceId,$bankId,0,$responce['connectionDriver']);
                    return $result;
                     
                }elseif($responce['paymentType']==2)
                {
                  
                    $result=new GraduateStudiesPortalController();
                    $result= $result->getInvoiceData($invoiceId,$bankId,0);
                
                    return $result;
                }

            if ($this->checkUserAuth($bankId)) {
                return $this->sendError('#203', "غير مخول بسداد لهذه الجامعة");
            }
         
            // Setup database connection
             $redirectConnectionService->setupDatabaseConnection($responce['connectionDriver']);
          
            // Register request log
          //  $this->register_RequestLogeFile($invoiceId, 0, 1);
               $this->register_RequestLogeFile($invoiceId, 0, 1,$responce['connectionDriver']);
            // Get request type
            $requestType = $this->getRequestType($input);
            
            // Get invoice data
            $invoice = $this->getInvoiceById($invoiceId);
        
            if (!$invoice) {
                   $this->update_RequestLogeFile($invoiceId,$responce['connectionDriver']);
                return $this->sendError('#504', "Invoice not found");
            }
            
            // Set request type if not provided
            if ($requestType === "") {
                $requestType = $invoice->PAYMENT_FLAG;
            }
            
            // Update request log with request type
            $this->register_RequestLogeFile($invoiceId, $requestType, 1,$responce['connectionDriver']);
           
            // Check student status
            if (!$this->check_student_status($invoice->STUDENT_IDENT, $invoiceId,$responce)) {
               $this->update_RequestLogeFile($invoice->INVOICE_IDENT,$responce['connectionDriver']);
                return $this->sendError('#504', "Invalid student status");
            }
       $apiPaymentId = DB::connection('mysql1')
    ->table('api_pending_payments')
    ->where('api_pending_payments.invoice_id', $invoiceId)
    ->value('api_pending_payments.status_flag');
    //->value('api_pending_payments.api_payment_id');



if(isset($apiPaymentId)&&$apiPaymentId!=99)
    return $this->sendError('#508', "Invoice already paid");
            // Process based on request type
            if ($requestType == 0) {
              
                return $this->processUnpaidInvoice($invoice, $invoiceId, $input, $bankId, $responce);
            } elseif ($requestType == 1) {
                return $this->processPaidInvoice($invoice, $invoiceId, $responce);
            } else {
                 $this->update_RequestLogeFile($invoiceId,$responce['connectionDriver']);
                return $this->sendError('#513', "Invalid request type");
            }
            
        } catch (\Exception $e) {
            Log::error('Show invoice error: ' . $e->getMessage());
           $this->update_RequestLogeFile($invoiceId,$responce['connectionDriver']);
            return $this->sendError('#551', "Server process Internal Error");
        }
    }
    
   
    
   
    
    /**
     * Get request type from input or default to empty string
     *
     * @param array $input
     * @return string
     */
    private function getRequestType(array $input)
    {
        try {
            return $input["TYPE"];
        } catch (\Throwable $th) {
            return "";
        }
    }
    
    /**
     * Get invoice by ID
     *
     * @param string $invoiceId
     * @return object|null
     */
    private function getInvoiceById(string $invoiceId)
    {
       
        if (invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->count() > 0) {
            return invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->first();
        }
        
        return null;
    }
    
    /**
     * Process unpaid invoice request
     *
     * @param object $invoice
     * @param string $invoiceId
     * @param array $input
     * @param int $bankId
     * @return \Illuminate\Http\Response
     */
    private function processUnpaidInvoice($invoice, $invoiceId, $input, $bankId,$genralPrameter)
    {
       
        // Check if invoice is unpaid
        if ($invoice->PAYMENT_FLAG != 0) {
           $this->update_RequestLogeFile($invoice->INVOICE_IDENT,$genralPrameter['connectionDriver']);
            return $this->sendError('#513', "Invoice already paid or canceled");
        }
          
        // Validate invoice amount
        $sumInvoiceDetails = $this->get_real_payment($invoice->INVOICE_IDENT);
        $invoiceCost = $invoice->REAL_FEE_AMOUNT;
        
        if ($sumInvoiceDetails !== $invoiceCost) {
            $this->update_RequestLogeFile($invoice->INVOICE_IDENT,$genralPrameter['connectionDriver']);
            return $this->sendError('#506', "Invoice amount mismatch");
        }
        
        // Check for pending payments
        if (API_PYMENT::on('mysql1')->where('INVOICE_IDENT', $input["INVOICE_IDENT"])->where('PAYMENT_FLAG', 99)->count() > 0) {
           $this->update_RequestLogeFile($invoice->INVOICE_IDENT,$genralPrameter['connectionDriver']);
            return $this->sendError('#551', "Server process Internal Error");
        }
        
        // Check deadline
        if (invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->whereDate('DEADLINE', '>=', $this->gettoday())->count() <= 0) {
                $this->update_RequestLogeFile($invoice->INVOICE_IDENT,$genralPrameter['connectionDriver']);
            return $this->sendError('#516', "Invoice deadline passed");
        }
     
        // Get invoice data
        $send = $this->get_invoice($invoice,$genralPrameter);
 
        // Check financial status
        $this->key = $this->CheckFininsial($invoice, $invoiceId, $bankId, 0, $genralPrameter);
      
        if ($this->key == 99 && $send['invoice_state'] != 1) {
            return $this->sendError('#514', "يرجى مراجعة الكلية");
        } else {
            return $this->sendResponse($send, "#101");
        }
    }
    
    /**
     * Process paid invoice request
     *
     * @param object $invoice
     * @param string $invoiceId
     * @return \Illuminate\Http\Response
     */
    private function processPaidInvoice($invoice, $invoiceId, $responce)
    {
        // Check if invoice is paid
        if ($invoice->PAYMENT_FLAG != 1) {
            $this->update_RequestLogeFile($invoice->INVOICE_IDENT,$responce['connectionDriver']);
            
            if ($invoice->PAYMENT_FLAG == 0) {
                return $this->sendError('#511', "Invoice not paid");
            } elseif ($invoice->PAYMENT_FLAG == 2) {
                return $this->sendError('#513', "Invoice canceled");
            }
        }
        
        // Return error for paid invoices
        return $this->sendError('#508', "Invoice already paid");
        
        
    }

    //========================  return invoice with state =======
    public function get_state($invoice)
    {
        $send = [
            'invoice_id' => $invoice->INVOICE_IDENT,
            'invoice_state' => $invoice->PAYMENT_FLAG

        ];

        return $send;
    }
    /**
     *
     * THis Function to get Real
     *  Pyments from
     *  Invoice_Ditails
     *
     *
     *  */



    public function get_real_paid_cost($id)
    {
        $Fees_Cost = invoicedetails::on('mysql1')->where('INVOICE_IDENT', $id)->sum('REAL_FEE_AMOUNT');
        $Fees_exchange = invoicedetails::on('mysql1')->where('INVOICE_IDENT', $id)->first()->EXCHANGE_PRICE;

        if (invoice::on('mysql1')->findOrFail($id)->PAYMENT_FLAG == 1) {
            return $Fees_Cost * $Fees_exchange;
        } else {
            return 0;
        }
    }
    public function get_real_payment($id)
    {
        $Fees_Cost = invoicedetails::on('mysql1')->where('INVOICE_IDENT', $id)->sum('REAL_FEE_AMOUNT');
       
        $Fees_Cost = collect(DB::connection('mysql1')->select('SELECT SUM(REAL_FEE_AMOUNT) as price FROM financial_invoices_details WHERE INVOICE_IDENT = ?', [$id]))->first()->price;

       
        return $Fees_Cost;
    }
    public function getlonginvoice()
    {

        return $this->longinvoice;
    }
    public function isMaster($student){

		try{

			//dd($student->STUDENT_IDENT);
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
    /**
     * Get formatted invoice data with student information
     *
     * @param object $invoice Invoice object from database
     * @return array Formatted invoice data
     */
    public function get_invoice($invoice,$genralPrameter=[])
    {
        try {
          
            $redirectConnectionService=new RedirectConnectionService();
            // Get student information
       
            $student = $this->getStudentByInvoice($invoice);
        
            if (!$student) {
                Log::error("Student not found for invoice: {$invoice->INVOICE_IDENT}");
                return [
                    'invoice_id' => $this->getlonginvoice(),
                    'invoice_state' => $invoice->PAYMENT_FLAG,
                    'error' => 'Student not found'
                ];
            }
            
            // Format student name
            $studentName = $this->formatStudentName($student);
          
            if($genralPrameter)
            {
            // Get university information
            $universityId = $redirectConnectionService->getuniv($genralPrameter['longInvoice']);
            $universityName = $redirectConnectionService->getunivName($genralPrameter['longInvoice']);
             
            // Calculate invoice amounts
            $invoiceCost = $this->calculateInvoiceCost($invoice);
            $invoicePayment = $this->calculateInvoicePayment($invoice->INVOICE_IDENT);
            $data=[
                // Invoice information
                'invoice_id' => $genralPrameter['longInvoice'],
                'invoice_cost' => $invoiceCost,
                'invoice_payment' => $invoicePayment,
                'invoice_state' => $invoice->PAYMENT_FLAG,
                'bound_id' => $invoice->BONDS_ID,
                'bound_date' => $invoice->BONDS_DATE,
                'payment_date' => $invoice->ACTUAL_PAYMENT_DATE,
                'currancy' => $invoice->PAYMENT_CURRENCY_ID,
                'user_id' => $invoice->PAYMENT_BY,
                
                // Student information
                'student_name' => $studentName,
                'student_id' => $invoice->STUDENT_IDENT,
                'education_type' => $this->isMaster($student),
                'faculty_id' => $student->FACULTY_IDENT,
                'program_id' => $student->PROGRAM_IDENT,
                'year' => $student->AC_YEAR,
                
                // University information
                'university_id' => $universityId,
                'university_name' => $universityName
            ];
           
            // Build response array
            return $data;
        }else{
                return[];
            }
        } catch (\Exception $e) {
           
            Log::error("Error in get_invoice: {$e->getMessage()}");
            $this->logFile($genralPrameter['connectionDriver'],$invoice->INVOICE_IDENT,Auth::user()->bank_id, $e->getMessage());
            return [
                'invoice_id' => $this->getlonginvoice(),
                'invoice_state' => $invoice->PAYMENT_FLAG,
                'error' => 'Error processing invoice data'
            ];
        }
    }
    
    /**
     * Get student by invoice
     *
     * @param object $invoice
     * @return object|null
     */
    private function getStudentByInvoice($invoice)
    {
        try {
           
            return students::on('mysql1')
                ->where('STUDENT_IDENT', $invoice->STUDENT_IDENT)
                ->firstOrFail();
        } catch (\Exception $e) {
            Log::error("Error getting student for invoice {$invoice->INVOICE_IDENT}: {$e->getMessage()}");
            return null;
        }
    }
    
    /**
     * Format student name
     *
     * @param object $student
     * @return string
     */
    private function formatStudentName($student)
    {
        return $student->S_FIRST_NAME . ' ' . $student->S_LAST_NAME;
    }
    
    /**
     * Calculate invoice cost
     *
     * @param object $invoice
     * @return float
     */
    private function calculateInvoiceCost($invoice)
    {
        return round(($invoice->REAL_FEE_AMOUNT) * ($invoice->EXCHANGE_PRICE), 3);
    }
    
    /**
     * Calculate invoice payment
     *
     * @param int $invoiceId
     * @return float
     */
    private function calculateInvoicePayment($invoiceId)
    {
        return round($this->get_real_paid_cost($invoiceId), 3);
    }


   

    public function register_RequestLogeFile($INVOICE_IDENT, $TYPE, $Flage,$database)
    {

        DB::connection('logConnection')->table('api_request_log_file'.$database)->insert([
                'INVOICE_IDENT' => $INVOICE_IDENT,
                'TYPE'          => $TYPE,
                'Flage'         => $Flage,
                'created_at'    =>  Carbon::now(),
                'updated_at'    =>  Carbon::now(),
            ]);
       /* $RequestLogeFile = new RequestLogeFile;
        $RequestLogeFile->setConnection('mysql1');
        $RequestLogeFile->INVOICE_IDENT = $INVOICE_IDENT;
        $RequestLogeFile->TYPE = $TYPE;
        $RequestLogeFile->Flage = $Flage;
        $RequestLogeFile->save();
        // Debug line to check request log file - commented out
        // dd($RequestLogeFile);*/
    }
   
  

    public function update_RequestLogeFile($INVOICE_IDENT,$database)
    {

        DB::connection('logConnection')
            ->table('api_request_log_file'.$database)
            ->where('INVOICE_IDENT', $INVOICE_IDENT)
            ->limit(1)
            ->update([
                'error'      => 1,
                'updated_at' =>  Carbon::now(),
            ]);
       /* $Request = RequestLogeFile::on('mysql1')->orderBy('id', 'DESC')->first();

        $Request->error = 1;
        $Request->save();*/
    }

    public function check_student_status($student_ident, $invoice_ident,$responce)
    {

        try {
            $resutlt = optional(collect(DB::connection('mysql1')->select(
                'SELECT Financial_GetStudentStatus(?) as msg',
                [
                    $student_ident

                ]
            ))->first())->msg;
            // Debug line to check student status result - commented out
            // dd($resutlt);
            if (!is_null($resutlt)) {
                if ($resutlt == 1) {
                    return true;
                } else {
                    return false;
                }
            }
        } catch (\Exception $e) {

            $error='Financial_GetStudentStatus==' . $e;
            $this->logFile($responce['connectionDriver'],$invoice_ident,Auth::user()->bank_id, $error);
            return $this->sendError('#551', "Server process Internal Error ");
        }
    }

   
}
