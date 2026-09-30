<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sección censal del INE: unidad territorial más fina de VIDA.
 *
 * La geocodificación devuelve la sección de cada dirección y con ella se
 * resuelve el centro que la atiende, sin consulta espacial
 * (docs/modulo-asignacion.md §3.2). El número de sección solo es único dentro
 * de su distrito: la clave global es el código INE de 10 dígitos.
 *
 * @property int $id
 * @property int $distrito_id
 * @property int|null $barrio_id
 * @property string $codigo_ine Código de 10 dígitos, ej: 2807921028.
 * @property string $codigo_en_distrito Número dentro del distrito, ej: 28.
 * @property bool $activa
 * @property Carbon|null $vigente_desde
 * @property Carbon|null $vigente_hasta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Distrito $distrito
 * @property-read Barrio|null $barrio
 *
 * @see docs/modulo-asignacion.md §2
 */
class SeccionCensal extends Model
{
    /** Prefijo INE del municipio: provincia 28 (Madrid) + municipio 079 (Madrid). */
    public const PREFIJO_MUNICIPIO = '28079';

    /** @var string */
    protected $table = 'secciones_censales';

    /** @var list<string> */
    protected $fillable = [
        'distrito_id',
        'barrio_id',
        'codigo_ine',
        'codigo_en_distrito',
        'activa',
        'vigente_desde',
        'vigente_hasta',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activa' => 'boolean',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    /**
     * Construye el código INE de 10 dígitos a partir de los códigos de distrito
     * y sección tal como los devuelve la BDC (sin ceros a la izquierda).
     *
     * @param string $distrito Código de distrito, ej: 21 o 2.
     * @param string $seccion Número de sección dentro del distrito, ej: 28.
     * @return string
     */
    public static function codigoIne(string $distrito, string $seccion): string
    {
        return self::PREFIJO_MUNICIPIO
            .str_pad(ltrim($distrito, '0') ?: '0', 2, '0', STR_PAD_LEFT)
            .str_pad(ltrim($seccion, '0') ?: '0', 3, '0', STR_PAD_LEFT);
    }

    /**
     * Distrito al que pertenece la sección.
     *
     * @return BelongsTo<Distrito, $this>
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Barrio al que pertenece la sección.
     *
     * @return BelongsTo<Barrio, $this>
     */
    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class, 'barrio_id');
    }

    /**
     * Filtra únicamente las secciones activas.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}
