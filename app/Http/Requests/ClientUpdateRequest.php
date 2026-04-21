<?php

namespace App\Http\Requests;

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
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'code' => 'required|string|max:50|alpha_dash|unique:clients,code,'.$this->route('client')->id,
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'is_active' => 'nullable|boolean',
            'currency' => 'required|string|max:10',
            'default_due_days' => 'required|integer|min:1|max:90',
            'billing_address' => 'nullable|string',
            'plan_name' => 'nullable|string|max:255',
            'usage_xlsx_path' => 'nullable|string|max:500',
        ];
    }
}
