<?php

namespace Modules\Centro\Tests\Feature\Asignacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\ModoAsignacionCentro;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AmbitoTerritorial;
use Modules\Centro\Models\AsignacionCentro;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Organizacion\Models\SeccionCensal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo C: asignación de centro (TF-ASG-09 a TF-ASG-15).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class AsignacionCentroTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    private AsignacionCentroService $servicio;

    /**
     * Monta el escenario común.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->montarEscenarioAsignacion();
        $this->servicio = app(AsignacionCentroService::class);
    }

    /**
     * Entrada abierta de la bandeja para una persona.
     *
     * @param int $ciudadanoId
     * @param TipoAsignacionPendiente $tipo
     * @return AsignacionPendiente|null
     */
    private function pendiente(int $ciudadanoId, TipoAsignacionPendiente $tipo): ?AsignacionPendiente
    {
        return AsignacionPendiente::abiertas()->where('ciudadano_id', $ciudadanoId)->where('tipo', $tipo)->first();
    }

    /**
     * TF-ASG-09 — Asignación geográfica inequívoca al normalizar la dirección.
     *
     * @return void
     */
    #[Test]
    public function la_direccion_inequivoca_asigna_el_centro_por_domicilio(): void
    {
        $this->normalizarEn($this->ana, '2807901001');

        $vigentes = AsignacionCentro::vigentes()->where('ciudadano_id', $this->ana->id)->where('tipo_centro', $this->tipoCss)->get();
        $this->assertCount(1, $vigentes);
        $this->assertSame($this->cssNorte->id, $vigentes->first()->centro_id);
        $this->assertSame(ModoAsignacionCentro::Geografico, $vigentes->first()->modo);
        $this->assertSame('2807901001', $vigentes->first()->seccion_censal_codigo);
        $this->assertNull($vigentes->first()->asignado_por_id);

        // Normalizar otra vez no crea una segunda asignación
        $this->normalizarEn($this->ana, '2807901002');
        $this->assertSame(1, AsignacionCentro::vigentes()->where('ciudadano_id', $this->ana->id)->count());
    }

    /**
     * TF-ASG-10 — Gana la unidad más específica.
     *
     * @return void
     */
    #[Test]
    public function gana_la_unidad_mas_especifica(): void
    {
        $cssEste = $this->crearCentro('CSS Este', $this->tipoCss);
        $this->anadirAmbito($cssEste, 'secciones_censales', SeccionCensal::where('codigo_ine', '2807902001')->value('id'));
        $persona = $this->crearCiudadano('Rosa');

        $this->normalizarEn($persona, '2807902001');

        $this->assertSame($cssEste->id, $this->servicio->vigente($persona, $this->tipoCss)?->centro_id);
    }

    /**
     * TF-ASG-11 — Sin cobertura, la persona va a la bandeja y no tiene centro.
     *
     * @return void
     */
    #[Test]
    public function sin_cobertura_va_a_la_bandeja(): void
    {
        AmbitoTerritorial::where('centro_id', $this->cssSur->id)->where('tipo', 'barrios')->delete();
        $persona = $this->crearCiudadano('Rosa');

        $this->actingAs($this->ts1);
        $this->normalizarEn($persona, '2807901003');

        $this->assertNull($this->servicio->vigente($persona, $this->tipoCss));
        $pendiente = $this->pendiente($persona->id, TipoAsignacionPendiente::SinCentro);
        $this->assertSame(MotivoAsignacionPendiente::SinCobertura, $pendiente?->motivo);
        $this->assertTrue(AsignacionPendiente::visiblesPara($this->cssNorte)->whereKey($pendiente->id)->exists());
    }

    /**
     * TF-ASG-12 — Sin códigos, la persona va a la bandeja con motivo sin_codigos.
     *
     * @return void
     */
    #[Test]
    public function sin_codigos_va_a_la_bandeja(): void
    {
        $persona = $this->normalizarEn($this->crearCiudadano('Rosa'), null, disparar: false);

        $asignacion = $this->servicio->asignarPorDireccion($persona, $this->tipoCss, $this->ts1);

        $this->assertNull($asignacion);
        $this->assertNull($this->servicio->vigente($persona, $this->tipoCss));
        $pendiente = $this->pendiente($persona->id, TipoAsignacionPendiente::SinCentro);
        $this->assertSame(MotivoAsignacionPendiente::SinCodigos, $pendiente?->motivo);
        $this->assertSame($this->cssNorte->id, $pendiente->centro_id);
    }

    /**
     * TF-ASG-13 — La libre elección añade un centro de otro tipo sin tocar el de domicilio.
     *
     * @return void
     */
    #[Test]
    public function la_libre_eleccion_convive_con_el_centro_por_domicilio(): void
    {
        $this->normalizarEn($this->ana, '2807901001');

        $this->servicio->asignarPorEleccion($this->ana, $this->ciam, $this->ts1);

        $vigentes = AsignacionCentro::vigentes()->where('ciudadano_id', $this->ana->id)->get()->keyBy('tipo_centro');
        $this->assertCount(2, $vigentes);
        $this->assertSame($this->cssNorte->id, $vigentes[$this->tipoCss]->centro_id);
        $this->assertSame(ModoAsignacionCentro::Geografico, $vigentes[$this->tipoCss]->modo);
        $this->assertSame($this->ciam->id, $vigentes['ciam']->centro_id);
        $this->assertSame(ModoAsignacionCentro::Eleccion, $vigentes['ciam']->modo);

        $this->expectException(\LogicException::class);
        $this->servicio->asignarPorEleccion($this->ana, $this->cssSur, $this->ts1);
    }

    /**
     * TF-ASG-14 [negativo] — Un cambio de domicilio no traslada: genera una propuesta.
     *
     * @return void
     */
    #[Test]
    public function el_cambio_de_domicilio_no_traslada_y_propone_al_supervisor(): void
    {
        $this->normalizarEn($this->ana, '2807901001');

        $this->normalizarEn($this->ana, '2807901003');

        $this->assertSame($this->cssNorte->id, $this->servicio->vigente($this->ana, $this->tipoCss)?->centro_id);
        $propuesta = $this->pendiente($this->ana->id, TipoAsignacionPendiente::CambioDomicilio);
        $this->assertNotNull($propuesta);
        $this->assertSame($this->cssSur->id, $propuesta->centro_propuesto_id);
        $this->assertTrue(AsignacionPendiente::visiblesPara($this->cssNorte)->whereKey($propuesta->id)->exists());

        $nueva = $this->servicio->confirmarCambioDomicilio($propuesta, 'Traslado de domicilio comprobado', $this->supervisor);

        $anterior = AsignacionCentro::where('ciudadano_id', $this->ana->id)->where('centro_id', $this->cssNorte->id)->first();
        $this->assertNotNull($anterior->fecha_fin);
        $this->assertSame($this->cssSur->id, $nueva->centro_id);
        $this->assertSame(ModoAsignacionCentro::Manual, $nueva->modo);
        $this->assertSame('Traslado de domicilio comprobado', $nueva->motivo);
        $this->assertSame(EstadoAsignacionPendiente::Resuelta, $propuesta->fresh()->estado);
    }

    /**
     * TF-ASG-15 — La asignación manual exige motivo y registra quién la hizo.
     *
     * @return void
     */
    #[Test]
    public function la_asignacion_manual_exige_motivo(): void
    {
        $persona = $this->normalizarEn($this->crearCiudadano('Rosa'), null, disparar: false);
        $this->servicio->asignarPorDireccion($persona, $this->tipoCss, $this->supervisor);

        try {
            $this->servicio->asignarManual($persona, $this->cssNorte, '   ', $this->supervisor);
            $this->fail('Debía exigirse el motivo');
        } catch (\InvalidArgumentException) {
            $this->assertNull($this->servicio->vigente($persona, $this->tipoCss));
        }

        $asignacion = $this->servicio->asignarManual($persona, $this->cssNorte, 'Vive en el barrio, dirección pendiente', $this->supervisor);

        $this->assertSame(ModoAsignacionCentro::Manual, $asignacion->modo);
        $this->assertSame($this->supervisor->id, $asignacion->asignado_por_id);
        $this->assertNull($this->pendiente($persona->id, TipoAsignacionPendiente::SinCentro));
    }
}
