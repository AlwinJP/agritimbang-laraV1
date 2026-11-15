<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'buyer_name' => ['nullable', 'string', 'max:255'],

            'buyer_phone' => [
                'nullable',
                'string',
                \App\Support\ContactValidationRules::philippineMobile(),
                'max:13',
            ],

            'transaction_date' => ['required', 'date_format:Y-m-d', function ($attribute, $value, $fail) { if ($value > now('Asia/Manila')->toDateString()) $fail('The transaction date must be today or an earlier date.'); }],

            'selling_location' => ['nullable', 'string', 'max:255'],
            // Farmer transaction UI records the sale location by barangay.
            // The human-readable selling_location is derived server-side.
            'selling_barangay_id' => ['required', 'uuid', 'exists:barangays,id'],
            'transaction_type' => ['nullable', 'in:private,auction'],
            'auction_schedule_id' => ['nullable', 'uuid', 'exists:auction_schedules,id'],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'items.*.livestock_id' => [
                'required',
                'uuid',
                'distinct',
                'exists:livestock_records,id',
            ],

            'items.*.valuation_id' => ['nullable', 'uuid', 'exists:valuations,id'],

            'items.*.sale_purpose' => ['required', 'in:slaughter,breeding'],

            'items.*.actual_selling_price' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:999999999999.99',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('transaction_type') === 'auction' && !$this->filled('auction_schedule_id')) {
                $validator->errors()->add('auction_schedule_id', 'Select the auction event for this auction sale.');
            }
            if ($this->input('transaction_type') === 'private' && $this->filled('auction_schedule_id')) {
                $validator->errors()->add('auction_schedule_id', 'Private sales cannot be linked to an auction event.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.min' =>
                'At least one livestock must be included.',

            'items.*.livestock_id.distinct' =>
                'The same livestock cannot appear twice.',

            'items.*.valuation_id.exists' =>
                'The selected valuation does not exist.',

            'items.*.actual_selling_price.gt' =>
                'The selling price must be greater than zero.',
        ];
    }
}
