<?php

namespace Modules\Prestaciones\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Prestaciones\Database\Factories\PrestacionFactory;

/**
 * Prestación del catálogo oficial de servicios sociales municipales.
 *
 * Fuente de verdad sobre qué prestaciones existen, sus condiciones y cómo
 * se accede a ellas. Es un módulo de catálogo de solo lectura para el resto
 * del sistema; el mantenimiento se realiza exclusivamente desde Filament.
 *
 * Los campos clasificatorios (objetivo_general, categoria_especifica, etc.)
 * son claves de catalogos_sistema, nunca FKs (principio 3.10).
 *
 * Los únicos enums son tipo_prestacion y nivel_garantia porque el código
 * toma decisiones de lógica de negocio basándose en ellos (principio 3.10).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $tipo_prestacion servicio|economica
 * @property string $nivel_garantia garantizada|condicionada
 * @property string|null $objetivo_general
 * @property string|null $categoria_especifica
 * @property string|null $nivel_atencion
 * @property string|null $competencia
 * @property string|null $forma_gestion
 * @property string|null $financiacion
 * @property string|null $ambito_territorial
 * @property array|null $poblacion_destinataria
 * @property array|null $modalidades
 * @property string|null $finalidad
 * @property string|null $descripcion
 * @property string|null $requisitos
 * @property string|null $procedimiento
 * @property string|null $compatibilidad
 * @property string|null $condiciones_acceso
 * @property string|null $obligaciones
 * @property string|null $estandares_calidad
 * @property string|null $normativa
 * @property string|null $aportacion_usuario
 * @property string|null $plazo_concesion
 * @property string|null $duracion_maxima
 * @property string|null $proveedor
 * @property string|null $financiacion_detalle
 * @property string|null $informacion_complementaria
 * @property bool $activa
 */
class Prestacion extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Versionable;

    protected static function newFactory(): PrestacionFactory
    {
        return PrestacionFactory::new();
    }

    protected $table = 'prestaciones';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_prestacion',
        'nivel_garantia',
        'objetivo_general',
        'categoria_especifica',
        'nivel_atencion',
        'competencia',
        'forma_gestion',
        'financiacion',
        'ambito_territorial',
        'poblacion_destinataria',
        'modalidades',
        'finalidad',
        'descripcion',
        'requisitos',
        'procedimiento',
        'compatibilidad',
        'condiciones_acceso',
        'obligaciones',
        'estandares_calidad',
        'normativa',
        'aportacion_usuario',
        'plazo_concesion',
        'duracion_maxima',
        'proveedor',
        'financiacion_detalle',
        'informacion_complementaria',
        'activa',
    ];

    protected $casts = [
        'poblacion_destinataria' => 'array',
        'modalidades' => 'array',
        'activa' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Tipos de centro vinculados a esta prestación.
     *
     * @return HasMany<PrestacionTipoCentro, self>
     */
    public function tiposCentro(): HasMany
    {
        return $this->hasMany(PrestacionTipoCentro::class, 'prestacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra prestaciones activas.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * Filtra prestaciones de tipo servicio (no económicas).
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeDeServicio(Builder $query): Builder
    {
        return $query->where('tipo_prestacion', 'servicio');
    }

    /**
     * Filtra prestaciones de tipo económico.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeEconomicas(Builder $query): Builder
    {
        return $query->where('tipo_prestacion', 'economica');
    }
}
