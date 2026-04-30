<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
            'send_mode' => 'nullable|string|in:auto,manual',
            'settings' => 'nullable|array',
        ];
    }

    public function messages()
    {
        return [
            'send_mode.in' => 'Send mode must be either "auto" or "manual"',
        ];
    }
}
