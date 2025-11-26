<?php
// Modules/Centro/Http/Requests/StoreTipoCentroRequest.php

namespace Modules\Centro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTipoCentroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create-tipos-centros'); // Asumiendo policy o gate via AppUser
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'unique:tipos_centros,nombre'],
            'descripcion' => ['nullable', 'string'],
            'tiene_plazas' => ['required', 'boolean'],
            'numero_plazas' => ['nullable', 'integer', 'min:1'],
            'criterio_asignacion_plazas' => ['nullable', 'string'],
            'prestaciones_default' => ['nullable', 'array'],
            'publico_objetivo' => ['nullable', 'array'],
            'schema_campos_dinamicos' => ['nullable', 'array'],
        ];
    }
}
