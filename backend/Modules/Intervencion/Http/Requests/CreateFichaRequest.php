<?php

namespace Modules\Intervencion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Intervencion\Services\FichaService;

class CreateFichaRequest extends FormRequest
{
    protected $fichaService;

    public function __construct(FichaService $fichaService)
    {
        $this->fichaService = $fichaService;
    }

    public function authorize(): bool
    {
        return true; // Sanctum para roles
    }

    public function rules(): array
    {
        return [
            'valoracion_id' => 'required|exists:valoraciones,id',
            'nombre_tipo' => 'required|string|exists:tipos_fichas,nombre', // Valida existencia via Service
            'datos' => 'required|json|array:min:1',
            'notas' => 'nullable|string|max:2000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->fichaService->validarDatos($this->nombre_tipo, $this->datos); // Llama Service
        });
    }

    public function messages(): array
    {
        return [
            'datos.required' => 'Los datos de la ficha son obligatorios.',
            'nombre_tipo.required' => 'El tipo de ficha es requerido.',
        ];
    }
}
