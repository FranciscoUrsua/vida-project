<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Centro;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CentroController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Centro::activos()->with(['director.profesional', 'profesionales'])->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => 'required|string|max:50',
            'nombre' => 'required|string|max:255',
            'direccion_postal' => 'required|string',
            'telefono' => 'required|string|max:20',
            'email_contacto' => 'nullable|email|max:255',
            'director_id' => 'nullable|exists:directores,id',
            'campos_especificos' => 'nullable|json',
        ]);

        $centro = Centro::create($validated);
        return response()->json($centro->load(['director.profesional']), 201);
    }

    // update: similar, con sync para profesionales si se envía array
    public function update(Request $request, Centro $centro): JsonResponse
    {
        $validated = $request->validate([/* mismos campos */]);
        $centro->update($validated);
        $centro->profesionales()->sync($request->input('profesionales_ids', [])); // Ejemplo para many-to-many
        return response()->json($centro->fresh()->load(['director.profesional', 'profesionales']));
    }

    // destroy similar...
}
