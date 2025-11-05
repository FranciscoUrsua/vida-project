<?php

namespace App\Modules\Intervencion\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Intervencion\Http\Requests\CreateValoracionRequest;
use App\Modules\Intervencion\Http\Requests\UpdateValoracionRequest; // Similar, crea si needed
use App\Modules\Intervencion\Models\Valoracion;
use App\Modules\Intervencion\Models\Historia;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ValoracionController extends Controller
{
    /**
     * Display a listing of the resource (e.g., por historia).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Valoracion::query()
            ->with(['historia.socialUser', 'profesional', 'fichas']) // Eager load ciclo parcial
            ->iniciales(); // Por defecto: iniciales

        if ($request->historia_id) {
            $query->porHistoria($request->historia_id);
        }

        $valoraciones = $query->paginate(10);

        return response()->json($valoraciones);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateValoracionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Opcional: auto-set profesional si auth user es el de la historia
        // $validated['profesional_id'] = $request->user()->id; // Si Sanctum

        $valoracion = Valoracion::create($validated);

        // Actualiza estado Historia a 'seguimiento' si inicial (lógica simple)
        $valoracion->historia->update(['estado' => 'seguimiento']);

        return response()->json($valoracion->load(['historia', 'profesional']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Valoracion $valoracion): JsonResponse
    {
        $valoracion->load(['historia.socialUser.profesional', 'profesional', 'fichas']);

        return response()->json($valoracion);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateValoracionRequest $request, Valoracion $valoracion): JsonResponse
    {
        $valoracion->update($request->validated());

        return response()->json($valoracion->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Valoracion $valoracion): JsonResponse
    {
        $valoracion->delete();

        return response()->json(null, 204);
    }
}
