<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillerPaymentNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_number' => ['required', 'string', 'max:64'],
            'bank_reference' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'paid_at' => ['required', 'date'],
        ];
    }
}


