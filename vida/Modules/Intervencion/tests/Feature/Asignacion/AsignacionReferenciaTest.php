<?php

namespace Modules\Intervencion\Tests\Feature\Asignacion;

use App\Models\HistoriaSocial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\AperturaHistoriaService;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Intervencion\Services\Asignacion\SorteoReferenciaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo F: asignación inicial de referencia (TF-ASG-25 a TF-ASG-30).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class AsignacionReferenciaTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /** Semilla con la que el primer sorteo de CSS Norte elige a ts2. */
    private const SEMILLA_TS2 = 1;

    /**
     * Monta el escenario común; Ana ya tiene asignado CSS Norte por domicilio.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->montarEscenarioAsignacion();
        $this->normalizarEn($this->ana, '2807901001');
    }

    /**
     * Abre la historia de un ciudadano con el servicio real de apertura.
     *
     * @param int $ciudadanoId
     * @param \App\Models\User|null $elegido
     * @return HistoriaSocial
     */
    private function abrir(int $ciudadanoId, ?\App\Models\User $elegido = null): HistoriaSocial
    {
        return app(AperturaHistoriaService::class)->abrir($ciudadanoId, $this->ts1, $elegido);
    }

    /**
     * Referencia vigente de una historia.
     *
     * @param HistoriaSocial $historia
     * @return AsignacionProfesional|null
     */
    private function referencia(HistoriaSocial $historia): ?AsignacionProfesional
    {
        return AsignacionProfesional::vigente()->where('historia_id', $historia->id)->first();
    }

    /**
     * TF-ASG-25 — Al abrir la historia en un centro de sorteo, la referencia la decide el sorteo.
     *
     * @return void
     */
    #[Test]
    public function en_un_centro_de_sorteo_la_referencia_la_decide_el_sorteo(): void
    {
        $this->fijarSemilla(self::SEMILLA_TS2);

        $referencia = $this->referencia($this->abrir($this->ana->id));

        $this->assertSame($this->ts2->id, $referencia->profesional_id, 'ts1 no es referencia por haberla abierto');
        $this->assertSame(OrigenAsignacionReferencia::Sorteo, $referencia->origen);
        $this->assertTrue($referencia->cuenta_en_reparto);
        $this->assertSame($this->cssNorte->id, $referencia->centro_id);
        $this->assertSame($this->ts2->id, $referencia->sorteo['elegido']);
    }

    /**
     * TF-ASG-26 — Un centro «quien abre» mantiene el comportamiento anterior.
     *
     * @return void
     */
    #[Test]
    public function un_centro_quien_abre_mantiene_el_comportamiento_anterior(): void
    {
        $this->cssNorte->update(['modo_asignacion_referencia' => ModoAsignacionReferenciaCentro::QuienAbre]);

        $referencia = $this->referencia($this->abrir($this->ana->id));

        $this->assertSame($this->ts1->id, $referencia->profesional_id);
        $this->assertSame(OrigenAsignacionReferencia::QuienAbre, $referencia->origen);
        $this->assertFalse($referencia->cuenta_en_reparto);
        $this->assertNull($referencia->sorteo);
    }

    /**
     * TF-ASG-27 — Libre elección: se asigna el elegido; sin elección, sorteo; un no elegible se rechaza.
     *
     * @return void
     */
    #[Test]
    public function en_libre_eleccion_se_asigna_el_elegido_o_se_sortea(): void
    {
        $this->cssNorte->update(['modo_asignacion_referencia' => ModoAsignacionReferenciaCentro::LibreEleccion]);
        $sinElegir = $this->normalizarEn($this->crearCiudadano('Marta'), '2807901002');
        $conEducador = $this->normalizarEn($this->crearCiudadano('Elena'), '2807901002');

        $elegida = $this->referencia($this->abrir($this->ana->id, $this->ts3));
        $sorteada = $this->referencia($this->abrir($sinElegir->id));

        $this->assertSame($this->ts3->id, $elegida->profesional_id);
        $this->assertSame(OrigenAsignacionReferencia::Eleccion, $elegida->origen);
        $this->assertTrue($elegida->cuenta_en_reparto);
        $this->assertSame(OrigenAsignacionReferencia::Sorteo, $sorteada->origen);

        $this->expectException(\LogicException::class);
        $this->abrir($conEducador->id, $this->educador);
    }

    /**
     * TF-ASG-28 — Las elecciones cuentan en el reparto: a quien eligen mucho le tocan menos.
     *
     * @return void
     */
    #[Test]
    public function la_eleccion_cuenta_en_el_reparto(): void
    {
        $this->cssNorte->update(['modo_asignacion_referencia' => ModoAsignacionReferenciaCentro::LibreEleccion]);
        $servicio = app(AsignacionReferenciaService::class);

        for ($i = 0; $i < 10; $i++) {
            $servicio->asignarInicial($this->historiaDe($this->crearCiudadano("Elige {$i}")), $this->cssNorte, $this->ts2, $this->ts1);
        }

        $resultado = app(SorteoReferenciaService::class)->sortear($this->cssNorte, today());
        $ts1 = collect($resultado->profesionales)->firstWhere('usuario_id', $this->ts1->id);

        $this->assertFalse($ts1['candidato']);
        $this->assertSame(10, $ts1['recibido']);
        $this->assertNotSame($this->ts1->id, $resultado->elegido->id);
    }

    /**
     * TF-ASG-29 — Quien se incorpora a una unidad de convivencia recibe la referencia de sus miembros.
     *
     * @return void
     */
    #[Test]
    public function la_unidad_de_convivencia_comparte_referencia(): void
    {
        AsignacionProfesional::create([
            'historia_id' => $this->historiaDe($this->pedro)->id,
            'profesional_id' => $this->ts2->id,
            'centro_id' => $this->cssNorte->id,
            'origen' => OrigenAsignacionReferencia::Sorteo,
            'cuenta_en_reparto' => true,
            'fecha_inicio' => today()->subMonth(),
        ]);
        $this->normalizarEn($this->lucia, '2807901001');

        $referencia = $this->referencia($this->abrir($this->lucia->id));

        $this->assertSame($this->ts2->id, $referencia->profesional_id);
        $this->assertSame(OrigenAsignacionReferencia::UnidadConvivencia, $referencia->origen);
        $this->assertFalse($referencia->cuenta_en_reparto);
        $this->assertNull($referencia->sorteo);
    }

    /**
     * TF-ASG-30 [negativo] — Solo el supervisor cambia la referencia, con motivo y sin reescribir el pasado.
     *
     * @return void
     */
    #[Test]
    public function solo_el_supervisor_cambia_la_referencia(): void
    {
        $historia = $this->historiaDe($this->ana);
        $anterior = AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $this->ts2->id,
            'centro_id' => $this->cssNorte->id,
            'origen' => OrigenAsignacionReferencia::Sorteo,
            'cuenta_en_reparto' => true,
            'fecha_inicio' => today()->subMonth(),
        ]);
        $servicio = app(AsignacionReferenciaService::class);

        try {
            $servicio->cambiarManual($historia, $this->ts3, 'Me lo quito', $this->ts2);
            $this->fail('Un profesional no puede cambiar la referencia');
        } catch (AuthorizationException) {
            $this->assertNull($anterior->fresh()->fecha_fin);
        }

        $nueva = $servicio->cambiarManual($historia, $this->ts3, 'Parentesco con la profesional', $this->supervisor);

        $anterior->refresh();
        $this->assertNotNull($anterior->fecha_fin);
        $this->assertSame($this->ts2->id, $anterior->profesional_id);
        $this->assertSame($this->ts3->id, $nueva->profesional_id);
        $this->assertSame(OrigenAsignacionReferencia::Manual, $nueva->origen);
        $this->assertFalse($nueva->cuenta_en_reparto);
        $this->assertSame('Parentesco con la profesional', $nueva->motivo);
        $this->assertSame(1, AsignacionProfesional::vigente()->where('historia_id', $historia->id)->count());
    }
}
