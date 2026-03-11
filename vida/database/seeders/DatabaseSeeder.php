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

        // 4. Usuario administrador con rol de sistema
        // IMPORTANTE: cambiar la contraseña tras el primer acceso
        $admin = User::create([
            'name'              => 'Administrador VIDA',
            'email'             => 'admin@vida.local',
            'password'          => bcrypt('Vida360!Admin'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('adm_sistema');

        $this->command->info('✓ Usuario administrador creado: admin@vida.local / Vida360!Admin');
    }
}
