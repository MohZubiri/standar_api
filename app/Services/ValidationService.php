<?php

namespace App\Services;

use App\Models\FinancialBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidationService
{


     /**
     * Validate show invoice request
     *
     * @param Request $request
     * @return array
     */
    public function validateShowInvoiceRequest(Request $request,ValidationService $validationService)
    {
        $input = $request->all();
        
        // Validate using Laravel validator
        $validator = Validator::make($input, [
            'INVOICE_IDENT' => 'required|integer',
            'TYPE' => 'integer'
        ]);
        
        if ($validator->fails()) {
            return ['success' => false, 'code' => '#502', 'message' => $validator->errors()];
        }
        
        // Check for invalid symbols
        foreach ($input as $key => $value) {
            $output = false;
            if ($key == "BONDS_DATE") {
                $output = $validationService->checkSymbols($value, 1);
            } else if ($key != "TYPE") {
                $output = $validationService->checkSymbols($value, 0);
            }
            
            if ($output) {
                return ['success' => false, 'code' => '#502', 'message' => 'Invalid symbols in input'];
            }
        }
        
        return ['success' => true];
    }
    /**
     * Validate payment request data
     *
     * @param Request $request
     * @return array
     */
    public function validatePaymentRequest(Request $request): array
    {
        $input = $request->all();
        
        $validator = Validator::make($input, [
            'INVOICE_IDENT' => 'required|integer',
            'BONDS_ID' => 'required|integer',
            'BONDS_DATE' => 'required|date',
            'PAYMENT_BY' => 'required',
            'PAYMENT' => 'required'
        ], [
            'required' => 'هذا الحقل مطلوب'
        ]);
        
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'code' => '#502',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ];
        }
        
        // Check for invalid symbols in input
        foreach ($input as $key => $value) {
            $output = false;
            
            if ($key == "BONDS_DATE") {
                $output = $this->checkSymbols($value, 1);
            } else if ($key == "PAYMENT") {
                $output = $this->checkSymbols($value, 2);
            } else {
                $output = $this->checkSymbols($value, 0);
            }
            
            if ($output) {
                return [
                    'status' => 'error',
                    'code' => '#502',
                    'message' => 'Invalid characters in input'
                ];
            }
        }
        
        return [
            'status' => 'success',
            'data' => $input
        ];
    }
    
    /**
     * Check for invalid symbols in input
     *
     * @param mixed $value
     * @param int $flag
     * @return bool
     */
    public function checkSymbols($value, int $flag): bool
    {
        if ($flag == 0) {
            $whiteListed = "\$\@\#\^\|\!\~\=\+\-\_\.\>\<\*\/";
        } elseif ($flag == 1) {
            $whiteListed = "\$\@\#\^\|\!\~\=\+\_\.\>\<\*\/";
        } elseif ($flag == 2) {
            $whiteListed = "\$\@\#\^\|\!\~\=\+\_\>\<\*\/";
        }
        
        return preg_match('/[' . $whiteListed . ']/', $value);
    }
    
    /**
     * Validate invoice details request
     *
     * @param Request $request
     * @return array
     */
    public function validateInvoiceDetailsRequest(Request $request): array
    {
        $input = $request->all();
        
        $validator = Validator::make($input, [
            'INVOICE_IDENT' => 'required|integer',
            'TYPE' => 'integer'
        ]);
        
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'code' => '#502',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ];
        }
        
        // Check for invalid symbols in input
        foreach ($input as $key => $value) {
            $output = false;
            
            if ($key == "BONDS_DATE") {
                $output = $this->checkSymbols($value, 1);
            } else {
                $output = $this->checkSymbols($value, 0);
            }
            
            if ($output) {
                return [
                    'status' => 'error',
                    'code' => '#502',
                    'message' => 'Invalid characters in input'
                ];
            }
        }
        
        return [
            'status' => 'success',
            'data' => $input
        ];
    }
    
    /**
     * Check if user is authorized to make payments for this bank
     *
     * @param int $bankId
     * @return bool
     */
    public function checkUserAuthorization(int $bankId): bool
    {
        try{
        $bank = FinancialBank::on('mysql1')->where('BANK_ID', $bankId)->first();
        
        if ($bank && $bank->HAS_API_CONNECT == 1 && $bank->IS_ENABLE == 1) {
            return true;
        }
        
        return false;
    }catch(\Exception $e){
       
        Log::error("Error checking user authorization: " . $e->getMessage());
        return false;
    }
}
    
    /**
     * Prepare payment data for processing
     *
     * @param array $input
     * @param string $invoiceId
     * @param callable $getInvoiceFunction
     * @param callable $getPaymentUserFunction
     * @return array
     */
    public function preparePaymentData(array $input, string $invoiceId, callable $getInvoiceFunction, callable $getPaymentUserFunction): array
    {
        $invoice = $getInvoiceFunction($invoiceId);
        
        if (!$invoice) {
            return [
                'status' => 'error',
                'code' => '#504',
                'message' => 'Invoice not found'
            ];
        }
        
        $paymentData = [
            'invoice_id' => $invoiceId,
            'bonds_id' => $input['BONDS_ID'],
            'bonds_date' => $input['BONDS_DATE'],
            'payment_by' => $getPaymentUserFunction($input['PAYMENT_BY']),
            'payment_amount' => $input['PAYMENT'],
            'faculty_id' => $invoice['faculty_id'],
            'program_id' => $invoice['program_id'],
            'student_id' => $invoice['student_id'],
            'key' => $input['key'] ?? ''
        ];
        
        return [
            'status' => 'success',
            'data' => $paymentData
        ];
    }
}
