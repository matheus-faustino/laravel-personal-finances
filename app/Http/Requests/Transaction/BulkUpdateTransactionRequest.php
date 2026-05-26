<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.id' => ['required', 'integer', 'exists:transactions,id', 'distinct'],
            'transactions.*.name' => ['required', 'string', 'max:255'],
            'transactions.*.description' => ['nullable', 'string'],
            'transactions.*.date' => ['required', 'date'],
            'transactions.*.value' => ['required', 'numeric', 'min:0.01'],
            'transactions.*.category_id' => ['required', 'integer', 'exists:categories,id'],
        ];
    }
}
