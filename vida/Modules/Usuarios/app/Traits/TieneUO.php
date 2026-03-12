<?php

namespace Modules\Usuarios\Traits;

use App\Models\UnidadOrganizativa;
use App\Models\UsuarioUo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait que añade al User la lógica de adscripción a Unidades Organizativas.
 *
 * Una UO determina el ÁMBITO de datos sobre el que el usuario puede
 * ejercer sus permisos: gestión completa en su UO, consulta libre fuera.
 *
 * @see docs/modulo-usuarios-permisos.md sección 1.1
 */
trait TieneUO
{
    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Todas las adscripciones a UO del usuario (históricas y vigentes).
     *
     * @return HasMany<UsuarioUo>
     */
    public function adscripciones(): HasMany
    {
        return $this->hasMany(UsuarioUo::class, 'usuario_id');
    }

    /**
     * Únicamente las adscripciones vigentes (fecha_fin null o futura).
     *
     * @return HasMany<UsuarioUo>
     */
    public function adscripcionesVigentes(): HasMany
    {
        return $this->adscripciones()->vigentes();
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Devuelve la colección de Unidades Organizativas activas
     * a las que el usuario está actualmente adscrito.
     *
     * @return Collection<int, UnidadOrganizativa>
     */
    public function uosActivas(): Collection
    {
        return UnidadOrganizativa::whereIn(
            'id',
            $this->adscripcionesVigentes()->pluck('unidad_organizativa_id')
        )->activas()->get();
    }

    /**
     * Indica si el usuario pertenece exactamente a la UO indicada
     * (sin considerar la jerarquía).
     *
     * Se usa en las Policies para distinguir gestión completa (misma UO)
     * de consulta libre (UO diferente).
     *
     * @param UnidadOrganizativa $uo UO a comprobar
     * @return bool
     */
    public function perteneceAUo(UnidadOrganizativa $uo): bool
    {
        return $this->adscripcionesVigentes()
            ->where('unidad_organizativa_id', $uo->id)
            ->exists();
    }

    /**
     * Indica si el usuario tiene acceso de gestión sobre la UO indicada
     * (su propia UO o una UO descendiente que gestiona).
     *
     * Usado para verificar si el usuario puede gestionar recursos
     * en una UO inferior a la suya en la jerarquía.
     *
     * @param UnidadOrganizativa $uo UO sobre la que se quiere operar
     * @return bool
     */
    public function tieneAccesoGestionA(UnidadOrganizativa $uo): bool
    {
        $idsUoUsuario = $this->adscripcionesVigentes()
            ->pluck('unidad_organizativa_id')
            ->toArray();

        // Acceso directo: la UO es una de las del usuario
        if (in_array($uo->id, $idsUoUsuario)) {
            return true;
        }

        // Acceso jerárquico: la UO es descendiente de alguna UO del usuario
        foreach ($idsUoUsuario as $idUoUsuario) {
            /** @var UnidadOrganizativa $uoUsuario */
            $uoUsuario = UnidadOrganizativa::find($idUoUsuario);
            if ($uoUsuario && $uo->isDescendantOf($uoUsuario)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si el usuario puede acceder en consulta libre a la UO indicada.
     *
     * Cualquier usuario autenticado puede consultar en otras UO
     * (sujeto a las restricciones de colectivos protegidos).
     * Este método siempre devuelve true; la restricción real
     * la impone la Policy según el ciudadano y el colectivo.
     *
     * @param UnidadOrganizativa $uo UO sobre la que se quiere consultar
     * @return bool
     */
    public function tieneAccesoConsultaA(UnidadOrganizativa $uo): bool
    {
        // La consulta libre (Nivel 2) está disponible para cualquier profesional
        // con el permiso correspondiente, independientemente de la UO.
        // La restricción de colectivos protegidos (Nivel 3) la evalúa la Policy.
        return true;
    }
}
