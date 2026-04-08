<?php

namespace App\Http\Requests\Campaign;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class CreateCampaignRequest extends Request
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel_id' => ['required', 'exists:App\Models\Channel,id'],
            'name' => ['required', 'string', 'max:255'],
            'has_attachment' => ['required', 'boolean'],
            'template_type' => ['required', 'in:text,image'],
            'template' => ['required', Rule::when(
                $this->template_type === 'text',
                ['string'],
                ['url']
            )],
            'records' => ['required', 'array'],
            'records.*.phone_number' => ['required', 'string'],
            'records.*.identification' => ['required', 'string'],
            'records.*.debtor_id' => ['required', 'integer'],
        ];
    }

    /**
     * Custom messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'channel_id.required' => 'El canal es requerido.',
            'channel_id.exists' => 'El canal seleccionado no existe.',
            'name.required' => 'El nombre es requerido.',
            'name.max' => 'El nombre debe tener como máximo 255 caracteres.',
            'has_attachment.required' => 'El campo has_attachment es requerido.',
            'has_attachment.boolean' => 'El campo has_attachment debe ser verdadero o falso.',
            'template_type.required' => 'El tipo de plantilla es requerido.',
            'template_type.in' => 'El tipo de plantilla debe ser texto o imagen.',
            'template.required' => 'La plantilla es requerida.',
            'template.string' => 'La plantilla debe ser una cadena de texto.',
            'template.url' => 'La plantilla debe ser una URL.',
            'records.required' => 'Los registros son requeridos.',
            'records.array' => 'Los registros deben ser un array.',
            'records.*.phone_number.required' => 'El número de teléfono es requerido.',
            'records.*.phone_number.string' => 'El número de teléfono debe ser una cadena de texto.',
            'records.*.identification.required' => 'La identificación es requerida.',
            'records.*.identification.string' => 'La identificación debe ser una cadena de texto.',
            'records.*.debtor_id.required' => 'El ID del deudor es requerido.',
            'records.*.debtor_id.integer' => 'El ID del deudor debe ser un número entero.',
        ];
    }
}
