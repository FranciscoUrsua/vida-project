<?php

namespace App\Models;

use Database\Factories\AccesoProtegidoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Usuarios\Policies\HistoriaSocialPolicy;

/**
 * @use HasFactory<AccesoProtegidoFactory>
 *
 * Modelo de solicitud de acceso a ciudadano especialmente protegido.
 *
 * Registra el flujo de aprobación definido en la sección 3 de
 * docs/modulo-usuarios-permisos.md: un profesional solicita acceso
 * a la Historia de un ciudadano protegido fuera de su UO; el supervisor
 * competente aprueba o deniega la solicitud.
 *
 * Los estados posibles son: pendiente, aprobado, denegado.
 *
 * @property int $id
 * @property int $usuario_id Profesional que solicita el acceso
 * @property int $ciudadano_id Ciudadano cuya Historia se quiere consultar
 * @property int $solicitante_id Quien realiza la solicitud formalmente
 * @property string $justificacion Justificación obligatoria
 * @property string $estado pendiente | aprobado | denegado
 * @property int|null $aprobado_por Supervisor que resuelve
 * @property Carbon|null $fecha_resolucion
 * @property Carbon|null $acceso_valido_hasta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see HistoriaSocialPolicy
 * @see docs/modulo-usuarios-permisos.md sección 3
 */
class AccesoProtegido extends Model
{
    /** @use HasFactory<AccesoProtegidoFactory> */
    use HasFactory;

    protected $table = 'accesos_protegidos';

    protected $fillable = [
        'usuario_id',
        'ciudadano_id',
        'solicitante_id',
        'justificacion',
        'estado',
        'aprobado_por',
        'fecha_resolucion',
        'acceso_valido_hasta',
    ];

    protected $casts = [
        'fecha_resolucion' => 'datetime',
        'acceso_valido_hasta' => 'datetime',
    ];

    /**
     * Profesional que solicitó el acceso.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Supervisor que aprobó o denegó la solicitud.
     *
     * @return BelongsTo<User, $this>
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
