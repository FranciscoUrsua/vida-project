<?php

namespace Modules\Ciudadania\Models;

use App\Models\Ciudadano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación entre dos ciudadanos.
 *
 * El campo tipo_relacion almacena el slug del catálogo tipos_relacion.
 * La reciprocidad se gestiona automáticamente en los hooks de booted():
 * al crear un registro se crea el inverso, y al cerrar/eliminar se sincroniza.
 *
 * @property int         $id
 * @property int         $ciudadano_id
 * @property int         $ciudadano_relacionado_id
 * @property string      $tipo_relacion              Slug del catálogo tipos_relacion
 * @property string      $fecha_inicio
 * @property string|null $fecha_fin
 * @property string|null $observaciones
 */
class CiudadanoRelacion extends Model
{
    protected $table = 'ciudadano_relaciones';

    /** Previene recursión infinita al crear/modificar la relación recíproca. */
    private static bool $sincronizandoReciproca = false;

    protected $fillable = [
        'ciudadano_id',
        'ciudadano_relacionado_id',
        'tipo_relacion',
        'fecha_inicio',
        'fecha_fin',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (self $relacion): void {
            if (self::$sincronizandoReciproca) {
                return;
            }
            $relacion->crearReciprocaSiProcede();
        });

        static::updated(function (self $relacion): void {
            if (self::$sincronizandoReciproca || ! $relacion->wasChanged('fecha_fin')) {
                return;
            }
            $relacion->sincronizarFechaFinReciproca();
        });

        static::deleted(function (self $relacion): void {
            if (self::$sincronizandoReciproca) {
                return;
            }
            $relacion->eliminarReciproca();
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones Eloquent
    // -------------------------------------------------------------------------

    /**
     * Ciudadano origen de la relación.
     *
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Ciudadano relacionado al otro lado del vínculo.
     *
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadanoRelacionado(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_relacionado_id');
    }

    /**
     * Tipo de relación asociado al vínculo.
     *
     * @return BelongsTo<TipoRelacion, self>
     */
    public function tipoRelacion(): BelongsTo
    {
        return $this->belongsTo(TipoRelacion::class, 'tipo_relacion', 'slug');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra relaciones sin fecha de fin.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereNull('fecha_fin');
    }

    // -------------------------------------------------------------------------
    // Lógica de reciprocidad
    // -------------------------------------------------------------------------

    /**
     * Al crear una relación, crea automáticamente el registro inverso
     * usando el tipo recíproco del catálogo. Para tipos simétricos
     * (tipoRecíproco() devuelve $this) evita el duplicado si ya existe.
     */
    private function crearReciprocaSiProcede(): void
    {
        $tipo = TipoRelacion::where('slug', $this->tipo_relacion)->first();
        if (! $tipo) {
            return;
        }

        $reciproco = $tipo->tipoRecíproco();
        if (! $reciproco) {
            return;
        }

        $yaExiste = self::where('ciudadano_id', $this->ciudadano_relacionado_id)
            ->where('ciudadano_relacionado_id', $this->ciudadano_id)
            ->where('tipo_relacion', $reciproco->slug)
            ->whereNull('fecha_fin')
            ->exists();

        if ($yaExiste) {
            return;
        }

        self::$sincronizandoReciproca = true;
        try {
            self::create([
                'ciudadano_id'             => $this->ciudadano_relacionado_id,
                'ciudadano_relacionado_id' => $this->ciudadano_id,
                'tipo_relacion'            => $reciproco->slug,
                'fecha_inicio'             => $this->fecha_inicio,
                'fecha_fin'                => $this->fecha_fin,
                'observaciones'            => $this->observaciones,
            ]);
        } finally {
            self::$sincronizandoReciproca = false;
        }
    }

    /**
     * Cuando se cierra una relación (fecha_fin), sincroniza la fecha en el
     * registro recíproco para mantener la coherencia bidireccional.
     */
    private function sincronizarFechaFinReciproca(): void
    {
        $tipo = TipoRelacion::where('slug', $this->tipo_relacion)->first();
        if (! $tipo) {
            return;
        }

        $reciproco = $tipo->tipoRecíproco();
        if (! $reciproco) {
            return;
        }

        $reciproca = self::where('ciudadano_id', $this->ciudadano_relacionado_id)
            ->where('ciudadano_relacionado_id', $this->ciudadano_id)
            ->where('tipo_relacion', $reciproco->slug)
            ->first();

        if (! $reciproca) {
            return;
        }

        self::$sincronizandoReciproca = true;
        try {
            $reciproca->update(['fecha_fin' => $this->fecha_fin]);
        } finally {
            self::$sincronizandoReciproca = false;
        }
    }

    /**
     * Al eliminar una relación, elimina también el registro inverso.
     */
    private function eliminarReciproca(): void
    {
        $tipo = TipoRelacion::where('slug', $this->tipo_relacion)->first();
        if (! $tipo) {
            return;
        }

        $reciproco = $tipo->tipoRecíproco();
        if (! $reciproco) {
            return;
        }

        self::$sincronizandoReciproca = true;
        try {
            self::where('ciudadano_id', $this->ciudadano_relacionado_id)
                ->where('ciudadano_relacionado_id', $this->ciudadano_id)
                ->where('tipo_relacion', $reciproco->slug)
                ->delete();
        } finally {
            self::$sincronizandoReciproca = false;
        }
    }
}
