<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Director extends Model implements AuditableContract
{
    use HasFactory, Auditable;

    protected $fillable = [
        'professional_id',
        'centro_id',
        'fecha_inicio',
        'fecha_fin',
        'info_relevante',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    protected $auditable = ['info_relevante'];

    // Relaciones
    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function centro()
    {
        return $this->belongsTo(Centro::class);
    }
}
