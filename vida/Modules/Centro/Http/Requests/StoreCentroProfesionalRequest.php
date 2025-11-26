<?php
// Modules/Centro/Http/Requests/StoreCentroProfesionalRequest.php

namespace Modules\Centro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCentroProfesionalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create-centro-profesionales');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'profesional_id' => ['required', 'exists:profesionales,id'],
            'centro_id' => ['required', 'exists:centros,id'],
            'fecha_alta' => ['required', 'date'],
            'fecha_baja' => ['nullable', 'date', 'after:fecha_alta'],
        ];
    }
}
