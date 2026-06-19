<?php

namespace App\Models;

use App\Enums\AccionAuditEnum;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Registro de auditoría de acceso a datos de ciudadanos.
 *
 * Inmutable por diseño: update() y delete() a nivel de instancia lanzan
 * LogicException. La única operación de borrado legítima es la purga por
 * retención ejecutada por AuditPurgeCommand mediante query builder directo.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AccionAuditEnum $accion
 * @property string $auditable_type
 * @property int $auditable_id
 * @property int|null $ciudadano_id
 * @property array|null $datos_antes
 * @property array|null $datos_despues
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array|null $contexto
 * @property Carbon $created_at
 *
 * @see docs/modulo-auditoria.md §2
 * @see AuditService
 */
class Audit extends Model
{
    /** Sin updated_at — los registros son inmutables. */
    const UPDATED_AT = null;

    /** @var string */
    protected $table = 'audits';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'accion',
        'auditable_type',
        'auditable_id',
        'ciudadano_id',
        'datos_antes',
        'datos_despues',
        'ip',
        'user_agent',
        'contexto',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'accion' => AccionAuditEnum::class,
        'datos_antes' => 'array',
        'datos_despues' => 'array',
        'contexto' => 'array',
        'created_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Inmutabilidad
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $attributes Atributos a actualizar.
     * @param array<string, mixed> $options Opciones de persistencia.
     *
     * @throws LogicException Los registros de auditoría son inmutables.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Los registros de auditoría son inmutables y no pueden modificarse.');
    }

    /**
     * @throws LogicException Use AuditPurgeCommand para purgas por retención.
     */
    public function delete(): ?bool
    {
        throw new LogicException('Los registros de auditoría no pueden eliminarse individualmente. Use AuditPurgeCommand.');
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Modelo afectado (polimórfico).
     *
     * @return MorphTo<Model, Audit>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Profesional que realizó la acción.
     *
     * @return BelongsTo<User, Audit>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ciudadano al que pertenece el dato accedido.
     *
     * @return BelongsTo<Ciudadano, Audit>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class);
    }
}
