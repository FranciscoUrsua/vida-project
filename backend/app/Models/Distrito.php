<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distrito extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => 'string',
    ];

    // Relaciones (si se usa en FKs como en centros/social_users)
    public function centros()
    {
        return $this->hasMany(Centro::class);
    }

    public function socialUsers()
    {
        return $this->hasMany(SocialUser::class);
    }

    // Accesor para nombre completo (e.g., "01 - Centro")
    public function getNombreCompletoAttribute(): string
    {
        return $this->codigo . ' - ' . $this->nombre;
    }
}
