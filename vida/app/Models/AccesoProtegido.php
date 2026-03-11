<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
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
 * @property \Illuminate\Support\Carbon|null $fecha_resolucion
 * @property \Illuminate\Support\Carbon|null $acceso_valido_hasta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see \App\Policies\HistoriaSocialPolicy
 * @see docs/modulo-usuarios-permisos.md sección 3
 */
class AccesoProtegido extends Model
{
    use HasFactory;

    /** @var string Tabla de base de datos */
    protected $table = 'accesos_protegidos';

    /** @var list<string> Campos asignables en masa */
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

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'fecha_resolucion'    => 'datetime',
        'acceso_valido_hasta' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Profesional que solicitó el acceso.
     *
     * @return BelongsTo<User, AccesoProtegido>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Supervisor que aprobó o denegó la solicitud.
     *
     * @return BelongsTo<User, AccesoProtegido>
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
