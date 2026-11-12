<?php

namespace Modules\Centro\Models;

use App\Contracts\DireccionableModel;
use App\Models\UnidadOrganizativa;
use App\Traits\TieneDireccion;
use App\Traits\Versionable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Centro\Traits\TieneDenominaciones;
use Modules\Organizacion\Models\Distrito;

/**
 * Centro de servicios sociales.
 *
 * Unidad operativa donde se prestan los servicios municipales.
 * Pertenece a una UnidadOrganizativa. El ámbito territorial se modela
 * a través de AmbitoTerritorial (tabla ambitos_territoriales).
 *
 * Todo centro es de un tipo (TipoCentro) toda su vida (RN-01): el tipo es
 * obligatorio al alta, no puede ser un tipo dado de baja y no cambia nunca; si
 * un centro debe ser de otro tipo, se da de baja y se crea otro con este como
 * antecesor. Cada centro tiene su propia UO instrumental y una UO no tiene más
 * de un centro (RN-14).
 *
 * tipo_gestion: municipal_directo | municipal_concertado | privado_concertado | privado_puro
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $nombre_corto
 * @property string $tipo_gestion
 * @property int $tipo_centro_id Tipo de centro (RN-01), inmutable.
 * @property int|null $centro_antecesor_id Centro al que sustituye (RN-01).
 * @property int|null $unidad_organizativa_id
 * @property string|null $direccion_texto
 * @property bool $direccion_normalizada
 * @property string|null $codigo_postal
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $web
 * @property string|null $notas
 * @property string|null $protocolo Protocolo propio; si es null, rige el del tipo.
 * @property bool $activo
 * @property Carbon $fecha_alta
 * @property Carbon|null $fecha_baja
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sala> $salas
 * @property-read TipoCentro $tipoCentro
 * @property-read array<string, mixed>|null $horario Horario efectivo de hoy (accesor).
 * @property-read Centro|null $antecesor
 */
class Centro extends Model implements DireccionableModel
{
    use SoftDeletes;
    use TieneDenominaciones;
    use TieneDireccion;
    use Versionable;

    protected $table = 'centros';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'tipo_gestion',
        'tipo_centro_id',
        'centro_antecesor_id',
        'unidad_organizativa_id',
        // Dirección canónica — campos del trait TieneDireccion
        'direccion_texto',
        'direccion_normalizada',
        'origen_direccion',
        'tipo_via',
        'nombre_via',
        'tipo_numeracion',
        'numero',
        'portal',
        'escalera',
        'piso',
        'puerta',
        'codigo_postal',
        'municipio',
        'coordenadas_lat',
        'coordenadas_lng',
        'geocoder_proveedor',
        'codigo_ndp',
        'distrito_codigo',
        'barrio_codigo',
        'seccion_censal_codigo',
        // Contacto y configuración
        'telefono',
        'email',
        'web',
        'notas',
        'protocolo',
        'activo',
        'fecha_alta',
        'fecha_baja',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    /**
     * Protege el tipo (RN-01) y la relación 1:1 con la UO (RN-14).
     *
     * @throws DomainException|LogicException
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $centro): void {
            $centro->exigirTipoActivo();
        });

        static::updating(function (self $centro): void {
            if ($centro->isDirty('tipo_centro_id')) {
                throw new LogicException('El tipo de un centro no se cambia (RN-01): da de baja el centro y crea otro.');
            }
        });

        static::saving(function (self $centro): void {
            $centro->exigirUoSinOtroCentro();
        });
    }

    /**
     * Horario efectivo de hoy, en lectura (sustituye a la antigua columna `horario`).
     *
     * @return array{dias_laborables: list<int>, apertura: string, cierre: string, inicio_atencion: string, fin_atencion: string, origen: string}|null
     */
    public function getHorarioAttribute(): ?array
    {
        $horario = app(\Modules\Centro\Services\ConfiguracionEfectivaCentro::class)->horario($this, today());

        return $horario === null ? null : [
            'dias_laborables' => $horario->diasLaborables,
            'apertura' => $horario->apertura,
            'cierre' => $horario->cierre,
            'inicio_atencion' => $horario->inicioAtencion,
            'fin_atencion' => $horario->finAtencion,
            'origen' => $horario->origen,
        ];
    }

    /**
     * Código del tipo del centro (estable, RN-02), p. ej. `css_general`.
     *
     * @return string
     */
    public function codigoTipo(): string
    {
        return app(\Modules\Centro\Services\ConfiguracionEfectivaCentro::class)->tipo($this)->codigo;
    }

