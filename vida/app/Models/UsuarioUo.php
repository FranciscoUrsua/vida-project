<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Intervencion\Services\Asignacion\SalidaCentroBloqueada;
use Modules\Intervencion\Services\Asignacion\SalidaCentroService;

/**
 * @use HasFactory<Factory<self>>
 *
 * Adscripción de un usuario a una Unidad Organizativa.
 *
 * Registra en qué UO opera el usuario y con qué tipo de vínculo laboral.
 * Tiene fechas de vigencia para mantener el historial completo
 * (principio 4.2: el pasado es inmutable).
 *
 * Los roles del usuario son globales (Spatie model_has_roles) y determinan
 * qué puede hacer; la adscripción determina dónde puede hacerlo con
 * acceso completo (nivel 1). En UOs ajenas solo puede consultar (nivel 2).
 *
 * Una adscripción no se borra: se cierra con fecha de fin. Cerrar la de la UO de
 * un centro exige que el profesional no tenga casos ni citas pendientes en él
 * (RN-21 de docs/modulo-tipo-centro.md); se comprueba aquí para que ningún
 * camino (Filament, servicios) se lo salte, y cierra sus perfiles horarios en
 * ese centro.
 *
 * @property int $id
 * @property int $usuario_id
 * @property int $unidad_organizativa_id
 * @property string $tipo_vinculo
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UsuarioUo extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $table = 'usuario_uo';

    protected $fillable = [
        'usuario_id',
        'unidad_organizativa_id',
        'tipo_vinculo',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    /**
     * Restricciones del ciclo de vida: sin borrado y salida de centro con RN-21.
     *
     * @return void
     *
     * @throws SalidaCentroBloqueada Al cerrar con casos o citas pendientes en el centro.
     * @throws LogicException Al borrar.
     */
    protected static function booted(): void
    {
        static::updating(function (UsuarioUo $adscripcion): void {
            if ($adscripcion->seCierra()) {
                app(SalidaCentroService::class)->exigirSalidaLibre($adscripcion);
            }
        });

        static::updated(function (UsuarioUo $adscripcion): void {
            if ($adscripcion->wasChanged('fecha_fin') && $adscripcion->fecha_fin !== null) {
                app(SalidaCentroService::class)->cerrarPerfilesHorarios($adscripcion);
            }
        });

        // El historial de adscripciones es inmutable (principio 4.2): se cierra, no se borra
        static::deleting(function (): void {
            throw new LogicException('Las adscripciones no se borran: se cierran con fecha de fin.');
        });
    }

    /**
     * Si el cambio pendiente de guardar adelanta el fin de la adscripción
     * (de abierta a cerrada, o a una fecha anterior), que es una salida.
     *
     * @return bool
     */
    public function seCierra(): bool
    {
        if (! $this->isDirty('fecha_fin') || $this->fecha_fin === null) {
            return false;
        }

        $anterior = $this->getOriginal('fecha_fin');

        return $anterior === null || $this->fecha_fin->lt($anterior);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * @param Builder<UsuarioUo> $consulta
     *
     * @return Builder<UsuarioUo>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where(function (Builder $q) {
            $q->whereNull('fecha_fin')
                ->orWhere('fecha_fin', '>=', Carbon::today());
        });
    }
}
