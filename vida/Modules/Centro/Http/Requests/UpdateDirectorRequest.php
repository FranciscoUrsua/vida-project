<?php
// Modules/Centro/Http/Requests/UpdateDirectorRequest.php

namespace Modules\Centro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Centro\Models\Director;

class UpdateDirectorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('update-directores', $this->route('director'));
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
