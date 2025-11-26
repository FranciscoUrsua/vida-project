<?php
// Modules/Centro/Http/Requests/UpdateTipoCentroRequest.php

namespace Modules\Centro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Centro\Models\TipoCentro;

class UpdateTipoCentroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('update-tipos-centros', $this->route('tipoCentro'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tipoCentroId = $this->route('tipoCentro')->id ?? 0;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:100', "unique:tipos_centros,nombre,{$tipoCentroId}"],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'tiene_plazas' => ['sometimes', 'required', 'boolean'],
            'numero_plazas' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'criterio_asignacion_plazas' => ['sometimes', 'nullable', 'string'],
            'prestaciones_default' => ['sometimes', 'nullable', 'array'],
            'publico_objetivo' => ['sometimes', 'nullable', 'array'],
            'schema_campos_dinamicos' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
