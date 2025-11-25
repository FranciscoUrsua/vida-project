<?php
// Modules/Centro/Http/Controllers/TipoCentroController.php
// Controlador Resource para CRUD de Tipos de Centro

namespace Modules\Centro\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Http\Requests\StoreTipoCentroRequest;
use Modules\Centro\Http\Requests\UpdateTipoCentroRequest;
use Modules\Centro\Http\Resources\TipoCentroResource;

class TipoCentroController extends Controller
{
    /**
     * Muestra lista de tipos de centro (paginado).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TipoCentro::query();
        if ($request->has('con_plazas')) {
            $query->conPlazas();
        }
        if ($request->has('publico')) {
            $query->paraPublico($request->input('publico'));
        }
        $tipos = $query->paginate($request->get('per_page', 15));

        return response()->json(TipoCentroResource::collection($tipos));
    }

    /**
     * Muestra un tipo de centro específico.
     */
    public function show(TipoCentro $tipoCentro): JsonResponse
    {
        return response()->json(new TipoCentroResource($tipoCentro->load('prestaciones')));
    }

    /**
     * Crea un nuevo tipo de centro.
     */
    public function store(StoreTipoCentroRequest $request): JsonResponse
    {
        $tipoCentro = TipoCentro::create($request->validated());

        return response()->json(new TipoCentroResource($tipoCentro), 201);
    }

    /**
     * Actualiza un tipo de centro existente.
     */
    public function update(UpdateTipoCentroRequest $request, TipoCentro $tipoCentro): JsonResponse
    {
        $tipoCentro->update($request->validated());

        return response()->json(new TipoCentroResource($tipoCentro));
    }

    /**
     * Elimina un tipo de centro (soft delete si aplica, pero como auxiliar, restrict en FK).
     */
    public function destroy(TipoCentro $tipoCentro): JsonResponse
    {
        if ($tipoCentro->centros()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar un tipo con centros asociados.'], 422);
        }
        $tipoCentro->delete();

        return response()->json(null, 204);
    }
}
