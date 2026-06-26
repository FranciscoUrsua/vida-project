<?php

namespace Modules\Usuarios\Traits;

use App\Models\UnidadOrganizativa;
use App\Models\UsuarioUo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Trait que añade al User la lógica de adscripción a Unidades Organizativas.
 */
trait TieneUO
{
    /**
     * @return HasMany<UsuarioUo, $this>
     */
    public function adscripciones(): HasMany
    {
        return $this->hasMany(UsuarioUo::class, 'usuario_id');
    }

    /**
     * @return HasMany<UsuarioUo, $this>
     */
    public function adscripcionesVigentes(): HasMany
    {
        return $this->adscripciones()->vigentes();
    }

    /**
     * @return BelongsToMany<UnidadOrganizativa, $this, Pivot, 'pivot'>
     */
    public function unidadesOrganizativas(): BelongsToMany
    {
        return $this->belongsToMany(
            UnidadOrganizativa::class,
            'usuario_uo',
            'usuario_id',
            'unidad_organizativa_id'
        );
    }

    /**
     * @return Collection<int, UnidadOrganizativa>
     */
    public function uosActivas(): Collection
    {
        return UnidadOrganizativa::whereIn(
            'id',
            $this->adscripcionesVigentes()->pluck('unidad_organizativa_id')
        )->activas()->get();
    }

    public function perteneceAUo(UnidadOrganizativa $uo): bool
    {
        return $this->adscripcionesVigentes()
            ->where('unidad_organizativa_id', $uo->id)
            ->exists();
    }

    public function tieneAccesoGestionA(UnidadOrganizativa $uo): bool
    {
        $idsUoUsuario = $this->adscripcionesVigentes()
            ->pluck('unidad_organizativa_id')
            ->toArray();

        if (in_array($uo->id, $idsUoUsuario, true)) {
            return true;
        }

        foreach ($idsUoUsuario as $idUoUsuario) {
            /** @var UnidadOrganizativa|null $uoUsuario */
            $uoUsuario = UnidadOrganizativa::find($idUoUsuario);
            if ($uoUsuario && $uo->isDescendantOf($uoUsuario)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int>
     */
    public function uoSubtreeIds(): array
    {
        return $this->uosActivas()
            ->flatMap(fn (UnidadOrganizativa $uo) => $uo->descendantsAndSelf()->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    public function tieneAccesoConsultaA(UnidadOrganizativa $uo): bool
    {
        return true;
    }
}
