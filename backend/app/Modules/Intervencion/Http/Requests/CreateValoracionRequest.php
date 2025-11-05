<?php

namespace App\Modules\Intervencion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateValoracionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Sanctum para roles; opcional: check si profesional coincide con auth user
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'historia_id' => 'required|exists:historias,id',
            'profesional_id' => 'required|exists:profesionales,id',
            'tipo' => 'required|in:inicial,sucesiva',
            'fecha_realizacion' => 'required|date|after_or_equal:historias.fecha_apertura', // Custom rule ref a Historia (usa Rule::exists o closure)
            'resumen' => 'nullable|string|max:2000',
            'resumen_ia' => 'nullable|json|array',
        ];
    }

    /**
     * Mensajes personalizados.
     */
    public function messages(): array
    {
        return [
            'historia_id.required' => 'La historia es obligatoria.',
            'profesional_id.required' => 'El profesional es requerido.',
            'fecha_realizacion.after_or_equal' => 'La fecha debe ser posterior a la apertura de la historia.',
        ];
    }
}
