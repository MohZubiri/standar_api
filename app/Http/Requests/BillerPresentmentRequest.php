<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillerPresentmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_number' => ['required', 'string', 'max:64'],
            'customer_id' => ['required', 'string', 'max:64'],
        ];
    }
}


