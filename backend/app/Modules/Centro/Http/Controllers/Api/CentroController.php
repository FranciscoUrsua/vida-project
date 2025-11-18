<?php

namespace App\Modules\Centro\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Centro\Models\Centro;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CentroController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Centro::activos()->with(['tipoCentro', 'director.profesional', 'profesionales'])->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge(
            [
                'tipo' => 'required|string|max:50',
                'nombre' => 'required|string|max:255',
                'tipo_centro_id' => 'required|exists:tipos_centro,id',
                'street_type' => 'required|string|max:50',
                'street_name' => 'required|string|max:100',
                'street_number' => 'required|string|max:20',
                'additional_info' => 'nullable|string|max:100',
                'postal_code' => 'required|string|size:5|regex:/^\d{5}$/',
                'city' => 'nullable|string|max:100',
                'pais' => 'nullable|string|max:50',
                'telefono' => 'required|string|max:20',
                'email_contacto' => 'nullable|email|max:255',
                'director_id' => 'nullable|exists:directores,id',
                'lat' => 'nullable|numeric|between:-90,90',
                'lng' => 'nullable|numeric|between:-180,180',
            ],
            [
                'address_components' => function ($attribute, $value, $fail) {
                    $components = $request->only(['street_type', 'street_name', 'street_number', 'additional_info', 'postal_code', 'city', 'pais']);
                    $addressArray = (new Centro())->buildAddressArrayFromComponents($components);
                    try {
                        (new Centro())->validateAddress($addressArray);
                    } catch (ValidationException $e) {
                        $fail('Dirección inválida: ' . $e->getMessage());
                    }
                },
            ]
        ));

        $centro = Centro::create($validated);
        return response()->json($centro->load(['tipoCentro', 'director.profesional']), 201);
    }

    public function update(Request $request, Centro $centro): JsonResponse
    {
        $validated = $request->validate(array_merge(
            [
                'tipo' => 'sometimes|string|max:50',
                'nombre' => 'sometimes|string|max:255',
                'tipo_centro_id' => 'sometimes|exists:tipos_centro,id',
                'street_type' => 'sometimes|required|string|max:50',
                'street_name' => 'sometimes|required|string|max:100',
                'street_number' => 'sometimes|required|string|max:20',
                'additional_info' => 'sometimes|nullable|string|max:100',
                'postal_code' => 'sometimes|required|string|size:5|regex:/^\d{5}$/',
                'city' => 'sometimes|nullable|string|max:100',
                'pais' => 'sometimes|nullable|string|max:50',
                'telefono' => 'sometimes|string|max:20',
                'email_contacto' => 'sometimes|nullable|email|max:255',
                'director_id' => 'nullable|exists:directores,id',
                'lat' => 'nullable|numeric|between:-90,90',
                'lng' => 'nullable|numeric|between:-180,180',
                // Nueva: Array de IDs de profesionales para sync
                'profesionales_ids' => 'nullable|array',
                'profesionales_ids.*' => 'exists:profesionales,id',
            ],
            [
                'address_components' => function ($attribute, $value, $fail) use ($centro) {
                    $components = array_merge($centro->toArray(), $request->only(['street_type', 'street_name', 'street_number', 'additional_info', 'postal_code', 'city', 'pais']));
                    $addressArray = (new Centro())->buildAddressArrayFromComponents($components);
                    try {
                        (new Centro())->validateAddress($addressArray);
                    } catch (ValidationException $e) {
                        $fail('Dirección inválida: ' . $e->getMessage());
                    }
                },
            ]
        ));

        $centro->update($validated);
        if ($request->has('profesionales_ids')) {
            $centro->profesionales()->sync($request->input('profesionales_ids', []));  // Sync profesionales
        }
        return response()->json($centro->fresh()->load(['tipoCentro', 'director.profesional', 'profesionales']));
    }

    public function show(Centro $centro): JsonResponse
    {
        return response()->json($centro->load([
            'tipoCentro',  // Relación belongsTo con TipoCentro
            'director.profesional',  // Director con su profesional asociado
            'profesionales'  // Relación N:N con Profesionales (incluye pivot)
        ]));
    }

    public function destroy(Centro $centro): JsonResponse
    {
        $centro->delete();
        return response()->json(null, 204);
    }


}
