<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProfesionalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Profesional::with(['titulacion', 'centros'])->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:100',
                'apellido1' => 'required|string|max:100',
                'apellido2' => 'nullable|string|max:100',
                'tipo_documento' => ['required', Rule::in(['DNI', 'NIE', 'PASAPORTE', 'OTRO'])],
                'numero_id' => 'required|string|max:20|unique:profesionales',
                'email' => 'required|email|unique:profesionales',
                'telefono' => 'nullable|string|max:20',
                'sexo' => ['nullable', Rule::in(['M', 'F', 'D'])],
                'titulacion_id' => 'nullable|exists:titulaciones,id',
            ]);

            $profesional = Profesional::create($validated);
            return response()->json($profesional->load(['titulacion']), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function update(Request $request, Profesional $profesional): JsonResponse
    {
        try {
            $validated = $request->validate(array_merge(
                [
                    'nombre' => 'sometimes|string|max:100',
                    'apellido1' => 'sometimes|string|max:100',
                    'apellido2' => 'nullable|string|max:100',
                    'email' => 'sometimes|email|unique:profesionales,email,' . $profesional->id,
                    'telefono' => 'sometimes|string|max:20',
                    'titulacion_id' => 'nullable|exists:titulaciones,id',
                    'centros_ids' => 'nullable|array',
                    'centros_ids.*' => 'exists:centros,id',
                ],
                Profesional::identityValidationRules()  // Ajusta unique para update
            ));

            $profesional->update($validated);
            if ($request->has('centros_ids')) {
                $profesional->centros()->sync($request->input('centros_ids', []));  // Sync: asigna/desa asigna centros
            }
            return response()->json($profesional->fresh()->load(['titulacion', 'centros']));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show(Profesional $profesional): JsonResponse
    {
        return response()->json($profesional->load([
            'titulacion',  // Relación con Titulacion
            'centros',  // Relación N:N con Centros (incluye pivot timestamps si needed)
            'directores'  // Relación hasMany con Director (muestra centros dirigidos)
        ]));
    }


    public function destroy(Profesional $profesional): JsonResponse
    {
        $profesional->delete();
        return response()->json(null, 204);
    }

}
