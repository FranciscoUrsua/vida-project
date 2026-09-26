<?php

namespace Modules\Mensajes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Mensajes\Enums\TipoReconocimiento;

/**
 * Evento del reconocimiento de una alerta: un destinatario la reconoce o
 * descarta, o se escala su parte a un supervisor. Registro de solo alta.
 *
 * @property int $id
 * @property int $alerta_id
 * @property int|null $alerta_destinatario_id
 * @property int $usuario_id
 * @property TipoReconocimiento $tipo
 * @property Carbon $reconocida_en
 * @property string|null $ip_address
 */
class AlertaReconocimiento extends Model
{
    protected $table = 'alerta_reconocimientos';

    public $timestamps = false;

    protected $fillable = [
        'alerta_id',
        'alerta_destinatario_id',
        'usuario_id',
        'tipo',
        'reconocida_en',
        'ip_address',
    ];

    protected $casts = [
        'tipo' => TipoReconocimiento::class,
        'reconocida_en' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Alerta a la que pertenece este reconocimiento.
     *
     * @return BelongsTo<Alerta, self>
     */
    public function alerta(): BelongsTo
    {
        return $this->belongsTo(Alerta::class, 'alerta_id');
    }

    /**
     * Parte de la alerta (destinatario) a la que se refiere el evento.
     *
     * @return BelongsTo<AlertaDestinatario, self>
     */
    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(AlertaDestinatario::class, 'alerta_destinatario_id');
    }

    /**
     * Usuario que realizó el reconocimiento (o supervisor que recibe la escalada).
     *
     * @return BelongsTo<User, self>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
