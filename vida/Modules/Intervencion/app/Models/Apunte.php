<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Intervencion\Database\Factories\ApunteFactory;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;

/**
 * Apunte asociado a un Plan de Intervención.
 *
 * Nodo de conexión entre el plan y entidades heterogéneas: entrevistas,
 * documentos, derivaciones, seguimientos o anotaciones sin entidad vinculada.
 *
 * Tres niveles de visibilidad (docs/modulo-intervencion.md §7.2):
 * - privada: solo el autor. Regla con precedencia absoluta.
 * - profesionales: cualquier profesional con acceso a la historia.
 * - ciudadano: visible también en la carpeta ciudadana.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $autor_id
 * @property \Illuminate\Support\Carbon $fecha
 * @property TipoApunte $tipo
 * @property string|null $apuntable_type
 * @property int|null $apuntable_id
 * @property string|null $contenido
 * @property VisibilidadApunte $visibilidad
 */
class Apunte extends Model
{
    use HasFactory;

    protected static function newFactory(): ApunteFactory
    {
        return ApunteFactory::new();
    }

    protected $table = 'plan_apuntes';

    protected $fillable = [
        'plan_id',
        'autor_id',
        'fecha',
        'tipo',
        'apuntable_type',
        'apuntable_id',
        'contenido',
        'visibilidad',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'tipo'        => TipoApunte::class,
        'visibilidad' => VisibilidadApunte::class,
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<PlanDeIntervencion, Apunte>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * @return BelongsTo<User, Apunte>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /**
     * Entidad concreta vinculada (polimórfica).
     *
     * @return MorphTo
     */
    public function apuntable(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Devuelve los apuntes visibles para un usuario dado.
     *
     * Los apuntes privados solo son visibles para su autor.
     * Los apuntes de visibilidad profesionales o ciudadano son visibles para todos.
     *
     * @param Builder $query
     * @param int $usuarioId
     * @return Builder
     */
    public function scopeVisiblesParaUsuario(Builder $query, int $usuarioId): Builder
    {
        return $query->where(function (Builder $q) use ($usuarioId) {
            $q->where('visibilidad', '!=', VisibilidadApunte::Privada->value)
              ->orWhere('autor_id', $usuarioId);
        });
    }
}
