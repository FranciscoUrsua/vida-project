<?php

namespace App\Models;

use App\Traits\HasValidatableAddress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Centro extends Model
{
    use HasFactory, HasValidatableAddress;

    protected $table = 'centros';

    protected $fillable = [
        'tipo', 'nombre', 'tipo_centro_id',  // Referencia a tipo en lugar de campos_especificos
        'street_type', 'street_name', 'street_number', 'additional_info', 'postal_code', 'city', 'pais',  // Dirección con defaults en DB
        'telefono', 'email_contacto', 'director_id',
        'lat', 'lng', 'direccion_validada'
    ];

    protected $casts = [
        'tipo_centro_id' => 'integer',
        'postal_code' => 'string',
        'city' => 'string',
        'pais' => 'string',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'direccion_validada' => 'boolean',
    ];

    // Relaciones: belongsTo TipoCentro
    public function tipoCentro()
    {
        return $this->belongsTo(TipoCentro::class, 'tipo_centro_id');
    }

    // Otras relaciones...
    public function director()
    {
        return $this->hasOne(Director::class);
    }

    public function profesionales()
    {
    return $this->belongsToMany(Profesional::class, 'centro_profesional')
                    ->withTimestamps()  // Pivot timestamps
                    ->withPivot('created_at');  // Opcional
    }

    public function scopeActivos($query)
    {
        return $query->whereHas('director', function ($q) {
            $q->whereNull('fecha_baja');
        });
    }

    // Boot idéntico a SocialUser
    protected static function bootHasValidatableAddress()
    {
        static::saving(function ($model) {
            $addressArray = $model->buildAddressArray();

            if (!empty($addressArray)) {
                try {
                    $validated = $model->validateAddress($addressArray);
                    $model->lat = $validated['latitude'] ?? null;
                    $model->lng = $validated['longitude'] ?? null;
                    $model->direccion_validada = true;
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $model->direccion_validada = false;
                    throw $e;
                }
            } else {
                $model->direccion_validada = false;
                $model->lat = null;
                $model->lng = null;
            }
        });
    }

    protected function buildAddressArray(): array
    {
        return [
            'street_type' => $this->street_type,
            'street_name' => $this->street_name,
            'street_number' => $this->street_number,
            'additional_info' => $this->additional_info,
            'postal_code' => $this->postal_code,
            'city' => $this->city ?? 'Madrid',
            'country' => $this->pais ?? 'España',
        ];
    }

    public function buildAddressArrayFromComponents(array $components): array
    {
        return [
            'street_type' => $components['street_type'] ?? null,
            'street_name' => $components['street_name'] ?? null,
            'street_number' => $components['street_number'] ?? null,
            'additional_info' => $components['additional_info'] ?? null,
            'postal_code' => $components['postal_code'] ?? null,
            'city' => $components['city'] ?? 'Madrid',
            'country' => $components['pais'] ?? 'España',
        ];
    }
}
