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
    public function presentment(BillerPresentmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $laravelRequest = new Request([
            'INVOICE_IDENT' => $validated['bill_number'],
            'TYPE' => 0,
        ]);
        // Build summary via API PostController::showinvoice
        $postController = app(\App\Http\Controllers\API\PostController::class);
        $validationService = app(ValidationService::class);
        $redirectService = app(RedirectConnectionService::class);
        $invoiceService = app(InvoiceService::class);

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

    public function payment(BillerPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Build the request expected by API\PostController::store
        $laravelRequest = new Request([
            'INVOICE_IDENT' => $validated['bill_number'],
            'BONDS_ID' => (int)($validated['payment_reference']),
            'BONDS_DATE' => now()->toDateString(),
            'PAYMENT_BY' => 'gateway',
            'PAYMENT' => (float)$validated['amount'],
        ]);

        $postController = app(\App\Http\Controllers\API\PostController::class);
        $validationService = app(ValidationService::class);
        $paymentService = app(PaymentService::class);

        $response = $postController->store($laravelRequest, $validationService, $paymentService);

        // Ensure JsonResponse
        if ($response instanceof JsonResponse) {
            return $response;
        }

        return response()->json($response);
    }

    public function paymentNotification(BillerPaymentNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Resolve university and setup DB connection like PostController (mysql1)
            $redirectService = app(RedirectConnectionService::class);
            $res = $redirectService->redirect_db($validated['bill_number']);
            if (!isset($res['connectionDriver'])) {
                return response()->json(['Error_Code' => '504', 'Error_Description' => 'Invalid invoice/university'], 400);
            }

            DB::purge('mysql1');
            config(['database.connections.mysql1.database' => $res['connectionDriver']]);
            DB::reconnect('mysql1');

            // Lookup API_PYMENT
            $apiPaymentId = request()->input('api_payment_id');

            $apiPayment = null;
            if ($apiPaymentId) {
                $apiPayment = API_PYMENT::on('mysql1')->where('API_PAYMENT_ID', $apiPaymentId)->first();
            }

            if (!$apiPayment) {
                // Fallback by invoice (short), bonds_id, and bank_id
                $invoiceLong = $validated['bill_number'];
                $invoiceShort = substr($invoiceLong, 5);
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

            // Fetch invoice to compute payment key
            $invoice = invoice::on('mysql1')->where('INVOICE_IDENT', $apiPayment->INVOICE_IDENT)->first();
            if (!$invoice) {
                return response()->json(['Error_Code' => '504', 'Error_Description' => 'Invoice not found'], 404);
            }

            $bankId = Auth::user()->bank_id ?? null;
            if ($bankId === null) {
                return response()->json(['Error_Code' => '203', 'Error_Description' => 'Unauthorized bank user'], 403);
            }

            // Reuse CheckFininsial from PostController to get the key
            $postController = app(\App\Http\Controllers\API\PostController::class);
            $paymentKey = $postController->CheckFininsial($invoice, $invoice->INVOICE_IDENT, (int)$bankId, 1, $res);
            if ($paymentKey == 99) {
                return response()->json(['Error_Code' => '514', 'Error_Description' => 'Financial validation failed'], 422);
            }

            // Execute financial function
            $result = optional(collect(DB::connection('mysql1')->select(
                'SELECT financial_function_api_send_payment(?,?) as msg',
                [
                    $apiPaymentId,
                    $paymentKey
                ]
            ))->first())->msg;

            if ($result == 1 && strpos(strtolower((string)$result), 'error') === false) {
                API_PYMENT::on('mysql1')
                    ->where('API_PAYMENT_ID', $apiPaymentId)
                    ->update(['PAYMENT_FLAG' => 1]);

                return response()->json([
                    'Error_Code' => '000',
                    'Error_Description' => 'Operation Success',
                ]);
            }

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


