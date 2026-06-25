<?php

namespace Modules\Centro\Database\Seeders;

use App\Models\UnidadOrganizativa;
use Illuminate\Database\Seeder;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Red;
use Modules\Centro\Models\SegmentoPoblacion;
use Modules\Centro\Models\TipoActividad;
use Modules\Centro\Models\TipoEspacio;

/**
 * Seeder del módulo Centro.
 *
 * Siembra:
 * 1. Catálogo de tipos de espacio físico (tipos_espacio).
 * 2. Catálogo de tipos de actividad (tipos_actividad).
 * 3. Catálogo de segmentos de población (segmentos_poblacion).
 * 4. Tres centros de ejemplo:
 *    - Albergue Municipal San Isidro (personas sin hogar) — en Red de Albergues
 *    - Albergue Municipal Vallecas (personas sin hogar)  — en Red de Albergues
 *    - Centro de Día Retiro (personas mayores)           — sin red, segmento distinto
 * 5. Una red de ejemplo que agrupa los dos albergues.
 *
 * Seeder idempotente: usa firstOrCreate en todos los registros.
 *
 * Ejecución directa:
 *   php artisan db:seed --class=Modules\\Centro\\Database\\Seeders\\CentroSeeder
 */
class CentroSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrarTiposEspacio();
        $this->sembrarTiposActividad();
        $segmentos = $this->sembrarSegmentosPoblacion();
        $this->sembrarCentrosYRed($segmentos);
    }

    // -------------------------------------------------------------------------
    // Catálogos
    // -------------------------------------------------------------------------

    /**
     * Tipos de espacio físico gestionable dentro de un centro.
     */
    private function sembrarTiposEspacio(): void
    {
        $tipos = [
            ['slug' => 'dormitorio-individual',  'nombre' => 'Dormitorio individual',   'descripcion' => 'Habitación de uso exclusivo para una persona.'],
            ['slug' => 'dormitorio-compartido',  'nombre' => 'Dormitorio compartido',   'descripcion' => 'Habitación con varias camas de uso compartido.'],
            ['slug' => 'habitacion-adaptada',    'nombre' => 'Habitación adaptada',     'descripcion' => 'Dormitorio adaptado para personas con movilidad reducida.'],
            ['slug' => 'sala-comun',             'nombre' => 'Sala común',              'descripcion' => 'Espacio de uso colectivo: comedor, salón, sala de estar.'],
            ['slug' => 'despacho-profesional',   'nombre' => 'Despacho profesional',    'descripcion' => 'Espacio de atención individualizada o trabajo técnico.'],
            ['slug' => 'sala-actividades',       'nombre' => 'Sala de actividades',     'descripcion' => 'Sala polivalente para talleres, grupos o formación.'],
            ['slug' => 'modulo-familiar',        'nombre' => 'Módulo familiar',         'descripcion' => 'Unidad de alojamiento para una unidad familiar.'],
        ];

        foreach ($tipos as $tipo) {
            TipoEspacio::updateOrCreate(
                ['slug' => $tipo['slug']],
                ['nombre' => $tipo['nombre'], 'descripcion' => $tipo['descripcion'], 'activo' => true]
            );
        }

        $this->command->info('✓ Tipos de espacio creados.');
    }

    /**
     * Tipos de actividad que pueden ofrecerse en un centro.
     *
     * Usa updateOrCreate con slug como clave estable para que re-ejecuciones
     * del seeder no generen duplicados y rellenen slugs en filas existentes.
     */
    private function sembrarTiposActividad(): void
    {
        $tipos = [
            ['slug' => 'taller-empleo',      'nombre' => 'Taller de empleo',           'descripcion' => 'Actividades de orientación e inserción laboral.'],
            ['slug' => 'grupo-terapeutico',  'nombre' => 'Grupo terapéutico',          'descripcion' => 'Sesiones grupales de apoyo psicosocial.'],
            ['slug' => 'actividad-deportiva','nombre' => 'Actividad deportiva',        'descripcion' => 'Deporte adaptado, fisioterapia y hábitos saludables.'],
            ['slug' => 'formacion',          'nombre' => 'Formación y alfabetización', 'descripcion' => 'Alfabetización básica, idiomas y competencias digitales.'],
            ['slug' => 'cultural-ocio',      'nombre' => 'Actividad cultural y ocio',  'descripcion' => 'Talleres artísticos, salidas culturales y tiempo libre.'],
            ['slug' => 'acompanamiento',     'nombre' => 'Acompañamiento social',      'descripcion' => 'Acompañamiento individualizado en gestiones y citas.'],
        ];

        foreach ($tipos as $tipo) {
            TipoActividad::updateOrCreate(
                ['slug' => $tipo['slug']],
                ['nombre' => $tipo['nombre'], 'descripcion' => $tipo['descripcion'], 'activo' => true]
            );
        }

        $this->command->info('✓ Tipos de actividad creados.');
    }

    /**
     * Segmentos de población a los que puede dirigirse un centro.
     *
     * @return array<string, SegmentoPoblacion>
     */
    private function sembrarSegmentosPoblacion(): array
    {
        $definiciones = [
            'personas_sin_hogar' => ['slug' => 'personas-sin-hogar', 'nombre' => 'Personas sin hogar',         'descripcion' => 'Personas en situación de sinhogarismo o exclusión residencial grave.'],
            'personas_mayores'   => ['slug' => 'personas-mayores',   'nombre' => 'Personas mayores',            'descripcion' => 'Personas de 65 años o más con necesidades de atención o apoyo social.'],
            'menores_familia'    => ['slug' => 'menores-familia',     'nombre' => 'Menores y familia',           'descripcion' => 'Menores en situación de riesgo y sus unidades familiares.'],
            'discapacidad'       => ['slug' => 'discapacidad',        'nombre' => 'Personas con discapacidad',   'descripcion' => 'Personas con discapacidad física, intelectual o del desarrollo.'],
            'vvg'                => ['slug' => 'segmento-vvg',        'nombre' => 'Víctimas de violencia de género', 'descripcion' => 'Mujeres y menores víctimas de violencia de género.'],
            'atencion_primaria'  => ['slug' => 'atencion-primaria',   'nombre' => 'Atención primaria general',   'descripcion' => 'Población general derivada a los servicios sociales de base.'],
        ];

        $resultado = [];
        foreach ($definiciones as $clave => $def) {
            $resultado[$clave] = SegmentoPoblacion::updateOrCreate(
                ['slug' => $def['slug']],
                ['nombre' => $def['nombre'], 'descripcion' => $def['descripcion'], 'activo' => true]
            );
        }

        $this->command->info('✓ Segmentos de población creados.');

        return $resultado;
    }

    // -------------------------------------------------------------------------
    // Centros y red
    // -------------------------------------------------------------------------

    /**
     * Crea tres centros de ejemplo y una red que agrupa los dos albergues.
     *
     * @param array<string, SegmentoPoblacion> $segmentos
     */
    private function sembrarCentrosYRed(array $segmentos): void
    {
        // UO de referencia: el Departamento de Atención Primaria es el padre común
        $uoDepartamento = UnidadOrganizativa::where('nombre', 'Departamento de Atención Primaria')->first();
        $uoCssArganzuela = UnidadOrganizativa::where('nombre', 'Centro de Servicios Sociales Arganzuela')->first();
        $uoCssRetiro = UnidadOrganizativa::where('nombre', 'Centro de Servicios Sociales Retiro')->first();

        // ---- Centro 1: Albergue Municipal San Isidro ----------------------------
        $albergueSanIsidro = Centro::firstOrCreate(
            ['nombre' => 'Albergue Municipal San Isidro'],
            [
                'nombre_corto' => 'Albergue San Isidro',
                'tipo_gestion' => 'municipal_directo',
                'unidad_organizativa_id' => $uoDepartamento?->id,
                'direccion_texto' => 'Calle Mesón de Paredes, 2',
                'direccion_normalizada' => false,
                'codigo_postal' => '28012',
                'municipio' => 'Madrid',
                'telefono' => '915 000 001',
                'email' => 'albergue.sanisidro@madrid.es',
                'inscripcion_libre' => false,
                'activo' => true,
                'fecha_alta' => '2020-01-01',
                'notas' => 'Centro de referencia de acogida nocturna y atención a personas sin hogar.',
            ]
        );
        $albergueSanIsidro->segmentosPoblacion()->syncWithoutDetaching([$segmentos['personas_sin_hogar']->id]);

        // ---- Centro 2: Albergue Municipal Vallecas ------------------------------
        $albergueVallecas = Centro::firstOrCreate(
            ['nombre' => 'Albergue Municipal Vallecas'],
            [
                'nombre_corto' => 'Albergue Vallecas',
                'tipo_gestion' => 'municipal_concertado',
                'unidad_organizativa_id' => $uoDepartamento?->id,
                'direccion_texto' => 'Avenida de la Albufera, 89',
                'direccion_normalizada' => false,
                'codigo_postal' => '28038',
                'municipio' => 'Madrid',
                'telefono' => '915 000 002',
                'email' => 'albergue.vallecas@madrid.es',
                'inscripcion_libre' => false,
                'activo' => true,
                'fecha_alta' => '2021-06-01',
                'notas' => 'Centro concertado de acogida nocturna para el distrito de Vallecas.',
            ]
        );
        $albergueVallecas->segmentosPoblacion()->syncWithoutDetaching([$segmentos['personas_sin_hogar']->id]);

        // ---- Centro 3: Centro de Día Retiro (personas mayores) -----------------
        $centroDiaRetiro = Centro::firstOrCreate(
            ['nombre' => 'Centro de Día Retiro'],
            [
                'nombre_corto' => 'CD Retiro',
                'tipo_gestion' => 'municipal_directo',
                'unidad_organizativa_id' => $uoCssRetiro?->id,
                'direccion_texto' => 'Calle Ibiza, 45',
                'direccion_normalizada' => false,
                'codigo_postal' => '28009',
                'municipio' => 'Madrid',
                'telefono' => '915 000 003',
                'email' => 'centrodia.retiro@madrid.es',
                'inscripcion_libre' => false,
                'activo' => true,
                'fecha_alta' => '2019-03-15',
                'notas' => 'Centro de atención diurna para personas mayores del distrito de Retiro.',
            ]
        );
        $centroDiaRetiro->segmentosPoblacion()->syncWithoutDetaching([$segmentos['personas_mayores']->id]);

        // ---- Red: agrupa los dos albergues --------------------------------------
        $redAlbergues = Red::firstOrCreate(
            ['nombre' => 'Red de Albergues Municipales'],
            [
                'nombre_corto' => 'Red Albergues',
                'descripcion' => 'Red que unifica la lista de espera y el pool de plazas de los albergues municipales de acogida nocturna.',
                'activa' => true,
                'fecha_alta' => '2021-06-01',
            ]
        );
        $redAlbergues->centros()->syncWithoutDetaching([
            $albergueSanIsidro->id,
            $albergueVallecas->id,
        ]);

        $this->command->info('✓ 3 centros de ejemplo creados (2 en Red de Albergues, 1 Centro de Día independiente).');
        $this->command->info('✓ Red de Albergues Municipales creada.');
    }
}
