<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría de una revelación de identidad.
 *
 * Cada fila documenta que un usuario específico, con una justificación
 * dada, resolvió un alias seudonimizado para obtener el ciudadano real.
 * Los registros son inmutables — sin updated_at.
 *
 * El requisito de justificación obligatoria y la trazabilidad completa
 * son parte del cumplimiento RGPD para el Nivel 1 de anonimización.
 * Ver docs/anonimizacion.md § 6.3.
 *
 * @property int    $id
 * @property int    $usuario_id
 * @property string $accion        Siempre 'revelar_identidad'
 * @property string $alias         El alias CIU-{...} que se resolvió
 * @property int    $ciudadano_id  El ciudadano cuya identidad se reveló
 * @property string $justificacion
 * @property \Illuminate\Support\Carbon $created_at
 */
class RevelacionIdentidad extends Model
{
    /** @var string */
    protected $table = 'revelaciones_identidad';

    /** Los registros de auditoría son inmutables */
    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'usuario_id',
        'accion',
        'alias',
        'ciudadano_id',
        'justificacion',
    ];

    /**
     * Usuario que realizó la revelación.
     *
     * @return BelongsTo<User, RevelacionIdentidad>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
