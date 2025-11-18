<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroProfesional extends Model
{
    use HasFactory;

    protected $table = 'centro_profesional'; // Tabla pivot explícita

    protected $fillable = [
        'centro_id',
        'profesional_id',
    ];

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }
}
