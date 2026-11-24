<?php

namespace App\Support\Ambito;

use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Centro\Models\Centro;
use Modules\Usuarios\Models\Profesional;

/**
 * Ámbito de la gestión de usuarios en el backoffice: qué usuarios, profesionales y
 * centros puede gestionar quien tiene `adm_usuarios`.
 *
 * `adm_usuarios` gestiona los usuarios y las agendas de su UO y de las que dependen
 * de ella (docs/modulo-usuarios-permisos.md §2, rol 3): sus UO son las de
 * `User::uoSubtreeIds()`, las mismas que ya limitaban la edición de usuarios.
 * `adm_sistema` no tiene límite. También lo usan las plantillas y estilos de informe,
 * que la supervisión edita en su ámbito (`uoEnAmbito()`).
 */
final class AmbitoGestionUsuarios
{
    /**
     * Si el gestor no tiene límite de ámbito.
     *
     * @param User $gestor
     *
     * @return bool
     */
    public function esGlobal(User $gestor): bool
    {
        return $gestor->hasRole('adm_sistema');
    }

    /**
     * Si el usuario indicado está adscrito (adscripción vigente) a una UO del ámbito del gestor.
     *
     * @param User $gestor
     * @param int|null $usuarioId
     *
     * @return bool
     */
    public function usuarioEnAmbito(User $gestor, ?int $usuarioId): bool
    {
        if ($this->esGlobal($gestor)) {
            return true;
        }

        return $usuarioId !== null
            && $this->adscripcionesDelAmbito($gestor)->where('usuario_id', $usuarioId)->exists();
    }

    /**
     * Si el profesional es del ámbito del gestor: por la adscripción de su usuario o,
     * si aún no tiene cuenta, por su UO de adscripción directa.
     *
     * @param User $gestor
     * @param Profesional $profesional
     *
     * @return bool
     */
    public function profesionalEnAmbito(User $gestor, Profesional $profesional): bool
    {
        if ($this->esGlobal($gestor)) {
            return true;
        }

        $usuarioId = User::withTrashed()->where('profesional_id', $profesional->id)->value('id');
        if ($usuarioId !== null) {
            return $this->usuarioEnAmbito($gestor, $usuarioId);
        }

        return $profesional->unidad_organizativa_id !== null
            && in_array($profesional->unidad_organizativa_id, $gestor->uoSubtreeIds(), true);
    }

    /**
     * Si la UO está en el ámbito del usuario (su UO y las que dependen de ella).
     *
     * @param User $gestor
     * @param int|null $uoId
     *
     * @return bool
     */
    public function uoEnAmbito(User $gestor, ?int $uoId): bool
    {
        if ($this->esGlobal($gestor)) {
            return true;
        }

        return $uoId !== null && in_array($uoId, $gestor->uoSubtreeIds(), true);
    }

    /**
     * Si el centro pertenece a una UO del ámbito del gestor.
     *
     * @param User $gestor
     * @param int|null $centroId
     *
     * @return bool
     */
    public function centroEnAmbito(User $gestor, ?int $centroId): bool
    {
        if ($this->esGlobal($gestor)) {
            return true;
        }

        return $centroId !== null && $this->centrosDelAmbito($gestor)->whereKey($centroId)->exists();
    }

    /**
     * Limita una consulta a los registros de centros del ámbito del gestor.
     *
     * @template TModelo of \Illuminate\Database\Eloquent\Model
     *
     * @param Builder<TModelo> $query
     * @param User $gestor
     * @param string $columnaCentro Columna con el id del centro.
     *
     * @return Builder<TModelo>
     */
    public function limitarPorCentro(Builder $query, User $gestor, string $columnaCentro = 'centro_id'): Builder
    {
        if ($this->esGlobal($gestor)) {
            return $query;
        }

        return $query->whereIn($columnaCentro, $this->centrosDelAmbito($gestor)->select('id'));
    }

    /**
     * Centros activos del ámbito, para los desplegables (id → nombre).
     *
     * @param User $gestor
     *
     * @return Collection<int, string>
     */
    public function opcionesCentros(User $gestor): Collection
    {
        $query = Centro::where('activo', true)->orderBy('nombre');

        if (! $this->esGlobal($gestor)) {
            $query->whereIn('unidad_organizativa_id', $gestor->uoSubtreeIds());
        }

        return $query->pluck('nombre', 'id');
    }

    /**
     * Usuarios del ámbito, para los desplegables (id → nombre).
     *
     * @param User $gestor
     *
     * @return Collection<int, string>
     */
    public function opcionesUsuarios(User $gestor): Collection
    {
        $query = User::orderBy('name');

        if (! $this->esGlobal($gestor)) {
            $query->whereIn('id', $this->adscripcionesDelAmbito($gestor)->select('usuario_id'));
        }

        return $query->pluck('name', 'id');
    }

    /**
     * Adscripciones vigentes a UO del ámbito del gestor.
     *
     * @param User $gestor
     *
     * @return Builder<UsuarioUo>
     */
    private function adscripcionesDelAmbito(User $gestor): Builder
    {
        return UsuarioUo::query()->vigentes()->whereIn('unidad_organizativa_id', $gestor->uoSubtreeIds());
    }

    /**
     * Centros (de cualquier estado) cuyas UO están en el ámbito del gestor.
     *
     * @param User $gestor
     *
     * @return Builder<Centro>
     */
    private function centrosDelAmbito(User $gestor): Builder
    {
        return Centro::query()->whereIn('unidad_organizativa_id', $gestor->uoSubtreeIds());
    }
}
