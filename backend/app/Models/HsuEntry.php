<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class HsuEntry extends Model implements AuditableContract
{
    use HasFactory, Auditable;

    protected $fillable = [
        'hsu_id',
        'fecha',
        'tipo_evento',
        'descripcion',
        'datos_adjuntos',
        'auditor_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'datos_adjuntos' => 'array',
    ];

    protected $auditable = ['descripcion', 'datos_adjuntos'];

    // Relaciones
    public function hsu()
    {
        return $this->belongsTo(Hsu::class);
    }

    public function auditor()
    {
        return $this->belongsTo(AppUser::class, 'auditor_id');
    }
}
