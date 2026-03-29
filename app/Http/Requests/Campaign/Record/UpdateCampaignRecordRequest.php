<?php

namespace App\Http\Requests\Campaign\Record;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class UpdateCampaignRecordRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => [
                'nullable',
                'string',
            ],
            'attachment_url' => [
                'nullable',
                'string',
                'url',
            ],
        ];
    }

    /**
     * Custom message for validation
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.string' => 'El mensaje debe ser una cadena de texto.',
            'attachment_url.string' => 'La URL del adjunto debe ser una cadena de texto.',
            'attachment_url.url' => 'La URL del adjunto debe ser una URL válida.',
        ];
    }
}
