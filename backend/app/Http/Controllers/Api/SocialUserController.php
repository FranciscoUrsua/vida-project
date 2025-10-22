<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Services\PadronService;  // Importa el servicio

class SocialUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Logging de acceso (para datos personales)
        Log::info('Acceso a social_users - Listado', [
            'user_id' => auth()->id(),
            'action' => 'read_all',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $socialUsers = SocialUser::with(['centro', 'profesionalReferencia', 'addressType'])
            ->when($request->has('ruu_only'), function ($query) {
                $query->ruu();  // Scope para subconjunto RUU
            })
            ->paginate(10);

        return response()->json($socialUsers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Logging de intento de creación
        Log::info('Intento de creación social_user', [
            'user_id' => auth()->id(),
            'action' => 'create',
            'ip' => $request->ip(),
        ]);

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name1' => 'nullable|string|max:100',
            'last_name2' => 'nullable|string|max:100',
            'dni_nie_pasaporte' => 'nullable|string|max:20|unique:social_users,dni_nie_pasaporte',  // DNI/NIE/pasaporte
            'situacion_administrativa' => 'nullable|in:activa,inactiva,suspendida',
            'numero_tarjeta_sanitaria' => 'nullable|string|max:20|unique:social_users,numero_tarjeta_sanitaria',
            'pais_origen' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'sexo' => 'nullable|in:M,F,Otro,No especificado',
            'estado_civil' => 'nullable|in:soltero,casado,divorciado,viudo,otro',
            'lugar_empadronamiento' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255|unique:social_users,correo',
            'telefono' => 'nullable|string|max:20',
            'centro_adscripcion_id' => 'nullable|exists:centros,id',
            'profesional_referencia_id' => 'nullable|exists:professionals,id',
            'tiene_representante_legal' => 'nullable|boolean',
            'representante_legal_id' => 'nullable|exists:social_users,id',
            'identificacion_desconocida' => 'nullable|boolean',
            'address_type_id' => 'nullable|exists:address_types,id',
            'street_name' => 'nullable|string|max:255',
            'street_number' => 'nullable|string|max:10',
            'additional_info' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100|default:Spain',
        ]);

        // Lógica para identificacion_desconocida: Si DNI null o vacío, set true
        if (empty($validated['dni_nie_pasaporte'])) {
            $validated['identificacion_desconocida'] = true;
        }

        // Búsqueda en padrón (flexible por ID o nombre + fecha)
        $padronService = new PadronService();
        $padronResult = $padronService->searchResidency($validated);

        if (!$padronResult['valid']) {
            return response()->json([
                'error' => 'No empadronado en Madrid o error en validación',
                'details' => $padronResult['error'] ?? 'No coincidencias',
                'matches' => $padronResult['matches'],  // Para front seleccionar si ambigüedad
            ], 422);
        }

        // Maneja matches (toma el primero si único, o error para ambigüedad)
        if (count($padronResult['matches']) > 1) {
            return response()->json([
                'error' => 'Múltiples coincidencias en padrón. Selecciona en la interfaz.',
                'matches' => $padronResult['matches'],  // Incluye historial_id
            ], 409);  // Conflict for ambiguity
        }

        $match = $padronResult['matches'][0] ?? [];
        $validated = array_merge($validated, [
            'postal_code' => $match['direccion']['postal_code'] ?? null,
            'city' => $match['direccion']['city'] ?? 'Madrid',
            'identificacion_historial' => $match['historial_id'] ?? [$validated['dni_nie_pasaporte'] ?? null],  // Versionado
            // Otros campos del match, ej: direccion completa
        ]);

        // Calcula requiere_permiso_especial si fecha presente
        if (!empty($validated['fecha_nacimiento'])) {
            $fechaNac = new \DateTime($validated['fecha_nacimiento']);
            $edad = $fechaNac->diff(new \DateTime())->y;
            $validated['requiere_permiso_especial'] = $edad < 18;
        }

        $socialUser = SocialUser::create($validated);

        Log::info('SocialUser creado', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json($socialUser, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SocialUser $socialUser, Request $request)
    {
        // Logging de acceso a fila específica
        Log::info('Acceso a social_user específico', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
            'action' => 'read_single',
            'ip' => $request->ip(),
        ]);

        if ($socialUser->requiere_permiso_especial && !auth()->user()->can('access-special', $socialUser)) {
            return response()->json(['error' => 'Permiso especial requerido para este registro'], 403);
        }

        $socialUser->load(['centro', 'profesionalReferencia', 'addressType', 'audits']);

        return response()->json($socialUser);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SocialUser $socialUser)
    {
        // Logging de intento de actualización
        Log::info('Intento de actualización social_user', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
            'action' => 'update',
            'ip' => $request->ip(),
        ]);

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name1' => 'nullable|string|max:100',
            'last_name2' => 'nullable|string|max:100',
            'dni_nie_pasaporte' => ['nullable', 'string', 'max:20', Rule::unique('social_users')->ignore($socialUser->id)],
            'situacion_administrativa' => 'nullable|in:activa,inactiva,suspendida',
            'numero_tarjeta_sanitaria' => ['nullable', 'string', 'max:20', Rule::unique('social_users')->ignore($socialUser->id)],
            'pais_origen' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'sexo' => 'nullable|in:M,F,Otro,No especificado',
            'estado_civil' => 'nullable|in:soltero,casado,divorciado,viudo,otro',
            'lugar_empadronamiento' => 'nullable|string|max:255',
            'correo' => ['nullable', 'email', 'max:255', Rule::unique('social_users')->ignore($socialUser->id)],
            'telefono' => 'nullable|string|max:20',
            'centro_adscripcion_id' => 'nullable|exists:centros,id',
            'profesional_referencia_id' => 'nullable|exists:professionals,id',
            'tiene_representante_legal' => 'nullable|boolean',
            'representante_legal_id' => 'nullable|exists:social_users,id',
            'identificacion_desconocida' => 'nullable|boolean',
            'address_type_id' => 'nullable|exists:address_types,id',
            'street_name' => 'nullable|string|max:255',
            'street_number' => 'nullable|string|max:10',
            'additional_info' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100|default:Spain',
        ]);

        // Lógica para identificacion_desconocida
        if (empty($validated['dni_nie_pasaporte'])) {
            $validated['identificacion_desconocida'] = true;
        }

        // Búsqueda en padrón si ID cambia o presente
        if (!empty($validated['dni_nie_pasaporte']) || $request->has('dni_nie_pasaporte')) {
            $padronService = new PadronService();
            $padronResult = $padronService->searchResidency($validated);

            if (!$padronResult['valid']) {
                return response()->json([
                    'error' => 'No empadronado en Madrid o error en validación',
                    'details' => $padronResult['error'] ?? 'No coincidencias',
                    'matches' => $padronResult['matches'],
                ], 422);
            }

            // Maneja matches
            if (count($padronResult['matches']) > 1) {
                return response()->json([
                    'error' => 'Múltiples coincidencias. Selecciona en la interfaz.',
                    'matches' => $padronResult['matches'],
                ], 409);
            }

            $match = $padronResult['matches'][0] ?? [];
            $validated = array_merge($validated, [
                'postal_code' => $match['direccion']['postal_code'] ?? null,
                'city' => $match['direccion']['city'] ?? 'Madrid',
                'identificacion_historial' => array_merge(
                    $socialUser->identificacion_historial ?? [],
                    $match['historial_id'] ?? [$validated['dni_nie_pasaporte']]
                ),
            ]);
        }

        // Recalcula permiso especial
        if (isset($validated['fecha_nacimiento'])) {
            $fechaNac = new \DateTime($validated['fecha_nacimiento']);
            $edad = $fechaNac->diff(new \DateTime())->y;
            $validated['requiere_permiso_especial'] = $edad < 18;
        }

        if ($socialUser->requiere_permiso_especial && !auth()->user()->can('update-special', $socialUser)) {
            return response()->json(['error' => 'Permiso especial requerido'], 403);
        }

        $socialUser->update($validated);

        Log::info('SocialUser actualizado', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json($socialUser);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SocialUser $socialUser, Request $request)
    {
        Log::info('Intento de eliminación social_user', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
            'action' => 'delete',
            'ip' => $request->ip(),
        ]);

        if ($socialUser->requiere_permiso_especial && !auth()->user()->can('delete-special', $socialUser)) {
            return response()->json(['error' => 'Permiso especial requerido'], 403);
        }

        $socialUser->delete();

        Log::info('SocialUser eliminado', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json(null, 204);
    }
}
