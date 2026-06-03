<?php

namespace Modules\Usuarios\Policies;

use App\Models\AccesoProtegido;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy de autorización para la Historia Social.
 *
 * Implementa los tres niveles de acceso definidos en
 * docs/modulo-usuarios-permisos.md sección 1.4:
 *
 *   Nivel 1 — Gestión completa: el profesional pertenece a la UO de la Historia
 *             o a una UO descendiente. Tiene acceso completo según su permiso.
 *   Nivel 2 — Consulta libre: cualquier profesional con permiso historia.leer
 *             puede consultar Historias fuera de su UO (solo lectura).
 *   Nivel 3 — Consulta con aprobación: ciudadanos de colectivos especialmente
 *             protegidos requieren aprobación previa del supervisor competente.
 *
 * La evaluación sigue siempre el mismo orden de tres pasos:
 *   1. ¿Tiene el permiso atómico requerido?
 *   2. ¿Pertenece al ámbito de UO del recurso? (Nivel 1 vs Nivel 2)
 *   3. ¿El ciudadano es colectivo protegido? (solo si Nivel 2)
 *
 * Caso especial — adm_sistema: NO tiene acceso privilegiado sobre datos de
 * ciudadanos; también debe pasar los filtros de UO y colectivo protegido.
 * Caso especial — supervision: solo lectura en su ámbito, nunca escritura.
 *
 * @see docs/modulo-usuarios-permisos.md secciones 1.4 y 4.4
 */
class HistoriaSocialPolicy
{
    use HandlesAuthorization;

    /** Permiso requerido para consultar una Historia Social. */
    private const PERMISO_LEER = 'historia.leer';

    /** Permiso requerido para crear una Historia Social. */
    private const PERMISO_CREAR = 'historia.crear';

    /** Permiso requerido para editar una Historia Social. */
    private const PERMISO_EDITAR = 'historia.editar';

    /** Permiso requerido para eliminar (baja lógica) una Historia Social. */
    private const PERMISO_ELIMINAR = 'historia.eliminar';

    /**
     * Decide si el usuario puede listar Historias Sociales.
     */
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(self::PERMISO_LEER);
    }

    /**
     * Decide si el usuario puede consultar la Historia Social.
     *
     * Evaluación en tres pasos:
     *   1. ¿Tiene el permiso historia.leer?
     *   2. ¿Pertenece al ámbito de la UO? → gestión completa (Nivel 1) o
     *      consulta libre (Nivel 2)
     *   3. Si Nivel 2: ¿el ciudadano es colectivo protegido? → requiere
     *      aprobación vigente (Nivel 3)
     */
    public function view(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_LEER)) {
            return false;
        }

        // Paso 2: ¿La UO del recurso está en el ámbito del usuario?
        if ($historia->unidad_organizativa_id !== null) {
            $uoIds = $usuario->uoSubtreeIds();
            if (in_array($historia->unidad_organizativa_id, $uoIds)) {
                // Nivel 1: gestión completa — permitir lectura
                return true;
            }
        }

        // Paso 3: UO diferente → consulta libre sujeta a colectivo protegido
        return $this->resolverConsultaExterna($usuario, $historia);
    }

    /**
     * Decide si el usuario puede crear una Historia Social.
     *
     * La creación solo está permitida dentro del propio ámbito de UO.
     * El rol supervision no puede crear aunque tenga UO.
     */
    public function create(User $usuario): bool
    {
        // supervision: solo lectura sobre datos de ciudadanos
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        return $usuario->can(self::PERMISO_CREAR);
    }

    /**
     * Decide si el usuario puede editar la Historia Social.
     *
     * La edición solo está permitida dentro del ámbito de UO del usuario.
     * El rol supervision no puede editar.
     */
    public function update(User $usuario, HistoriaSocial $historia): bool
    {
        // supervision: solo lectura sobre datos de ciudadanos
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_EDITAR)) {
            return false;
        }

        // Paso 2: Solo permite editar si la Historia está en el ámbito de UO del usuario
        if ($historia->unidad_organizativa_id === null) {
            return true;
        }

        $uoIds = $usuario->uoSubtreeIds();

        return in_array($historia->unidad_organizativa_id, $uoIds);
    }

    /**
     * Decide si el usuario puede eliminar (baja lógica) la Historia Social.
     *
     * La eliminación solo está permitida dentro del ámbito de UO del usuario.
     */
    public function delete(User $usuario, HistoriaSocial $historia): bool
    {
        // supervision: solo lectura sobre datos de ciudadanos
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_ELIMINAR)) {
            return false;
        }

        // Paso 2: Solo permite eliminar si la Historia está en el ámbito de UO del usuario
        if ($historia->unidad_organizativa_id === null) {
            return true;
        }

        $uoIds = $usuario->uoSubtreeIds();

        return in_array($historia->unidad_organizativa_id, $uoIds);
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Resuelve el acceso cuando el usuario es externo al ámbito de UO de la Historia.
     *
     * Si el ciudadano no es especialmente protegido → consulta libre (Nivel 2).
     * Si el ciudadano es especialmente protegido → requiere aprobación (Nivel 3).
     */
    private function resolverConsultaExterna(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 3: ¿El ciudadano está marcado como especialmente protegido?
        if (! $historia->ciudadano_protegido) {
            return true; // Nivel 2: consulta libre
        }

        // Nivel 3: Ciudadano protegido → requiere aprobación vigente
        return $this->tieneAprobacionVigente($usuario, $historia);
    }

    /**
     * Comprueba si existe una aprobación de acceso vigente para el usuario y el ciudadano.
     */
    private function tieneAprobacionVigente(User $usuario, HistoriaSocial $historia): bool
    {
        return AccesoProtegido::where('usuario_id', $usuario->id)
            ->where('ciudadano_id', $historia->ciudadano_id)
            ->where('estado', 'aprobado')
            ->where(function ($consulta) {
                $consulta->whereNull('acceso_valido_hasta')
                    ->orWhere('acceso_valido_hasta', '>=', now());
            })
            ->exists();
    }
}
