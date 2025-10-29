<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfesionalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Profesional::with(['titulacion', 'centros'])->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'tipo_id' => 'required|string|max:10',
            'numero_id' => 'required|string|max:20|unique:profesionales',
            'email' => 'required|email|unique:profesionales',
            'telefono' => 'nullable|string|max:20',
            'titulacion_id' => 'nullable|exists:titulaciones,id',
        ]);

        $profesional = Profesional::create($validated);
        return response()->json($profesional->load(['titulacion']), 201);
    }

    // show, update, destroy similares... (omito por brevedad; usa $profesional->update($validated))

    public function destroy(Profesional $profesional): JsonResponse
    {
        $profesional->delete();
        return response()->json(null, 204);
    }
}
