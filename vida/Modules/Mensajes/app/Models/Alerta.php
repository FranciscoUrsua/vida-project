<?php

namespace Modules\Mensajes\Models;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
 * @property \Illuminate\Support\Carbon|null $expira_en
 * @property \Illuminate\Support\Carbon|null $escalada_en
 * @property int|null $escalada_a_usuario_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
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
        'tipo'              => TipoAlerta::class,
        'destinatario_type' => DestinatarioType::class,
        'estado'            => EstadoAlerta::class,
        'expira_en'         => 'datetime',
        'escalada_en'       => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /** @return HasMany<AlertaReconocimiento> */
    public function reconocimientos(): HasMany
    {
        return $this->hasMany(AlertaReconocimiento::class, 'alerta_id');
    }

    /** @return BelongsTo<User, Alerta> */
    public function destinatarioUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_usuario_id');
    }

    /** @return BelongsTo<UnidadOrganizativa, Alerta> */
    public function destinatarioUo(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'destinatario_uo_id');
    }

    /** @return BelongsTo<User, Alerta> */
    public function escaladaA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalada_a_usuario_id');
    }

    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** @param Builder<Alerta> $query */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoAlerta::Pendiente);
    }

    /**
     * Alertas de tipo 'alerta' con el plazo de reconocimiento vencido.
     *
     * @param Builder<Alerta> $query
     */
    public function scopeVencidas(Builder $query): Builder
    {
        return $query
            ->where('tipo', TipoAlerta::Alerta)
            ->where('estado', EstadoAlerta::Pendiente)
            ->where('expira_en', '<', now());
    }
}
