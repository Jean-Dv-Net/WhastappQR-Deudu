<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Request;

class CreateOrUpdateSettingRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $key = $this->route('key') ?? $this->input('key');

        return match ($key) {
            'campaign-messaging-rules' => $this->campaignMessagingRules(),
            default => []
        };
    }

    public function messages(): array
    {
        return [
            'settings.max_messages_per_campaign.required'        => 'El límite máximo de mensajes es requerido.',
            'settings.max_messages_per_campaign.integer'         => 'El límite máximo de mensajes debe ser un número entero.',
            'settings.max_messages_per_campaign.min'             => 'El límite máximo de mensajes debe ser al menos 1.',
            'settings.send_cadence.messages_each_second.required' => 'La cadencia de envío es requerida.',
            'settings.send_cadence.messages_each_second.integer'  => 'La cadencia de envío debe ser un número entero.',
            'settings.send_cadence.messages_each_second.min'      => 'La cadencia de envío debe ser al menos 1.',
            'settings.time_window.start.required'                => 'La hora de inicio es requerida.',
            'settings.time_window.start.date_format'             => 'La hora de inicio debe tener el formato HH:MM.',
            'settings.time_window.end.required'                  => 'La hora de fin es requerida.',
            'settings.time_window.end.date_format'               => 'La hora de fin debe tener el formato HH:MM.',
            'settings.time_window.end.after'                     => 'La hora de fin debe ser posterior a la hora de inicio.',
            'settings.time_window.timezone.required'             => 'La zona horaria es requerida.',
            'settings.time_window.timezone.timezone'             => 'La zona horaria no es válida.',
            'settings.auto_pause_on_error.required'              => 'El campo de pausa automática es requerido.',
            'settings.auto_pause_on_error.boolean'               => 'El campo de pausa automática debe ser verdadero o falso.',
            'settings.error_threshold_percentage.required'       => 'El umbral de error es requerido.',
            'settings.error_threshold_percentage.numeric'        => 'El umbral de error debe ser un número.',
            'settings.error_threshold_percentage.min'            => 'El umbral de error debe ser al menos 1%.',
            'settings.error_threshold_percentage.max'            => 'El umbral de error no puede superar el 100%.',
            'settings.notifications.enabled.required'            => 'El campo de notificaciones es requerido.',
            'settings.notifications.enabled.boolean'             => 'El campo de notificaciones debe ser verdadero o falso.',
        ];
    }

    // -------------------------------------------------------------------------
    // Rules by key
    // -------------------------------------------------------------------------
    private function campaignMessagingRules(): array
    {
        return [
            'settings.max_messages_per_campaign'        => ['required', 'integer', 'min:1'],
            'settings.send_cadence.messages_each_second' => ['required', 'integer', 'min:1'],
            'settings.time_window.start'                => ['required', 'date_format:H:i'],
            'settings.time_window.end'                  => ['required', 'date_format:H:i', 'after:settings.time_window.start'],
            'settings.time_window.timezone'             => ['required', 'timezone'],
            'settings.auto_pause_on_error'              => ['required', 'boolean'],
            'settings.error_threshold_percentage'       => ['required', 'numeric', 'min:1', 'max:100'],
            'settings.notifications.enabled'            => ['required', 'boolean'],
        ];
    }
}
