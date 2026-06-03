<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Enums\EstadoValoracion;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\Valoracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo E: Entrevista.
 *
 * @see docs/modulo-intervencion.md §3, §10 Grupo E
 */
class EntrevistaTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $profesional;

    private HistoriaSocial $historia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Entrevista',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->profesional = User::factory()->create();

        $this->historia = HistoriaSocial::create([
            'ciudadano_id' => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-E01: Entrevista sin cita_id es válida.
     */
    #[Test]
    public function entrevista_sin_cita_id_es_valida(): void
    {
        $entrevista = Entrevista::create([
            'historia_id' => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'cita_id' => null,
            'fecha_hora' => now()->toDateTimeString(),
            'modalidad' => 'domicilio',
            'tipo' => 'urgencia',
            'estado' => 'realizada',
        ]);

        $this->assertNotNull($entrevista->id, 'La entrevista sin cita_id debe guardarse sin errores');
        $this->assertNull($entrevista->cita_id);
    }

    /**
     * TF-INT-E02: Entrevista puede existir sin plan asociado.
     */
    #[Test]
    public function entrevista_puede_existir_sin_plan_asociado(): void
    {
        $entrevista = Entrevista::create([
            'historia_id' => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'cita_id' => null,
            'plan_intervencion_id' => null,
            'fecha_hora' => now()->toDateTimeString(),
            'modalidad' => 'presencial',
            'tipo' => 'inicial',
            'estado' => 'realizada',
        ]);

        $this->assertNotNull($entrevista->id, 'La entrevista debe guardarse sin errores');
        $this->assertNull($entrevista->planDeIntervencion, '$entrevista->planDeIntervencion debe devolver null');
    }

    /**
     * TF-INT-E03: Entrevista de tipo inicial puede generar valoración.
     */
    #[Test]
    public function entrevista_inicial_puede_generar_valoracion(): void
    {
        $tipoValoracion = TipoValoracion::create([
            'nombre' => 'Valoración inicial ASP',
            'contexto' => 'ASP',
            'activo' => true,
        ]);

        $entrevista = Entrevista::create([
            'historia_id' => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'cita_id' => null,
            'fecha_hora' => now()->toDateTimeString(),
            'modalidad' => 'presencial',
            'tipo' => 'inicial',
            'estado' => 'realizada',
        ]);

        $valoracion = Valoracion::create([
            'historia_id' => $this->historia->id,
            'entrevista_id' => $entrevista->id,
            'profesional_id' => $this->profesional->id,
            'tipo_valoracion_id' => $tipoValoracion->id,
            'fecha' => today()->toDateString(),
            'estado' => EstadoValoracion::Borrador,
        ]);

        $this->assertEquals(
            $valoracion->id,
            $entrevista->valoracion->id,
            '$entrevista->valoracion debe devolver la valoración creada'
        );

        $this->assertEquals(
            $entrevista->id,
            $valoracion->entrevista->id,
            '$valoracion->entrevista debe devolver la entrevista de origen'
        );
    }
}
