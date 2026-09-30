<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\EstadoSolicitudCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Services\Citas\BusquedaHuecosService;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\PropuestaHueco;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Mensajes\Models\AlertaDestinatario;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo C: búsqueda de huecos (TF-CIT-10 a 17).
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class BusquedaHuecosTest extends TestCase
{
    use CitasTestSetup;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioCitas();
    }

    /**
     * Crea una solicitud de María (o de otra persona) como consulta.
     *
     * @param array<string, mixed> $cambios
     * @return SolicitudCita
     */
    private function solicitud(array $cambios = []): SolicitudCita
    {
        return app(SolicitudCitaService::class)->crear($cambios + [
            'ciudadano_id' => $this->maria->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoSeguimiento->id,
            'urgencia' => 'ordinaria',
            'destino' => 'referencia',
        ], $this->consulta);
    }

    /**
     * @param SolicitudCita $solicitud
     * @return \Illuminate\Support\Collection<int, PropuestaHueco>
     */
    private function buscar(SolicitudCita $solicitud, int $limite = 100)
    {
        return app(BusquedaHuecosService::class)->buscar($solicitud, $limite);
    }

    /**
     * TF-CIT-10 — Profesional concreto: solo ese profesional.
     *
     * @return void
     */
    #[Test]
    public function profesional_concreto(): void
    {
        $propuestas = $this->buscar($this->solicitud(['destino' => 'profesional_concreto', 'profesional_destino_id' => $this->tsr2->id]));

        $this->assertNotEmpty($propuestas);
        $propuestas->each(function (PropuestaHueco $p) {
            $this->assertSame($this->tsr2->id, $p->profesional->id);
            $this->assertSame(ModoAsignacionCita::ProfesionalConcreto, $p->modo);
        });
    }

    /**
     * TF-CIT-11 — La referencia con hueco va primero, aunque sea más tarde que otros.
     *
     * @return void
     */
    #[Test]
    public function la_referencia_disponible_va_primero(): void
    {
        // tsr solo tiene hueco la semana que viene; tsr2, hoy mismo
        Slot::where('usuario_id', $this->tsr->id)->whereDate('fecha', '<', '2026-10-12')->update(['estado' => EstadoSlot::Reservado->value]);

        $primera = $this->buscar($this->solicitud())->first();

        $this->assertSame($this->tsr->id, $primera->profesional->id);
        $this->assertSame(ModoAsignacionCita::Referencia, $primera->modo);
        $this->assertTrue($primera->slot->fecha->gte('2026-10-12'));
    }

    /**
     * TF-CIT-12 — Ausencia prolongada de la referencia: sustitutos del mismo perfil.
     *
     * @return void
     */
    #[Test]
    public function la_ausencia_prolongada_propone_sustitutos_del_mismo_perfil(): void
    {
        ExcepcionProfesional::create([
            'usuario_id' => $this->tsr->id,
            'centro_id' => $this->centro->id,
            'tipo' => 'baja_medica',
            'fecha_inicio' => '2026-10-06',
            'fecha_fin' => '2026-10-25',
            'afecta_disponibilidad' => true,
            'origen' => 'manual',
            'creado_por_id' => $this->supervisor->id,
        ]);

        $propuestas = $this->buscar($this->solicitud());

        $this->assertNotEmpty($propuestas);
        $propuestas->each(function (PropuestaHueco $p) {
            $this->assertSame($this->tsr2->id, $p->profesional->id, 'Solo sustitutos del mismo perfil');
            $this->assertSame(ModoAsignacionCita::Sustituto, $p->modo);
        });

        $this->assertSame($this->tsr->id, AsignacionProfesional::vigente()->where('historia_id', $this->historiaMaria->id)->value('profesional_id'));
    }

    /**
     * TF-CIT-13 — Una ausencia corta no deriva a sustitutos.
     *
     * @return void
     */
    #[Test]
    public function la_ausencia_corta_no_deriva_a_sustitutos(): void
    {
        ExcepcionProfesional::create([
            'usuario_id' => $this->tsr->id,
            'centro_id' => $this->centro->id,
            'tipo' => 'vacaciones',
            'fecha_inicio' => '2026-10-07',
            'fecha_fin' => '2026-10-09',
            'afecta_disponibilidad' => true,
            'origen' => 'manual',
            'creado_por_id' => $this->supervisor->id,
        ]);
        // Durante la ausencia sus slots no están disponibles
        Slot::where('usuario_id', $this->tsr->id)->whereBetween('fecha', ['2026-10-07', '2026-10-09'])->update(['estado' => EstadoSlot::Anulado->value]);

        $propuestas = $this->buscar($this->solicitud());

        $this->assertTrue($propuestas->contains(fn (PropuestaHueco $p) => $p->profesional->id === $this->tsr->id && $p->modo === ModoAsignacionCita::Referencia));
        $this->assertFalse($propuestas->contains(fn (PropuestaHueco $p) => $p->modo === ModoAsignacionCita::Sustituto));
    }

    /**
     * TF-CIT-14 — Una solicitud ordinaria nunca consume slots de urgencia. [negativo]
     *
     * @return void
     */
    #[Test]
    public function la_urgencia_ordinaria_no_consume_slots_de_urgencia(): void
    {
        $propuestas = $this->buscar($this->solicitud(['destino' => 'primer_libre']), 500);

        $this->assertNotEmpty($propuestas);
        $this->assertFalse($propuestas->contains(fn (PropuestaHueco $p) => $p->slot->estado === EstadoSlot::BloqueadoUrgencia));
    }

    /**
     * TF-CIT-15 — Una urgente puede consumir un slot de urgencia; se avisa a supervisión.
     *
     * @return void
     */
    #[Test]
    public function la_urgente_consume_un_slot_de_urgencia_y_avisa(): void
    {
        // Solo queda libre el slot de urgencia de tsr2 hoy a las 13:00 dentro de la ventana
        Slot::where('estado', EstadoSlot::Disponible->value)->update(['estado' => EstadoSlot::Reservado->value]);
        Slot::where('estado', EstadoSlot::BloqueadoUrgencia->value)
            ->where(fn ($q) => $q->where('usuario_id', '!=', $this->tsr2->id)->orWhere('fecha', '!=', '2026-10-06'))
            ->update(['estado' => EstadoSlot::Anulado->value]);

        $solicitud = $this->solicitud(['urgencia' => 'urgente', 'destino' => 'primer_libre']);
        $propuestas = $this->buscar($solicitud);

        $this->assertCount(1, $propuestas);
        $cita = app(CitacionService::class)->citar($solicitud, $propuestas->first()->slot, $propuestas->first()->modo, $this->consulta);

        $this->assertSame(EstadoSlot::Reservado, $cita->slot->fresh()->estado);
        $this->assertTrue(AlertaDestinatario::where('usuario_id', $this->supervisor->id)
            ->whereHas('alerta', fn ($q) => $q->where('titulo', 'Slot de urgencia consumido'))->exists());
    }

    /**
     * TF-CIT-16 — Los tipos de slot incompatibles quedan fuera.
     *
     * @return void
     */
    #[Test]
    public function los_tipos_de_slot_incompatibles_quedan_fuera(): void
    {
        // tsr2 solo tiene slots grupales libres
        Slot::where('usuario_id', $this->tsr2->id)->update(['estado' => EstadoSlot::Reservado->value]);
        $linea = LineaCuadrante::where('usuario_id', $this->tsr2->id)->where('fecha', '2026-10-07')->firstOrFail();
        $this->slot($linea, $this->tsr2, $this->slotGrupal, '2026-10-07', '14:00');

        $propuestas = $this->buscar($this->solicitud(['destino' => 'primer_libre']), 500);

        $this->assertFalse($propuestas->contains(fn (PropuestaHueco $p) => $p->profesional->id === $this->tsr2->id));
    }

    /**
     * TF-CIT-17 — Sin huecos compatibles: resultado vacío y la solicitud no cambia.
     *
     * @return void
     */
    #[Test]
    public function sin_huecos_el_resultado_es_vacio(): void
    {
        Slot::query()->update(['estado' => EstadoSlot::Reservado->value]);
        $solicitud = $this->solicitud(['destino' => 'primer_libre']);

        $this->assertTrue($this->buscar($solicitud)->isEmpty());
        $this->assertSame(EstadoSolicitudCita::Pendiente, $solicitud->fresh()->estado);
    }

    /**
     * Los huecos de hoy que ya empezaron no se proponen.
     *
     * @return void
     */
    #[Test]
    public function no_se_proponen_huecos_que_ya_empezaron(): void
    {
        $propuestas = $this->buscar($this->solicitud(['destino' => 'primer_libre']), 500);

        $this->assertFalse($propuestas->contains(fn (PropuestaHueco $p) => $p->slot->fecha->isToday() && str_starts_with((string) $p->slot->hora_inicio, '09:00')));
    }
}
