<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1', 'max:999'],
            'currency' => ['nullable', 'string'],
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ];
    }
}