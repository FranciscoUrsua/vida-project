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
    /** Nivel de supervisión: la asignación no es efectiva hasta su aprobación. */
    public const APROBACION_PREVIA = 'aprobacion_previa';

    /** Nivel de supervisión: la asignación es efectiva al momento y genera alerta. */
    public const ALERTA_SUPERVISADA = 'alerta_supervisada';

    /**
     * Roles que requieren aprobación previa cuando no tienen configuración explícita.
     *
     * Es el nivel documentado en 2.8. Sin este respaldo, un rol crítico sin fila
     * en configuracion_roles se activaría sin aprobación.
     *
     * @var list<string>
     */
    private const ROLES_APROBACION_POR_DEFECTO = ['adm_sistema', 'supervision'];

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

    // -------------------------------------------------------------------------
    // Consultas
    // -------------------------------------------------------------------------

    /**
     * Nivel de supervisión que aplica a la asignación de un rol.
     *
     * Usa la configuración del backoffice si existe; si no, el nivel por defecto de 2.8.
     *
     * @param Role $rol Rol que se asigna.
     *
     * @return string APROBACION_PREVIA o ALERTA_SUPERVISADA.
     */
    public static function nivelPara(Role $rol): string
    {
        $configurado = self::where('rol_id', $rol->id)->value('nivel_supervision');

        if ($configurado !== null) {
            return $configurado;
        }

        return in_array($rol->name, self::ROLES_APROBACION_POR_DEFECTO, true)
            ? self::APROBACION_PREVIA
            : self::ALERTA_SUPERVISADA;
    }
}
