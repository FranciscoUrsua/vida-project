<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Models\SiaContacto;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo F: Contacto SIA.
 *
 * @see docs/modulo-intervencion.md §2, §10 Grupo F
 */
class ContactoSiaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-F01: Registro SIA con clasificacion otra_administracion no requiere urgencia.
     */
    #[Test]
    public function registro_sia_otra_administracion_no_requiere_urgencia(): void
    {
        $ciudadano = Ciudadano::factory()->create();
        $auxiliar = User::factory()->create();

        $contacto = SiaContacto::create([
            'ciudadano_id' => $ciudadano->id,
            'auxiliar_id' => $auxiliar->id,
            'fecha_hora' => now()->toDateTimeString(),
            'canal' => 'presencial',
            'descripcion_demanda' => 'Consulta sobre ayuda de dependencia autonómica.',
            'clasificacion' => ClasificacionSia::OtraAdministracion,
            'informacion_prestada' => 'Se informa del trámite en la Comunidad de Madrid.',
            'urgencia' => null,
        ]);

        $this->assertNotNull($contacto->id, 'El registro SIA debe guardarse sin errores');
        $this->assertNull($contacto->urgencia, 'La urgencia debe ser null para competencia de otra administración');
    }

    /**
     * TF-INT-F02: Scope competenciaMunicipal filtra correctamente.
     */
    #[Test]
    public function scope_competencia_municipal_filtra_correctamente(): void
    {
        $ciudadano = Ciudadano::factory()->create();
        $auxiliar = User::factory()->create();

        $base = [
            'ciudadano_id' => $ciudadano->id,
            'auxiliar_id' => $auxiliar->id,
            'fecha_hora' => now()->toDateTimeString(),
            'canal' => 'presencial',
            'descripcion_demanda' => 'Descripción de prueba',
        ];

        SiaContacto::create(array_merge($base, ['clasificacion' => ClasificacionSia::CompetenciaMunicipal, 'urgencia' => 'ordinario']));
        SiaContacto::create(array_merge($base, ['clasificacion' => ClasificacionSia::CompetenciaMunicipal, 'urgencia' => 'prioritario']));
        SiaContacto::create(array_merge($base, ['clasificacion' => ClasificacionSia::OtraAdministracion,   'urgencia' => null]));

        $this->assertEquals(
            2,
            SiaContacto::competenciaMunicipal()->count(),
            'El scope competenciaMunicipal debe devolver solo los 2 registros de competencia municipal'
        );
    }
}
