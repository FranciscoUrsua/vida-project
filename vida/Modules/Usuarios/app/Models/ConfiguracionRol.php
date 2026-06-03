<?php

namespace Modules\Usuarios\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * Configuración adicional de un rol de Spatie.
 *
 * Extiende los roles de Spatie sin modificar sus tablas.
 * Actualmente almacena el nivel de supervisión requerido
 * para que la asignación de ese rol sea efectiva.
 *
 * nivel_supervision:
 * - aprobacion_previa: la asignación no es efectiva hasta que
 *   el supervisor la aprueba explícitamente (adm_sistema, supervision).
 * - alerta_supervisada: la asignación es inmediata, genera alerta
 *   que el supervisor debe reconocer (resto de roles).
 *
 * @property int $id
 * @property int $rol_id
 * @property string $nivel_supervision
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see docs/modulo-usuarios-permisos.md sección 2.8
 */
class ConfiguracionRol extends Model
{
    /** @var string */
    protected $table = 'configuracion_roles';

    /** @var list<string> */
    protected $fillable = [
        'rol_id',
        'nivel_supervision',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Rol de Spatie al que corresponde esta configuración.
     *
     * @return BelongsTo<Role, ConfiguracionRol>
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }
}
