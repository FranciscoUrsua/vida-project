<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Centro;
use App\Models\TipoCentro;

class CentrosSeeder extends Seeder
{
    public function run(): void
    {
        $tipoIds = TipoCentro::pluck('id')->toArray(); // Usa tipos seedeados

        $centros = [
            [
                'tipo' => $tipoIds[0] ?? 1, // Básicos
                'nombre' => 'Centro Municipal de Servicios Sociales de Arganzuela',
                'direccion_postal' => 'Paseo de las Delicias, 12, 28045 Madrid',
                'telefono' => '+34 915 555 100',
                'email_contacto' => 'arganzuela@madrid.es',
                'director_id' => null, // Nullable, se asigna después
                'campos_especificos' => json_encode(['distrito' => 'Arganzuela', 'capacidad' => 150]),
                'lat' => 40.4025,
                'lng' => -3.6914,
                'direccion_validada' => true,
            ],
            [
                'tipo' => $tipoIds[1] ?? 2, // Día para Mayores
                'nombre' => 'Centro de Mayores de Chamberí',
                'direccion_postal' => 'Calle de Sagasta, 18, 28004 Madrid',
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
                'direccion_postal' => 'Calle de la Isla de Hierro, 1, 28035 Madrid',
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
                'direccion_postal' => 'Av. de Los Poblados, 1, 28041 Madrid',
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
                'direccion_postal' => 'Calle de Marcelo Usera, 23, 28026 Madrid',
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
