<?php

namespace App\Modules\Intervencion\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Intervencion\Http\Requests\CreateFichaRequest;
use App\Modules\Intervencion\Http\Requests\UpdateFichaRequest; // Similar, crea si needed
use App\Modules\Intervencion\Services\FichaService;
use App\Modules\Intervencion\Models\Ficha;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FichaController extends Controller
{
    protected $fichaService;

    public function __construct(FichaService $fichaService)
    {
        $this->fichaService = $fichaService;
    }

    /**
     * Index: list por valoracion_id (granular).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ficha::with(['valoracion.historia', 'tipoFicha'])
            ->completas();

        if ($request->valoracion_id) {
            $query->where('valoracion_id', $request->valoracion_id);
        }

        if ($request->tipo) {
            $query->porTipo($request->tipo);
        }

        $fichas = $query->paginate(20);

        return response()->json($fichas);
    }

    /**
     * Store: crea con validación Service.
     */
    public function store(CreateFichaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $ficha = $this->fichaService->crearFicha($data);

        return response()->json($ficha->load(['valoracion', 'tipoFicha']), 201);
    }

    /**
     * Show: con datos completos.
     */
    public function show(Ficha $ficha): JsonResponse
    {
        $ficha->load(['valoracion.historia.socialUser', 'tipoFicha']);

        return response()->json($ficha);
    }

    /**
     * Update: actualiza datos/notas.
     */
    public function update(UpdateFichaRequest $request, Ficha $ficha): JsonResponse
    {
        $data = $request->validated();
        $this->fichaService->validarDatos($ficha->tipoFicha->nombre, $data['datos'] ?? $ficha->datos);

        $ficha->update($data);

        return response()->json($ficha->fresh());
    }

    /**
     * Destroy: soft delete.
     */
    public function destroy(Ficha $ficha): JsonResponse
    {
        $ficha->delete();

        return response()->json(null, 204);
    }
}
