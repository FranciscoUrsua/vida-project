<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Titulacion extends Model
{
    use HasFactory;

    // FIX: Especifica para plural español
    protected $table = 'titulaciones';

    protected $fillable = ['nombre', 'descripcion'];

    public function profesionales()
    {
        return $this->hasMany(Profesional::class);
    }
}
