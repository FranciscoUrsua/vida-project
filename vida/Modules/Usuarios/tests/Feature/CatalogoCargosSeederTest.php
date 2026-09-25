<?php

namespace Modules\Usuarios\Tests\Feature;

use Database\Seeders\CargosSeeder;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\RolesSugeridosCargoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Usuarios\Models\Cargo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de coherencia entre el catálogo de cargos y sus roles sugeridos iniciales.
 *
 * Una instalación nueva debe quedar igual que la BD compartida revisada el
 * 2026-09-25: 9 cargos identificados por slug, todos con sugerencia de roles.
 *
 * @see docs/seeders.md
 * @see docs/modulo-usuarios-permisos.md sección 2.9
 */
class CatalogoCargosSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepara los roles del sistema, necesarios para validar las sugerencias.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
    }

    #[Test]
    public function instalacion_nueva_crea_nueve_cargos_todos_con_roles_sugeridos(): void
    {
        // Cuando se cargan ambos seeders dos veces sobre una BD vacía
        $this->seed(CargosSeeder::class);
        $this->seed(RolesSugeridosCargoSeeder::class);
        $this->seed(CargosSeeder::class);
        $this->seed(RolesSugeridosCargoSeeder::class);

        // Entonces hay 9 cargos, sin duplicados, y ninguno queda sin sugerencia
        $this->assertSame(9, Cargo::count());
        $this->assertSame(
            ['abogado', 'administrativo', 'auxadmin', 'auxss', 'coordinador', 'educadorsocial', 'psicologo', 'terapeutaocupacional', 'ts'],
            Cargo::orderBy('slug')->pluck('slug')->all()
        );
        $this->assertSame(0, Cargo::doesntHave('rolesSugeridos')->count());

        $coordinador = Cargo::where('slug', 'coordinador')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['supervision', 'intervencion'],
            $coordinador->rolesSugeridos()->pluck('rol')->all()
        );
    }

    #[Test]
    public function cargos_existentes_se_actualizan_por_slug_sin_duplicarse(): void
    {
        // Dado un cargo ya existente con el slug del catálogo y otra descripción
        Cargo::create(['nombre' => 'Trabajador/a Social', 'slug' => 'ts', 'descripcion' => 'Antigua', 'activo' => true]);

        // Cuando se ejecuta el seeder del catálogo
        $this->seed(CargosSeeder::class);

        // Entonces no se crea otro «Trabajador/a Social»
        $this->assertSame(1, Cargo::where('nombre', 'Trabajador/a Social')->count());
        $this->assertNull(Cargo::where('slug', 'ts')->value('descripcion'));
    }
}
