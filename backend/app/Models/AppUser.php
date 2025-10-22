<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AppUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'app_users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relaciones
    public function socialUsers()
    {
        return $this->hasMany(SocialUser::class, 'profesional_referencia_id');
    }

    public function hsuEntries()
    {
        return $this->hasMany(HsuEntry::class, 'auditor_id');
    }

    public function professional()
    {
        return $this->hasOne(Professional::class);
    }

    public function director()
    {
        return $this->hasOne(Director::class, 'professional_id');
    }

    // Scope para roles (integrar con Spatie después)
    public function scopeWithRole($query, $role)
    {
        return $query->whereJsonContains('perfil_acceso', $role);  // Asumiendo json en tabla
    }
}
