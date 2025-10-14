<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_number' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_reference' => ['required', 'string', 'max:128'],
        ];
    }
}


