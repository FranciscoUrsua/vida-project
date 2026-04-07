<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property \Illuminate\Support\Carbon $vigente_desde
 * @property \Illuminate\Support\Carbon|null $vigente_hasta
 * @property bool $activo
 * @property string|null $notas
 */
class PerfilHorarioProfesional extends Model
{
    protected $table = 'perfiles_horario_profesional';

    protected $guarded = [];

    protected $casts = [
        'horario_habitual'      => 'array',
        'jornada_semanal_horas' => 'decimal:2',
        'vigente_desde'         => 'date',
        'vigente_hasta'         => 'date',
        'activo'                => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function lineasCuadrante(): HasMany
    {
        return $this->hasMany(LineaCuadrante::class, 'usuario_id', 'usuario_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query->where('vigente_desde', '<=', $hoy)
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy);
            });
    }
}
