<?php

namespace Modules\Intervencion\Models;

use App\Enums\AccionAuditEnum;
use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use App\Support\Fichas\ValorCampo;
use App\Traits\Auditable;
use App\Traits\Versionable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Database\Factories\FichaFactory;

/**
 * @use HasFactory<FichaFactory>
 *
 * Ficha de datos reales: de una valoración, de un formulario de solicitud o de la persona.
 *
 * Cada ficha corresponde a un TipoFicha y almacena los valores
 * introducidos por el profesional. El campo datos es un JSON libre;
 * el campo notas permite texto sin estructura durante la entrevista.
 * `datos` y `notas` se guardan **cifrados** (datos personales, CLAUDE.md §3; decisión del
 * 27 nov 2026): no se pueden consultar por SQL.
 *
 * Las fichas de uso `persona` (SD-7, ficha sociodemográfica) pertenecen al ciudadano
 * (`ciudadano_id`), sin Historia, valoración ni encargo (CHECK `fichas_persona_sin_historia`),
 * y no generan apunte. La situación vigente es la última completada de cada tipo
 * (`vigentePara()`); actualizarla es una ficha nueva, corregirla es una versión (Versionable).
 *
 * historia_id se usa cuando la ficha se crea desde RegistrarValoracionPage
 * antes de existir una Valoracion formal (valoracion_id nullable).
 * TODO: vincular siempre a Valoracion cuando ese flujo esté completo.
 *
 * @property int $id
 * @property int|null $historia_id
 * @property int|null $ciudadano_id Persona dueña de una ficha de uso persona (SD-7); nulo en el resto.
 * @property int|null $valoracion_id
 * @property int $tipo_ficha_id
 * @property array<string, mixed>|null $schema_snapshot Copia del schema del TipoFicha al crear la ficha
 * @property int|null $profesional_id
 * @property array<string, mixed>|null $datos Cifrado.
 * @property list<string>|null $no_contesta campo_id que la persona no ha querido contestar (SD-9).
 * @property string|null $notas Cifrado.
 * @property bool $completada
 * @property int|null $derivacion_id Encargo profesional desde el que se registra (EA-5).
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Ficha extends Model
{
    use Auditable;
    use HasFactory;
    use Versionable;

    protected static function newFactory(): FichaFactory
    {
        return FichaFactory::new();
    }

    protected $table = 'fichas';

    /**
     * Registra la guarda de las fichas de la persona en cada guardado.
     *
     * @return void
     *
     * @throws DomainException Si una ficha de uso persona no tiene `ciudadano_id` o al revés (desde exigirCoherenciaDePersona()).
     */
    protected static function booted(): void
    {
        static::saving(fn (Ficha $ficha) => $ficha->exigirCoherenciaDePersona());
    }

    /**
     * SD-7, venga de donde venga: una ficha de un tipo de uso persona pertenece a un ciudadano
     * (y el CHECK impide que además cuelgue de una Historia), y una ficha con `ciudadano_id`
     * es de un tipo de uso persona. Así una ficha de la persona no puede entrar por la
     * valoración o la solicitud, ni una de valoración quedarse sin Historia.
     *
     * @return void
     *
     * @throws DomainException
     */
    public function exigirCoherenciaDePersona(): void
    {
        if (! $this->isDirty(['tipo_ficha_id', 'ciudadano_id'])) {
            return;
        }

        $dePersona = TipoFicha::whereKey($this->tipo_ficha_id)->value('uso') === 'persona';

        if ($dePersona && $this->ciudadano_id === null) {
            throw new DomainException('Una ficha de la persona tiene que pertenecer a un ciudadano.');
        }
        if (! $dePersona && $this->ciudadano_id !== null) {
            throw new DomainException('Solo las fichas de la persona pertenecen directamente a un ciudadano.');
        }
    }

    protected $fillable = [
        'historia_id',
        'ciudadano_id',
        'valoracion_id',
        'tipo_ficha_id',
        'schema_snapshot',
        'profesional_id',
        'datos',
        'no_contesta',
        'notas',
        'completada',
        'derivacion_id',
    ];

    protected $casts = [
        'datos' => 'encrypted:array',
        'notas' => 'encrypted',
        'no_contesta' => 'array',
        'schema_snapshot' => 'array',
        'completada' => 'boolean',
    ];

    /**
     * Encargo profesional desde el que se registró la ficha (EA-5).
     *
     * @return BelongsTo<Derivacion, $this>
     */
    public function derivacion(): BelongsTo
    {
        return $this->belongsTo(Derivacion::class, 'derivacion_id');
    }

    /**
     * Usuario que cumplimentó la ficha (autor en las vistas de lectura y en la tarjeta de la persona).
     *
     * @return BelongsTo<User, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Historia social a la que pertenece esta ficha (flujo directo, sin valoracion formal).
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * @return BelongsTo<Valoracion, $this>
     */
    public function valoracion(): BelongsTo
    {
        return $this->belongsTo(Valoracion::class, 'valoracion_id');
    }

    /**
     * @return BelongsTo<TipoFicha, $this>
     */
    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'tipo_ficha_id');
    }

    /**
     * Persona dueña de una ficha de uso persona (SD-7).
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Resuelve el ciudadano_id para la auditoría: el de la ficha de persona o el de su historia.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        if ($this->ciudadano_id !== null) {
            return (int) $this->ciudadano_id;
        }

        return $this->historia()->withoutGlobalScopes()->value('ciudadano_id');
    }

    /**
     * Si es una ficha de la persona (no de una Historia Social).
     *
     * @return bool
     */
    public function esDePersona(): bool
    {
        return $this->ciudadano_id !== null;
    }

    /**
     * Fichas de la persona de un ciudadano, de un tipo si se indica, de la más reciente a la más antigua.
     *
     * @param Builder<self> $query Consulta base.
     * @param int $ciudadanoId Persona.
     * @param int|null $tipoFichaId Tipo de ficha, o todos.
     *
     * @return Builder<self>
     */
    public function scopeDePersona(Builder $query, int $ciudadanoId, ?int $tipoFichaId = null): Builder
    {
        return $query
            ->where('ciudadano_id', $ciudadanoId)
            ->when($tipoFichaId !== null, fn (Builder $q) => $q->where('tipo_ficha_id', $tipoFichaId))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Situación vigente de una persona para un tipo de ficha: su última ficha completada (SD-8).
     * Los borradores no cuentan.
     *
     * @param int $ciudadanoId Persona.
     * @param int $tipoFichaId Tipo de ficha de uso persona.
     *
     * @return self|null
     */
    public static function vigentePara(int $ciudadanoId, int $tipoFichaId): ?self
    {
        return static::query()->dePersona($ciudadanoId, $tipoFichaId)->where('completada', true)->first();
    }

    /**
     * Campos del `schema_snapshot` con su valor, listos para mostrar en lectura (pantalla de la
     * ficha y resumen de la tarjeta de la persona). `texto` es el valor formateado con
     * `ValorCampo::mostrar()` (cadena vacía si no hay respuesta) y `no_contesta` marca los campos
     * que la persona no ha querido contestar (SD-9), que no es lo mismo que no haberse preguntado.
     *
     * @return list<array{id: string, etiqueta: string, tipo: string, valor: mixed, texto: string, unidad: string|null, obligatorio: bool, no_contesta: bool}>
     */
    public function camposConValor(): array
    {
        $campos = $this->schema_snapshot['campos'] ?? [];
        $datos = is_array($this->datos) ? $this->datos : [];
        $noContesta = is_array($this->no_contesta) ? $this->no_contesta : [];

        $resultado = [];
        foreach (is_array($campos) ? $campos : [] as $campo) {
            if (! is_array($campo) || ! is_string($campo['id'] ?? null)) {
                continue;
            }

            $valor = $datos[$campo['id']] ?? null;
            $resultado[] = [
                'id' => $campo['id'],
                'etiqueta' => (string) ($campo['etiqueta'] ?? $campo['id']),
                'tipo' => (string) ($campo['tipo'] ?? 'texto'),
                'valor' => $valor,
                'texto' => ValorCampo::mostrar($campo, $valor),
                'unidad' => $campo['unidad'] ?? null,
                'obligatorio' => (bool) ($campo['obligatorio'] ?? false),
                'no_contesta' => in_array($campo['id'], $noContesta, true),
            ];
        }

        return $resultado;
    }

    /**
     * Indica si la ficha es de la historia social dada, para no mostrar por id una ficha ajena.
     *
     * La ficha guarda su historia en historia_id (flujo de RegistrarValoracionPage).
     * Solo si historia_id es nulo se toma la historia de su valoración formal; si
     * tampoco hay valoración, la ficha no pertenece a ninguna historia.
     *
     * @param int $historiaId Identificador de la historia social.
     *
     * @return bool
     */
    public function perteneceAHistoria(int $historiaId): bool
    {
        if ($this->historia_id !== null) {
            return (int) $this->historia_id === $historiaId;
        }

        if ($this->valoracion_id === null) {
            return false;
        }

        // Sin scopes: la historia de la página ya se ha autorizado; aquí solo se compara el id
        $historiaDeValoracion = Valoracion::withoutGlobalScopes()
            ->whereKey($this->valoracion_id)
            ->value('historia_id');

        return $historiaDeValoracion !== null && (int) $historiaDeValoracion === $historiaId;
    }

    /**
     * Fichas de un tipo concreto para una historia, ordenadas de más reciente a más antigua.
     *
     * @param Builder<self> $query Consulta base.
     * @param int $historiaId Identificador de la historia social.
     * @param int $tipoFichaId Identificador del tipo de ficha.
     *
     * @return Builder<self>
     */
    public function scopeHistorialPara(Builder $query, int $historiaId, int $tipoFichaId): Builder
    {
        return $query
            ->where('historia_id', $historiaId)
            ->where('tipo_ficha_id', $tipoFichaId)
            ->orderByDesc('created_at');
    }

    /**
     * Genera el array de datos pre-rellenado para una nueva ficha.
     *
     * Reglas (ver `prerellenarConAvisos()`):
     * - Campo en schema actual Y en datos anteriores → se copia.
     * - Campo en schema actual pero no en datos anteriores → null (campo nuevo).
     * - Campo en datos anteriores pero no en schema actual → se descarta (retirado).
     * - Campo de catálogo cuya clave ya no está vigente → null (SD-8).
     *
     * @param self $fichaAnterior Ficha de referencia.
     * @param TipoFicha $tipoFicha TipoFicha con el schema actual.
     *
     * @return array<string, mixed>
     */
    public static function prerellenarDesde(self $fichaAnterior, TipoFicha $tipoFicha): array
    {
        return self::prerellenarConAvisos($fichaAnterior, $tipoFicha)['datos'];
    }

    /**
     * Pre-relleno con la lista de campos de catálogo que se han dejado vacíos porque su clave
     * ya no está activa o vigente, para avisar al profesional (SD-8).
     *
     * @param self $fichaAnterior Ficha de referencia.
     * @param TipoFicha $tipoFicha TipoFicha con el schema actual.
     *
     * @return array{datos: array<string, mixed>, avisos: list<array{campo_id: string, etiqueta: string, valor_anterior: string}>}
     */
    public static function prerellenarConAvisos(self $fichaAnterior, TipoFicha $tipoFicha): array
    {
        $camposSchema = $tipoFicha->schema['campos'] ?? [];
        $datosAnteriores = is_array($fichaAnterior->datos) ? $fichaAnterior->datos : [];
        $datos = [];
        $avisos = [];

        foreach (is_array($camposSchema) ? $camposSchema : [] as $campo) {
            $campoId = is_array($campo) ? ($campo['id'] ?? null) : null;
            if (! is_string($campoId) || $campoId === '') {
                continue;
            }

            $valor = $datosAnteriores[$campoId] ?? null;

            // Una clave que ya no se puede elegir no se arrastra: se deja vacía y se avisa
            if (($campo['tipo'] ?? null) === 'catalogo' && ValorCampo::tieneValor($valor)
                && ! CatalogoSistema::where('grupo', (string) ($campo['catalogo'] ?? ''))->where('clave', (string) $valor)->vigentes()->exists()) {
                $avisos[] = [
                    'campo_id' => $campoId,
                    'etiqueta' => (string) ($campo['etiqueta'] ?? $campoId),
                    'valor_anterior' => ValorCampo::mostrar($campo, $valor),
                ];
                $valor = null;
            }

            $datos[$campoId] = $valor;
        }

        return ['datos' => $datos, 'avisos' => $avisos];
    }

    /**
     * Resumen de auditoría (U3): «Registró la valoración X» o, en las fichas de la persona,
     * «Registró la ficha X».
     *
     * @param AccionAuditEnum $accion
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     *
     * @return string|null
     */
    public function resumenAuditoria(AccionAuditEnum $accion, array $antes, array $despues): ?string
    {
        $nombre = $this->tipoFicha()->value('nombre') ?? 'sin tipo';
        $que = $this->ciudadano_id !== null ? "la ficha {$nombre}" : "la valoración {$nombre}";

        return match ($accion) {
            AccionAuditEnum::Crear => "Registró {$que}",
            AccionAuditEnum::Editar => "Modificó {$que}",
            AccionAuditEnum::Eliminar => "Eliminó {$que}",
            default => null,
        };
    }
}
