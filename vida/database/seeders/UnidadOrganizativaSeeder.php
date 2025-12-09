<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Centro\Models\UnidadOrganizativa;
use App\Modules\Centro\Models\Centro;
use Illuminate\Support\Str;

class OrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        // OA Raíz
        $dgSs = UnidadOrganizativa::create([
            'id' => (string) Str::uuid(),
            'codigo' => 'DG-SS',
            'nombre_corto' => 'Dirección General Servicios Sociales',
            'descripcion' => 'Órgano superior.',
            'parent_id' => null,
        ]);

        // OA Penúltima: Departamento de Mayores (hijo de DG)
        $depMay = UnidadOrganizativa::create([
            'id' => (string) Str::uuid(),
            'codigo' => 'DEP-MAY',
            'nombre_corto' => 'Departamento de Mayores',
            'descripcion' => 'Gestión departamental para mayores (penúltimo nivel).',
            'parent_id' => $dgSs->id,
        ]);

        // Centro bajo DEP-MAY
        Centro::create([
            'codigo' => 'C-MAY-PEPE',
            'nombre' => 'Centro de Mayores Pepe Pérez',
            'descripcion' => 'Centro operativo en distrito centro.',
            'unidad_organizativa_id' => $depMay->id, // Pertenece a departamento (penúltimo)
            'tipo_calle' => 'Calle',
            'nombre_calle' => 'Pepe Pérez',
            'numero' => '1',
            'codigo_postal' => '28001',
            'lat' => 40.4168,
            'lng' => -3.7038,
            'direccion_validada' => true,
            'capacidad_maxima' => 150,
            'tipo_servicio' => 'mayores',
        ]);

        // OA Penúltima: Departamento de Juventud
        $depJuv = UnidadOrganizativa::create([
            'id' => (string) Str::uuid(),
            'codigo' => 'DEP-JUV',
            'nombre_corto' => 'Departamento de Juventud',
            'descripcion' => 'Gestión para juventud.',
            'parent_id' => $dgSs->id,
        ]);

        // Centro bajo DEP-JUV
        Centro::create([
            'codigo' => 'C-JUV-NOR',
            'nombre' => 'Centro de Juventud Norte',
            'descripcion' => 'Centro en distrito norte.',
            'unidad_organizativa_id' => $depJuv->id,
            'nombre_calle' => 'Avenida Norte',
            'numero' => '45',
            'codigo_postal' => '28010',
            'lat' => 40.4500,
            'lng' => -3.7000,
            'direccion_validada' => false,
            'capacidad_maxima' => 200,
            'tipo_servicio' => 'juventud',
        ]);

        // OA inactiva
        $oaInact = UnidadOrganizativa::create([
            'id' => (string) Str::uuid(),
            'codigo' => 'OA-INACT',
            'nombre_corto' => 'OA Inactiva',
            'parent_id' => null,
        ]);
        $oaInact->update(['activa' => false]);
    }
}
