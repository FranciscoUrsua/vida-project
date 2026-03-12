<?php

namespace Modules\Usuarios\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historial de roles de un usuario.
 *
 * Registra qué roles ha tenido el usuario a lo largo del tiempo.
 * Es COMPLEMENTARIO a la tabla model_has_roles de Spatie:
 * - Spatie gestiona los roles activos en este momento.
 * - Esta tabla mantiene el historial con fechas de vigencia.
 *
 * Cuando se crea un UsuarioRol con fecha_fin null, el Observer
 * asigna automáticamente el rol en Spatie. Cuando se establece
 * fecha_fin, el Observer revoca el rol en Spatie si ya no hay
 * ningún UsuarioRol vigente para ese rol.
 *
 * @property int $id
 * @property int $user_id
 * @property string $rol Nombre del rol de Spatie
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see \Modules\Usuarios\Observers\UsuarioRolObserver
 * @see docs/modulo-usuarios-permisos.md sección 4.2
 */
class UsuarioRol extends Model
{
    /** @var string */
    protected $table = 'usuario_rol';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'rol',
        'fecha_inicio',
        'fecha_fin',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // -------------------------------------------------------------------------
    // Boot: registrar observer
    // -------------------------------------------------------------------------

    /**
     * Registra el observer para mantener Spatie sincronizado.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::observe(\Modules\Usuarios\Observers\UsuarioRolObserver::class);
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Usuario al que corresponde este historial de rol.
     *
     * @return BelongsTo<User, UsuarioRol>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra únicamente los registros de rol vigentes:
     * aquellos donde fecha_fin es null o todavía no ha llegado.
     *
     * @param Builder<UsuarioRol> $consulta
     * @return Builder<UsuarioRol>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where(function (Builder $q) {
            $q->whereNull('fecha_fin')
              ->orWhere('fecha_fin', '>=', Carbon::today());
        });
    }
}
