<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder principal de la base de datos.
 *
 * Orquesta la ejecución de todos los seeders en el orden correcto:
 * primero los catálogos y configuración (permisos, roles, UO),
 * luego los datos de ejemplo.
 *
 * El orden importa: RolesSeeder depende de PermisosSeeder.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ejecuta los seeders en el orden correcto de dependencias.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. Permisos atómicos (contratos del sistema)
        $this->call(PermisosSeeder::class);

        // 2. Roles con sus permisos asignados
        $this->call(RolesSeeder::class);

        // 3. Estructura de Unidades Organizativas de ejemplo
        $this->call(UoSeeder::class);

        // 4. Usuario de desarrollo con rol de administrador
        $admin = User::factory()->create([
            'name'  => 'Administrador VIDA',
            'email' => 'admin@vida.local',
        ]);
        $admin->assignRole('adm_sistema');

        $this->command->info('✓ Usuario administrador creado: admin@vida.local');
    }
}
