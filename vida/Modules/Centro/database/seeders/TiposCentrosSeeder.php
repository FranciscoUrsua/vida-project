<?php

namespace Modules\Centro\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centro\Models\TipoCentro;

class TiposCentrosSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'nombre' => 'Centro de Servicios Sociales Básicos',
                'descripcion' => 'Centros municipales que ofrecen servicios generales de información, asesoramiento y prestaciones básicas en servicios sociales.',
                'tiene_plazas' => true,
                'numero_plazas' => 200,
                'criterio_asignacion_plazas' => 'Asignación por residencia en distrito y prioridad por vulnerabilidad socioeconómica (e.g., familias monoparentales).',
                'prestaciones_default' => json_encode([1, 2, 3]), // Ej: IDs de prestaciones básicas de la Guía 2024 (info, asesoramiento, ayudas económicas)
                'publico_objetivo' => json_encode(['familias', 'adultos', 'inmigrantes']),
                'schema_campos_dinamicos' => json_encode([
                    'capacidad' => ['type' => 'number', 'required' => true],
                    'horario_atencion' => ['type' => 'string', 'required' => false]
                ]),
            ],
            [
                'nombre' => 'Centro de Día para Mayores',
                'descripcion' => 'Espacios dedicados a actividades diurnas para personas mayores, promoviendo autonomía y socialización.',
                'tiene_plazas' => true,
                'numero_plazas' => 50,
                'criterio_asignacion_plazas' => 'Prioridad por edad (>65 años), grado de dependencia y proximidad geográfica.',
                'prestaciones_default' => json_encode([45, 67, 89]), // Ej: apoyo domiciliario, talleres educativos, teleasistencia (Guía págs. 2-3)
                'publico_objetivo' => json_encode(['mayores']),
                'schema_campos_dinamicos' => json_encode([
                    'programas' => ['type' => 'array', 'required' => true],
                    'teleasistencia' => ['type' => 'boolean', 'required' => false]
                ]),
            ],
            [
                'nombre' => 'Centro de Apoyo a la Familia',
                'descripcion' => 'Centros enfocados en intervención familiar, con énfasis en planes PISO y apoyo a menores.',
                'tiene_plazas' => true,
                'numero_plazas' => 100,
                'criterio_asignacion_plazas' => 'Evaluación inicial por trabajador social; prioridad para casos de riesgo familiar.',
                'prestaciones_default' => json_encode([20, 35, 50]), // Ej: acogida familiar, apoyo educativo, PISO (alineado con Plan Estratégico págs. 35-46)
                'publico_objetivo' => json_encode(['familias', 'menores']),
                'schema_campos_dinamicos' => json_encode([
                    'enfoque' => ['type' => 'string', 'required' => true],
                    'menores' => ['type' => 'boolean', 'required' => false]
                ]),
            ],
            [
                'nombre' => 'Espacio de Igualdad',
                'descripcion' => 'Servicios especializados en igualdad y atención a víctimas de violencia de género.',
                'tiene_plazas' => false,
                'numero_plazas' => null,
                'criterio_asignacion_plazas' => null,
                'prestaciones_default' => json_encode([78, 95, 110]), // Ej: asesoramiento género, urgencias VG, talleres igualdad (Guía 2024)
                'publico_objetivo' => json_encode(['mujeres', 'victimas_vg']),
                'schema_campos_dinamicos' => json_encode([
                    'especialidad' => ['type' => 'string', 'required' => true],
                    'urgencias' => ['type' => 'boolean', 'required' => false]
                ]),
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoCentro::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                $tipo
            );
        }
    }
}
