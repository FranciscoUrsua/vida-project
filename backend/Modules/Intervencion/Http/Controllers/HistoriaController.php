<?php

namespace Modules\Intervencion\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Intervencion\Http\Requests\CreateHistoriaRequest;
use Modules\Intervencion\Http\Requests\UpdateHistoriaRequest; // Similar a Create, crea si needed
use Modules\Intervencion\Models\Historia;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HistoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Historia::query()
            ->with(['socialUser', 'profesional', 'centro']) // Eager load incluyendo profesional
            ->abiertas(); // Scope por defecto: abiertas

        if ($request->distrito) {
            $query->porDistrito(explode(',', $request->distrito));
        }

        if ($request->profesional_id) {
            $query->porProfesional($request->profesional_id); // Nuevo scope para filtrar por pro
        }

        $historias = $query->paginate(15); // Paginación para dashboards

        return response()->json($historias);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateHistoriaRequest $request): JsonResponse
    {
        $historia = Historia::create($request->validated());

        // Lógica simple: disparar evento para notif inicial (futuro, via observer)
        // event(new HistoriaAbierta($historia));

        return response()->json($historia->load(['socialUser', 'profesional']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Historia $historia): JsonResponse
    {
        $historia->load(['socialUser', 'profesional', 'centro', 'valoraciones', 'planes', 'herramientas']); // Carga completa incluyendo profesional

        return response()->json($historia);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHistoriaRequest $request, Historia $historia): JsonResponse
    {
        $historia->update($request->validated());

        return response()->json($historia->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Historia $historia): JsonResponse
    {
        $historia->delete(); // Soft delete si activas en modelo

        return response()->json(null, 204);
    }
}
