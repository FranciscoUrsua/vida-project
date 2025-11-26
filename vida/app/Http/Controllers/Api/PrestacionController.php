<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prestacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrestacionController extends Controller
{
    /**
     * Lista prestaciones (con filtros opcionales por categoría/nivel).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Prestacion::query();

        // Filtros del documento (e.g., ?categoria=mayores_y_dependencia)
        if ($request->filled('categoria')) {
            $query->porCategoria($request->categoria);
        }
        if ($request->filled('nivel')) {
            $query->porNivel($request->nivel);
        }

        // Nueva: Carga relación para mostrar asignaciones
        return response()->json($query->with('socialUsers')->paginate(10));
    }

    /**
     * Crea una nueva prestación.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:prestacions,nombre',
            'descripcion' => 'required|string',
            'categoria' => 'required|string|in:mayores_y_dependencia,familia_e_infancia',  // Del índice p. 2
            'nivel' => 'required|string|in:estatal,autonomica,municipal',  // Subsecciones
            'requisitos' => 'nullable|json|array',  // JSON para condiciones (e.g., edad, ingresos)
            'documentos' => 'nullable|array|max:10',  // Array de strings (e.g., ["DNI", "certificado"])
            'documentos.*' => 'string|max:100',
            'publico_objetivo' => 'required|string|max:500',  // Descripción de target
            'subcategoria' => 'nullable|string|max:100',  // Opcional para granularidad
        ]);

        $prestacion = Prestacion::create($validated);

        // Sync usuarios si enviados
        if ($request->has('social_users_ids')) {
            $prestacion->socialUsers()->sync($request->input('social_users_ids', []));
        }

        return response()->json($prestacion->load('socialUsers'), 201);

    }

    /**
     * Muestra una prestación específica.
     */
    public function show(Prestacion $prestacion): JsonResponse
    {
        return response()->json($prestacion->load('socialUsers'));
    }

    /**
     * Actualiza una prestación existente.
     */
    public function update(Request $request, Prestacion $prestacion): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255|unique:prestacions,nombre,' . $prestacion->id,
            'descripcion' => 'sometimes|string',
            'categoria' => 'sometimes|string|in:mayores_y_dependencia,familia_e_infancia',
            'nivel' => 'sometimes|string|in:estatal,autonomica,municipal',
            'requisitos' => 'sometimes|json|array',
            'documentos' => 'sometimes|array|max:10',
            'documentos.*' => 'string|max:100',
            'publico_objetivo' => 'sometimes|required|string|max:500',
            'subcategoria' => 'sometimes|string|max:100',
            'social_users_ids' => 'nullable|array',
            'social_users_ids.*' => 'exists:social_users,id',
        ]);

        $prestacion->update($validated);

        if ($request->has('social_users_ids')) {
            $prestacion->socialUsers()->sync($request->input('social_users_ids', []));  
        }

        return response()->json($prestacion->fresh()->load('socialUsers'));

    }

    /**
     * Elimina una prestación.
     */
    public function destroy(Prestacion $prestacion): JsonResponse
    {
        // Opcional: Desasigna usuarios antes de delete
        $prestacion->socialUsers()->detach();
        $prestacion->delete();
        return response()->json(null, 204);
    }
}
