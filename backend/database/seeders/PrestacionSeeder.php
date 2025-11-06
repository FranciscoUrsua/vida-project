<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Prestacion;  // Ajusta a tu modelo

class PrestacionSeeder extends Seeder
{
    public function run(): void
    {
        $prestaciones = [
            [
                'tipo' => 'rmi',  // Renta Mínima de Inserción (Guía p.47)
                'descripcion' => 'Ayuda económica para personas en exclusión social con ingresos bajos.',
                'monto' => 404.04,  // Monto base 2024
                'categoria' => 'inclusion',  // De Guía: inclusión/necesidades básicas
                'requisitos' => json_encode(['ingresos < 800€', 'residencia Madrid']),  // JSON para flex
            ],
            [
                'tipo' => 'ayuda_vivienda',
                'descripcion' => 'Subvención para alquiler o pago de hipoteca (Guía p.52).',
                'monto' => 250.00,
                'categoria' => 'inclusion',
                'requisitos' => json_encode(['familia con menores', 'riesgo desahucio']),
            ],
            [
                'tipo' => 'alojamiento_temporal',
                'descripcion' => 'Alojamiento de emergencia para sin hogar (Guía p.60).',
                'monto' => 0,  // No monetaria
                'categoria' => 'inclusion',
                'requisitos' => json_encode(['evaluación inicial', 'prioridad alta']),
            ],
            [
                'tipo' => 'caf_apoyo_familiar',
                'descripcion' => 'Asesoramiento en centros de apoyo a familias (Guía p.92, Plan p.2).',
                'monto' => 0,
                'categoria' => 'apoyo_familiar',
                'requisitos' => json_encode(['dificultades parentales', 'seguimiento periódico']),
            ],
            [
                'tipo' => 'samur_social',
                'descripcion' => 'Atención de emergencia social (Plan p.2, 20 años).',
                'monto' => 0,
                'categoria' => 'emergencia',
                'requisitos' => json_encode(['situación de crisis', '24/7']),
            ],
            // Agrega más hasta 112 si quieres; usa loop para random
        ];

        foreach ($prestaciones as $prestacion) {
            Prestacion::create($prestacion);  // O usa updateOrCreate para idempotencia
        }

        $this->command->info('¡Seed completado! ' . count($prestaciones) . ' prestaciones insertadas.');
    }
}
