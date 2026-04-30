<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceRequest extends FormRequest
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
            'client_id' => 'required|exists:clients,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'add_stamp_duty' => 'nullable|boolean',
            'save_as_draft' => 'nullable|boolean',
        ];
    }

    public function messages()
    {
        return [
            'client_id.required' => 'Client ID is required',
            'client_id.exists' => 'Client not found',
            'description.required' => 'Description is required',
            'amount.required' => 'Amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be greater than 0',
            'issue_date.required' => 'Issue date is required',
            'issue_date.date' => 'Issue date must be a valid date',
            'due_date.required' => 'Due date is required',
            'due_date.date' => 'Due date must be a valid date',
            'due_date.after_or_equal' => 'Due date must be after or equal to issue date',
        ];
    }
}
