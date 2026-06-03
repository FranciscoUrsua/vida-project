<?php

namespace Modules\Prestaciones\Database\Seeders;

use App\Models\CatalogoSistema;
use Illuminate\Database\Seeder;

/**
 * Valores iniciales de todos los grupos de catalogos_sistema
 * necesarios para el módulo Prestaciones.
 *
 * Seeder idempotente: usa updateOrCreate, puede ejecutarse múltiples veces.
 */
class CatalogosSistemaSeeder extends Seeder
{
    public function run(): void
    {
        $this->cargarGrupo('prestacion.objetivo_general', [
            ['clave' => '01', 'etiqueta' => 'Acceso, información y valoración',          'orden' => 1],
            ['clave' => '02', 'etiqueta' => 'Inclusión social y empleo',                  'orden' => 2],
            ['clave' => '03', 'etiqueta' => 'Familia, infancia y adolescencia',           'orden' => 3],
            ['clave' => '04', 'etiqueta' => 'Autonomía personal y vida independiente',    'orden' => 4],
            ['clave' => '05', 'etiqueta' => 'Atención a la dependencia',                  'orden' => 5],
            ['clave' => '06', 'etiqueta' => 'Urgencia social, emergencia y violencia de género', 'orden' => 6],
            ['clave' => '07', 'etiqueta' => 'Desarrollo comunitario',                     'orden' => 7],
            ['clave' => '08', 'etiqueta' => 'Participación social, cultura y ocio',       'orden' => 8],
        ]);

        $this->cargarGrupo('prestacion.categoria', [
            // Objetivo 01
            ['clave' => '0101', 'etiqueta' => 'Información y orientación',                'orden' => 101],
            ['clave' => '0102', 'etiqueta' => 'Valoración y diagnóstico social',          'orden' => 102],
            ['clave' => '0103', 'etiqueta' => 'Informes sociales',                        'orden' => 103],
            ['clave' => '0104', 'etiqueta' => 'Coordinación y derivación',                'orden' => 104],
            // Objetivo 02
            ['clave' => '0201', 'etiqueta' => 'Atención a personas sin hogar',            'orden' => 201],
            ['clave' => '0202', 'etiqueta' => 'Inserción sociolaboral',                   'orden' => 202],
            ['clave' => '0203', 'etiqueta' => 'Prestaciones económicas de inclusión',     'orden' => 203],
            ['clave' => '0204', 'etiqueta' => 'Intervención con población en riesgo de exclusión', 'orden' => 204],
            // Objetivo 03
            ['clave' => '0301', 'etiqueta' => 'Preservación y apoyo familiar',            'orden' => 301],
            ['clave' => '0302', 'etiqueta' => 'Protección de menores',                    'orden' => 302],
            ['clave' => '0303', 'etiqueta' => 'Apoyo a la crianza y parentalidad positiva', 'orden' => 303],
            ['clave' => '0304', 'etiqueta' => 'Mediación familiar',                       'orden' => 304],
            ['clave' => '0305', 'etiqueta' => 'Atención a adolescentes',                  'orden' => 305],
            // Objetivo 04
            ['clave' => '0401', 'etiqueta' => 'Atención domiciliaria',                    'orden' => 401],
            ['clave' => '0402', 'etiqueta' => 'Apoyo a la vida independiente',            'orden' => 402],
            ['clave' => '0403', 'etiqueta' => 'Teleasistencia',                           'orden' => 403],
            ['clave' => '0404', 'etiqueta' => 'Adaptación del hogar y ayudas técnicas',   'orden' => 404],
            // Objetivo 05
            ['clave' => '0501', 'etiqueta' => 'Centros de día y atención diurna',         'orden' => 501],
            ['clave' => '0502', 'etiqueta' => 'Atención residencial para personas mayores', 'orden' => 502],
            ['clave' => '0503', 'etiqueta' => 'Atención residencial para personas con discapacidad', 'orden' => 503],
            ['clave' => '0504', 'etiqueta' => 'Respiro y apoyo a cuidadores',             'orden' => 504],
            // Objetivo 06
            ['clave' => '0601', 'etiqueta' => 'Urgencia social y emergencia',             'orden' => 601],
            ['clave' => '0602', 'etiqueta' => 'Atención a víctimas de violencia de género', 'orden' => 602],
            ['clave' => '0603', 'etiqueta' => 'Ayudas de emergencia',                     'orden' => 603],
            // Objetivo 07
            ['clave' => '0701', 'etiqueta' => 'Animación y desarrollo comunitario',       'orden' => 701],
            ['clave' => '0702', 'etiqueta' => 'Programas de convivencia y cohesión social', 'orden' => 702],
            ['clave' => '0703', 'etiqueta' => 'Voluntariado y participación ciudadana',   'orden' => 703],
            // Objetivo 08
            ['clave' => '0801', 'etiqueta' => 'Actividades culturales y de ocio',         'orden' => 801],
            ['clave' => '0802', 'etiqueta' => 'Programas de envejecimiento activo',       'orden' => 802],
            ['clave' => '0803', 'etiqueta' => 'Deporte y actividad física',               'orden' => 803],
        ]);

        $this->cargarGrupo('prestacion.nivel_atencion', [
            ['clave' => 'asp',          'etiqueta' => 'Atención Social Primaria',      'orden' => 1],
            ['clave' => 'especializada', 'etiqueta' => 'Atención Social Especializada', 'orden' => 2],
            ['clave' => 'no_aplica',    'etiqueta' => 'No aplica',                     'orden' => 3],
        ]);

        $this->cargarGrupo('prestacion.competencia', [
            ['clave' => 'municipal',   'etiqueta' => 'Municipal',             'orden' => 1],
            ['clave' => 'autonomica',  'etiqueta' => 'Autonómica',            'orden' => 2],
            ['clave' => 'estatal',     'etiqueta' => 'Estatal',               'orden' => 3],
            ['clave' => 'compartida',  'etiqueta' => 'Compartida',            'orden' => 4],
        ]);

        $this->cargarGrupo('prestacion.forma_gestion', [
            ['clave' => 'directa',   'etiqueta' => 'Gestión directa',   'orden' => 1],
            ['clave' => 'indirecta', 'etiqueta' => 'Gestión indirecta', 'orden' => 2],
            ['clave' => 'mixta',     'etiqueta' => 'Gestión mixta',     'orden' => 3],
        ]);

        $this->cargarGrupo('prestacion.financiacion', [
            ['clave' => 'local',        'etiqueta' => 'Local',                'orden' => 1],
            ['clave' => 'autonomica',   'etiqueta' => 'Autonómica',           'orden' => 2],
            ['clave' => 'cofinanciada', 'etiqueta' => 'Cofinanciada',         'orden' => 3],
            ['clave' => 'estatal',      'etiqueta' => 'Estatal',              'orden' => 4],
        ]);

        $this->cargarGrupo('prestacion.poblacion', [
            ['clave' => 'poblacion_general', 'etiqueta' => 'Población general',                    'orden' => 1],
            ['clave' => 'infancia',          'etiqueta' => 'Infancia (0-12 años)',                  'orden' => 2],
            ['clave' => 'juventud',          'etiqueta' => 'Juventud (13-29 años)',                 'orden' => 3],
            ['clave' => 'familia',           'etiqueta' => 'Familias',                              'orden' => 4],
            ['clave' => 'exclusion',         'etiqueta' => 'Personas en riesgo de exclusión social', 'orden' => 5],
            ['clave' => 'discapacidad',      'etiqueta' => 'Personas con discapacidad',             'orden' => 6],
            ['clave' => 'vg',                'etiqueta' => 'Víctimas de violencia de género',       'orden' => 7],
            ['clave' => 'lgtbi',             'etiqueta' => 'Personas LGTBI+',                       'orden' => 8],
            ['clave' => 'mayores',           'etiqueta' => 'Personas mayores',                      'orden' => 9],
            ['clave' => 'dependencia',       'etiqueta' => 'Personas en situación de dependencia',  'orden' => 10],
            ['clave' => 'migrantes',         'etiqueta' => 'Personas migrantes y refugiadas',       'orden' => 11],
            ['clave' => 'sin_hogar',         'etiqueta' => 'Personas sin hogar',                    'orden' => 12],
        ]);

        $this->cargarGrupo('centro.tipo', [
            ['clave' => 'css_general',        'etiqueta' => 'Centro de Servicios Sociales General',        'orden' => 1],
            ['clave' => 'css_especializado',  'etiqueta' => 'Centro de Servicios Sociales Especializado',  'orden' => 2],
            ['clave' => 'centro_dia',         'etiqueta' => 'Centro de Día',                               'orden' => 3],
            ['clave' => 'residencia_mayores', 'etiqueta' => 'Residencia para personas mayores',            'orden' => 4],
            ['clave' => 'residencia_disc',    'etiqueta' => 'Residencia para personas con discapacidad',   'orden' => 5],
            ['clave' => 'albergue',           'etiqueta' => 'Albergue o centro de acogida',                'orden' => 6],
            ['clave' => 'piso_insercion',     'etiqueta' => 'Piso de inserción o vivienda tutelada',       'orden' => 7],
            ['clave' => 'centro_vg',          'etiqueta' => 'Centro de atención a víctimas de VG',         'orden' => 8],
            ['clave' => 'punto_encuentro',    'etiqueta' => 'Punto de encuentro familiar',                 'orden' => 9],
            ['clave' => 'servicio_especifico', 'etiqueta' => 'Servicio o recurso específico',               'orden' => 10],
            ['clave' => 'recurso_educativo',  'etiqueta' => 'Recurso educativo o de formación',            'orden' => 11],
            ['clave' => 'centro_dia_inf',     'etiqueta' => 'Centro de Día para infancia y adolescencia',  'orden' => 12],
        ]);
    }

    /**
     * Carga un grupo de valores usando updateOrCreate (idempotente).
     *
     * @param array<int, array{clave: string, etiqueta: string, orden: int}> $valores
     */
    private function cargarGrupo(string $grupo, array $valores): void
    {
        foreach ($valores as $valor) {
            CatalogoSistema::updateOrCreate(
                ['grupo' => $grupo, 'clave' => $valor['clave']],
                ['etiqueta' => $valor['etiqueta'], 'orden' => $valor['orden'], 'activo' => true]
            );
        }
    }
}
