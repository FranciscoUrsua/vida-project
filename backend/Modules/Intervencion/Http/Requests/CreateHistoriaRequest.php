<?php

namespace Modules\Intervencion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateHistoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Usa middleware Sanctum para roles (e.g., trabajador_social)
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'social_user_id' => 'required|exists:social_users,id',
            'profesional_id' => 'required|exists:profesionales,id', // Nuevo: requerido para trazabilidad
            'fecha_apertura' => 'required|date|before_or_equal:today',
            'centro_id' => 'nullable|exists:centros,id',
            'metadatos' => 'nullable|json|array', // Validación ligera; más en service si needed
        ];
    }

    /**
     * Mensajes personalizados (en español para UX).
     */
    public function messages(): array
    {
        return [
            'social_user_id.required' => 'El ID del usuario social es obligatorio.',
            'profesional_id.required' => 'El ID del profesional es obligatorio.',
            'fecha_apertura.required' => 'La fecha de apertura es requerida.',
        ];
    }
}
