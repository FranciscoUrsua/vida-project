<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ámbito territorial de atención de un centro.
 *
 * Define la población geográfica a la que atiende el centro.
 * Un centro puede tener varios registros combinando tipos distintos.
 * Si existe un registro de tipo 'ciudad_completa', no puede coexistir
 * con ningún otro ámbito para ese mismo centro.
 */
class AmbitoTerritorial extends Model
{
    public const TIPOS = [
        'ciudad_completa',
        'demarcacion_oficial',
        'barrios',
        'secciones_censales',
        'poligono_gis',
    ];

    protected $table = 'ambitos_territoriales';

    protected $fillable = [
        'centro_id',
        'tipo',
        'descripcion',
        'referencia_id',
        'referencia_tipo',
        'geojson',
    ];

    protected $casts = [
        'geojson' => 'array',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    // ── Validaciones de modelo ──────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (AmbitoTerritorial $ambito) {
            $ambito->validarCoherencia();
        });

        static::updating(function (AmbitoTerritorial $ambito) {
            $ambito->validarCoherencia();
        });
    }

    /**
     * Valida las reglas de coherencia del ámbito:
     * - ciudad_completa no puede coexistir con otros ámbitos del mismo centro.
     * - poligono_gis requiere geojson.
     *
     * @throws \InvalidArgumentException
     */
    protected function validarCoherencia(): void
    {
        if ($this->tipo === 'poligono_gis' && empty($this->geojson)) {
            throw new \InvalidArgumentException(
                'Un ámbito de tipo poligono_gis requiere el campo geojson.'
            );
        }

        $query = static::where('centro_id', $this->centro_id)
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id));

        if ($this->tipo === 'ciudad_completa' && $query->exists()) {
            throw new \InvalidArgumentException(
                'Un centro con ámbito ciudad_completa no puede tener otros ámbitos.'
            );
        }

        if ($this->tipo !== 'ciudad_completa' && $query->where('tipo', 'ciudad_completa')->exists()) {
            throw new \InvalidArgumentException(
                'No se pueden añadir ámbitos adicionales a un centro con ámbito ciudad_completa.'
            );
        }
    }
}
