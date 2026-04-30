<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MarkInvoiceAsSentRequest extends FormRequest
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
            'manual_sent_at' => 'nullable|date',
            'manual_note' => 'nullable|string|max:500',
        ];
    }

    public function messages()
    {
        return [
            'manual_sent_at.date' => 'Manual sent date must be a valid date',
            'manual_note.max' => 'Note cannot exceed 500 characters',
        ];
    }
}
