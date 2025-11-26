<?php
// Modules/Centro/Http/Requests/UpdateCentroProfesionalRequest.php

namespace Modules\Centro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Centro\Models\CentroProfesional;

class UpdateCentroProfesionalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('update-centro-profesionales', $this->route('centroProfesional'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'profesional_id' => ['sometimes', 'required', 'exists:profesionales,id'],
            'centro_id' => ['sometimes', 'required', 'exists:centros,id'],
            'fecha_alta' => ['sometimes', 'required', 'date'],
            'fecha_baja' => ['sometimes', 'nullable', 'date', 'after:fecha_alta'],
        ];
    }
}
