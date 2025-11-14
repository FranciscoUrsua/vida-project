<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasValidatableAddress; // Trait para validación de dirección (geocoding y bounds)
use App\Traits\ValidatesIdentification; // Trait para validación de ID (DNI/NIE/Pasaporte + checksum)
use App\Traits\Versionable; // Trait para versionado
use OwenIt\Auditing\Contracts\Auditable;

class SocialUser extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasValidatableAddress, ValidatesIdentification, Versionable, \OwenIt\Auditing\Auditable; // Traits activan validaciones en saving

    protected $fillable = [
        'first_name',
        'last_name1',
        'last_name2',
        'situacion_administrativa',
        'numero_tarjeta_sanitaria',
        'pais_origen_id',
        'region_id',
        'fecha_nacimiento',
        'sexo',
        'estado_civil',
        'lugar_empadronamiento',
        'street_type',
        'street_name',
        'street_number',
        'additional_info',
        'postal_code',
        'distrito_id',
        'city',
        'country',
        'correo',
        'telefono',
        'centro_adscripcion_id',
        'profesional_referencia_id',
        'tiene_representante_legal',
        'representante_legal_id',
        'requiere_permiso_especial',
        'identificacion_desconocida',
        'identificacion_historial',
        'tipo_documento',
        'numero_id',
        'lat',
        'lng',
        'direccion_validada',
        'formatted_address',
    ];

    protected $casts = [
        'situacion_administrativa' => 'string',
        'fecha_nacimiento' => 'date',
        'sexo' => 'string',
        'estado_civil' => 'string',
        'identificacion_historial' => 'array',
        'tiene_representante_legal' => 'boolean',
        'requiere_permiso_especial' => 'boolean',
        'identificacion_desconocida' => 'boolean',
        'tipo_documento' => 'string',
        'lat' => 'encrypted:decimal:8',
        'lng' => 'encrypted:decimal:8',
        'direccion_validada' => 'boolean',
// ENCRIPTACIÓN: Casts para fields sensibles (auto encrypt/decrypt)
        'first_name' => 'encrypted',
        'last_name1' => 'encrypted',
        'last_name2' => 'encrypted',
        'numero_tarjeta_sanitaria' => 'encrypted',
        'lugar_empadronamiento' => 'encrypted',
        'numero_id' => 'encrypted', // DNI hashed ya, pero extra encrypt
        'correo' => 'encrypted',
        'telefono' => 'encrypted',
        'street_type' => 'encrypted',
        'street_name' => 'encrypted',
        'street_number' => 'encrypted',
        'additional_info' => 'encrypted',
        'postal_code' => 'encrypted',
        'city' => 'encrypted',
        'formatted_address' => 'encrypted',

    ];

    protected $auditExclude = ['identificacion_historial'];

    protected $auditEvents = [
        'created',
        'updated',
        'deleted',
        'restored',
    ];

    // Relaciones
    public function paisOrigen()
    {
        return $this->belongsTo(Country::class, 'pais_origen_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function centroAdscripcion()
    {
        return $this->belongsTo(Centro::class, 'centro_adscripcion_id');
    }

    public function profesionalReferencia()
    {
        return $this->belongsTo(Profesional::class, 'profesional_referencia_id');
    }

    public function representanteLegal()
    {
        return $this->belongsTo(self::class, 'representante_legal_id');
    }

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }

    public function versions(): MorphMany
    {
        return $this->morphMany(Version::class, 'versionable');
    }

}
