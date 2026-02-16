<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessRequest extends FormRequest
{
    /**
     * Validation rules for creating a business (onboarding and create business page).
     * Keep required/optional in sync with the frontend multi-step form.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'business_type' => 'nullable|in:small_business,medium_business,large_business,sole_proprietorship,partnership,corporation,other',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_person_name' => 'required|string|max:255',
        ];
    }
}
