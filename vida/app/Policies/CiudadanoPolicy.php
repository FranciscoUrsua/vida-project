<?php

namespace App\Policies;

use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy de autorización para el modelo Ciudadano.
 *
 * Implementa los tres pasos estándar de seguridad en profundidad:
 *
 *   Paso 1 — Permiso atómico: ¿tiene el usuario el permiso Spatie requerido?
 *   Paso 2 — Ámbito de UO: ¿el ciudadano tiene Historia en la UO del usuario
 *             o en una UO descendiente? (Nivel 1 = gestión, Nivel 2 = consulta)
 *   Paso 3 — Colectivo protegido: solo en Nivel 2, verificar aprobación vigente.
 *
 * La UO se determina vía la Historia Social activa del ciudadano. Si el ciudadano
 * no tiene Historia Social, el acceso corresponde a la UO del usuario activo.
 *
 * Caso especial — supervision: solo lectura, nunca escritura sobre datos de ciudadanos.
 * Caso especial — adm_sistema: también debe pasar los filtros de UO y colectivo protegido.
 *
 * @see docs/modulo-usuarios-permisos.md
 */
class CiudadanoPolicy
{
    use HandlesAuthorization;

    /** Permiso requerido para leer la ficha del ciudadano. */
    private const PERMISO_LEER = 'ciudadano.leer';

    /** Permiso requerido para crear un ciudadano. */
    private const PERMISO_CREAR = 'ciudadano.crear';

    /** Permiso requerido para editar un ciudadano. */
    private const PERMISO_EDITAR = 'ciudadano.editar';

    /** Permiso requerido para eliminar (baja lógica) un ciudadano. */
    private const PERMISO_ELIMINAR = 'ciudadano.eliminar';

    /**
     * Decide si el usuario puede listar ciudadanos.
     *
     * @param User $usuario Usuario autenticado.
     */
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(self::PERMISO_LEER);
    }

    /**
     * Decide si el usuario puede consultar la ficha del ciudadano.
     *
     * Evaluación en tres pasos estándar más verificación de colectivo protegido.
     *
     * @param User $usuario Usuario autenticado.
     * @param Ciudadano $ciudadano Ciudadano objetivo.
     */
    public function view(User $usuario, Ciudadano $ciudadano): bool
    {
        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_LEER)) {
            return false;
        }

        // Paso 2: Verificar ámbito de UO vía Historia Social activa
        $uoIds = $usuario->uoSubtreeIds();
        $historia = $this->obtenerHistoriaPrincipal($ciudadano);

        if ($historia !== null && in_array($historia->unidad_organizativa_id, $uoIds)) {
            // Nivel 1: gestión completa dentro del ámbito de UO
            return true;
        }

        // Nivel 2: consulta libre fuera del ámbito → verificar colectivo protegido
        return $this->resolverConsultaExterna($usuario, $ciudadano);
    }

    /**
     * Decide si el usuario puede crear un ciudadano.
     *
     * El rol supervision no puede crear ciudadanos.
     *
     * @param User $usuario Usuario autenticado.
     */
    public function create(User $usuario): bool
    {
        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        return $usuario->can(self::PERMISO_CREAR);
    }

    /**
     * Decide si el usuario puede editar el ciudadano.
     *
     * La edición solo está permitida dentro del ámbito de UO del usuario.
     * El rol supervision no puede editar.
     *
     * @param User $usuario Usuario autenticado.
     * @param Ciudadano $ciudadano Ciudadano objetivo.
     */
    public function update(User $usuario, Ciudadano $ciudadano): bool
    {
        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_EDITAR)) {
            return false;
        }

        // Paso 2: Solo permite editar si el ciudadano está en el ámbito de UO
        $uoIds = $usuario->uoSubtreeIds();
        $historia = $this->obtenerHistoriaPrincipal($ciudadano);

        if ($historia === null) {
            // Sin Historia Social conocida → permitir si tiene el permiso (alta inicial)
            return true;
        }

        return in_array($historia->unidad_organizativa_id, $uoIds);
    }

    /**
     * Decide si el usuario puede eliminar (baja lógica) el ciudadano.
     *
     * La eliminación solo está permitida dentro del ámbito de UO del usuario.
     *
     * @param User $usuario Usuario autenticado.
     * @param Ciudadano $ciudadano Ciudadano objetivo.
     */
    public function delete(User $usuario, Ciudadano $ciudadano): bool
    {
        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_ELIMINAR)) {
            return false;
        }

        // Paso 2: Solo permite eliminar si el ciudadano está en el ámbito de UO
        $uoIds = $usuario->uoSubtreeIds();
        $historia = $this->obtenerHistoriaPrincipal($ciudadano);

        if ($historia === null) {
            return true;
        }

        return in_array($historia->unidad_organizativa_id, $uoIds);
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Resuelve el acceso en consulta libre (Nivel 2).
     *
     * Si el ciudadano pertenece a un colectivo especialmente protegido,
     * requiere aprobación vigente de acceso (Nivel 3).
     */
    private function resolverConsultaExterna(User $usuario, Ciudadano $ciudadano): bool
    {
        // ¿El ciudadano pertenece a un colectivo especialmente protegido?
        if (! $ciudadano->colectivo_extra_protegido) {
            return true; // Nivel 2: consulta libre
        }

        // Nivel 3: requiere aprobación vigente
        return AccesoProtegido::where('usuario_id', $usuario->id)
            ->where('ciudadano_id', $ciudadano->id)
            ->where('estado', 'aprobado')
            ->where(function ($consulta) {
                $consulta->whereNull('acceso_valido_hasta')
                    ->orWhere('acceso_valido_hasta', '>=', now());
            })
            ->exists();
    }

    /**
     * Obtiene la Historia Social principal del ciudadano para determinar su UO.
     *
     * Devuelve la Historia Social más reciente y activa del ciudadano,
     * que determina a qué UO pertenece funcionalmente.
     */
    private function obtenerHistoriaPrincipal(Ciudadano $ciudadano): ?HistoriaSocial
    {
        return HistoriaSocial::where('ciudadano_id', $ciudadano->id)
            ->whereIn('estado', ['abierta', 'en_seguimiento'])
            ->latest()
            ->first();
    }
}
