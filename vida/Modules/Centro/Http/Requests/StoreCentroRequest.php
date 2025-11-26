<?php
// Modules/Centro/Http/Requests/StoreCentroRequest.php

namespace Modules\Centro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCentroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create-centros');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tipo_centro_id' => ['required', 'exists:tipos_centros,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'estado' => ['nullable', 'in:activo,inactivo'],
            'street_type' => ['nullable', 'string', 'max:50'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:10'],
            'additional_info' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string', 'size:5'],
            'distrito_id' => ['nullable', 'exists:distritos,id'],
            'city' => ['nullable', 'string', 'default:Madrid'],
            'country' => ['nullable', 'string', 'default:España'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^\+?[\d\s\-\(\)]+$/'],
            'email_contacto' => ['nullable', 'email', 'max:255'],
            'director_id' => ['nullable', 'exists:directores,id'],
            'personal' => ['nullable', 'array'],
            'datos_especificos' => ['nullable', 'array'],
        ];
    }
}
