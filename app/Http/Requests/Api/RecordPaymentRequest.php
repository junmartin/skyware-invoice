<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
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
            'paid_at' => 'nullable|date',
            'payment_note' => 'nullable|string|max:500',
        ];
    }

    public function messages()
    {
        return [
            'paid_at.date' => 'Paid date must be a valid date',
            'payment_note.max' => 'Payment note cannot exceed 500 characters',
        ];
    }
}
