<?php
// Modules/Centro/Http/Controllers/CentroProfesionalController.php
// Controlador Resource para CRUD de asignaciones Centro-Profesional

namespace Modules\Centro\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Centro\Models\CentroProfesional;
use Modules\Centro\Http\Requests\StoreCentroProfesionalRequest;
use Modules\Centro\Http\Requests\UpdateCentroProfesionalRequest;
use Modules\Centro\Http\Resources\CentroProfesionalResource;

class CentroProfesionalController extends Controller
{
    /**
     * Muestra lista de asignaciones (paginado, filtros por centro/profesional/fecha).
     */
    public function index(Request $request): JsonResponse
    {
        $query = CentroProfesional::query();
        if ($request->has('centro_id')) {
            $query->where('centro_id', $request->input('centro_id'));
        }
        if ($request->has('profesional_id')) {
            $query->where('profesional_id', $request->input('profesional_id'));
        }
        if ($request->has('fecha')) {
            $query->enFecha($request->input('fecha'));
        }
        $asignaciones = $query->with(['profesional', 'centro'])->paginate($request->get('per_page', 15));

        return response()->json(CentroProfesionalResource::collection($asignaciones));
    }

    /**
     * Muestra una asignación específica.
     */
    public function show(CentroProfesional $centroProfesional): JsonResponse
    {
        $centroProfesional->load(['profesional', 'centro']);

        return response()->json(new CentroProfesionalResource($centroProfesional));
    }

    /**
     * Crea una nueva asignación (verifica no solapamiento).
     */
    public function store(StoreCentroProfesionalRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Verifica no solapamiento en centro-profesional
        $existente = CentroProfesional::where('profesional_id', $data['profesional_id'])
            ->where('centro_id', $data['centro_id'])
            ->where('fecha_alta', $data['fecha_alta'])
            ->first();
        if ($existente) {
            return response()->json(['error' => 'Asignación ya existe.'], 422);
        }

        $asignacion = CentroProfesional::create($data);

        return response()->json(new CentroProfesionalResource($asignacion), 201);
    }

    /**
     * Actualiza una asignación (e.g., fecha_baja).
     */
    public function update(UpdateCentroProfesionalRequest $request, CentroProfesional $centroProfesional): JsonResponse
    {
        $centroProfesional->update($request->validated());

        return response()->json(new CentroProfesionalResource($centroProfesional));
    }

    /**
     * Elimina una asignación (soft delete).
     */
    public function destroy(CentroProfesional $centroProfesional): JsonResponse
    {
        $centroProfesional->delete();

        return response()->json(null, 204);
    }
}
