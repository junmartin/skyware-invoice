<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClientStoreRequest extends FormRequest
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
        return [
            'code' => 'required|string|unique:clients,code|max:50',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
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
            'code.required' => 'Client code is required',
            'code.unique' => 'Client code must be unique',
            'name.required' => 'Client name is required',
            'email.required' => 'Client email is required',
            'email.email' => 'Email must be valid',
            'default_due_days.integer' => 'Default due days must be a number',
        ];
    }
}
