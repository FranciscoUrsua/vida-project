<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SocialUser; // Asume modelo auxiliar en app/Models para social_users
use Modules\Centro\Models\Centro; // Asume modelo auxiliar en app/Models para centros
use App\Models\Profesional; // Asume modelo auxiliar en app/Models para profesionales
//use App\Modules\Common\Traits\HasDocuments; // Para docs futuros (morphMany Documento)
//use App\Modules\Common\Traits\Encryptable; // Para encriptación en metadatos si sensibles

class Historia extends Model implements Auditable
{
    use HasFactory, HasDocuments, Encryptable, OwenIt\Auditing\Auditable;

    protected $fillable = [
        'social_user_id',
        'profesional_id', // Nuevo: quién abre y gestiona el ciclo
        'estado',
        'fecha_apertura',
        'centro_id',
        'metadatos',
    ];

    protected $casts = [
        'fecha_apertura' => 'date',
        'metadatos' => 'array',
    ];

    /**
     * Relaciones clave (preparadas para el ciclo: valoraciones, planes, herramientas).
     */
    public function socialUser(): BelongsTo
    {
        return $this->belongsTo(SocialUser::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(Valoracion::class);
    }

    public function planes(): HasMany
    {
        return $this->hasMany(PlanIntervencion::class);
    }

    public function herramientas(): HasMany
    {
        return $this->hasMany(Herramienta::class);
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class); // Indirecto via planes, pero directo si needed
    }

    /**
     * Scopes útiles (e.g., para dashboards: abiertas por distrito o profesional).
     */
    public function scopeAbiertas($query)
    {
        return $query->where('estado', 'abierto');
    }

    public function scopePorDistrito($query, array $distritos)
    {
        return $query->where(function ($q) use ($distritos) {
            $q->whereJsonContains('metadatos->distritos', $distritos[0])
              ->orWhereJsonContains('metadatos->distritos', $distritos);
        });
    }

    public function scopePorProfesional($query, int $profesionalId)
    {
        return $query->where('profesional_id', $profesionalId);
    }

    /**
     * Accesor para estado legible (alineado con Plan pág. 59: seguimiento).
     */
    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'abierto' => 'Abierto (Valoración Inicial)',
            'seguimiento' => 'En Seguimiento (Planes Activos)',
            'alta' => 'Alta (Cerrado)',
            default => 'Desconocido',
        };
    }
}
