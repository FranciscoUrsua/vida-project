<?php

namespace Modules\Mensajes\Models;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;

/**
 * Alerta del sistema.
 *
 * @property int $id
 * @property TipoAlerta $tipo
 * @property string $origen_type
 * @property int $origen_id
 * @property string $titulo
 * @property string $cuerpo
 * @property DestinatarioType $destinatario_type
 * @property int|null $destinatario_usuario_id
 * @property string|null $destinatario_rol
 * @property int|null $destinatario_uo_id
 * @property EstadoAlerta $estado
 * @property Carbon|null $expira_en
 * @property Carbon|null $escalada_en
 * @property int|null $escalada_a_usuario_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'tipo',
        'origen_type',
        'origen_id',
        'titulo',
        'cuerpo',
        'destinatario_type',
        'destinatario_usuario_id',
        'destinatario_rol',
        'destinatario_uo_id',
        'estado',
        'expira_en',
        'escalada_en',
        'escalada_a_usuario_id',
    ];

    protected $casts = [
        'tipo' => TipoAlerta::class,
        'destinatario_type' => DestinatarioType::class,
        'estado' => EstadoAlerta::class,
        'expira_en' => 'datetime',
        'escalada_en' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Reconocimientos registrados para esta alerta.
     *
     * @return HasMany<AlertaReconocimiento, self>
     */
    public function reconocimientos(): HasMany
    {
        return $this->hasMany(AlertaReconocimiento::class, 'alerta_id');
    }

    /**
     * Usuario al que va dirigida la alerta (cuando el destinatario es un usuario concreto).
     *
     * @return BelongsTo<User, self>
     */
    public function destinatarioUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_usuario_id');
    }

    /**
     * Unidad organizativa destinataria (cuando el destinatario es un rol+UO).
     *
     * @return BelongsTo<UnidadOrganizativa, self>
     */
    public function destinatarioUo(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'destinatario_uo_id');
    }

    /**
     * Usuario al que fue escalada la alerta tras vencer el plazo de reconocimiento.
     *
     * @return BelongsTo<User, self>
     */
    public function escaladaA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalada_a_usuario_id');
    }

    /**
     * Entidad que originó la alerta (relación polimórfica).
     *
     * @return MorphTo<Model, self>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra alertas en estado pendiente.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoAlerta::Pendiente);
    }

    /**
     * Alertas de tipo 'alerta' con el plazo de reconocimiento vencido.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeVencidas(Builder $query): Builder
    {
        return $query
            ->where('tipo', TipoAlerta::Alerta)
            ->where('estado', EstadoAlerta::Pendiente)
            ->where('expira_en', '<', now());
    }
}
