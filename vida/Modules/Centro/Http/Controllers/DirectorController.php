<?php
// Modules/Centro/Http/Controllers/DirectorController.php
// Controlador Resource para CRUD de Directores (asignaciones)

namespace Modules\Centro\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Centro\Models\Director;
use Modules\Centro\Http\Requests\StoreDirectorRequest;
use Modules\Centro\Http\Requests\UpdateDirectorRequest;
use Modules\Centro\Http\Resources\DirectorResource;

class DirectorController extends Controller
{
    /**
     * Muestra lista de directores (paginado, filtros por centro/fecha).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Director::query();
        if ($request->has('centro_id')) {
            $query->where('centro_id', $request->input('centro_id'));
        }
        if ($request->has('fecha')) {
            $query->enFecha($request->input('fecha'));
        }
        $directores = $query->with(['profesional', 'centro'])->paginate($request->get('per_page', 15));

        return response()->json(DirectorResource::collection($directores));
    }

    /**
     * Muestra una asignación de director específica.
     */
    public function show(Director $director): JsonResponse
    {
        $director->load(['profesional', 'centro']);

        return response()->json(new DirectorResource($director));
    }

    /**
     * Crea una nueva asignación de director (verifica unique).
     */
    public function store(StoreDirectorRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Verifica no solapamiento con existente en centro
        $existente = Director::where('centro_id', $data['centro_id'])
            ->whereNull('fecha_baja')
            ->first();
        if ($existente) {
            return response()->json(['error' => 'Ya hay un director activo en este centro.'], 422);
        }

        $director = Director::create($data);
        // Sync centro->director_id si es actual
        $director->centro->update(['director_id' => $director->id]);

        return response()->json(new DirectorResource($director), 201);
    }

    /**
     * Actualiza una asignación (e.g., fecha_baja para cierre).
     */
    public function update(UpdateDirectorRequest $request, Director $director): JsonResponse
    {
        $director->update($request->validated());

        return response()->json(new DirectorResource($director));
    }

    /**
     * Elimina una asignación (soft delete).
     */
    public function destroy(Director $director): JsonResponse
    {
        // Si era actual, set null en centro
        if ($director->es_actual) {
            $director->centro->update(['director_id' => null]);
        }
        $director->delete();

        return response()->json(null, 204);
    }
}
