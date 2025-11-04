<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestacion extends Model
{
    use HasFactory;

    // FIX: Especifica tabla en plural español
    protected $table = 'prestaciones';

    protected $fillable = [
        'nombre',  // e.g., "Pensión no contributiva jubilación (PNC)"
        'descripcion',  // Detalle completo (e.g., "Prestación económica para jubilados sin cotizaciones suficientes")
        'categoria',  // e.g., "mayores_y_dependencia", "familia_e_infancia" (del índice p. 2)
        'nivel',  // e.g., "estatal", "autonomica", "municipal" (subsecciones 1.1/1.2, 2.1)
        'requisitos',  // JSON para condiciones (e.g., ["edad_minima": 65, "ingresos_max": 7000])
        'documentos',  // Array para requeridos (e.g., ["DNI", "certificado_empadronamiento"])
        'publico_objetivo',  // Text para target (e.g., "Personas mayores en situación de dependencia")
        'subcategoria',  // Opcional, e.g., "ayuda_domicilio" para SAD (de Ordenanza 10/2022 p. 16)
    ];

    protected $casts = [
        'requisitos' => 'array',
        'documentos' => 'array',
    ];

    // Nueva: Muchos social users (N:N)
    public function socialUsers()
    {
        return $this->belongsToMany(SocialUser::class, 'prestacion_social_user')
                    ->withTimestamps()
                    ->withPivot('fecha_fin');  // Incluye fecha_fin del pivot
    }

    // Scope para categoría (útil para filtros en API)
    public function scopePorCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    // Scope para nivel
    public function scopePorNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }
}
