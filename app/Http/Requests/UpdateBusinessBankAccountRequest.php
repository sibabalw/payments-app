<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessBankAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $business = $this->route('business');

        if (! $business) {
            return false;
        }

        $user = $this->user();

        // Owner or manager can update bank account details
        return $user->businesses()
            ->where('businesses.id', $business->id)
            ->whereIn('business_user.role', ['owner', 'manager'])
            ->exists()
            || $user->ownedBusinesses()->where('id', $business->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_account_details' => ['required', 'array'],
            'bank_account_details.account_number' => ['required', 'string', 'max:255'],
            'bank_account_details.bank_name' => ['required', 'string', 'max:255'],
            'bank_account_details.account_holder_name' => ['required', 'string', 'max:255'],
            'bank_account_details.account_type' => ['required', 'string', 'in:checking,savings,business'],
            'bank_account_details.branch_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_account_details.required' => 'Bank account details are required.',
            'bank_account_details.account_number.required' => 'Account number is required.',
            'bank_account_details.bank_name.required' => 'Bank name is required.',
            'bank_account_details.account_holder_name.required' => 'Account holder name is required.',
            'bank_account_details.account_type.required' => 'Account type is required.',
            'bank_account_details.account_type.in' => 'Account type must be checking, savings, or business.',
        ];
    }
}
