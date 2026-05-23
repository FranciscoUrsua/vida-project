<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder principal de la base de datos.
 *
 * Orquesta la ejecución de todos los seeders en el orden correcto.
 * El orden importa: RolesSeeder depende de PermisosSeeder,
 * y los seeders de módulos deben ejecutarse después de los del core.
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

        // 4. Datos del módulo Organizacion (configuracion, colectivos, distritos, etc.)
        $this->call(\Modules\Organizacion\Database\Seeders\OrganizacionSeeder::class);

        // 5. Catálogos del módulo Profesional
        $this->call(CargosSeeder::class);
        $this->call(TitulacionesSeeder::class);
        $this->call(TiposRelacionProfesionalSeeder::class);

        // 6. Módulo Centro: catálogos base + 3 centros de ejemplo + red
        $this->call(\Modules\Centro\Database\Seeders\CentroSeeder::class);

        // 7. Módulo Prestaciones: catálogos del sistema y prestaciones de cartera
        $this->call(\Modules\Prestaciones\Database\Seeders\CatalogosSistemaSeeder::class);
        $this->call(\Modules\Prestaciones\Database\Seeders\PrestacionesSeeder::class);

        // 8. Módulo Agenda: horario y tipos de slot de ejemplo (requiere centros)
        $this->call(\Modules\Agenda\Database\Seeders\AgendaSeeder::class);

        // 9. Módulo Documentos: catálogos y plantilla de informe de ejemplo
        $this->call(\Modules\Documentos\Database\Seeders\DocumentosSeeder::class);

        // 10. Módulo Intervención: tipos de ficha y valoración
        $this->call(\Modules\Intervencion\Database\Seeders\IntervencionSeeder::class);

        // 11. Perfiles de anonimización predefinidos del sistema
        $this->call(PerfilesAnonimizacionSeeder::class);

        // 12. Usuario administrador con rol de sistema
        // IMPORTANTE: cambiar la contraseña tras el primer acceso
        $admin = User::firstOrCreate(
            ['email' => 'admin@vida.local'],
            [
                'name'              => 'Administrador VIDA',
                'password'          => 'Vida360!Admin',
                'email_verified_at' => now(),
            ]
        );
        if (! $admin->hasRole('adm_sistema')) {
            $admin->assignRole('adm_sistema');
        }

        $this->command->info('✓ Usuario administrador: admin@vida.local / Vida360!Admin');
    }
}
