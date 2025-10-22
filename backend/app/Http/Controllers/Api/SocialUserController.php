<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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

        $socialUsers = SocialUser::with(['centro', 'profesionalReferencia'])
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
            'dni_nie_pasaporte' => 'nullable|string|max:20|unique:social_users,dni_nie_pasaporte',  // Nullable, unique solo si presente
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
            'identificacion_desconocida' => 'nullable|boolean',  // Opcional, se setea auto si DNI null
        ]);

        // Lógica para identificacion_desconocida: Si DNI null o vacío, set true
        if (empty($validated['dni_nie_pasaporte'])) {
            $validated['identificacion_desconocida'] = true;
        }

    // Calcula requiere_permiso_especial si fecha presente
        if (!empty($validated['fecha_nacimiento'])) {
            $fechaNac = new \DateTime($validated['fecha_nacimiento']);
            $edad = $fechaNac->diff(new \DateTime())->y;
            $validated['requiere_permiso_especial'] = $edad < 18;
        }

        $socialUser = SocialUser::create($validated);

        // Logging exitoso
        Log::info('SocialUser creado exitosamente', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
            'action' => 'create_success',
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

        // Chequeo de permiso especial por fila
        if ($socialUser->requiere_permiso_especial && !auth()->user()->can('access-special', $socialUser)) {
            return response()->json(['error' => 'Permiso especial requerido para este registro'], 403);
        }

        $socialUser->load(['centro', 'profesionalReferencia', 'audits']);  // Incluye historial de auditing

        return response()->json($socialUser);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SocialUser $socialUser)
    {
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
        ]);

        // Lógica para identificacion_desconocida en update: Si DNI se hace null o vacío, set true
        if (empty($validated['dni_nie_pasaporte'])) {
            $validated['identificacion_desconocida'] = true;
        }

        // Recalcula permiso especial si fecha cambia
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
        // Logging de intento de eliminación
        Log::info('Intento de eliminación social_user', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
            'action' => 'delete',
            'ip' => $request->ip(),
        ]);

        // Chequeo de permiso especial
        if ($socialUser->requiere_permiso_especial && !auth()->user()->can('delete-special', $socialUser)) {
            return response()->json(['error' => 'Permiso especial requerido para eliminar este registro'], 403);
        }

        $socialUser->delete();  // Soft delete si agregas SoftDeletes trait

        Log::info('SocialUser eliminado exitosamente', [
            'id' => $socialUser->id,
            'user_id' => auth()->id(),
            'action' => 'delete_success',
        ]);

        return response()->json(null, 204);
    }
}
