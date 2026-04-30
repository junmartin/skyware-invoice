<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClientUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $clientId = $this->route('client')->id ?? $this->route('id');

        return [
            'code' => 'nullable|string|unique:clients,code,' . $clientId . '|max:50',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
            'currency' => 'nullable|string|max:3',
            'default_due_days' => 'nullable|integer|min:1',
            'billing_address' => 'nullable|string|max:1000',
            'plan_name' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'code.unique' => 'Client code must be unique',
            'email.email' => 'Email must be valid',
            'default_due_days.integer' => 'Default due days must be a number',
        ];
    }
}
