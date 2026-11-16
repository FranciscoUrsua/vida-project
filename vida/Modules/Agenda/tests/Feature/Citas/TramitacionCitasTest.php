<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Livewire\Citas\BandejaCitacionPage;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Ciudadania\Tests\Concerns\ConsultaBasicaTestSetup;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Citas para `tramitacion` (fase 4, paso 5; docs/modulo-citas.md §7).
 *
 * TF-CBA-27 y 28.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class TramitacionCitasTest extends TestCase
{
    use ConsultaBasicaTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario de la fase.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarConsultaBasica();
    }

    /**
     * Datos de una cita directa para Pedro en un centro con un profesional concreto.
     *
     * @param int $centroId
     * @param int $profesionalId
     *
     * @return array<string, mixed>
     */
    private function datosCita(int $centroId, int $profesionalId): array
    {
        return [
            'ciudadano_id' => $this->pedro->id,
            'centro_id' => $centroId,
            'tipo_cita_id' => TipoCita::generico()->id,
            'urgencia' => 'ordinaria',
            'destino' => 'profesional_concreto',
            'profesional_destino_id' => $profesionalId,
        ];
    }

    /** TF-CBA-27 — Tramitación gestiona citas en su UO, no en otra. */
    #[Test]
    public function tf_cba_27_tramitacion_gestiona_citas(): void
    {
        Livewire::actingAs($this->adminNorte)->test(BandejaCitacionPage::class)->assertOk();
        $this->actingAs($this->adminNorte)->get(route('agenda.citas.bandeja'))->assertOk()->assertSee('Citación');

        $citacion = app(CitacionService::class);
        $hueco = $this->slotEn($this->tsNorte, $this->cssNorte, '2026-11-04', '10:00');
        $otroHueco = $this->slotEn($this->tsNorte, $this->cssNorte, '2026-11-04', '11:00');

        $cita = $citacion->citarDirecto($this->datosCita($this->cssNorte->id, $this->tsNorte->id), $hueco, ModoAsignacionCita::ProfesionalConcreto, $this->adminNorte);
        $this->assertSame(EstadoCita::Confirmada, $cita->estado);

        $nueva = $citacion->reprogramar($cita, $otroHueco, PedidoPor::Ciudadano, 'Prefiere más tarde', $this->adminNorte);
        $this->assertSame(EstadoCita::Reprogramada, $cita->fresh()->estado);

        $citacion->cancelar($nueva, PedidoPor::Ciudadano, 'Ya no la necesita', $this->adminNorte);
        $this->assertSame(EstadoCita::Cancelada, $nueva->fresh()->estado);

        // En el CIAM de Vallecas (otra UO), no
        $huecoCiam = $this->slotEn($this->psicologaVallecas, $this->ciamVallecas, '2026-11-04', '17:00');
        try {
            $citacion->citarDirecto($this->datosCita($this->ciamVallecas->id, $this->psicologaVallecas->id), $huecoCiam, ModoAsignacionCita::ProfesionalConcreto, $this->adminNorte);
            $this->fail('Tramitación no da citas fuera de su UO.');
        } catch (AuthorizationException|\DomainException) {
            // RN-15 o la policy lo rechazan
        }
        try {
            $citacion->cancelar($this->citaFuturaCiam, PedidoPor::Ciudadano, 'Intento', $this->adminNorte);
            $this->fail('Tramitación no cancela citas fuera de su UO.');
        } catch (AuthorizationException|\DomainException) {
            // RN-15 o la policy lo rechazan
        }
        $this->assertSame(EstadoCita::Confirmada, $this->citaFuturaCiam->fresh()->estado);
    }

    /** TF-CBA-28 — La solicitud de cita nueva avisa también a tramitación del centro. */
    #[Test]
    public function tf_cba_28_avisos_de_solicitud_a_tramitacion(): void
    {
        app(SolicitudCitaService::class)->crear($this->datosCita($this->cssNorte->id, $this->tsNorte->id), $this->tsNorte);

        $this->assertTrue(Alerta::pendientesPara($this->adminNorte)->where('titulo', 'Nueva solicitud de cita')->exists());
        $this->assertTrue(Alerta::pendientesPara($this->ventanillaNorte)->where('titulo', 'Nueva solicitud de cita')->exists());
        // Tramitación de otro centro no lo recibe
        $adminSur = $this->profesionalCon('admin.sur', 'tramitacion', $this->cssSur, 'Auxiliar administrativo');
        $this->assertFalse(Alerta::pendientesPara($adminSur)->where('titulo', 'Nueva solicitud de cita')->exists());
    }
}
