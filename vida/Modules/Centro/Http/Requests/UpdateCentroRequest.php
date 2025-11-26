<?php
// Modules/Centro/Http/Requests/UpdateCentroRequest.php

namespace Modules\Centro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Centro\Models\Centro;

class UpdateCentroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('update-centros', $this->route('centro'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $centroId = $this->route('centro')->id ?? 0;

        return [
            'tipo_centro_id' => ['sometimes', 'required', 'exists:tipos_centros,id'],
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
            'street_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'street_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'street_number' => ['sometimes', 'nullable', 'string', 'max:10'],
            'additional_info' => ['sometimes', 'nullable', 'string'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'size:5'],
            'distrito_id' => ['sometimes', 'nullable', 'exists:distritos,id'],
            'city' => ['sometimes', 'nullable', 'string'],
            'country' => ['sometimes', 'nullable', 'string'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?[\d\s\-\(\)]+$/'],
            'email_contacto' => ['sometimes', 'nullable', 'email', 'max:255'],
            'director_id' => ['sometimes', 'nullable', 'exists:directores,id'],
            'personal' => ['sometimes', 'nullable', 'array'],
            'datos_especificos' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
