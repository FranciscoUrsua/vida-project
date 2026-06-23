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
 * Apunte de una Historia Social.
 *
 * Nodo de conexión entre la Historia Social y entidades heterogéneas: entrevistas,
 * documentos, derivaciones, seguimientos o anotaciones sin entidad vinculada.
 * El apunte pertenece a la Historia Social directamente; el plan es un vínculo
 * opcional que se establece cuando existe un plan en curso.
 *
 * Tres niveles de visibilidad (docs/modulo-intervencion.md §7.2):
 * - privada: solo el autor. Regla con precedencia absoluta.
 * - profesionales: cualquier profesional con acceso a la historia.
 * - ciudadano: visible también en la carpeta ciudadana.
 *
 * @property int         $id
 * @property int         $historia_id
 * @property int|null    $plan_id     Vínculo opcional al plan en curso cuando se creó el apunte.
 * @property int         $autor_id
 * @property Carbon      $fecha
 * @property TipoApunte  $tipo
 * @property string|null $apuntable_type
 * @property int|null    $apuntable_id
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
     * El filtro se aplica directamente vía historia_id → historias_sociales.unidad_organizativa_id.
     * Los apuntes privados del usuario autenticado siempre son visibles para él.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ambito_uo', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            $usuario = auth()->user();

            if ($usuario->hasRole('adm_sistema')) {
                return;
            }

            $uoIds = $usuario->uoSubtreeIds();

            $builder->where(function (Builder $q) use ($uoIds, $usuario) {
                // Condición A: la historia del apunte pertenece al ámbito de UO
                $q->whereIn('plan_apuntes.historia_id', function ($sub) use ($uoIds) {
                    $sub->select('historias_sociales.id')
                        ->from('historias_sociales')
                        ->whereIn('historias_sociales.unidad_organizativa_id', $uoIds)
                        ->whereNull('historias_sociales.deleted_at');
                });

                // Condición B: el apunte es del propio usuario (privados solo visibles para el autor)
                $q->orWhere('plan_apuntes.autor_id', $usuario->id);
            });
        });
    }

    protected $fillable = [
        'historia_id',
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
     * Historia Social a la que pertenece el apunte.
     *
     * @return BelongsTo<HistoriaSocial, self>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * Plan de intervención en curso cuando se creó el apunte (opcional).
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * Autor del apunte.
     *
     * @return BelongsTo<User, self>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /**
     * Entidad concreta vinculada (polimórfica).
     */
    public function apuntable(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Métodos
    // -------------------------------------------------------------------------

    /**
     * ID del ciudadano titular de la historia social asociada al apunte.
     * Resuelve directamente vía historia_id para evitar dependencia del plan.
     */
    public function getCiudadanoId(): ?int
    {
        return HistoriaSocial::withoutGlobalScopes()
            ->where('id', $this->historia_id)
            ->value('ciudadano_id');
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
     * @param int           $usuarioId ID del usuario que consulta.
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
