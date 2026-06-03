<?php

namespace Modules\Usuarios\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Usuarios\Observers\UsuarioRolObserver;
use Spatie\Permission\Models\Role;

/**
 * Historial de roles de un usuario.
 *
 * Es la FUENTE DE VERDAD para la asignación de roles.
 * model_has_roles de Spatie es el estado derivado activo, optimizado
 * para la evaluación rápida de can() en cada request.
 *
 * El campo `estado` gestiona el flujo de aprobación previa:
 * - pendiente_aprobacion: asignación solicitada, aún no activa en Spatie.
 * - activo: vigente, sincronizado en model_has_roles.
 * - inactivo: revocado, eliminado de model_has_roles.
 *
 * El Observer mantiene model_has_roles sincronizado con los cambios
 * en esta tabla.
 *
 * @property int $id
 * @property int $usuario_id
 * @property int $rol_id
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property int|null $asignado_por
 * @property string $estado pendiente_aprobacion|activo|inactivo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see UsuarioRolObserver
 * @see docs/modulo-usuarios-permisos.md secciones 4.2 y 4.3
 */
class UsuarioRol extends Model
{
    /** @var string */
    protected $table = 'usuario_rol';

    /** @var list<string> */
    protected $fillable = [
        'usuario_id',
        'rol_id',
        'fecha_inicio',
        'fecha_fin',
        'asignado_por',
        'estado',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Boot: registrar observer
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::observe(UsuarioRolObserver::class);
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
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Rol de Spatie asignado.
     *
     * @return BelongsTo<Role, UsuarioRol>
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    /**
     * Usuario que realizó la asignación.
     *
     * @return BelongsTo<User, UsuarioRol>
     */
    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Registros de rol vigentes (activos con fecha válida).
     *
     * @param Builder<UsuarioRol> $consulta
     *
     * @return Builder<UsuarioRol>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta
            ->where('estado', 'activo')
            ->where(function (Builder $q) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', Carbon::today());
            });
    }

    /**
     * Registros pendientes de aprobación.
     *
     * @param Builder<UsuarioRol> $consulta
     *
     * @return Builder<UsuarioRol>
     */
    public function scopePendientes(Builder $consulta): Builder
    {
        return $consulta->where('estado', 'pendiente_aprobacion');
    }
}
