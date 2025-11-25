<?php
// Modules/Centro/Http/Controllers/CentroController.php
// Controlador Resource para CRUD de Centros

namespace Modules\Centro\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Http\Requests\StoreCentroRequest;
use Modules\Centro\Http\Requests\UpdateCentroRequest;
use Modules\Centro\Http\Resources\CentroResource;

class CentroController extends Controller
{
    /**
     * Muestra lista de centros (paginado, con filtros).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Centro::activos(); // Scope para activos por default
        if ($request->has('distrito_id')) {
            $query->enDistrito($request->input('distrito_id'));
        }
        if ($request->has('tipo_centro_id')) {
            $query->where('tipo_centro_id', $request->input('tipo_centro_id'));
        }
        $centros = $query->with(['tipoCentro', 'distrito', 'director.profesional'])->paginate($request->get('per_page', 15));

        return response()->json(CentroResource::collection($centros));
    }

    /**
     * Muestra un centro específico (con relaciones).
     */
    public function show(Centro $centro): JsonResponse
    {
        $centro->load(['tipoCentro.prestaciones', 'distrito', 'director.profesional', 'profesionales.profesional']);

        return response()->json(new CentroResource($centro));
    }

    /**
     * Crea un nuevo centro (invoca trait para validación de dirección).
     */
    public function store(StoreCentroRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['estado'] = $data['estado'] ?? 'activo';

        $centro = Centro::create($data);
        // El trait HasValidatableAddress se invoca en mutator o boot para lat/lng y validación

        return response()->json(new CentroResource($centro), 201);
    }

    /**
     * Actualiza un centro existente (versionado via trait).
     */
    public function update(UpdateCentroRequest $request, Centro $centro): JsonResponse
    {
        $centro->update($request->validated());

        return response()->json(new CentroResource($centro));
    }

    /**
     * Elimina un centro (soft delete para versionado histórico).
     */
    public function destroy(Centro $centro): JsonResponse
    {
        $centro->delete();

        return response()->json(null, 204);
    }
}
