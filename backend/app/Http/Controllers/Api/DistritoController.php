<?php

namespace App\Http\Controllers;

use App\Models\Distrito;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class DistritoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Distrito::query();

        // Filtro opcional por código o nombre
        if ($request->has('codigo')) {
            $query->where('codigo', $request->codigo);
        }
        if ($request->has('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        $distritos = $query->orderBy('codigo')->paginate($request->get('per_page', 15));

        return response()->json($distritos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'size:2', 'unique:distritos, codigo'],
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $distrito = Distrito::create($validated);

        return response()->json($distrito, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Distrito $distrito): JsonResponse
    {
        return response()->json($distrito->load(['centros', 'socialUsers'])); // Incluye relaciones si aplica
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Distrito $distrito): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['sometimes', 'string', 'size:2', Rule::unique('distritos', 'codigo')->ignore($distrito->id)],
            'nombre' => ['sometimes', 'string', 'max:100'],
        ]);

        $distrito->update($validated);

        return response()->json($distrito);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Distrito $distrito): JsonResponse
    {
        $distrito->delete();

        return response()->json(null, 204);
    }
}
