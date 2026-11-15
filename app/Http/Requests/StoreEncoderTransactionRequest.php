<?php

namespace App\Http\Requests;

use App\Models\FarmerProfile;
use Illuminate\Validation\Rule;

class StoreEncoderTransactionRequest extends StoreTransactionRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && $user->roles()->where('slug', 'lgu_encoder')->exists();
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'farmer_id' => ['required', 'uuid', Rule::exists((new FarmerProfile())->getTable(), 'id')],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_phone' => ['nullable', 'string', \App\Support\ContactValidationRules::philippineMobile(), 'max:13'],
            'transaction_type' => ['required', 'in:auction'],
            'auction_schedule_id' => ['required', 'uuid', 'exists:auction_schedules,id'],
            'selling_location' => ['required', 'string', 'max:255'],
            'selling_barangay_id' => ['nullable', 'uuid', 'exists:barangays,id'],
        ]);
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);
    }
}
