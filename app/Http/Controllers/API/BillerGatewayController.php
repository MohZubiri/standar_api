<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillerPresentmentRequest;
use App\Http\Requests\BillerPaymentRequest;
use App\Http\Requests\BillerPaymentNotificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Legacy\InvoiceService as LegacyInvoiceService;
use App\Services\Legacy\PaymentService as LegacyPaymentService;

class BillerGatewayController extends Controller
{
    public function presentment(BillerPresentmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $legacy = new LegacyInvoiceService();
        $laravelRequest = new Request([
            'INVOICE_IDENT' => $validated['bill_number'],
            'TYPE' => 0,
        ]);
        $result = $legacy->getInvoiceDetails($laravelRequest, new \stdClass());
        return response()->json($result);
    }

    public function payment(BillerPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $parameters = ['connectionDriver' => config('database.connections.mysql.database')];
        $legacy = new LegacyPaymentService();
        $paymentData = [
            'invoice_id' => $validated['bill_number'],
            'invoice_cost' => $validated['amount'],
            'faculty_id' => 0,
            'program_id' => 0,
            'student_id' => 0,
        ];
        $external = [
            'bonds_id' => (int)($validated['payment_reference']),
            'bonds_date' => now()->toDateString(),
            'payment_by' => 'gateway',
            'payment_amount' => (float)$validated['amount'],
            'key' => $validated['payment_reference'],
        ];
        $result = $legacy->processPayment($paymentData, $parameters, 0, $external);
        return response()->json($result);
    }

    public function paymentNotification(BillerPaymentNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        return response()->json(['status' => 'CONFIRMED']);
    }
}


