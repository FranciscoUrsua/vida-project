<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Usuarios\Models\Profesional;

/**
 * Director o responsable de un centro en un periodo dado.
 *
 * El director puede ser interno (profesional_id relleno) o externo
 * (campos nombre/telefono/email rellenos). Ambas opciones son mutuamente
 * excluyentes — se valida en booted().
 * El registro activo es el que tiene fecha_fin = null.
 *
 * @property int $id
 * @property int $centro_id
 * @property int|null $profesional_id
 * @property string|null $nombre
 * @property string|null $telefono
 * @property string|null $email
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 */
class DirectorCentro extends Model
{
    use Versionable;

    protected $table = 'directores_centro';

    protected $fillable = [
        'centro_id',
        'profesional_id',
        'nombre',
        'telefono',
        'email',
        'fecha_inicio',
        'fecha_fin',
        'notas',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // -------------------------------------------------------------------------
    // Validación en modelo
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (self $director) {
            $tieneInterno  = ! empty($director->profesional_id);
            $tieneExterno  = ! empty($director->nombre)
                || ! empty($director->email)
                || ! empty($director->telefono);

            if ($tieneInterno && $tieneExterno) {
                throw new \InvalidArgumentException(
                    'Un director no puede tener profesional_id y datos de contacto externo al mismo tiempo.'
                );
            }

            if (! $tieneInterno && ! $tieneExterno) {
                throw new \InvalidArgumentException(
                    'Un director debe ser un profesional interno o tener datos de contacto externo.'
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActivo(Builder $query): Builder
    {
        return $query->whereNull('fecha_fin');
    }
}
