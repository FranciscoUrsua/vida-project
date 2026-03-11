<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * Modelo pivot de adscripción usuario–UO–rol.
 *
 * Registra la vinculación de un usuario a una Unidad Organizativa
 * con un rol concreto y un tipo de vínculo laboral. Tiene fechas de
 * vigencia para mantener el historial completo de adscripciones
 * (principio 4.2: el pasado es inmutable).
 *
 * Un usuario puede tener varios registros vigentes simultáneamente
 * (varios roles en varias UO). El permiso efectivo es la unión de
 * todos los roles activos (docs/modulo-usuarios-permisos.md § 1.3).
 *
 * @property int $id
 * @property int $usuario_id
 * @property int $unidad_organizativa_id
 * @property int $rol_id
 * @property string $tipo_vinculo
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class UsuarioUoRol extends Model
{
    use HasFactory;

    /** @var string Tabla de base de datos */
    protected $table = 'usuario_uo_rol';

    /** @var list<string> Campos asignables en masa */
    protected $fillable = [
        'usuario_id',
        'unidad_organizativa_id',
        'rol_id',
        'tipo_vinculo',
        'fecha_inicio',
        'fecha_fin',
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Usuario al que corresponde esta adscripción.
     *
     * @return BelongsTo<User, UsuarioUoRol>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Unidad Organizativa a la que el usuario está adscrito.
     *
     * @return BelongsTo<UnidadOrganizativa, UsuarioUoRol>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Rol de Spatie asignado en esta adscripción.
     *
     * @return BelongsTo<Role, UsuarioUoRol>
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra únicamente las adscripciones vigentes:
     * aquellas donde fecha_fin es null o todavía no ha llegado.
     *
     * @param Builder<UsuarioUoRol> $consulta
     * @return Builder<UsuarioUoRol>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where(function (Builder $q) {
            $q->whereNull('fecha_fin')
              ->orWhere('fecha_fin', '>=', Carbon::today());
        });
    }
}