    /**
     * Columnas que guardan la denominación vigente.
     *
     * @return array{0: string, 1: string}
     */
    public function columnasCacheDenominacion(): array
    {
        return ['nombre', 'nombre_corto'];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Tipo del centro (RN-01).
     *
     * @return BelongsTo<TipoCentro, $this>
     */
    public function tipoCentro(): BelongsTo
    {
        return $this->belongsTo(TipoCentro::class, 'tipo_centro_id');
    }

    /**
     * Centro al que sustituye, si nació de una baja y alta (RN-01).
     *
     * @return BelongsTo<Centro, $this>
     */
    public function antecesor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'centro_antecesor_id')->withTrashed();
    }

    /**
     * Unidad organizativa a la que pertenece el centro.
     *
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Ámbitos territoriales de atención del centro.
     *
     * @return HasMany<AmbitoTerritorial, $this>
     */
    public function ambitosTeritoriales(): HasMany
    {
        return $this->hasMany(AmbitoTerritorial::class, 'centro_id');
    }

    /**
     * @deprecated distrito_id fue eliminado en v1.1. Usar ambitosTeritoriales() en su lugar.
     *
     * @return BelongsTo<Distrito, $this>
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Colecciones de plazas del centro.
     *
     * @return HasMany<ColeccionPlazas, $this>
     */
    public function coleccionesPlazas(): HasMany
    {
        return $this->hasMany(ColeccionPlazas::class, 'centro_id');
    }

    /**
     * Salas funcionales del centro (aulas, salas de reuniones, despachos...).
     *
     * @return HasMany<Sala, $this>
     */
    public function salas(): HasMany
    {
        return $this->hasMany(Sala::class, 'centro_id');
    }

    /**
     * Actividades programadas en el centro.
     *
     * @return HasMany<Actividad, $this>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'centro_id');
    }

    /**
     * Historial de directores del centro.
     *
     * @return HasMany<DirectorCentro, $this>
     */
    public function directores(): HasMany
    {
        return $this->hasMany(DirectorCentro::class, 'centro_id');
    }

    /**
     * Personas de contacto adicionales del centro.
     *
     * @return HasMany<ContactoCentro, $this>
     */
    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoCentro::class, 'centro_id');
    }

    /**
     * Inscripciones de ciudadanos al centro.
     *
     * @return HasMany<InscripcionCentro, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionCentro::class, 'centro_id');
    }

    /**
     * Redes a las que pertenece el centro.
     *
     * @return BelongsToMany<Red, $this>
     */
    public function redes(): BelongsToMany
    {
        return $this->belongsToMany(Red::class, 'red_centro', 'centro_id', 'red_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de negocio
    // -------------------------------------------------------------------------

    /**
     * Devuelve el DirectorCentro activo (fecha_fin null), o null si no lo hay.
     *
     * @return DirectorCentro|null
     */
    public function directorActivo(): ?DirectorCentro
    {
        return $this->directores()->activo()->first();
    }

    /**
     * Nombra un nuevo director cerrando el activo actual (si existe).
     *
     * $datos debe incluir los campos de DirectorCentro (profesional_id o nombre/telefono/email)
     * y opcionalmente fecha_inicio (por defecto hoy).
     *
     * @param array{profesional_id?: int|null, nombre?: string|null, telefono?: string|null, email?: string|null, fecha_inicio?: \DateTimeInterface|string|null, fecha_fin?: \DateTimeInterface|string|null, notas?: string|null} $datos Datos del nuevo director.
     * @return DirectorCentro
     */
    public function nombrarDirector(array $datos): DirectorCentro
    {
        $this->directores()->whereNull('fecha_fin')->update(['fecha_fin' => today()]);

        return $this->directores()->create(array_merge(
            ['fecha_inicio' => today()],
            $datos,
        ));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra centros activos y sin fecha de baja.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->whereNull('fecha_baja');
    }

    // -------------------------------------------------------------------------
    // Invariantes
    // -------------------------------------------------------------------------

    /**
     * Todo centro nace con un tipo activo (RN-01, RN-11).
     *
     * @throws DomainException
     *
     * @return void
     */
    private function exigirTipoActivo(): void
    {
        if ($this->tipo_centro_id === null) {
            throw new DomainException('Todo centro tiene un tipo de centro (RN-01).');
        }

        if (! TipoCentro::whereKey($this->tipo_centro_id)->where('activo', true)->exists()) {
            throw new DomainException('No se puede dar de alta un centro en un tipo dado de baja (RN-11).');
        }
    }

    /**
     * Una UO tiene como mucho un centro (RN-14). El índice único parcial
     * `centros_una_por_uo` lo garantiza también en base de datos.
     *
     * @throws DomainException
     *
     * @return void
     */
    private function exigirUoSinOtroCentro(): void
    {
        if ($this->unidad_organizativa_id === null || ! $this->isDirty('unidad_organizativa_id')) {
            return;
        }

        $ocupada = self::query()
            ->where('unidad_organizativa_id', $this->unidad_organizativa_id)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->exists();

        if ($ocupada) {
            throw new DomainException('Esa UO ya tiene un centro: cada centro tiene su propia UO (RN-14).');
        }
    }
}
