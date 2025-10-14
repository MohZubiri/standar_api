<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillerPresentmentRequest;
use App\Http\Requests\BillerPaymentRequest;
use App\Http\Requests\BillerPaymentNotificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\InvoiceService;
use App\Services\ValidationService;
use App\Services\RedirectConnectionService;
use App\Services\PaymentService;
use App\Services\Legacy\InvoiceService as LegacyInvoiceService;
use App\Services\Legacy\PaymentService as LegacyPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\API_PYMENT;
use App\Models\invoice;

class BillerGatewayController extends Controller
{
    /**
     * Handle bill presentment request from payment gateway
     * معالجة طلب عرض الفاتورة من بوابة الدفع
     *
     * @param BillerPresentmentRequest $request
     * @return JsonResponse
     */
    public function presentment(BillerPresentmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Convert gateway request to internal format
        $laravelRequest = new Request([
            'INVOICE_IDENT' => $validated['bill_number'],
            'TYPE' => 0,
        ]);
        // Inject required services
        $postController = app(\App\Http\Controllers\API\PostController::class);
        $validationService = app(ValidationService::class);
        $redirectService = app(RedirectConnectionService::class);
        $invoiceService = app(InvoiceService::class);

        // Get invoice summary using existing API
        $summaryResponse = $postController->showinvoice($laravelRequest, $validationService, $redirectService);
        $summaryData = $summaryResponse instanceof JsonResponse
            ? $summaryResponse->getData(true)
            : (is_array($summaryResponse) ? $summaryResponse : []);

        // Get details using InvoiceService (same data model as show_invoice_details)
        $detailsResult = $invoiceService->getInvoiceDetails($laravelRequest, $validationService);
        if (isset($detailsResult['status']) && $detailsResult['status'] === 'error') {
            return response()->json($detailsResult);
        }
        $detailsData = $detailsResult;
        if (isset($detailsResult['status']) && ($detailsResult['status'] === 'success' || $detailsResult['status'] === 'seccess')) {
            $detailsData = $detailsResult['data'] ?? $detailsResult;
        }

        $combined = ['result' => $summaryData];
        $combined['result']['deetails'] = $detailsData;

        return response()->json($combined);
    }

    /**
     * Handle payment request from payment gateway
     * معالجة طلب الدفع من بوابة الدفع
     *
     * @param BillerPaymentRequest $request
     * @return JsonResponse
     */
    public function payment(BillerPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Convert gateway payment request to internal format
        $laravelRequest = new Request([
            'INVOICE_IDENT' => $validated['bill_number'],
            'BONDS_ID' => (int)($validated['payment_reference']),
            'BONDS_DATE' => now()->toDateString(),
            'PAYMENT_BY' => 'gateway',
            'PAYMENT' => (float)$validated['amount'],
        ]);

        // Inject required services
        $postController = app(\App\Http\Controllers\API\PostController::class);
        $validationService = app(ValidationService::class);
        $paymentService = app(PaymentService::class);

        // Process payment using existing API
        $response = $postController->store($laravelRequest, $validationService, $paymentService);

        // Ensure JsonResponse
        if ($response instanceof JsonResponse) {
            return $response;
        }

        return response()->json($response);
    }

    /**
     * Handle payment notification from payment gateway
     * معالجة إشعار الدفع من بوابة الدفع
     * 
     * This endpoint is called by the payment gateway to confirm/finalize a payment.
     * It looks up the payment record by api_payment_id (if provided) or by invoice/bonds/bank combination,
     * then executes the financial function to complete the payment.
     *
     * @param BillerPaymentNotificationRequest $request
     * @return JsonResponse {Error_Code: '000', Error_Description: 'Operation Success'} on success
     */
    public function paymentNotification(BillerPaymentNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Resolve university database and setup connection (mysql1)
            $redirectService = app(RedirectConnectionService::class);
            $res = $redirectService->redirect_db($validated['bill_number']);
            if (!isset($res['connectionDriver'])) {
                return response()->json(['Error_Code' => '504', 'Error_Description' => 'Invalid invoice/university'], 400);
            }

            // Setup database connection for the university
            DB::purge('mysql1');
            config(['database.connections.mysql1.database' => $res['connectionDriver']]);
            DB::reconnect('mysql1');

            // Try to get api_payment_id from request (optional parameter)
            $apiPaymentId = request()->input('api_payment_id');

            // Lookup payment record by API_PAYMENT_ID if provided
            $apiPayment = null;
            if ($apiPaymentId) {
                $apiPayment = API_PYMENT::on('mysql1')->where('API_PAYMENT_ID', $apiPaymentId)->first();
            }

            // If not found by api_payment_id, lookup by invoice/bonds/bank combination
            if (!$apiPayment) {
                $invoiceLong = $validated['bill_number'];
                $invoiceShort = substr($invoiceLong, 5); // Remove prefix to get short invoice ID
                $bondsId = (int)$validated['bank_reference'];
                $bankId = Auth::user()->bank_id ?? null;

                $apiPayment = API_PYMENT::on('mysql1')
                    ->where('INVOICE_IDENT', $invoiceShort)
                    ->where('BONDS_ID', $bondsId)
                    ->when($bankId !== null, function ($q) use ($bankId) {
                        $q->where('BANK_ID', (int)$bankId);
                    })
                    ->orderByDesc('API_PAYMENT_ID')
                    ->first();

                if (!$apiPayment) {
                    return response()->json(['Error_Code' => '510', 'Error_Description' => 'Payment record not found'], 404);
                }
                $apiPaymentId = $apiPayment->API_PAYMENT_ID;
            }

            // Fetch invoice record to compute payment key
            $invoice = invoice::on('mysql1')->where('INVOICE_IDENT', $apiPayment->INVOICE_IDENT)->first();
            if (!$invoice) {
                return response()->json(['Error_Code' => '504', 'Error_Description' => 'Invoice not found'], 404);
            }

            // Verify authenticated bank user
            $bankId = Auth::user()->bank_id ?? null;
            if ($bankId === null) {
                return response()->json(['Error_Code' => '203', 'Error_Description' => 'Unauthorized bank user'], 403);
            }

            // Get payment key using financial validation service
            $postController = app(\App\Http\Controllers\API\PostController::class);
            $paymentKey = $postController->CheckFininsial($invoice, $invoice->INVOICE_IDENT, (int)$bankId, 1, $res);
            if ($paymentKey == 99) {
                return response()->json(['Error_Code' => '514', 'Error_Description' => 'Financial validation failed'], 422);
            }

            // Execute financial stored procedure to finalize payment
            $result = optional(collect(DB::connection('mysql1')->select(
                'SELECT financial_function_api_send_payment(?,?) as msg',
                [
                    $apiPaymentId,
                    $paymentKey
                ]
            ))->first())->msg;

            // Check if financial function succeeded
            if ($result == 1 && strpos(strtolower((string)$result), 'error') === false) {
                // Mark payment as completed (PAYMENT_FLAG = 1)
                API_PYMENT::on('mysql1')
                    ->where('API_PAYMENT_ID', $apiPaymentId)
                    ->update(['PAYMENT_FLAG' => 1]);

                return response()->json([
                    'Error_Code' => '000',
                    'Error_Description' => 'Operation Success',
                ]);
            }

            // Financial function failed
            return response()->json([
                'Error_Code' => '551',
                'Error_Description' => $result ?: 'Unknown error',
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Gateway paymentNotification error: ' . $e->getMessage());
            return response()->json([
                'Error_Code' => '551',
                'Error_Description' => 'Server process Internal Error',
            ], 500);
        }
    }
}


