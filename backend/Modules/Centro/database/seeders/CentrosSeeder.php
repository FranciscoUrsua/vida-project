<?php

namespace Modules\Centro\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Models\Distrito; // Para distrito_id

class CentrosSeeder extends Seeder
{
    public function run(): void
    {
        $tipoIds = TipoCentro::pluck('id')->toArray();
        $distritoIds = Distrito::pluck('id', 'codigo')->toArray(); // Map código a ID

        $centros = [
            [
                'tipo' => $tipoIds[0] ?? 1, // Básicos
                'nombre' => 'Centro Municipal de Servicios Sociales de Arganzuela',
                'street_type' => 'Calle',
                'street_name' => 'Piedra', // Limpiado: "de la Piedra" → "Piedra"
                'street_number' => '5',
                'additional_info' => null,
                'postal_code' => '28005',
                'distrito_id' => $distritoIds['02'] ?? 2, // Arganzuela
                'city' => 'Madrid',
                'country' => 'España',
                'telefono' => '+34 915 555 100',
                'email_contacto' => 'arganzuela@madrid.es',
                'director_id' => null,
                'campos_especificos' => json_encode(['distrito' => 'Arganzuela', 'capacidad' => 150]),
                'lat' => 40.4025,
                'lng' => -3.6914,
                'direccion_validada' => true,
            ],
            [
                'tipo' => $tipoIds[1] ?? 2, // Día para Mayores
                'nombre' => 'Centro de Mayores de Chamberí',
                'street_type' => 'Calle',
                'street_name' => 'Almagro', // Sin "de " prefiijo
                'street_number' => '3',
                'additional_info' => null,
                'postal_code' => '28010',
                'distrito_id' => $distritoIds['07'] ?? 7, // Chamberí
                'city' => 'Madrid',
                'country' => 'España',
                'telefono' => '+34 915 555 200',
                'email_contacto' => 'chamberi.mayores@madrid.es',
                'director_id' => null,
                'campos_especificos' => json_encode(['programas' => ['teleasistencia', 'talleres']]),
                'lat' => 40.4319,
                'lng' => -3.7003,
                'direccion_validada' => true,
            ],
            [
                'tipo' => $tipoIds[2] ?? 3, // Acogida Familiar
                'nombre' => 'Centro de Apoyo Familiar de Fuencarral-El Pardo',
                'street_type' => 'Calle',
                'street_name' => 'Remonta', // Limpiado: "de la Remonta" → "Remonta"
                'street_number' => '8',
                'additional_info' => null,
                'postal_code' => '28039',
                'distrito_id' => $distritoIds['08'] ?? 8, // Fuencarral - El Pardo
                'city' => 'Madrid',
                'country' => 'España',
                'telefono' => '+34 915 555 300',
                'email_contacto' => 'fuencarral.familias@madrid.es',
                'director_id' => null,
                'campos_especificos' => json_encode(['enfoque' => 'PISO', 'menores' => true]),
                'lat' => 40.4890,
                'lng' => -3.6906,
                'direccion_validada' => true,
            ],
            [
                'tipo' => $tipoIds[3] ?? 4, // Especializada
                'nombre' => 'Centro de Intervención en Género de Latina',
                'street_type' => 'Calle',
                'street_name' => 'Poveda', // Sin "de la "
                'street_number' => '2',
                'additional_info' => null,
                'postal_code' => '28047',
                'distrito_id' => $distritoIds['10'] ?? 10, // Latina
                'city' => 'Madrid',
                'country' => 'España',
                'telefono' => '+34 915 555 400',
                'email_contacto' => 'latina.genero@madrid.es',
                'director_id' => null,
                'campos_especificos' => json_encode(['especialidad' => 'Violencia de Género', 'urgencias' => true]),
                'lat' => 40.3856,
                'lng' => -3.7471,
                'direccion_validada' => true,
            ],
            [
                'tipo' => $tipoIds[0] ?? 1, // Básicos
                'nombre' => 'Centro de Servicios Sociales de Usera',
                'street_type' => 'Calle',
                'street_name' => 'Pedro Roldán', // Sin "de "
                'street_number' => '7',
                'additional_info' => null,
                'postal_code' => '28026',
                'distrito_id' => $distritoIds['12'] ?? 12, // Usera
                'city' => 'Madrid',
                'country' => 'España',
                'telefono' => '+34 915 555 500',
                'email_contacto' => 'usera@madrid.es',
                'director_id' => null,
                'campos_especificos' => json_encode(['distrito' => 'Usera', 'inmigrantes' => true]),
                'lat' => 40.3880,
                'lng' => -3.7222,
                'direccion_validada' => true,
            ],
        ];

        foreach ($centros as $centro) {
            Centro::firstOrCreate(
                ['nombre' => $centro['nombre']],
                $centro
            );
        }
    }
}
