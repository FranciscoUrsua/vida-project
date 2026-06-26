<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Agenda\Database\Factories\PerfilHorarioProfesionalFactory;
use Modules\Centro\Models\Centro;

/**
 * Perfil horario de un profesional en un centro concreto.
 *
 * Define la jornada semanal y las franjas habituales de trabajo. Un profesional
 * puede tener perfiles en varios centros (profesionales itinerantes). Solo puede
 * existir un perfil activo por combinación (usuario_id, centro_id) en cada momento;
 * este constraint se valida en capa de aplicación, no en base de datos.
 *
 * @property int $id
 * @property int $usuario_id
 * @property int $centro_id
 * @property float $jornada_semanal_horas
 * @property array $horario_habitual
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 * @property bool $activo
 * @property string|null $notas
 */
class PerfilHorarioProfesional extends Model
{
    /** @use HasFactory<PerfilHorarioProfesionalFactory> */
    use HasFactory;

    protected static function newFactory(): PerfilHorarioProfesionalFactory
    {
        return PerfilHorarioProfesionalFactory::new();
    }

    /**
     * Valida que no exista ya un perfil activo para la misma combinación
     * (usuario_id, centro_id) antes de persistir.
     *
     * La restricción se aplica en capa de aplicación (no en BD) porque un
     * profesional puede tener varios perfiles históricos inactivos para el
     * mismo centro, y solo uno vigente y activo a la vez.
     */
    protected static function booted(): void
    {
        static::saving(function (PerfilHorarioProfesional $perfil) {
            if (! $perfil->activo) {
                return;
            }

            $existe = static::where('usuario_id', $perfil->usuario_id)
                ->where('centro_id', $perfil->centro_id)
                ->where('activo', true)
                ->when($perfil->exists, fn (Builder $q) => $q->where('id', '!=', $perfil->id))
                ->exists();

            if ($existe) {
                throw new LogicException(
                    'Ya existe un perfil horario activo para este profesional en este centro.'
                );
            }
        });
    }

    protected $table = 'perfiles_horario_profesional';

    protected $guarded = [];

    protected $casts = [
        'horario_habitual' => 'array',
        'jornada_semanal_horas' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'activo' => 'boolean',
    ];

    /**
     * Profesional al que pertenece el perfil horario.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Centro al que pertenece el perfil horario.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Líneas de cuadrante asociadas al perfil.
     *
     * @return HasMany<LineaCuadrante, $this>
     */
    public function lineasCuadrante(): HasMany
    {
        return $this->hasMany(LineaCuadrante::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Filtra perfiles activos.
     *
     * @param Builder<PerfilHorarioProfesional> $query
     *
     * @return Builder<PerfilHorarioProfesional>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Filtra perfiles de un centro.
     *
     * @param Builder<PerfilHorarioProfesional> $query
     *
     * @return Builder<PerfilHorarioProfesional>
     */
    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    /**
     * Filtra perfiles vigentes en la fecha actual.
     *
     * @param Builder<PerfilHorarioProfesional> $query
     *
     * @return Builder<PerfilHorarioProfesional>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query->where('vigente_desde', '<=', $hoy)
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy);
            });
    }
}
