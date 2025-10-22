<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Ruu extends Model implements AuditableContract
{
    use HasFactory, Auditable;

    protected $table = 'ruu';

    protected $fillable = [
        'first_name',  // Required en controller
        'last_name1',  // Required
        'last_name2',  // Nullable
        'dni_nie_pasaporte',  // Required para Ruu
        'situacion_administrativa',
        'numero_tarjeta_sanitaria',
        'pais_origen',
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
        'identificacion_desconocida',
        'address_type_id',  // Nuevo para domicilio
        'street_name',
        'street_number',
        'additional_info',
        'postal_code',
        'city',
        'region',
        'country',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'requiere_permiso_especial' => 'boolean',
        'identificacion_desconocida' => 'boolean',
    ];

    protected $auditable = [
        'first_name',
        'last_name1',
        'last_name2',
        'dni_nie_pasaporte',
        'fecha_nacimiento',
        'correo',
        'telefono',
        'street_name', 'postal_code', 'city', 'region', 'country',
    ];

    // Relaciones
    public function addressType()
    {
        return $this->belongsTo(AddressType::class);
    }

    public function centro()
    {
        return $this->belongsTo(Centro::class, 'centro_adscripcion_id');
    }

    public function profesionalReferencia()
    {
        return $this->belongsTo(Professional::class, 'profesional_referencia_id');
    }

    public function representanteLegal()
    {
        return $this->belongsTo(self::class, 'representante_legal_id');
    }

    public function hsu()
    {
        return $this->hasOne(Hsu::class, 'ruu_user_id');
    }

    // Scope para permiso especial (menor de edad)
    public function scopeRequiriendoPermisoEspecial($query)
    {
        return $query->where('requiere_permiso_especial', true);
    }

    public function getFullNameAttribute()
    {
        $names = array_filter([$this->first_name, $this->last_name1, $this->last_name2]);
        return implode(' ', $names) ?: 'Usuario desconocido';
    }

    // Método para calcular si es menor (actualizar en save o observer)
    public function esMenorDeEdad()
    {
        return $this->fecha_nacimiento->gt(now()->subYears(18));
    }
}
