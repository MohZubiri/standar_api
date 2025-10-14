<?php

namespace App\Services\Legacy;

use App\Models\API_PYMENT;
use App\Models\invoice;
use App\Models\Invoicedetails;
use App\Models\PaymentLogeFile;
use App\Models\PendingPayment;
use App\Models\PaymentUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function validateProcesspayment($paymentData, $extrnalDataPayment)
    {
        $invoice = $this->getInvoice($this->getshortinvoice($paymentData['invoice_id']));
        $pendingRecourd = PendingPayment::on('mysql1')->where('invoice_id', $this->getshortinvoice($paymentData['invoice_id']))->where('status_flag', 1)->exists();

        if (!$invoice) {
            return ['status' => 'error', 'code' => '#504', 'message' => 'Invoice not found'];
        }
        if (!$this->isInvoiceValid($invoice)) {
            return ['status' => 'error', 'code' => '#516', 'message' => 'Invoice deadline has passed'];
        }
        if ($invoice->PAYMENT_FLAG != 0 || $pendingRecourd) {
            return ['status' => 'error', 'code' => '#508', 'message' => ' '];
        }
        $invoiceCostRY = round(($invoice->REAL_FEE_AMOUNT) * ($invoice->EXCHANGE_PRICE), 3);
        if ($extrnalDataPayment['payment_amount'] != $invoiceCostRY) {
            return ['status' => 'error', 'code' => '#503', 'message' => 'Payment amount does not match invoice amount'];
        }
        $sumInvoiceDetails = $this->getInvoiceDetailsSum($this->getshortinvoice($paymentData['invoice_id']));
        if (number_format($sumInvoiceDetails, 2, '.', '') !== (number_format($invoice->REAL_FEE_AMOUNT, 2, '.', ''))) {
            return ['status' => 'error', 'code' => '#506', 'message' => 'Invoice details sum does not match invoice amount'];
        }
        if ($this->isBondsIdUsed($extrnalDataPayment['bonds_id'])) {
            return ['status' => 'error', 'code' => '#510', 'message' => 'Bonds ID already used'];
        }
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
            $connectionDriver = $parameters['connectionDriver'];
            $this->setupDatabaseConnection($connectionDriver);
            $validatePayment = $this->validateProcesspayment($paymentData, $extrnalDataPayment);
            if ($validatePayment !== true) {
                return $validatePayment;
            }
            $payment = $this->createPaymentRecord($paymentData, $bankId, $extrnalDataPayment);
            if ($payment['status'] === 'error') {
                return $payment;
            }
            if ($payment['status'] === 'success') {
                return [
                    'status' => 'success',
                    'code' => '#200',
                    'message' => 'Payment registered successfully',
                    'data' => $paymentData
                ];
            }
        } catch (\Exception $e) {
            Log::error("Payment processing error: " . $e->getMessage());
            return ['status' => 'error', 'code' => '#551', 'message' => 'Server process internal error: ' . $e->getMessage()];
        }
    }

    private function setupDatabaseConnection(string $connectionDriver): void
    {
        DB::purge('mysql1');
        config(['database.connections.mysql1.database' => $connectionDriver]);
        DB::reconnect('mysql1');
    }

    private function getInvoice(string $invoiceId)
    {
        return invoice::on('mysql1')->where('INVOICE_IDENT', $invoiceId)->get()->last();
    }

    private function isInvoiceValid(object $invoice): bool
    {
        $today = date('Y-m-d');
        return invoice::on('mysql1')->where('INVOICE_IDENT', $invoice->INVOICE_IDENT)
            ->whereDate('DEADLINE', '>=', $today)
            ->count() > 0;
    }

    public function getshortinvoice($invoice_ident)
    {
        return substr($invoice_ident, 5, strlen($invoice_ident));
    }

    private function getInvoiceDetailsSum(string $invoiceId): float
    {
        $Fees_Cost = collect(DB::connection('mysql1')->select('SELECT SUM(REAL_FEE_AMOUNT) as price FROM financial_invoices_details WHERE INVOICE_IDENT = ?', [$invoiceId]))->first()->price;
        return $Fees_Cost;
    }

    private function isBondsIdUsed(int $bondsId): bool
    {
        if (PendingPayment::on('mysql1')->where('bound_id', $bondsId)->exists() || invoice::on('mysql1')->where('BONDS_ID', $bondsId)->exists()) {
            return true;
        } else {
            return false;
        }
    }

    private function createPaymentRecord(array $paymentData, int $bankId, array $extrnalDataPayment)
    {
        $payment = null;
        $apiPayment = API_PYMENT::on('mysql1')->where('BONDS_ID', $extrnalDataPayment['bonds_id']);
        if (!$apiPayment->where('PAYMENT_FLAG', '>', 0)->exists()) {
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
            ->where('BONDS_ID', '!=', $extrnalDataPayment['bonds_id'])->update(['PAYMENT_FLAG' => 99]);

        $PendingPayment = PendingPayment::on('mysql1')->where('invoice_id', $this->getshortinvoice($paymentData['invoice_id']))->first();
        if ($PendingPayment) {
            $PendingPayment->update([
                'status_flag' => 1,
                'bound_id' => $payment->BONDS_ID,
                'payment_key' => $extrnalDataPayment['key'],
                'api_payment_id' => $payment->API_PAYMENT_ID
            ]);
        } else {
            $PendingPayment = new PendingPayment;
            $PendingPayment->setConnection('mysql1');
            $PendingPayment->api_payment_id = $payment->API_PAYMENT_ID;
            $PendingPayment->invoice_id = $this->getshortinvoice($paymentData['invoice_id']);
            $PendingPayment->payment_key = $extrnalDataPayment['key'];
            $PendingPayment->bound_id = $payment->BONDS_ID;
            $PendingPayment->status_flag = 1;
            $PendingPayment->save();
        }
        return ['status' => 'success', 'code' => '#200', 'message' => 'Payment registered successfully'];
    }

    private function getApiUserId(): int
    {
        return (int) (collect(DB::connection('mysql1')->select("select USER_IDENT from users where GROUP_IDENT=250 AND LOGON_ID='sar_api@localhost'"))->first()->USER_IDENT ?? 0);
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
            return (int) PaymentUser::on('mysql1')->where('BANK_IDENT', $bank_id)->where('EXTERNAL_PAYMENT_USER', $payment_by)->first()->PAYMENT_USER_IDENT;
        }
    }
}


