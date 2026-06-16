<?php

namespace Modules\Ciudadania\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Modules\Ciudadania\Database\Factories\UnidadConvivenciaFactory;

/**
 * Unidad de convivencia — grupo de ciudadanos que comparten domicilio.
 *
 * Entidad con identidad propia: tiene domicilio, fechas de vigencia y
 * composición propia. Es la unidad de referencia para el cálculo de
 * prestaciones económicas y para la intervención familiar.
 *
 * El domicilio se cifra en aplicación (AES-256 vía Crypt). La UC no tiene
 * titular; los planes y prestaciones se asignan a personas concretas o a la
 * UC como entidad (ver docs/modulo-intervencion.md sección 5).
 *
 * @property int $id
 * @property string|null $domicilio Cifrado
 * @property float|null $latitud
 * @property float|null $longitud
 * @property \Illuminate\Support\Carbon $fecha_constitucion
 * @property \Illuminate\Support\Carbon|null $fecha_disolucion
 * @property string|null $observaciones
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class UnidadConvivencia extends Model
{
    /** @use HasFactory<UnidadConvivenciaFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'unidades_convivencia';

    protected $fillable = [
        'domicilio',
        'latitud',
        'longitud',
        'fecha_constitucion',
        'fecha_disolucion',
        'observaciones',
    ];

    protected $casts = [
        'fecha_constitucion' => 'date',
        'fecha_disolucion'   => 'date',
        'latitud'            => 'decimal:7',
        'longitud'           => 'decimal:7',
    ];

    // -------------------------------------------------------------------------
    // Cifrado en aplicación
    // -------------------------------------------------------------------------

    /**
     * El domicilio se cifra antes de persistir y se descifra al recuperar.
     */
    protected function domicilio(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Todas las membresías (históricas y activas).
     *
     * @return HasMany<UnidadConvivenciaMiembro, self>
     */
    public function miembros(): HasMany
    {
        return $this->hasMany(UnidadConvivenciaMiembro::class, 'unidad_convivencia_id');
    }

    /**
     * Solo miembros activos (sin fecha_fin).
     *
     * @return HasMany<UnidadConvivenciaMiembro, self>
     */
    public function miembrosActivos(): HasMany
    {
        return $this->miembros()->whereNull('fecha_fin');
    }

    /**
     * Miembros activos con residencia verificada.
     *
     * @return HasMany<UnidadConvivenciaMiembro, self>
     */
    public function miembrosVerificados(): HasMany
    {
        return $this->miembrosActivos()->where('verificado', true);
    }

    /**
     * Ciudadanos que pertenecen o han pertenecido a esta UC.
     *
     * @return BelongsToMany<\App\Models\Ciudadano, self>
     */
    public function ciudadanos(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Ciudadano::class,
            'unidad_convivencia_miembros',
            'unidad_convivencia_id',
            'ciudadano_id'
        )->withPivot(['fecha_inicio', 'fecha_fin', 'fuente', 'verificado',
                      'verificado_por', 'verificado_en'])
         ->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // Métodos de negocio
    // -------------------------------------------------------------------------

    /**
     * Indica si la unidad está disuelta (fecha_disolucion en el pasado).
     */
    public function estaDisuelta(): bool
    {
        return $this->fecha_disolucion !== null
            && $this->fecha_disolucion->isPast();
    }

    /**
     * Añade un ciudadano como miembro activo.
     *
     * @throws \LogicException Si el ciudadano ya es miembro activo.
     */
    public function agregarMiembro(
        int $ciudadanoId,
        string $fuente = 'manual',
        ?\DateTimeInterface $fechaInicio = null
    ): UnidadConvivenciaMiembro {
        $yaActivo = $this->miembrosActivos()
            ->where('ciudadano_id', $ciudadanoId)
            ->exists();

        if ($yaActivo) {
            throw new \LogicException(
                "El ciudadano #{$ciudadanoId} ya es miembro activo de esta unidad de convivencia."
            );
        }

        return $this->miembros()->create([
            'ciudadano_id'  => $ciudadanoId,
            'fecha_inicio'  => $fechaInicio ?? now()->toDateString(),
            'fuente'        => $fuente,
            'verificado'    => false,
        ]);
    }

    /**
     * Da de baja a un miembro (fecha_fin = hoy o la fecha indicada).
     *
     * @throws \LogicException Si el ciudadano no es miembro activo.
     */
    public function darDeBajaMiembro(
        int $ciudadanoId,
        ?\DateTimeInterface $fechaFin = null
    ): void {
        $miembro = $this->miembrosActivos()
            ->where('ciudadano_id', $ciudadanoId)
            ->first();

        if (! $miembro) {
            throw new \LogicException(
                "El ciudadano #{$ciudadanoId} no es miembro activo de esta unidad de convivencia."
            );
        }

        $miembro->update(['fecha_fin' => $fechaFin ?? now()->toDateString()]);
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    protected static function newFactory(): UnidadConvivenciaFactory
    {
        return UnidadConvivenciaFactory::new();
    }
}
