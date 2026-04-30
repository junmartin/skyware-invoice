<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VoidInvoiceRequest extends FormRequest
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
            'void_reason' => 'nullable|string|max:500',
        ];
    }

    public function messages()
    {
        return [
            'void_reason.max' => 'Void reason cannot exceed 500 characters',
        ];
    }
}
