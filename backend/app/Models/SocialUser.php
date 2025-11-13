<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;
use App\Traits\HasValidatableAddress;
use App\Traits\ValidatesIdentification;

class SocialUser extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, Auditable, HasValidatableAddress, ValidatesIdentification;

    protected $table = 'social_users';

    protected $fillable = [
        'first_name',
        'last_name1',
        'last_name2',
        'identificacion_desconocida',
        'identificacion_historial',
        'tipo_documento',
        'numero_id',
        'situacion_administrativa',
        'pais_origen_id',
        'fecha_nacimiento',
        'sexo',
        'estado_civil',
        'lugar_empadronamiento',
        'correo',
        'telefono',
        'centro_adscripcion_id',
        'profesional_referencia_id',
        'tiene_representante_legal',
        'representante_legal_id',
        'requiere_permiso_especial',
        'address_type_id',
        'street_type',
        'street_name',
        'street_number',
        'additional_info',
        'postal_code',
        'city',
        'lat',
        'lng',
        'direccion_validada',
        'formatted_address',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'identificacion_historial' => 'array',
        'requiere_permiso_especial' => 'boolean',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'direccion_validada' => 'boolean',
    ];

    protected $auditable = [
        'first_name',
        'last_name1',
        'last_name2',
        'identificacion_desconocida',
        'identificacion_historial',
        'tipo_documento',
        'numero_id',
        'fecha_nacimiento',
        'correo',
        'telefono',
        'lat', 'lng',
        'formatted_address',
    ];

    protected static function boot()
    {
        parent::boot();
        static::bootValidatesIdentification(); // Llama explícito si boot custom
    }

    // Relaciones
    public function centro()
    {
        return $this->belongsTo(Centro::class, 'centro_adscripcion_id');
    }

    public function prestaciones()
    {
        return $this->belongsToMany(Prestacion::class, 'prestacion_social_user')
                    ->withTimestamps()
                    ->withPivot('fecha_fin');  // Incluye fecha_fin del pivot
    }


    public function profesionalReferencia()
    {
        return $this->belongsTo(Professional::class, 'profesional_referencia_id');
    }

    public function representanteLegal()
    {
        return $this->belongsTo(self::class, 'representante_legal_id');
    }

    // Scope para RUU (subconjunto, ej: con tarjeta sanitaria)
    public function scopeRuu($query)
    {
        return $query->whereNotNull('numero_tarjeta_sanitaria');
    }

    // Método para esMenorDeEdad (igual que en Ruu)
    public function esMenorDeEdad()
    {
        return $this->fecha_nacimiento->gt(now()->subYears(18));
    }

    public function paisOrigen()
    {
        return $this->belongsTo(Country::class, 'pais_origen_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function getFullIdAttribute()
    {
        return $this->tipo_documento ? $this->tipo_documento . '-' . $this->numero_id : null;
    }


}
