<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Agenda\Database\Seeders\AgendaSeeder;
use Modules\Centro\Database\Seeders\CentroSeeder;
use Modules\Documentos\Database\Seeders\DocumentosSeeder;
use Modules\Escalas\Database\Seeders\EscalaSeeder;
use Modules\Intervencion\Database\Seeders\IntervencionSeeder;
use Modules\Organizacion\Database\Seeders\OrganizacionSeeder;
use Modules\Prestaciones\Database\Seeders\CatalogosSistemaSeeder;
use Modules\Prestaciones\Database\Seeders\PrestacionesSeeder;

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
        $this->call(OrganizacionSeeder::class);

        // 5. Catálogos del módulo Profesional
        $this->call(CargosSeeder::class);
        $this->call(TitulacionesSeeder::class);
        $this->call(TiposRelacionProfesionalSeeder::class);

        // 6. Módulo Centro: catálogos base + 3 centros de ejemplo + red
        $this->call(CentroSeeder::class);

        // 7. Módulo Prestaciones: catálogos del sistema y prestaciones de cartera
        $this->call(CatalogosSistemaSeeder::class);
        $this->call(PrestacionesSeeder::class);

        // 8. Módulo Agenda: horario y tipos de slot de ejemplo (requiere centros)
        $this->call(AgendaSeeder::class);

        // 9. Módulo Documentos: catálogos y plantilla de informe de ejemplo
        $this->call(DocumentosSeeder::class);

        // 10. Módulo Intervención: tipos de ficha y valoración
        $this->call(IntervencionSeeder::class);

        // 11. Módulo Escalas: instrumentos de valoración (Barthel, Pfeiffer, Lawton-Brody)
        $this->call(EscalaSeeder::class);

        // 13. Perfiles de anonimización predefinidos del sistema
        $this->call(PerfilesAnonimizacionSeeder::class);

        // 14. Usuarios de desarrollo con sus roles y adscripciones a UO
        $this->call(UsuariosSeeder::class);
    }
}
