<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Prestacion;

class PrestacionesSeeder extends Seeder
{
    public function run(): void
    {
        $prestaciones = [
            [
                'nombre' => '010101 Servicio de Información, Valoración y Orientación',
                'descripcion' => 'Información y orientación sobre servicios y prestaciones del Sistema Público de Servicios Sociales.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['ninguno' => true]),
                'duracion_meses' => 12,
                'costo' => 0.00,
            ],
            [
                'nombre' => '010102 Servicio de Información y Orientación: Oficinas de Información de Prestaciones No Municipales (OIP)',
                'descripcion' => 'Informar y facilitar acceso a prestaciones no municipales.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['residencia' => 'Madrid']),
                'duracion_meses' => null,
                'costo' => 0.00,
            ],
            [
                'nombre' => '010201 Elaboración del Plan Individualizado de Intervención',
                'descripcion' => 'Diagnóstico social y prescripción de recursos para inclusión.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['valoracion_inicial' => true]),
                'duracion_meses' => 6,
                'costo' => 0.00,
            ],
            [
                'nombre' => '020101 Servicio de Instrucción, Apoyo a la Inserción (RMI)',
                'descripcion' => 'Renta Mínima de Inserción para cubrir necesidades básicas.',
                'categoria' => 'especializada',
                'requisitos' => json_encode(['ingresos_bajos' => true, 'residencia' => 'Madrid']),
                'duracion_meses' => 12,
                'costo' => 500.00,
            ],
            [
                'nombre' => '020202 Prestación de Alojamiento Alternativo',
                'descripcion' => 'Alojamiento temporal en situaciones de urgencia.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['sinhogarismo' => true]),
                'duracion_meses' => 3,
                'costo' => 0.00,
            ],
            [
                'nombre' => '020301 Red de Centros de Acogida para Emergencia Social',
                'descripcion' => 'Alojamiento, aseo y manutención para personas sin hogar.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['emergencia' => true]),
                'duracion_meses' => null,
                'costo' => 0.00,
            ],
            [
                'nombre' => '030101 Intervención Social con Familias',
                'descripcion' => 'Apoyo técnico a familias en riesgo.',
                'categoria' => 'especializada',
                'requisitos' => json_encode(['familia_riesgo' => true]),
                'duracion_meses' => 12,
                'costo' => 0.00,
            ],
            [
                'nombre' => '030202 Servicio de Intervención Familiar y Comunitaria',
                'descripcion' => 'Apoyo a familias monoparentales o en exclusión.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['menores' => true]),
                'duracion_meses' => 6,
                'costo' => 0.00,
            ],
            [
                'nombre' => '030203 Centros de Atención a la Infancia y la Familia',
                'descripcion' => 'Atención integral a menores y familias.',
                'categoria' => 'especializada',
                'requisitos' => json_encode(['menores_vulnerables' => true]),
                'duracion_meses' => null,
                'costo' => 0.00,
            ],
            [
                'nombre' => '040301 Prevención de la Dependencia y Promoción de la Autonomía Personal',
                'descripcion' => 'Actividades para mayores en centros municipales.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['mayores_65' => true]),
                'duracion_meses' => 12,
                'costo' => 0.00,
            ],
            [
                'nombre' => '040302 Servicio de Ayuda a Domicilio para Personas Mayores',
                'descripcion' => 'Apoyo doméstico y personal para autonomía.',
                'categoria' => 'complementaria',
                'requisitos' => json_encode(['dependencia' => true]),
                'duracion_meses' => null,
                'costo' => 200.00,
            ],
            [
                'nombre' => '050101 Centros de Día Municipales para Personas con Discapacidad',
                'descripcion' => 'Atención diurna para promoción de autonomía.',
                'categoria' => 'especializada',
                'requisitos' => json_encode(['discapacidad' => true]),
                'duracion_meses' => null,
                'costo' => 0.00,
            ],
            [
                'nombre' => '060301 Servicio de Atención a Víctimas de Violencia de Género',
                'descripcion' => 'Asesoramiento y apoyo integral.',
                'categoria' => 'especializada',
                'requisitos' => json_encode(['victima_vg' => true]),
                'duracion_meses' => 12,
                'costo' => 0.00,
            ],
            [
                'nombre' => '070101 Información y Sensibilización sobre Voluntariado',
                'descripcion' => 'Formación para participación ciudadana.',
                'categoria' => 'basica',
                'requisitos' => json_encode(['mayor_18' => true]),
                'duracion_meses' => 3,
                'costo' => 0.00,
            ],
            [
                'nombre' => '080001 Enseñanzas Artísticas en Escuelas Municipales de Música y Danza',
                'descripcion' => 'Clases artísticas para promoción sociocultural.',
                'categoria' => 'complementaria',
                'requisitos' => json_encode(['inscripcion' => true]),
                'duracion_meses' => 9,
                'costo' => 150.00,
            ],
        ];

        foreach ($prestaciones as $prest) {
            Prestacion::firstOrCreate(
                ['nombre' => $prest['nombre']],
                $prest
            );
        }
    }
}
