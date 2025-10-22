<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Hsu extends Model implements AuditableContract
{
    use HasFactory, Auditable;

    protected $fillable = [
        'ruu_user_id',
        'situacion_actual',
        'requiere_permiso_especial',
    ];

    protected $casts = [
        'situacion_actual' => 'array',
        'requiere_permiso_especial' => 'boolean',
    ];

    protected $auditable = ['situacion_actual'];

    // Relaciones
    public function ruuUser()
    {
        return $this->belongsTo(Ruu::class, 'ruu_user_id');
    }

    public function socialUser()
    {
        return $this->belongsTo(SocialUser::class, 'ruu_user_id');  // Asumiendo ID compartido
    }

    public function entries()
    {
        return $this->hasMany(HsuEntry::class);
    }

    // Scope para casos especiales
    public function scopeConViolenciaGenero($query)
    {
        return $query->where('requiere_permiso_especial', true);
    }
}
