<?php

namespace Modules\Centro\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Centro\Models\TipoCentro;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TipoCentroController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TipoCentro::withCount('centros')->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:tipos_centro,nombre',
            'descripcion' => 'nullable|string',
            'plazas' => 'required|boolean',
            'numero_plazas' => 'nullable|required_if:plazas,true|integer|min:1',
            'criterio_asignacion_plazas' => 'nullable|string',
            'publico_objetivo' => 'required|string|max:500',
        ]);

        $tipo = TipoCentro::create($validated);
        return response()->json($tipo->loadCount('centros'), 201);
    }

    public function show(TipoCentro $tipoCentro): JsonResponse
    {
        return response()->json($tipoCentro->load(['centros', 'centros.direccion_validada']));
    }

    public function update(Request $request, TipoCentro $tipoCentro): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:100|unique:tipos_centro,nombre,' . $tipoCentro->id,
            'descripcion' => 'nullable|string',
            'plazas' => 'sometimes|boolean',
            'numero_plazas' => 'nullable|required_if:plazas,true|integer|min:1',
            'criterio_asignacion_plazas' => 'nullable|string',
            'publico_objetivo' => 'sometimes|required|string|max:500',
        ]);

        $tipoCentro->update($validated);
        return response()->json($tipoCentro->fresh()->loadCount('centros'));
    }

    public function destroy(TipoCentro $tipoCentro): JsonResponse
    {
        if ($tipoCentro->centros()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar un tipo con centros asignados'], 422);
        }
        $tipoCentro->delete();
        return response()->json(null, 204);
    }
}
