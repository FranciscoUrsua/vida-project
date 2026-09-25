<?php

namespace Modules\Usuarios\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de cargos profesionales.
 *
 * Configurable desde el backoffice por adm_sistema.
 * Ejemplos: Trabajador/a Social, Psicólogo/a, Educador/a Social.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $slug Identificador estable único, ej: 'ts'
 * @property string|null $descripcion
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CargoRolSugerido> $rolesSugeridos
 *
 * @see docs/modulo-usuarios-permisos.md secciones 2.9 y 5.2
 */
class Cargo extends Model
{
    /** @var string */
    protected $table = 'cargos';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activo' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Profesionales con este cargo.
     *
     * @return HasMany<Profesional>
     */
    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class, 'cargo_id');
    }

    /**
     * Roles que se proponen al dar de alta a un usuario con este cargo.
     *
     * No otorgan permisos: solo los leen el formulario de alta de usuario
     * y el aviso de cambio de cargo (sección 2.9).
     *
     * @return HasMany<CargoRolSugerido, $this>
     */
    public function rolesSugeridos(): HasMany
    {
        return $this->hasMany(CargoRolSugerido::class, 'cargo_id');
    }

    // -------------------------------------------------------------------------
    // Operaciones
    // -------------------------------------------------------------------------

    /**
     * Sustituye los roles sugeridos del cargo por los indicados.
     *
     * Crea y borra fila a fila para que cada cambio quede auditado. No toca
     * los roles de ningún usuario.
     *
     * @param list<string> $roles Nombres de roles Spatie.
     *
     * @throws \InvalidArgumentException si algún rol no existe.
     */
    public function sincronizarRolesSugeridos(array $roles): void
    {
        $roles = array_values(array_unique($roles));

        DB::transaction(function () use ($roles): void {
            $this->rolesSugeridos()
                ->whereNotIn('rol', $roles)
                ->get()
                ->each(fn (CargoRolSugerido $sugerencia) => $sugerencia->delete());

            foreach ($roles as $rol) {
                $this->rolesSugeridos()->firstOrCreate(['rol' => $rol]);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo cargos activos.
     *
     * @param Builder<Cargo> $consulta
     *
     * @return Builder<Cargo>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}
