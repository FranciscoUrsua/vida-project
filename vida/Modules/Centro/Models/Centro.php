<?php

namespace Modules\Centro\Models;

use App\Traits\HasValidatableAddress;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Models\Distrito;
use Modules\Centro\Models\Director;
use Modules\Centro\Models\Profesional; // Asumiendo que existe para personal

class Centro extends Model
{
    use HasFactory, SoftDeletes, HasValidatableAddress, Versionable;

    protected $table = 'centros';

    protected $fillable = [
        'tipo_centro_id',
        'nombre',
        'estado',
        'street_type',
        'street_name',
        'street_number',
        'additional_info',
        'postal_code',
        'distrito_id',
        'city',
        'country',
        'telefono',
        'email_contacto',
        'director_id',
        'personal', // JSON array de IDs
        'datos_especificos', // JSON para overrides dinámicos
        'lat',
        'lng',
        'direccion_validada',
        'formatted_address',
    ];

    protected $casts = [
        'tipo_centro_id' => 'integer',
        'estado' => 'string', // Enum: activo, inactivo
        'postal_code' => 'string',
        'city' => 'string',
        'country' => 'string',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'direccion_validada' => 'boolean',
        'personal' => 'array', // Array de IDs para personal
        'datos_especificos' => 'array', // JSON dinámico
    ];

    /**
     * Relación con el tipo de centro (hereda prestaciones, etc.).
     */
    public function tipoCentro(): BelongsTo
    {
        return $this->belongsTo(TipoCentro::class, 'tipo_centro_id');
    }

    /**
     * Relación con el distrito.
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class);
    }

    /**
     * Relación con el director (FK a directores).
     */
    public function director(): BelongsTo
    {
        return $this->belongsTo(Director::class, 'director_id');
    }

    /**
     * Accesor para personal: Carga Profesionales basados en el array JSON de IDs.
     * Si prefieres tabla pivot, cambia a belongsToMany.
     */
    public function getProfesionalesAttribute()
    {
        if (empty($this->personal)) {
            return collect();
        }
        return Profesional::whereIn('id', $this->personal)->get();
    }

    /**
     * Setter para personal: Asegura que sea array de IDs.
     */
    public function setPersonalAttribute($value)
    {
        $this->attributes['personal'] = is_array($value) ? $value : json_decode($value, true) ?? [];
    }

    /**
     * Relación many-to-many con prestaciones (heredadas del tipo, con overrides posibles via datos_especificos).
     * Asume tabla pivot 'centro_prestacion'; ajusta si es via TipoCentro.
     */
    public function prestaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Prestacion::class, // Asumiendo modelo global de Prestaciones
            'centro_prestacion',
            'centro_id',
            'prestacion_id'
        )->withTimestamps();
    }

    /**
     * Scope para centros activos (basado en estado, no en director por simplicidad).
     * Si quieres filtrar por director activo, ajusta.
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para búsqueda por distrito.
     */
    public function scopeEnDistrito($query, $distritoId)
    {
        return $query->where('distrito_id', $distritoId);
    }

    /**
     * Método para obtener prestaciones efectivas: del tipo + overrides.
     */
    public function prestacionesEfectivas()
    {
        $prestacionesDelTipo = $this->tipoCentro->prestaciones ?? collect();
        $overrides = collect($this->datos_especificos['prestaciones'] ?? []);
        return $prestacionesDelTipo->merge($overrides);
    }
}
