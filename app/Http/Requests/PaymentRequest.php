<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/** Record a payment against an invoice. */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via InvoicePolicy@pay
    }

    public function rules(): array
    {
        return [
            'method' => ['required', new Enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function amountString(): string
    {
        return number_format((float) $this->input('amount'), 2, '.', '');
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('method'));
    }
}
