<?php

namespace Modules\Atencion\Models;

use App\Models\Ciudadano;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Atencion\Database\Factories\RegistroAtencionFactory;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Registro de una interacción con un ciudadano que no implica apertura de Historia Social.
 *
 * Cuelga del Ciudadano (no de HistoriaSocial). Permite documentar consultas
 * de información, orientaciones y contactos informales. Ver docs/modulo-atencion.md.
 *
 * Reglas de negocio en código:
 *   - Tipo `informacion`: profesional_id obligatorio.
 *   - Tipo `actividad`: origen_tipo y origen_id obligatorios.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property string $tipo informacion | actividad | contacto
 * @property Carbon $fecha
 * @property int|null $profesional_id
 * @property int|null $prestacion_id
 * @property string|null $demanda
 * @property string|null $respuesta
 * @property string $origen manual | sistema
 * @property string|null $origen_tipo Clase del modelo origen (polimórfico manual)
 * @property int|null $origen_id ID del modelo origen
 * @property int|null $cita_generada_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RegistroAtencion extends Model
{
    use HasFactory;

    /** @var string Tabla de la base de datos */
    protected $table = 'registros_atencion';

    /**
     * Devuelve la factory específica del módulo Atencion.
     *
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RegistroAtencionFactory::new();
    }

    /** @var list<string> Campos asignables en masa */
    protected $fillable = [
        'ciudadano_id',
        'tipo',
        'fecha',
        'profesional_id',
        'prestacion_id',
        'demanda',
        'respuesta',
        'origen',
        'origen_tipo',
        'origen_id',
        'cita_generada_id',
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'fecha' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Reglas de negocio
    // -------------------------------------------------------------------------

    /**
     * Valida restricciones de negocio antes de persistir.
     *
     *
     * @throws \LogicException Si se viola una restricción de negocio del tipo.
     */
    protected static function booted(): void
    {
        static::saving(function (self $registro): void {
            if ($registro->tipo === 'informacion' && empty($registro->profesional_id)) {
                throw new \LogicException(
                    'Los registros de atención de tipo información requieren un profesional identificado.'
                );
            }

            if ($registro->tipo === 'actividad'
                && (empty($registro->origen_tipo) || empty($registro->origen_id))) {
                throw new \LogicException(
                    'Los registros de atención de tipo actividad requieren origen_tipo y origen_id.'
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Ciudadano titular de la atención.
     *
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Profesional que registró la atención (null para registros generados por sistema).
     *
     * @return BelongsTo<User, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Prestación del catálogo vinculada a la atención.
     *
     * @return BelongsTo<Prestacion, self>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    /**
     * Modelo que originó este registro (polimórfico manual).
     * Devuelve null si no hay origen o la clase no existe.
     */
    public function modeloOrigen(): ?Model
    {
        if (! $this->origen_tipo || ! $this->origen_id) {
            return null;
        }

        if (! class_exists($this->origen_tipo)) {
            return null;
        }

        return $this->origen_tipo::find($this->origen_id);
    }

    // -------------------------------------------------------------------------
    // Métodos de presentación
    // -------------------------------------------------------------------------

    /**
     * Texto de resumen para la línea del historial (máximo 80 caracteres).
     */
    public function resumenHistorial(): string
    {
        return match ($this->tipo) {
            'informacion' => str($this->demanda ?? '')->limit(80)->toString(),
            'actividad' => $this->modeloOrigen()?->nombre ?? 'Actividad',
            'contacto' => str($this->demanda ?? '')->limit(80)->toString(),
            default => '—',
        };
    }

    // -------------------------------------------------------------------------
    // Métodos de fábrica
    // -------------------------------------------------------------------------

    /**
     * Crea un RegistroAtencion desde la inscripción en una actividad.
     * Llamado por el módulo Centro (fase 2) — no requiere profesional.
     *
     * @param int $ciudadanoId ID del ciudadano.
     * @param string $origenTipo Clase del modelo origen.
     * @param int $origenId ID del modelo origen.
     * @param DateTimeInterface $fecha Fecha de la actividad.
     */
    public static function crearDesdeOrigen(
        int $ciudadanoId,
        string $origenTipo,
        int $origenId,
        DateTimeInterface $fecha
    ): self {
        return static::create([
            'ciudadano_id' => $ciudadanoId,
            'tipo' => 'actividad',
            'fecha' => $fecha->format('Y-m-d'),
            'origen' => 'sistema',
            'origen_tipo' => $origenTipo,
            'origen_id' => $origenId,
        ]);
    }
}
