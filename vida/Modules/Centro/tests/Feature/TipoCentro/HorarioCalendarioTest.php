<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Agenda\Database\Factories\SlotFactory;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\CuadranteGeneratorService;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\HorarioTipoCentro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Centro\Services\TipoCentro\CierreCalendarioService;
use Modules\Centro\Services\TipoCentro\TipoCentroService;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Horario y calendario (TF-TCE-12 a 19).
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class HorarioCalendarioTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario común de Tipo de Centro.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
    }

    /**
     * Servicio bajo prueba.
     */
    private function config(): ConfiguracionEfectivaCentro
    {
        return app(ConfiguracionEfectivaCentro::class);
    }

    /**
     * Horario de agenda del centro que hereda el del tipo.
     */
    private function horarioHeredado(Centro $centro): HorarioCentro
    {
        return HorarioCentro::create(['centro_id' => $centro->id, 'nombre' => 'Agenda', 'vigente_desde' => '2026-01-01', 'buffer_inicio_minutos' => 15]);
    }

    /**
     * Horario propio L-J 8:00-15:00 para `$cssSur`.
     */
    private function horarioPropioSur(): HorarioCentro
    {
        return HorarioCentro::create([
            'centro_id' => $this->cssSur->id, 'nombre' => 'Propio', 'vigente_desde' => '2026-01-01',
            'dias_laborables' => [1, 2, 3, 4], 'hora_apertura' => '08:00', 'hora_cierre' => '15:00',
            'hora_inicio_atencion' => '08:00', 'hora_fin_atencion' => '15:00', 'buffer_inicio_minutos' => 10,
        ]);
    }

    /** TF-TCE-12 — Un centro que hereda tiene el horario del tipo. */
    #[Test]
    public function tf_tce_12_herencia_del_horario(): void
    {
        $horario = $this->horarioHeredado($this->cssNorte);

        $efectivo = $this->config()->horario($this->cssNorte, today());
        $this->assertTrue($horario->fresh()->hereda_horario_tipo);
        $this->assertSame([1, 2, 3, 4, 5], $efectivo->diasLaborables);
        $this->assertSame(['09:00', '14:00'], [$efectivo->apertura, $efectivo->cierre]);
        $this->assertSame('tipo', $this->config()->origen($this->cssNorte, 'horario'));
    }

    /** TF-TCE-13 — Un horario propio sustituye entero al del tipo. */
    #[Test]
    public function tf_tce_13_horario_propio_sustituye_entero(): void
    {
        $this->horarioPropioSur();

        $this->assertFalse($this->config()->dia($this->cssSur, Carbon::parse('2026-11-06'))->abierto); // viernes
        $jueves = $this->config()->dia($this->cssSur, Carbon::parse('2026-11-05'));
        $this->assertSame(['08:00', '15:00'], [$jueves->apertura, $jueves->cierre]);
        $this->assertSame('centro', $this->config()->origen($this->cssSur, 'horario'));
    }

    /** TF-TCE-14 — Un horario nuevo del tipo llega a quien hereda y no a quien tiene horario propio. */
    #[Test]
    public function tf_tce_14_propagacion_solo_a_quien_hereda(): void
    {
        $this->horarioHeredado($this->cssNorte);
        $this->horarioPropioSur();

        app(TipoCentroService::class)->programarHorario($this->tipoCss, [
            'dias_laborables' => [1, 2, 3, 4, 5], 'hora_apertura' => '08:30', 'hora_cierre' => '14:30',
            'hora_inicio_atencion' => '08:30', 'hora_fin_atencion' => '14:30',
        ], today(), $this->admin);

        $norte = $this->config()->horario($this->cssNorte, today());
        $sur = $this->config()->horario($this->cssSur, today());
        $this->assertSame(['08:30', '14:30'], [$norte->apertura, $norte->cierre]);
        $this->assertSame(['08:00', '15:00'], [$sur->apertura, $sur->cierre]);
    }

    /** TF-TCE-15 — Volver al valor del tipo vacía el horario propio y conserva la agenda. */
    #[Test]
    public function tf_tce_15_volver_al_valor_del_tipo(): void
    {
        $horario = $this->horarioPropioSur();

        $this->actingAs($this->directoraSur);
        $horario->volverAlTipo();

        $horario->refresh();
        $this->assertTrue($horario->hereda_horario_tipo);
        foreach (HorarioCentro::CAMPOS_HORARIO as $campo) {
            $this->assertNull($horario->{$campo}, $campo);
        }
        $this->assertSame(10, $horario->buffer_inicio_minutos);
        $efectivo = $this->config()->horario($this->cssSur, today());
        $this->assertSame(['tipo', '09:00', '14:00'], [$efectivo->origen, $efectivo->apertura, $efectivo->cierre]);
    }

    /** TF-TCE-16 — Vigencias del horario del tipo, sin solape. */
    #[Test]
    public function tf_tce_16_vigencias_del_horario_del_tipo(): void
    {
        app(TipoCentroService::class)->programarHorario($this->tipoCss, [
            'nombre' => 'Verano', 'dias_laborables' => [1, 2, 3, 4, 5], 'hora_apertura' => '08:00', 'hora_cierre' => '13:00',
            'hora_inicio_atencion' => '08:00', 'hora_fin_atencion' => '13:00',
        ], Carbon::parse('2027-07-01'), $this->admin);

        $this->assertSame('09:00', $this->config()->horario($this->cssNorte, Carbon::parse('2027-06-30'))->apertura);
        $this->assertSame('08:00', $this->config()->horario($this->cssNorte, Carbon::parse('2027-07-01'))->apertura);

        $this->expectException(DomainException::class);
        HorarioTipoCentro::create([
            'tipo_centro_id' => $this->tipoCss->id, 'nombre' => 'Solapado', 'dias_laborables' => [1],
            'hora_apertura' => '10:00', 'hora_cierre' => '11:00', 'hora_inicio_atencion' => '10:00', 'hora_fin_atencion' => '11:00',
            'vigente_desde' => '2027-06-01', 'vigente_hasta' => '2027-07-15',
        ]);
    }

    /**
     * Calendario de TF-TCE-17: en el tipo, cierre el 9 y reducido 9-12 el 10; en `$cssNorte`, reducido 10-13 el 9.
     */
    private function calendarioDeNoviembre(): void
    {
        $cierres = app(CierreCalendarioService::class);
        $cierres->crear($this->tipoCss, ['fecha_desde' => '2026-11-09', 'fecha_hasta' => '2026-11-09', 'tipo' => 'cierre', 'motivo' => 'Festivo'], $this->admin);
        $cierres->crear($this->tipoCss, ['fecha_desde' => '2026-11-10', 'fecha_hasta' => '2026-11-10', 'tipo' => 'horario_reducido', 'hora_inicio' => '09:00', 'hora_fin' => '12:00', 'motivo' => 'Jornada reducida'], $this->admin);
        $cierres->crear($this->cssNorte, ['fecha_desde' => '2026-11-09', 'fecha_hasta' => '2026-11-09', 'tipo' => 'horario_reducido', 'hora_inicio' => '10:00', 'hora_fin' => '13:00', 'motivo' => 'Abre por la mañana'], $this->admin);
    }

    /** TF-TCE-17 — Cierres acumulables: manda el del centro sobre el del tipo, y el del tipo sobre el horario. */
    #[Test]
    public function tf_tce_17_cierres_acumulables_y_prioridad(): void
    {
        $this->calendarioDeNoviembre();
        $dia = fn (Centro $c, string $f) => $this->config()->dia($c, Carbon::parse($f));

        $this->assertSame([true, '10:00', '13:00'], [$dia($this->cssNorte, '2026-11-09')->abierto, $dia($this->cssNorte, '2026-11-09')->apertura, $dia($this->cssNorte, '2026-11-09')->cierre]);
        $this->assertSame(['09:00', '12:00'], [$dia($this->cssNorte, '2026-11-10')->apertura, $dia($this->cssNorte, '2026-11-10')->cierre]);
        $this->assertSame(['09:00', '14:00', 'horario'], [$dia($this->cssNorte, '2026-11-11')->apertura, $dia($this->cssNorte, '2026-11-11')->cierre, $dia($this->cssNorte, '2026-11-11')->origen]);

        $this->assertFalse($dia($this->cssSur, '2026-11-09')->abierto);
        $this->assertSame(['09:00', '12:00'], [$dia($this->cssSur, '2026-11-10')->apertura, $dia($this->cssSur, '2026-11-10')->cierre]);
        $this->assertSame(['09:00', '14:00'], [$dia($this->cssSur, '2026-11-11')->apertura, $dia($this->cssSur, '2026-11-11')->cierre]);
    }

    /** TF-TCE-18 — La generación de slots usa el calendario efectivo. */
    #[Test]
    public function tf_tce_18_la_generacion_de_slots_usa_el_calendario_efectivo(): void
    {
        $this->calendarioDeNoviembre();
        $horario = HorarioCentro::create(['centro_id' => $this->cssSur->id, 'nombre' => 'Agenda', 'vigente_desde' => '2026-01-01']);
        $horario->tiposSlot()->attach(TipoSlot::factory()->create(['duracion_minutos' => 60, 'porcentaje_urgencias' => 0]));
        PerfilHorarioProfesional::factory()->create([
            'usuario_id' => $this->psicologa->id, 'centro_id' => $this->cssSur->id, 'vigente_desde' => '2026-01-01',
            'horario_habitual' => array_fill_keys(['1', '2', '3', '4', '5'], [['inicio' => '09:00', 'fin' => '14:00']]),
        ]);

        app(CuadranteGeneratorService::class)->generarYPublicarAutomaticamente($this->cssSur, 2026, 11);

        $slots = fn (string $fecha) => Slot::where('centro_id', $this->cssSur->id)->whereDate('fecha', $fecha)->orderBy('hora_inicio')->get();
        $this->assertCount(0, $slots('2026-11-09'));
        $dia10 = $slots('2026-11-10');
        $this->assertCount(3, $dia10);
        $this->assertSame('09:00', substr($dia10->first()->hora_inicio, 0, 5));
        $this->assertSame('12:00', substr($dia10->last()->hora_fin, 0, 5));
        $this->assertCount(5, $slots('2026-11-11'));
    }

    /** TF-TCE-19 — Un cierre no cancela citas ni cambia slots; avisa de las afectadas. */
    #[Test]
    public function tf_tce_19_un_cierre_no_cancela_citas(): void
    {
        $citas = collect(['10:00', '11:00', '12:00'])->map(function (string $hora) {
            $slot = SlotFactory::new()->reservado()->create(['centro_id' => $this->cssNorte->id, 'fecha' => '2026-11-16', 'hora_inicio' => $hora, 'hora_fin' => $hora]);

            return Cita::factory()->create(['slot_id' => $slot->id, 'centro_id' => $this->cssNorte->id, 'fecha' => '2026-11-16', 'tipo_slot_id' => $slot->tipo_slot_id]);
        });
        $slotsAntes = Slot::whereIn('id', $citas->pluck('slot_id'))->orderBy('id')->get(['id', 'estado', 'hora_inicio'])->toArray();

        $resultado = app(CierreCalendarioService::class)->crear($this->tipoCss, ['fecha_desde' => '2026-11-16', 'fecha_hasta' => '2026-11-16', 'tipo' => 'cierre', 'motivo' => 'Cierre'], $this->admin);

        $this->assertSame(['3 citas afectadas en CSS Norte'], $resultado['avisos']);
        $this->assertSame(3, Cita::whereIn('id', $citas->pluck('id'))->where('estado', EstadoCita::Confirmada)->count());
        $this->assertSame($slotsAntes, Slot::whereIn('id', $citas->pluck('slot_id'))->orderBy('id')->get(['id', 'estado', 'hora_inicio'])->toArray());
    }
}
