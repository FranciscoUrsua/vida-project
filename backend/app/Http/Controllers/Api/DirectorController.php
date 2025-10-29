<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Director;
use App\Models\Profesional;
use App\Models\Centro;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DirectorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Director::activos()->with(['profesional', 'centro'])->paginate(10));
    }

    // Alta de director: selecciona profesional y centro
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profesional_id' => 'required|exists:profesionales,id',
            'centro_id' => 'required|exists:centros,id',
            'fecha_alta' => 'required|date',
        ]);

        // Verifica que no sea ya director activo de ese centro
        if (Director::where('profesional_id', $validated['profesional_id'])
                ->where('centro_id', $validated['centro_id'])
                ->whereNull('fecha_baja')->exists()) {
            return response()->json(['error' => 'Ya es director activo'], 422);
        }

        $profesional = Profesional::find($validated['profesional_id']);
        $centro = Centro::find($validated['centro_id']);
        $director = $profesional->asignarComoDirector($centro, $validated['fecha_alta']);
        $centro->update(['director_id' => $director->id]);

        return response()->json($director->load(['profesional', 'centro']), 201);
    }

    // Baja
    public function darDeBaja(Request $request, Director $director): JsonResponse
    {
        $validated = $request->validate(['fecha_baja' => 'required|date|after:fecha_alta']);
        $director->darDeBaja($validated['fecha_baja']);
        return response()->json($director->fresh()->load(['profesional', 'centro']));
    }

    // destroy, etc.
}
