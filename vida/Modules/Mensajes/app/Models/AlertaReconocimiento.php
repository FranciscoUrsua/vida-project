<?php

namespace Modules\Mensajes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Mensajes\Enums\TipoReconocimiento;

/**
 * Reconocimiento individual de una alerta por un usuario.
 *
 * @property int $id
 * @property int $alerta_id
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
     * Usuario que realizó el reconocimiento.
     *
     * @return BelongsTo<User, self>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
