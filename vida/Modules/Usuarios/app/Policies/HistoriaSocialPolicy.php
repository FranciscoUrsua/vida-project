<?php

namespace Modules\Usuarios\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Policies\CiudadanoPolicy;
use App\Services\AmbitoUsuario;
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
 * Personas protegidas (RN-17): nivel 1 solo para quien las gestiona
 * (AmbitoUsuario::gestionaHistoria()); el resto, por consulta con aprobación.
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
     *
     * @param User $usuario Usuario autenticado.
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
     *
     * @param User $usuario Usuario autenticado.
     * @param HistoriaSocial $historia Historia social consultada.
     */
    public function view(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_LEER)) {
            return false;
        }

        // Paso 2: Nivel 1 si gestiona la historia (ámbito, y RN-17 si la persona está protegida)
        if (app(AmbitoUsuario::class)->gestionaHistoria($usuario, $historia)) {
            return true;
        }

        // Paso 3: UO diferente → consulta libre sujeta a colectivo protegido
        return $this->resolverConsultaExterna($usuario, $historia);
    }

    /**
     * Decide si el usuario puede crear una Historia Social.
     *
     * La creación solo está permitida dentro del propio ámbito de UO.
     * El rol supervision no puede crear aunque tenga UO.
     *
     * @param User $usuario Usuario autenticado.
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
     *
     * @param User $usuario Usuario autenticado.
     * @param HistoriaSocial $historia Historia social a editar.
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

        // Paso 2: Solo permite editar si gestiona la historia (ámbito y RN-17)
        if ($historia->unidad_organizativa_id === null) {
            return true;
        }

        return app(AmbitoUsuario::class)->gestionaHistoria($usuario, $historia);
    }

    /**
     * Decide si el usuario puede eliminar (baja lógica) la Historia Social.
     *
     * La eliminación solo está permitida dentro del ámbito de UO del usuario.
     *
     * @param User $usuario Usuario autenticado.
     * @param HistoriaSocial $historia Historia social a eliminar.
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

        // Paso 2: Solo permite eliminar si gestiona la historia (ámbito y RN-17)
        if ($historia->unidad_organizativa_id === null) {
            return true;
        }

        return app(AmbitoUsuario::class)->gestionaHistoria($usuario, $historia);
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Resuelve el acceso en consulta libre (Nivel 2) delegando en CiudadanoPolicy.
     *
     * El criterio de colectivo protegido vive en un solo sitio (CLAUDE.md §3):
     * CiudadanoPolicy::consultaExternaPermitida().
     */
    private function resolverConsultaExterna(User $usuario, HistoriaSocial $historia): bool
    {
        return app(CiudadanoPolicy::class)
            ->consultaExternaPermitida($usuario, $historia->ciudadano_id, (bool) $historia->ciudadano_protegido);
    }
}
