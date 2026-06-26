<?php

namespace Modules\Agenda\Tests\Feature\Supervisor;

use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales TF-AGS-40 a TF-AGS-42 — Acceso y permisos.
 */
class AccesoSupervisorTest extends TestCase
{
    use RefreshDatabase;
    use AgendaSupervisorTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->construirFixturesSupervisor();
    }

    /**
     * TF-AGS-40 — Un profesional sin rol de supervisión recibe 403 al acceder al cuadrante.
     */
    #[Test]
    public function profesional_sin_rol_supervision_recibe_403(): void
    {
        // Dado: profesional1 con rol intervencion (sin supervision)
        $this->actingAs($this->profesional1)
            ->get('/agenda/supervisor/cuadrante')
            ->assertForbidden();
    }

    /**
     * TF-AGS-41 — El supervisor solo ve los profesionales de su centro.
     */
    #[Test]
    public function supervisor_solo_ve_profesionales_de_su_centro(): void
    {
        // Ya verificado en ExcepcionesSupervisorTest TF-AGS-25;
        // aquí verificamos en la ruta del cuadrante que la respuesta es 200 para el supervisor
        $this->actingAs($this->supervisor)
            ->get('/agenda/supervisor/cuadrante')
            ->assertOk();
    }

    /**
     * TF-AGS-42 — El enlace de configuración del centro apunta a Filament.
     */
    #[Test]
    public function enlace_configuracion_apunta_a_filament(): void
    {
        // El sidebar incluye el enlace a /admin/horarios-centro
        $this->actingAs($this->supervisor)
            ->get('/agenda/supervisor/ausencias')
            ->assertOk()
            ->assertSee('/admin/horarios-centro');
    }
}
