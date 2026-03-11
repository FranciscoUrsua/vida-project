<?php

namespace App\Policies;

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
 * @see docs/principios-vida360.md principios 3.4 y 3.6
 */
class HistoriaSocialPolicy
{
    use HandlesAuthorization;

    /**
     * Permiso requerido para consultar una Historia Social.
     */
    private const PERMISO_LEER = 'historia.leer';

    /**
     * Permiso requerido para crear una Historia Social.
     */
    private const PERMISO_ABRIR = 'historia.abrir';

    /**
     * Permiso requerido para editar una Historia Social.
     */
    private const PERMISO_EDITAR = 'historia.editar';

    // -------------------------------------------------------------------------
    // Métodos de autorización
    // -------------------------------------------------------------------------

    /**
     * Decide si el usuario puede consultar la Historia Social.
     *
     * Evaluación en cuatro pasos (§ 4.4):
     *   1. ¿Tiene el permiso historia.leer?
     *   2. ¿Pertenece a la UO de la Historia? → gestión completa (Nivel 1)
     *   3. ¿El ciudadano es especialmente protegido? → si no, consulta libre (Nivel 2)
     *   4. ¿Existe aprobación de acceso vigente? → Nivel 3
     *
     * @param User $usuario Usuario autenticado
     * @param HistoriaSocial $historia Historia a la que se solicita acceso
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
     * La apertura solo está permitida dentro de la propia UO:
     * no tiene sentido abrir una Historia en una UO ajena.
     *
     * @param User $usuario Usuario autenticado
     * @param HistoriaSocial $historia Historia que se quiere crear
     * @return bool
     */
    public function create(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 1: Permiso atómico
        if (! $usuario->can(self::PERMISO_ABRIR)) {
            return false;
        }

        // Paso 2: Solo en la propia UO
        if ($historia->unidad_organizativa_id !== null &&
            ! $usuario->perteneceAUo($historia->unidadOrganizativa)) {
            return false;
        }

        return true;
    }

    /**
     * Decide si el usuario puede editar la Historia Social.
     *
     * La edición solo está permitida dentro de la propia UO;
     * fuera de ella solo existe consulta.
     *
     * @param User $usuario Usuario autenticado
     * @param HistoriaSocial $historia Historia a editar
     * @return bool
     */
    public function update(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 1: Permiso atómico
        if (! $usuario->can(self::PERMISO_EDITAR)) {
            return false;
        }

        // Paso 2: Solo en la propia UO
        if ($historia->unidad_organizativa_id !== null &&
            ! $usuario->perteneceAUo($historia->unidadOrganizativa)) {
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Métodos auxiliares privados
    // -------------------------------------------------------------------------

    /**
     * Resuelve el acceso cuando el usuario es externo a la UO de la Historia
     * (Niveles 2 y 3 del modelo de acceso).
     *
     * Paso 3: Si el ciudadano no es de colectivo protegido → acceso libre (Nivel 2).
     * Paso 4: Si es protegido → comprueba si existe aprobación vigente (Nivel 3).
     *
     * @param User $usuario Usuario solicitante
     * @param HistoriaSocial $historia Historia a la que se solicita acceso
     * @return bool
     */
    private function resolverConsultaExterna(User $usuario, HistoriaSocial $historia): bool
    {
        // Paso 3: ¿El ciudadano está marcado como especialmente protegido?
        if (! $historia->ciudadano_protegido) {
            // Nivel 2: consulta libre para cualquier profesional con el permiso
            return true;
        }

        // Paso 4: Ciudadano protegido → requiere aprobación de acceso vigente
        return $this->tieneAprobacionVigente($usuario, $historia);
    }

    /**
     * Comprueba si existe en la tabla `accesos_protegidos` una aprobación
     * vigente para este usuario y este ciudadano.
     *
     * Un acceso es vigente si:
     *   - su estado es 'aprobado', Y
     *   - acceso_valido_hasta es null (sin límite) o todavía no ha expirado.
     *
     * @param User $usuario Usuario solicitante
     * @param HistoriaSocial $historia Historia del ciudadano protegido
     * @return bool
     *
     * @todo Cuando se implemente el modelo Ciudadano, reemplazar
     *       $historia->ciudadano_id por la FK correspondiente.
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
