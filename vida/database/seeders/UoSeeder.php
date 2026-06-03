<?php

namespace Database\Seeders;

use App\Models\UnidadOrganizativa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de Unidades Organizativas — estructura mínima para desarrollo.
 *
 * Crea una jerarquía de ejemplo que refleja la estructura real del
 * Ayuntamiento de Madrid descrita en docs/modulo-usuarios-permisos.md § 1.2:
 *
 *   Ayuntamiento de Madrid (raíz)
 *   └── Área de Gobierno de Políticas Sociales
 *       └── Dirección General de Servicios Sociales
 *           └── Departamento de Atención Primaria
 *               ├── CSS Arganzuela
 *               └── CSS Retiro
 *
 * También puebla el catálogo de tipos de UO.
 */
class UoSeeder extends Seeder
{
    /**
     * Crea los tipos de UO y la estructura mínima de ejemplo.
     */
    public function run(): void
    {
        $this->crearTipos();
        $this->crearEstructura();
    }

    /**
     * Inserta el catálogo configurable de tipos de Unidad Organizativa.
     */
    private function crearTipos(): void
    {
        $tipos = [
            ['codigo' => 'ayuntamiento', 'nombre' => 'Ayuntamiento'],
            ['codigo' => 'area_gobierno', 'nombre' => 'Área de Gobierno'],
            ['codigo' => 'dg',            'nombre' => 'Dirección General'],
            ['codigo' => 'departamento',  'nombre' => 'Departamento'],
            ['codigo' => 'centro',        'nombre' => 'Centro de Servicios Sociales'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipos_unidad_organizativa')->updateOrInsert(
                ['codigo' => $tipo['codigo']],
                array_merge($tipo, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Tipos de UO creados.');
    }

    /**
     * Crea la jerarquía de UO de ejemplo para desarrollo.
     */
    private function crearEstructura(): void
    {
        // Nodo raíz
        $ayuntamiento = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'Ayuntamiento de Madrid', 'tipo' => 'ayuntamiento'],
            ['parent_id' => null, 'activa' => true]
        );

        // Área de Gobierno
        $areaGobierno = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'Área de Gobierno de Políticas Sociales, Familia e Igualdad', 'tipo' => 'area_gobierno'],
            ['parent_id' => $ayuntamiento->id, 'activa' => true]
        );

        // Dirección General
        $dgServicios = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'Dirección General de Servicios Sociales', 'tipo' => 'dg'],
            ['parent_id' => $areaGobierno->id, 'activa' => true]
        );

        // Departamento
        $deptoAtencion = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'Departamento de Atención Primaria', 'tipo' => 'departamento'],
            ['parent_id' => $dgServicios->id, 'activa' => true]
        );

        // Centros
        UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'Centro de Servicios Sociales Arganzuela', 'tipo' => 'centro'],
            ['parent_id' => $deptoAtencion->id, 'activa' => true]
        );

        UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'Centro de Servicios Sociales Retiro', 'tipo' => 'centro'],
            ['parent_id' => $deptoAtencion->id, 'activa' => true]
        );

        $this->command->info('✓ Estructura de UO de ejemplo creada (Ayuntamiento → Área → DG → Departamento → 2 Centros).');
    }
}
