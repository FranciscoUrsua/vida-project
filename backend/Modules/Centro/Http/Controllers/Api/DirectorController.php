<?php

namespace Modules\Centro\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Centro\Models\Director;
use Modules\Centro\Models\Profesional;
use Modules\Centro\Models\Centro;
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

    /**
     * Muestra un director específico con sus relaciones.
     */
    public function show(Director $director): JsonResponse
    {
        return response()->json($director->load([
            'profesional',  // Relación belongsTo con Profesional (detalles del director)
            'centro'  // Relación belongsTo con Centro (contexto del centro dirigido)
        ]));
    }

    /**
     * Actualiza un director (e.g., fecha_baja o re-asignación limitada).
     * Nota: Para cambios mayores (e.g., nuevo centro), usa store nuevo.
     */
    public function update(Request $request, Director $director): JsonResponse
    {
        $validated = $request->validate([
            'fecha_baja' => [
                'nullable',
                'date',
                'after:fecha_alta',  // Debe ser después de fecha_alta
                Rule::when($request->has('fecha_baja'), function ($rule) use ($director) {
                    return $rule->where('id', $director->id);  // Solo actualiza si no baja ya
                }),
            ],
            // Opcional: Si permites re-asignar centro (cambia FK)
            'centro_id' => 'sometimes|nullable|exists:centros,id',
        ]);

        $director->update($validated);

        // Si se actualiza centro_id, actualiza referencia en centro
        if ($request->filled('centro_id')) {
            $nuevoCentro = Centro::findOrFail($validated['centro_id']);
            $nuevoCentro->update(['director_id' => $director->id]);
        }

        // Si fecha_baja seteada, llama darDeBaja para lógica extra (opcional)
        if ($request->filled('fecha_baja')) {
            $director->darDeBaja($validated['fecha_baja']);
        }

        return response()->json($director->fresh()->load(['profesional', 'centro']));
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
