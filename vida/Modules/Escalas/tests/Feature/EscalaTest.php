<?php

namespace Modules\Escalas\Tests\Feature;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Escalas\Database\Seeders\EscalaSeeder;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Database\Factories\FichaFactory;
use Modules\Intervencion\Models\Ficha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Módulo Escalas (TF-ESC-A01 … TF-ESC-C04).
 *
 * @see docs/modulo-escala.md §9
 */
class EscalaTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;
    private User $profesional;
    private HistoriaSocial $historia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uo = UnidadOrganizativa::create([
            'nombre'    => 'UO Test Escalas',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->profesional = User::factory()->create();

        $this->historia = HistoriaSocial::create([
            'ciudadano_id'           => 1001,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);
    }

    // =========================================================================
    // Grupo A — Integridad de configuración
    // =========================================================================

    /**
     * TF-ESC-A01 — TipoEscala con schema JSON inválido no puede guardarse.
     */
    #[Test]
    public function tipo_escala_con_schema_invalido_no_puede_guardarse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TipoEscala::create([
            'nombre'                => 'Escala Prueba',
            'codigo'                => 'prueba_invalida',
            'schema'                => ['sin_secciones' => []],
            'rangos_interpretacion' => [
                'rangos' => [['desde' => 0, 'hasta' => 100, 'etiqueta' => 'Total', 'codigo' => 'total']],
            ],
        ]);

        $this->assertDatabaseCount('tipo_escalas', 0);
    }

    /**
     * TF-ESC-A02 — TipoEscala con rangos solapados no puede guardarse.
     */
    #[Test]
    public function tipo_escala_con_rangos_solapados_no_puede_guardarse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TipoEscala::create([
            'nombre' => 'Escala Solapada',
            'codigo' => 'solapada',
            'schema' => $this->schemaMinimo(),
            'rangos_interpretacion' => [
                'rangos' => [
                    ['desde' => 0,  'hasta' => 60, 'etiqueta' => 'Bajo',  'codigo' => 'bajo'],
                    ['desde' => 55, 'hasta' => 100, 'etiqueta' => 'Alto', 'codigo' => 'alto'],
                ],
            ],
        ]);

        $this->assertDatabaseCount('tipo_escalas', 0);
    }

    /**
     * TF-ESC-A03 — TipoEscala con rangos con huecos no puede guardarse.
     */
    #[Test]
    public function tipo_escala_con_rangos_con_huecos_no_puede_guardarse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TipoEscala::create([
            'nombre' => 'Escala Con Hueco',
            'codigo' => 'con_hueco',
            'schema' => $this->schemaMinimo(),
            'rangos_interpretacion' => [
                'rangos' => [
                    ['desde' => 0,  'hasta' => 8,  'etiqueta' => 'Bajo',  'codigo' => 'bajo'],
                    ['desde' => 11, 'hasta' => 20, 'etiqueta' => 'Alto',  'codigo' => 'alto'],
                ],
            ],
        ]);

        $this->assertDatabaseCount('tipo_escalas', 0);
    }

    /**
     * TF-ESC-A04 — TipoEscala inactiva no aparece en scope aplicables.
     */
    #[Test]
    public function tipo_escala_inactiva_no_aparece_en_scope_aplicables(): void
    {
        TipoEscala::factory()->create(['activa' => true]);
        TipoEscala::factory()->inactiva()->create();

        $aplicables = TipoEscala::aplicables()->get();

        $this->assertCount(1, $aplicables);
        $this->assertTrue($aplicables->first()->activa);
    }

    /**
     * TF-ESC-A05 — Codigo de TipoEscala es inmutable si existen pases asociados.
     */
    #[Test]
    public function codigo_de_tipo_escala_es_inmutable_si_existen_pases(): void
    {
        $escala = TipoEscala::factory()->create(['codigo' => 'barthel_test']);

        PaseEscala::factory()->create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
        ]);

        $this->expectException(\LogicException::class);

        $escala->update(['codigo' => 'barthel_v2']);

        $this->assertSame('barthel_test', $escala->fresh()->codigo);
    }

    /**
     * TF-ESC-A06 — Schema de ítem existente es inmutable si existen pases asociados.
     */
    #[Test]
    public function schema_de_item_existente_es_inmutable_si_existen_pases(): void
    {
        $escala = TipoEscala::factory()->create();

        PaseEscala::factory()->create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'respuestas'     => ['sec_1' => ['item_1_1' => 5, 'item_1_2' => 10]],
        ]);

        $schemaModificado = $escala->schema;
        $schemaModificado['secciones'][0]['items'][0]['opciones'] = [
            ['valor' => 0, 'etiqueta' => 'Nada'],
            ['valor' => 99, 'etiqueta' => 'Todo'],
        ];

        $this->expectException(\LogicException::class);

        $escala->update(['schema' => $schemaModificado]);
    }

    // =========================================================================
    // Grupo B — Comportamiento del pase
    // =========================================================================

    /**
     * TF-ESC-B01 — PaseEscala en borrador puede tener ítems sin respuesta.
     */
    #[Test]
    public function pase_en_borrador_puede_tener_items_sin_respuesta(): void
    {
        $escala = TipoEscala::factory()->create();

        $pase = PaseEscala::create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => today(),
            'respuestas'     => ['sec_1' => ['item_1_1' => 5]],
            'estado'         => EstadoPase::Borrador,
        ]);

        $this->assertDatabaseHas('pases_escala', ['id' => $pase->id]);
        $this->assertNull($pase->score_total);
    }

    /**
     * TF-ESC-B02 — PaseEscala completado requiere respuesta para todos los ítems.
     */
    #[Test]
    public function completar_pase_falla_si_faltan_respuestas(): void
    {
        $escala = TipoEscala::factory()->create();

        $pase = PaseEscala::create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => today(),
            'respuestas'     => ['sec_1' => ['item_1_1' => 5]],
            'estado'         => EstadoPase::Borrador,
        ]);

        $this->expectException(\LogicException::class);

        $pase->completar();

        $this->assertSame(EstadoPase::Borrador, $pase->fresh()->estado);
    }

    /**
     * TF-ESC-B03 — calcularScores suma correctamente todos los valores.
     */
    #[Test]
    public function calcular_scores_suma_correctamente(): void
    {
        $escala = TipoEscala::factory()->create();

        $pase = new PaseEscala([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => today(),
            'respuestas'     => ['sec_1' => ['item_1_1' => 10, 'item_1_2' => 5]],
            'estado'         => EstadoPase::Borrador,
        ]);

        $pase->calcularScores();

        $this->assertSame(15, $pase->score_total);
    }

    /**
     * TF-ESC-B04 — calcularScores produce scores_seccion correctos por sección.
     */
    #[Test]
    public function calcular_scores_produce_scores_por_seccion(): void
    {
        $escala = TipoEscala::factory()->create([
            'schema' => [
                'secciones' => [
                    [
                        'id'     => 'sec_1',
                        'titulo' => 'Sección 1',
                        'orden'  => 1,
                        'items'  => [
                            ['id' => 'item_1_1', 'texto' => 'Ítem 1', 'orden' => 1, 'opciones' => [['valor' => 0], ['valor' => 5], ['valor' => 10]]],
                            ['id' => 'item_1_2', 'texto' => 'Ítem 2', 'orden' => 2, 'opciones' => [['valor' => 0], ['valor' => 5], ['valor' => 10]]],
                        ],
                    ],
                    [
                        'id'     => 'sec_2',
                        'titulo' => 'Sección 2',
                        'orden'  => 2,
                        'items'  => [
                            ['id' => 'item_2_1', 'texto' => 'Ítem 3', 'orden' => 1, 'opciones' => [['valor' => 0], ['valor' => 5], ['valor' => 10]]],
                        ],
                    ],
                ],
            ],
            'rangos_interpretacion' => [
                'rangos' => [
                    ['desde' => 0,  'hasta' => 15, 'etiqueta' => 'Bajo', 'codigo' => 'bajo'],
                    ['desde' => 16, 'hasta' => 30, 'etiqueta' => 'Alto', 'codigo' => 'alto'],
                ],
            ],
        ]);

        $pase = new PaseEscala([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => today(),
            'respuestas'     => ['sec_1' => ['item_1_1' => 10, 'item_1_2' => 5], 'sec_2' => ['item_2_1' => 0]],
            'estado'         => EstadoPase::Borrador,
        ]);

        $pase->calcularScores();

        $this->assertSame(['sec_1' => 15, 'sec_2' => 0], $pase->scores_seccion);
    }

    /**
     * TF-ESC-B05 — interpretacion_codigo se asigna correctamente según score_total.
     */
    #[Test]
    public function interpretacion_codigo_se_asigna_segun_score_total(): void
    {
        $escala = TipoEscala::factory()->create([
            'rangos_interpretacion' => [
                'rangos' => [
                    ['desde' => 0,  'hasta' => 10, 'etiqueta' => 'Bajo', 'codigo' => 'bajo'],
                    ['desde' => 11, 'hasta' => 20, 'etiqueta' => 'Alto', 'codigo' => 'alto'],
                ],
            ],
        ]);

        $pase = new PaseEscala([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => today(),
            'respuestas'     => [],
            'estado'         => EstadoPase::Borrador,
            'score_total'    => 15,
        ]);
        $pase->setRelation('tipoEscala', $escala);

        $pase->asignarInterpretacion();

        $this->assertSame('alto', $pase->interpretacion_codigo);
    }

    /**
     * TF-ESC-B06 — Score total de pase completado es inmutable.
     */
    #[Test]
    public function score_total_de_pase_completado_es_inmutable(): void
    {
        $pase = PaseEscala::factory()->completado()->create([
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'score_total'    => 15,
        ]);

        $this->expectException(\LogicException::class);

        $pase->update(['score_total' => 80]);

        $this->assertSame(15, $pase->fresh()->score_total);
    }

    /**
     * TF-ESC-B07 — PaseEscala sin ficha_id ni entrevista_id es válido.
     */
    #[Test]
    public function pase_sin_ficha_ni_entrevista_es_valido(): void
    {
        $escala = TipoEscala::factory()->create();

        $pase = PaseEscala::create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => today(),
            'respuestas'     => [],
            'estado'         => EstadoPase::Borrador,
            'ficha_id'       => null,
            'entrevista_id'  => null,
        ]);

        $this->assertDatabaseHas('pases_escala', [
            'id'            => $pase->id,
            'ficha_id'      => null,
            'entrevista_id' => null,
        ]);
    }

    /**
     * TF-ESC-B08 — PaseEscala con ficha_id mantiene referencia al completarse la ficha.
     */
    #[Test]
    public function pase_con_ficha_id_mantiene_referencia_al_completarse_la_ficha(): void
    {
        $ficha = Ficha::factory()->create();

        $escala = TipoEscala::factory()->create();

        $pase = PaseEscala::create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => today(),
            'respuestas'     => [],
            'estado'         => EstadoPase::Borrador,
            'ficha_id'       => $ficha->id,
        ]);

        $ficha->update(['completada' => true]);

        $this->assertSame($ficha->id, $pase->fresh()->ficha_id);
    }

    // =========================================================================
    // Grupo C — Historial, consultas y seeder
    // =========================================================================

    /**
     * TF-ESC-C01 — Historia Social devuelve pases en orden cronológico.
     */
    #[Test]
    public function historia_social_devuelve_pases_en_orden_cronologico(): void
    {
        $escala = TipoEscala::factory()->create();

        PaseEscala::factory()->completado()->create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => '2025-06-15',
        ]);
        PaseEscala::factory()->completado()->create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => '2026-01-20',
        ]);
        PaseEscala::factory()->completado()->create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
            'fecha'          => '2025-01-10',
        ]);

        $pases = $this->historia
            ->pasesEscala()
            ->where('tipo_escala_id', $escala->id)
            ->orderBy('fecha')
            ->get();

        $this->assertCount(3, $pases);
        $this->assertSame('2025-01-10', $pases->first()->fecha->toDateString());
        $this->assertSame('2026-01-20', $pases->last()->fecha->toDateString());
    }

    /**
     * TF-ESC-C02 — Pases de distintos ciudadanos no se mezclan en el historial.
     */
    #[Test]
    public function pases_de_distintos_ciudadanos_no_se_mezclan(): void
    {
        $historia2 = HistoriaSocial::create([
            'ciudadano_id'           => 2002,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        $escala = TipoEscala::factory()->create();

        PaseEscala::factory()->completado()->count(2)->create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $this->historia->id,
            'profesional_id' => $this->profesional->id,
        ]);
        PaseEscala::factory()->completado()->count(2)->create([
            'tipo_escala_id' => $escala->id,
            'historia_id'    => $historia2->id,
            'profesional_id' => $this->profesional->id,
        ]);

        $pasesCiudadano1 = $this->historia->pasesEscala()->get();

        $this->assertCount(2, $pasesCiudadano1);
        $pasesCiudadano1->each(
            fn ($pase) => $this->assertSame($this->historia->id, $pase->historia_id)
        );
    }

    /**
     * TF-ESC-C03 — Seeder produce los tres TipoEscala esperados.
     */
    #[Test]
    public function seeder_produce_los_tres_tipo_escala_esperados(): void
    {
        $this->seed(EscalaSeeder::class);

        $this->assertSame(3, TipoEscala::count());

        foreach (['barthel', 'pfeiffer_spmsq', 'lawton_brody'] as $codigo) {
            $escala = TipoEscala::where('codigo', $codigo)->first();
            $this->assertNotNull($escala, "Falta TipoEscala con codigo '{$codigo}'");
            $this->assertTrue($escala->activa);
            $this->assertNotEmpty($escala->schema['secciones']);
            $this->assertNotEmpty($escala->schema['secciones'][0]['items']);
            $this->assertNotEmpty($escala->rangos_interpretacion['rangos']);
        }
    }

    /**
     * TF-ESC-C04 — Seeder es idempotente: segunda ejecución no duplica registros.
     */
    #[Test]
    public function seeder_es_idempotente(): void
    {
        $this->seed(EscalaSeeder::class);
        $this->seed(EscalaSeeder::class);

        $this->assertSame(3, TipoEscala::count());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Schema mínimo válido con 1 sección, 2 ítems, 2 opciones cada uno. */
    private function schemaMinimo(): array
    {
        return [
            'secciones' => [
                [
                    'id'    => 'sec_1',
                    'titulo' => 'Sección',
                    'orden'  => 1,
                    'items' => [
                        [
                            'id'       => 'item_1',
                            'texto'    => 'Ítem 1',
                            'orden'    => 1,
                            'opciones' => [
                                ['valor' => 0, 'etiqueta' => 'No'],
                                ['valor' => 1, 'etiqueta' => 'Sí'],
                            ],
                        ],
                        [
                            'id'       => 'item_2',
                            'texto'    => 'Ítem 2',
                            'orden'    => 2,
                            'opciones' => [
                                ['valor' => 0, 'etiqueta' => 'No'],
                                ['valor' => 1, 'etiqueta' => 'Sí'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
