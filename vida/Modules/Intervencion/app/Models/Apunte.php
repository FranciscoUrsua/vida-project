<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
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
 * @property Carbon $fecha
 * @property TipoApunte $tipo
 * @property string|null $apuntable_type
 * @property int|null $apuntable_id
 * @property string|null $contenido
 * @property VisibilidadApunte $visibilidad
 */
class Apunte extends Model
{
    use Auditable;
    use HasFactory;

    protected static function newFactory(): ApunteFactory
    {
        return ApunteFactory::new();
    }

    protected $table = 'plan_apuntes';

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Registra el Global Scope de ámbito de UO para filtrado automático.
     *
     * El filtro se aplica vía plan_id → planes_intervencion.historia_id →
     * historias_sociales.unidad_organizativa_id. Este modelo usa una subquery
     * de dos niveles en lugar del AmbitoUoScope genérico.
     *
     * Para los apuntes privados (visibilidad=Privada), el acceso del usuario
     * actual se garantiza añadiendo la condición OR autor_id = :id.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ambito_uo', function (Builder $builder) {
            // Sin usuario autenticado → no filtrar
            if (! auth()->check()) {
                return;
            }

            $usuario = auth()->user();

            // adm_sistema → acceso global
            if ($usuario->hasRole('adm_sistema')) {
                return;
            }

            $uoIds = $usuario->uoSubtreeIds();

            $builder->where(function (Builder $q) use ($uoIds, $usuario) {
                // Condición A: el plan del apunte pertenece a una Historia en el ámbito de UO
                $q->whereIn('plan_apuntes.plan_id', function ($sub) use ($uoIds) {
                    $sub->select('planes_intervencion.id')
                        ->from('planes_intervencion')
                        ->join('historias_sociales', 'historias_sociales.id', '=', 'planes_intervencion.historia_id')
                        ->whereIn('historias_sociales.unidad_organizativa_id', $uoIds)
                        ->whereNull('planes_intervencion.deleted_at')
                        ->whereNull('historias_sociales.deleted_at');
                });

                // Condición B: el apunte es del propio usuario (privados solo visibles para el autor)
                $q->orWhere('plan_apuntes.autor_id', $usuario->id);
            });
        });
    }

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
        'fecha' => 'date',
        'tipo' => TipoApunte::class,
        'visibilidad' => VisibilidadApunte::class,
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * ID del ciudadano titular de la historia social asociada al apunte.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        // Se evitan todos los global scopes (AmbitoUoScope en Plan e Historia)
        // porque esta resolución es interna del sistema de auditoría.
        $historiaId = $this->plan()->withoutGlobalScopes()->value('historia_id');

        if (! $historiaId) {
            return null;
        }

        return HistoriaSocial::withoutGlobalScopes()
            ->where('id', $historiaId)
            ->value('ciudadano_id');
    }

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
     * @param Builder<self> $query
     * @param int $usuarioId ID del usuario que consulta.
     *
     * @return Builder<self>
     */
    public function scopeVisiblesParaUsuario(Builder $query, int $usuarioId): Builder
    {
        return $query->where(function (Builder $q) use ($usuarioId) {
            $q->where('visibilidad', '!=', VisibilidadApunte::Privada->value)
                ->orWhere('autor_id', $usuarioId);
        });
    }
}
