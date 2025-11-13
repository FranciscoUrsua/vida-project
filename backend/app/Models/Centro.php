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

}
