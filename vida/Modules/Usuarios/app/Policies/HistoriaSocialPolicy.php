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
 *   Nivel 1 — Gestión completa: el profesional pertenece a la UO de la Historia.
 *   Nivel 2 — Consulta libre: cualquier profesional con permiso historia.leer
 *             puede consultar Historias fuera de su UO (auditoría activa).
 *   Nivel 3 — Consulta con aprobación: ciudadanos de colectivos especialmente
 *             protegidos requieren aprobación previa del supervisor competente.
 *
 * La evaluación sigue siempre el mismo orden de cuatro pasos
 * (docs/modulo-usuarios-permisos.md § 4.4).
 *
 * @see docs/modulo-usuarios-permisos.md secciones 1.4 y 4.4
 */
class HistoriaSocialPolicy
{
    use HandlesAuthorization;

    /** Permiso requerido para consultar una Historia Social. */
    private const PERMISO_LEER = 'historia.leer';

    /** Permiso requerido para crear una Historia Social. */
    private const PERMISO_ABRIR = 'historia.abrir';

    /** Permiso requerido para editar una Historia Social. */
    private const PERMISO_EDITAR = 'historia.editar';

    /**
     * Decide si el usuario puede consultar la Historia Social.
     *
     * Evaluación en cuatro pasos (§ 4.4):
     *   1. ¿Tiene el permiso historia.leer?
     *   2. ¿Pertenece a la UO de la Historia? → gestión completa (Nivel 1)
     *   3. ¿El ciudadano es especialmente protegido? → si no, consulta libre (Nivel 2)
     *   4. ¿Existe aprobación de acceso vigente? → Nivel 3
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     * @return bool
     */
    public function view(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_LEER)) {
            return false;
        }

        // Paso 2: Misma UO → gestión completa (Nivel 1)
        if ($historia->unidad_organizativa_id !== null &&
            $usuario->perteneceAUo($historia->unidadOrganizativa)) {
            return true;
        }

        // Pasos 3 y 4: UO diferente → consulta libre o con aprobación
        return $this->resolverConsultaExterna($usuario, $historia);
    }

    /**
     * Decide si el usuario puede abrir (crear) una Historia Social.
     *
     * La apertura solo está permitida dentro de la propia UO.
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     * @return bool
     */
    public function create(User $usuario, HistoriaSocial $historia): bool
    {
        if (! $usuario->can(self::PERMISO_ABRIR)) {
            return false;
        }

        if ($historia->unidad_organizativa_id !== null &&
            ! $usuario->perteneceAUo($historia->unidadOrganizativa)) {
            return false;
        }

        return true;
    }

    /**
     * Decide si el usuario puede editar la Historia Social.
     *
     * La edición solo está permitida dentro de la propia UO.
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     * @return bool
     */
    public function update(User $usuario, HistoriaSocial $historia): bool
    {
        if (! $usuario->can(self::PERMISO_EDITAR)) {
            return false;
        }

        if ($historia->unidad_organizativa_id !== null &&
            ! $usuario->perteneceAUo($historia->unidadOrganizativa)) {
            return false;
        }

        return true;
    }

    /**
     * Resuelve el acceso cuando el usuario es externo a la UO de la Historia.
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     * @return bool
     */
    private function resolverConsultaExterna(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 3: ¿El ciudadano está marcado como especialmente protegido?
        if (! $historia->ciudadano_protegido) {
            return true; // Nivel 2: consulta libre
        }

        // Paso 4: Ciudadano protegido → requiere aprobación vigente
        return $this->tieneAprobacionVigente($usuario, $historia);
    }

    /**
     * Comprueba si existe una aprobación de acceso vigente.
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     * @return bool
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
