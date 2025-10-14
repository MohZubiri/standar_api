<?php

namespace App\Services;

use App\Models\API_PYMENT;
use App\Models\invoice;
use App\Models\Invoicedetails;
use App\Models\LogFile;
use App\Models\PaymentLogeFile;
use App\Models\PendingPayment;
use Exception;
use Carbon\Carbon;
use App\Models\PaymentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PaymentService
{
    /**
     * Process invoice payment
     * معالجة دفع الفاتورة والتحقق من صحتها
     *
     * @param array $paymentData بيانات الدفع (invoice_id, faculty_id, program_id, student_id, invoice_cost)
     * @param array $parameters معاملات الاتصال (connectionDriver, univ_id, etc.)
     * @param int $bankId معرف البنك
     * @param array $extrnalDataPayment بيانات الدفع الخارجية (bonds_id, bonds_date, payment_by, payment_amount, key)
     * @return array ['status' => 'success'|'error', 'code' => string, 'message' => string, 'data' => array, 'api_payment_id' => int|null]
     */

    /**
     * Validate payment processing requirements
     * التحقق من صحة متطلبات معالجة الدفع
     *
     * @param array $paymentData بيانات الدفع
     * @param array $extrnalDataPayment بيانات الدفع الخارجية
     * @return array|true true إذا كان التحقق ناجحاً، أو array مع تفاصيل الخطأ
     */
    public function validateProcesspayment($paymentData, $extrnalDataPayment)
    {
        // Get invoice details
        $invoice = $this->getInvoice($this->getshortinvoice($paymentData['invoice_id']));

        // Check if invoice exists
         if (!$invoice) {
                return ['status' => 'error', 'code' => '#504', 'message' => 'Invoice not found'];
            }

            // Check invoice deadline
            if (!$this->isInvoiceValid($invoice)) {
                return ['status' => 'error', 'code' => '#516', 'message' => 'Invoice deadline has passed'];
            }

            // Check if invoice is unpaid
            if ($invoice->PAYMENT_FLAG != 0) {
                return ['status' => 'error', 'code' => '#508', 'message' => ' '];
            }

            // Verify payment amount
            $invoiceCostRY = round(($invoice->REAL_FEE_AMOUNT) * ($invoice->EXCHANGE_PRICE), 3);
            if ($extrnalDataPayment['payment_amount'] != $invoiceCostRY) {
                return ['status' => 'error', 'code' => '#503', 'message' => 'Payment amount does not match invoice amount'];
            }

            // Verify invoice details sum
            $sumInvoiceDetails = $this->getInvoiceDetailsSum($this->getshortinvoice($paymentData['invoice_id']));

            if (number_format($sumInvoiceDetails, 2, '.', '') !== (number_format($invoice->REAL_FEE_AMOUNT, 2, '.', ''))) {
                return ['status' => 'error', 'code' => '#506', 'message' => 'Invoice details sum does not match invoice amount'];
            }

            // Check if bonds ID is already used
            if ($this->isBondsIdUsed($extrnalDataPayment['bonds_id'])) {
                return ['status' => 'error', 'code' => '#510', 'message' => 'Bonds ID already used'];
            }

            // Verify bonds date
            $invoiceDate = date('Y-m-d', strtotime($invoice->RECORDED_ON));
            $bondsDate = date('Y-m-d', strtotime($extrnalDataPayment['bonds_date']));
            $today = date('Y-m-d');

            if ($invoiceDate > $bondsDate || $bondsDate !== $today) {
                return ['status' => 'error', 'code' => '#518', 'message' => 'Invalid bonds date'];
            }

        return true;
    }
    public function processPayment(array $paymentData, $parameters, int $bankId, $extrnalDataPayment): array
    {
        try {
            // Extract connection driver from parameters
            $connectionDriver = $parameters['connectionDriver'];

            // Setup database connection
            $this->setupDatabaseConnection($connectionDriver);
            
            // Validate payment data
            $validatePayment = $this->validateProcesspayment($paymentData, $extrnalDataPayment);
            if ($validatePayment !== true) {
                return $validatePayment;
            }

            // Create payment record in database
            $payment = $this->createPaymentRecord($paymentData, $bankId, $extrnalDataPayment);
            
            if ($payment['status'] === 'error') {
                return $payment;
            }
            
            if ($payment['status'] === 'success') {
                // Return success immediately after registering payment
                // Actual processing will be done via cron job
                return [
                    'status' => 'success',
                    'code' => '#200',
                    'message' => 'Payment registered successfully',
                    'data' => $paymentData,
                    'api_payment_id' => $payment['api_payment_id'] ?? null
                ];
            }

        } catch (Exception $e) {
            Log::error("Payment processing error: " . $e->getMessage());
            return ['status' => 'error', 'code' => '#551', 'message' => 'Server process internal error: ' . $e->getMessage()];
        }
    }

    /**
     * Setup database connection
     *
     * @param string $connectionDriver
     * @return void
     */
    private function setupDatabaseConnection(string $connectionDriver): void
    {
        DB::purge('mysql1');
        config(['database.connections.mysql1.database' => $connectionDriver]);
        DB::reconnect('mysql1');
    }

    /**
     * Get invoice by ID
     *
     * @param string $invoiceId
     * @return mixed
     */
    private function getInvoice(string $invoiceId)
    {
        return invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->get()->last();
    }

    /**
     * Check if invoice is still valid (not expired)
     *
     * @param object $invoice
     * @return bool
     */
    private function isInvoiceValid(object $invoice): bool
    {
        $today = date('Y-m-d');
        return invoice::on('mysql1')->where('INVOICE_IDENT', $invoice->INVOICE_IDENT)
            ->whereDate('DEADLINE', '>=', $today)
            ->count() > 0;
    }
    public function getshortinvoice($invoice_ident)
    {


        return   substr($invoice_ident, 5, strlen($invoice_ident));
    }
    /**
     * Get sum of invoice details
     *
     * @param string $invoiceId
     * @return float
     */
    private function getInvoiceDetailsSum(string $invoiceId): float
    {
        $Fees_Cost = Invoicedetails::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->sum('REAL_FEE_AMOUNT');
        //  $Fees_exchange=invoicedetails::where('INVOICE_IDENT',$id)sum('EXCHANGE_PRICE');

        /*   $Fees_Cost=collect(DB::connection('mysql1')->select('SELECT SUM(REAL_FEE_AMOUNT*EXCHANGE_PRICE) as price FROM financial_invoices_details WHERE INVOICE_IDENT = ?', [$id]))->first()->price;*/

        $Fees_Cost = collect(DB::connection('mysql1')->select('SELECT SUM(REAL_FEE_AMOUNT) as price FROM financial_invoices_details WHERE INVOICE_IDENT = ?', [$invoiceId]))->first()->price;


        return $Fees_Cost;
    }

    /**
     * Check if bonds ID is already used
     *
     * @param int $bondsId
     * @return bool
     */
    private function isBondsIdUsed(int $bondsId): bool
    {
        if ( invoice::on('mysql1')->where('BONDS_ID', $bondsId)->exists()) {
            return true;
        }else{
            return false;
        }

    }

    /**
     * Create payment record
     * إنشاء سجل دفع جديد أو استرجاع سجل موجود
     *
     * @param array $paymentData بيانات الدفع (invoice_id, faculty_id, program_id, student_id, invoice_cost)
     * @param int $bankId معرف البنك
     * @param array $extrnalDataPayment بيانات الدفع الخارجية (bonds_id, bonds_date, payment_by)
     * @return array ['status' => 'success'|'error', 'code' => string, 'message' => string, 'api_payment_id' => int|null]
     */
    private function createPaymentRecord(array $paymentData, int $bankId, array $extrnalDataPayment): array
    {
        $payment = null;

        // First, check if there is any payment with PAYMENT_FLAG > 0 for this BONDS_ID
        $apiPayment = API_PYMENT::on('mysql1')->where('BONDS_ID', $extrnalDataPayment['bonds_id']);
        if (!$apiPayment->where('PAYMENT_FLAG', '>', 0)->exists()) {
            // Now, create a new query builder for PAYMENT_FLAG = 0
            $payment = API_PYMENT::on('mysql1')->where('BONDS_ID', $extrnalDataPayment['bonds_id'])->where('PAYMENT_FLAG', '=', 0)->first();

            if (!$payment) {
                $userId = $this->getApiUserId();
                $payment = new API_PYMENT;
                $payment->setConnection('mysql1');
                $payment->FACULTY_IDENT = $paymentData['faculty_id'];
                $payment->PROGRAM_IDENT = $paymentData['program_id'];
                $payment->STUDENT_IDENT = $paymentData['student_id'];
                $payment->INVOICE_IDENT = $this->getshortinvoice($paymentData['invoice_id']);
                $payment->BANK_ID = $bankId;
                $payment->REAL_FEE_AMOUNT = $paymentData['invoice_cost'];
                $payment->BONDS_ID = $extrnalDataPayment['bonds_id'];
                $payment->BONDS_DATE = date('Y-m-d', strtotime($extrnalDataPayment['bonds_date']));
                $payment->PAYMENT_BY = $this->get_payment_user($extrnalDataPayment['payment_by'], $bankId);
                $payment->ACTUAL_PAYMENT_DATE = date("Y-m-d H:i:s");
                $payment->PAYMENT_FLAG = 0;
                $payment->UPDATED_BY = $userId;
                $payment->RECORDED_BY = $userId;
                $payment->save();
            }
        }
        if (!$payment)
            return ['status' => 'error', 'code' => '#510', 'message' => 'Bonds ID already used'];

        API_PYMENT::on('mysql1')->where('PAYMENT_FLAG', '=', 0)
        ->where('INVOICE_IDENT', '=', $this->getshortinvoice($paymentData['invoice_id']))
        ->where('BONDS_ID','!=',$extrnalDataPayment['bonds_id'])->update(['PAYMENT_FLAG'=>99]);

        return [
            'status' => 'success',
            'code' => '#200',
            'message' => 'Payment registered successfully',
            'api_payment_id' => $payment->API_PAYMENT_ID ?? null
        ];

    }

    /**
     * Get API user ID
     *
     * @return int
     */
    private function getApiUserId(): int
    {
        return collect(DB::connection('mysql1')->select("select USER_IDENT from users where GROUP_IDENT=250 AND LOGON_ID='sar_api@localhost'"))->first()->USER_IDENT;
    }
     public function get_payment_user($payment_by, $bank_id)
    {
         if (PaymentUser::on('mysql1')->where('BANK_IDENT', $bank_id)->where('EXTERNAL_PAYMENT_USER', $payment_by)->count() == 0) {

            $Payment_By = new PaymentUser();
            $Payment_By->setConnection('mysql1');
            $Payment_By->EXTERNAL_PAYMENT_USER = $payment_by;
            $Payment_By->BANK_IDENT = $bank_id;
            $Payment_By->save();

            return $Payment_By->PAYMENT_USER_IDENT;
        } else {
            return  PaymentUser::on('mysql1')->where('BANK_IDENT', $bank_id)->where('EXTERNAL_PAYMENT_USER', $payment_by)->first()->PAYMENT_USER_IDENT;
        }
    }
    /**
     * Execute financial function for payment
     *
     * @param int $paymentId
     * @param string $key
     * @return mixed
     */
    private function executeFinancialFunction(int $paymentId, string $key)
    {
        return optional(collect(DB::connection('mysql1')->select(
            'SELECT financial_function_api_send_payment(?,?) as msg',
            [
                $paymentId,
                $key
            ]
        ))->first())->msg;
    }

    /**
     * Handle payment result
     *
     * @param mixed $result
     * @param API_PYMENT $payment
     * @param string $invoiceId
     * @return array
     */


    /**
     * Log payment error
     *
     * @param string $invoiceId
     * @param int $paymentId
     * @param string $function
     * @param mixed $error
     * @param int $mailError
     * @param int $bankId
     * @return void
     */


    /**
     * Register payment log file
     *
     * @param string $invoiceId
     * @param int $bondsId
     * @param string $bondsDate
     * @param string $paymentBy
     * @param float $payment
     * @return void
     */
    public function registerPaymentLogFile(string $invoiceId, int $bondsId, string $bondsDate, string $paymentBy, float $payment,$database): void
    {

      DB::connection('logConnection')->table('api_payment_log_file'.$database)->insert([
            'INVOICE_IDENT' => $invoiceId,
            'BONDS_ID'      => $bondsId,
            'BONDS_DATE'    => $bondsDate,
            'PAYMENT_BY'    => $paymentBy,
            'PAYMENT'       => $payment,
            'Flage'         => 0,
            'created_at'    =>  Carbon::now(),
            'updated_at'    =>  Carbon::now(),
        ]);

    }

    /**
     * Update payment log file status
     *
     * @return void
     */
    public function updatePaymentLogFile($invoiceId,$database): void
    {

        DB::connection('logConnection')
          ->table('api_payment_log_file'.$database)
            ->where('Flage', 0)
            ->where('INVOICE_IDENT', $invoiceId)
            ->orderByDesc('id')
            ->limit(1)
            ->update([
                'Flage'      => 1,
                'updated_at' =>  Carbon::now(),
            ]);
        $paymentLog = PaymentLogeFile::on('mysql1')->where('Flage', 0)->latest()->first();
        if ($paymentLog) {
            $paymentLog->Flage = 1;
            $paymentLog->save();
        }
    }
}
