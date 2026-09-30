<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Organizacion\Models\Barrio;
use Modules\Organizacion\Models\Distrito;
use Modules\Organizacion\Models\SeccionCensal;

/**
 * Ámbito territorial de atención de un centro.
 *
 * Define la población geográfica a la que atiende el centro: cada registro es
 * una unidad territorial (distrito, barrio o sección censal) o la ciudad
 * completa. Un centro puede tener varios registros combinando tipos distintos.
 * Si existe un registro de tipo 'ciudad_completa', no puede coexistir
 * con ningún otro ámbito para ese mismo centro.
 *
 * Con estos registros se resuelve el centro de una dirección
 * (docs/modulo-asignacion.md §3). Por eso dos centros activos del mismo tipo y
 * con adscripción por domicilio no pueden compartir una misma unidad: la
 * persona quedaría en dos centros a la vez (RN-02). Sí pueden tener unidades
 * de distinto nivel que se contengan (gana la más específica).
 *
 * @property int $id
 * @property int $centro_id
 * @property string $tipo
 * @property string $descripcion
 * @property int|null $referencia_id
 * @property string|null $referencia_tipo Clase del modelo referenciado.
 * @property array|null $geojson
 * @property-read Centro $centro
 * @property-read Distrito|Barrio|SeccionCensal|null $referencia
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

    /**
     * Clase de la unidad territorial que referencia cada tipo de ámbito.
     *
     * @var array<string, class-string<Model>>
     */
    public const CLASE_REFERENCIA = [
        'demarcacion_oficial' => Distrito::class,
        'barrios' => Barrio::class,
        'secciones_censales' => SeccionCensal::class,
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

    /**
     * Centro asociado al ámbito territorial.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Unidad territorial referenciada (distrito, barrio o sección censal).
     *
     * @return MorphTo<Model, $this>
     */
    public function referencia(): MorphTo
    {
        return $this->morphTo('referencia', 'referencia_tipo', 'referencia_id');
    }

    // ── Validaciones de modelo ──────────────────────────────────────────

    /**
     * Registra la validación de coherencia antes de crear o actualizar.
     *
     * @return void
     */
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
     * - distrito, barrio y sección apuntan a una unidad existente de su clase.
     * - ciudad_completa no puede coexistir con otros ámbitos del mismo centro.
     * - poligono_gis requiere geojson.
     * - la unidad no puede estar ya en otro centro del mismo tipo con
     *   adscripción por domicilio (RN-02).
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function validarCoherencia(): void
    {
        $this->validarReferencia();

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

        $this->validarSinSolapamiento();
    }

    /**
     * Comprueba que el tipo de ámbito y la clase referenciada coinciden y que la
     * unidad existe. Si la clase no viene informada, la deduce del tipo.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function validarReferencia(): void
    {
        $clase = self::CLASE_REFERENCIA[$this->tipo] ?? null;

        if ($clase === null) {
            return;
        }

        $this->referencia_tipo ??= $clase;

        if ($this->referencia_tipo !== $clase) {
            throw new \InvalidArgumentException(
                "Un ámbito de tipo {$this->tipo} debe referenciar un ".class_basename($clase).'.'
            );
        }

        if ($this->referencia_id === null || ! $clase::whereKey($this->referencia_id)->exists()) {
            throw new \InvalidArgumentException(
                'El ámbito debe indicar un '.class_basename($clase).' existente.'
            );
        }
    }

    /**
     * Rechaza el ámbito si la misma unidad ya pertenece a otro centro activo del
     * mismo tipo con adscripción por domicilio: la persona de esa unidad tendría
     * dos centros y la asignación sería ambigua. Unidades de distinto nivel no
     * cuentan como solapamiento (se resuelve por la más específica).
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function validarSinSolapamiento(): void
    {
        $centro = Centro::find($this->centro_id);

        if ($centro === null || ! $centro->activo || $centro->inscripcion_libre || $centro->tipo_centro === null) {
            return;
        }

        $otro = static::query()
            ->where('tipo', $this->tipo)
            ->where('centro_id', '!=', $this->centro_id)
            ->when($this->tipo !== 'ciudad_completa', fn ($q) => $q
                ->where('referencia_tipo', $this->referencia_tipo)
                ->where('referencia_id', $this->referencia_id))
            ->whereHas('centro', fn ($q) => $q
                ->where('activo', true)
                ->where('inscripcion_libre', false)
                ->where('tipo_centro', $centro->tipo_centro))
            ->with('centro')
            ->first();

        if ($otro !== null) {
            throw new \InvalidArgumentException(
                "«{$this->descripcion}» ya está en el ámbito de {$otro->centro->nombre}, del mismo tipo de centro. "
                .'Una misma unidad no puede pertenecer a dos centros con adscripción por domicilio.'
            );
        }
    }
}