<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ruu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RuuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Logging de acceso (para datos personales)
        Log::info('Acceso a ruu - Listado', [
            'user_id' => auth()->id(),
            'action' => 'read_all',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $ruu = Ruu::with(['centro', 'profesionalReferencia', 'addressType'])
            ->paginate(10);

        return response()->json($ruu);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Logging de intento de creación
        Log::info('Intento de creación ruu', [
            'user_id' => auth()->id(),
            'action' => 'create',
            'ip' => $request->ip(),
        ]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name1' => 'required|string|max:100',
            'last_name2' => 'nullable|string|max:100',
            'dni_nie_pasaporte' => 'required|string|max:20|unique:ruu,dni_nie_pasaporte',  // Required para Ruu
            'situacion_administrativa' => 'nullable|in:activa,inactiva,suspendida',
            'numero_tarjeta_sanitaria' => 'nullable|string|max:20|unique:ruu,numero_tarjeta_sanitaria',
            'pais_origen' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'sexo' => 'nullable|in:M,F,Otro,No especificado',
            'estado_civil' => 'nullable|in:soltero,casado,divorciado,viudo,otro',
            'lugar_empadronamiento' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255|unique:ruu,correo',
            'telefono' => 'nullable|string|max:20',
            'centro_adscripcion_id' => 'nullable|exists:centros,id',
            'profesional_referencia_id' => 'nullable|exists:professionals,id',
            'tiene_representante_legal' => 'nullable|boolean',
            'representante_legal_id' => 'nullable|exists:ruu,id',
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

        // Lógica para identificacion_desconocida: Si DNI null o vacío, set true (aunque required, por si se envía vacío)
        if (empty($validated['dni_nie_pasaporte'])) {
            $validated['identificacion_desconocida'] = true;
        }

        // Calcula requiere_permiso_especial si fecha presente
        if (!empty($validated['fecha_nacimiento'])) {
            $fechaNac = new \DateTime($validated['fecha_nacimiento']);
            $edad = $fechaNac->diff(new \DateTime())->y;
            $validated['requiere_permiso_especial'] = $edad < 18;
        }

        $ruu = Ruu::create($validated);

        Log::info('Ruu creado', [
            'id' => $ruu->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json($ruu, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Ruu $ruu, Request $request)
    {
        // Logging de acceso a fila específica
        Log::info('Acceso a ruu específico', [
            'id' => $ruu->id,
            'user_id' => auth()->id(),
            'action' => 'read_single',
            'ip' => $request->ip(),
        ]);

        if ($ruu->requiere_permiso_especial && !auth()->user()->can('access-special', $ruu)) {
            return response()->json(['error' => 'Permiso especial requerido para este registro'], 403);
        }

        $ruu->load(['centro', 'profesionalReferencia', 'addressType', 'audits']);

        return response()->json($ruu);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ruu $ruu)
    {
        // Logging de intento de actualización
        Log::info('Intento de actualización ruu', [
            'id' => $ruu->id,
            'user_id' => auth()->id(),
            'action' => 'update',
            'ip' => $request->ip(),
        ]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name1' => 'required|string|max:100',
            'last_name2' => 'nullable|string|max:100',
            'dni_nie_pasaporte' => ['required', 'string', 'max:20', Rule::unique('ruu')->ignore($ruu->id)],  // Required para Ruu
            'situacion_administrativa' => 'nullable|in:activa,inactiva,suspendida',
            'numero_tarjeta_sanitaria' => 'nullable|string|max:20|unique:ruu,numero_tarjeta_sanitaria',
            'pais_origen' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'sexo' => 'nullable|in:M,F,Otro,No especificado',
            'estado_civil' => 'nullable|in:soltero,casado,divorciado,viudo,otro',
            'lugar_empadronamiento' => 'nullable|string|max:255',
            'correo' => 'nullable|email|max:255|unique:ruu,correo',
            'telefono' => 'nullable|string|max:20',
            'centro_adscripcion_id' => 'nullable|exists:centros,id',
            'profesional_referencia_id' => 'nullable|exists:professionals,id',
            'tiene_representante_legal' => 'nullable|boolean',
            'representante_legal_id' => 'nullable|exists:ruu,id',
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

        // Recalcula permiso especial
        if (isset($validated['fecha_nacimiento'])) {
            $fechaNac = new \DateTime($validated['fecha_nacimiento']);
            $edad = $fechaNac->diff(new \DateTime())->y;
            $validated['requiere_permiso_especial'] = $edad < 18;
        }

        if ($ruu->requiere_permiso_especial && !auth()->user()->can('update-special', $ruu)) {
            return response()->json(['error' => 'Permiso especial requerido'], 403);
        }

        $ruu->update($validated);

        Log::info('Ruu actualizado', [
            'id' => $ruu->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json($ruu);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ruu $ruu, Request $request)
    {
        Log::info('Intento de eliminación ruu', [
            'id' => $ruu->id,
            'user_id' => auth()->id(),
            'action' => 'delete',
            'ip' => $request->ip(),
        ]);

        if ($ruu->requiere_permiso_especial && !auth()->user()->can('delete-special', $ruu)) {
            return response()->json(['error' => 'Permiso especial requerido'], 403);
        }

        $ruu->delete();

        Log::info('Ruu eliminado', [
            'id' => $ruu->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json(null, 204);
    }
}
