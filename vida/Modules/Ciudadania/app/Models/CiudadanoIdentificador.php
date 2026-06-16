<?php

namespace Modules\Ciudadania\Models;

use App\Models\Ciudadano;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de identidad de un ciudadano.
 *
 * Un ciudadano puede tener múltiples documentos a lo largo del tiempo.
 * El campo `valor` está cifrado; `valor_hash` permite búsqueda sin descifrar.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property string $tipo
 * @property string $valor Cifrado
 * @property string $valor_hash SHA-256(strtolower(valor))
 * @property string $fecha_inicio
 * @property string|null $fecha_fin
 * @property bool $verificado
 * @property string $fuente
 * @property string|null $observaciones
 */
class CiudadanoIdentificador extends Model
{
    use Auditable;

    /** @var string */
    protected $table = 'ciudadano_identificadores';

    /** @var list<string> */
    protected $fillable = [
        'ciudadano_id',
        'tipo',
        'valor',
        'valor_hash',
        'fecha_inicio',
        'fecha_fin',
        'verificado',
        'fuente',
        'observaciones',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valor' => 'encrypted',
        'verificado' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    /**
     * Auto-calcula valor_hash al crear o actualizar si no está asignado.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->valor_hash) && ! empty($model->valor)) {
                $model->valor_hash = hash('sha256', strtolower($model->valor));
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('valor')) {
                $model->valor_hash = hash('sha256', strtolower($model->valor));
            }
        });
    }

    /**
     * @return BelongsTo<Ciudadano, CiudadanoIdentificador>
     */
    /** {@inheritDoc} */
    public function getCiudadanoId(): ?int
    {
        return $this->ciudadano_id;
    }

    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class);
    }
}
