<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Livewire\Citas\CitaDirectaPage;
use Modules\Agenda\Models\CitaAcompanante;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Services\Citas\BuscadorPersonasCita;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\AgendaPage;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Models\Apunte;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de interfaz de citas: bandeja (TF-CIT-09), propuesta de vinculación en
 * la ficha (TF-CIT-34), secciones del timeline (TF-CIT-43, parte de la ficha) y
 * acciones de la agenda del profesional (paso 8.4 de las instrucciones).
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class InterfazCitasTest extends TestCase
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
     * TF-CIT-09 — La bandeja no expone el motivo ni el nombre interno.
     *
     * @return void
     */
    #[Test]
    public function la_bandeja_no_expone_el_motivo_ni_el_nombre_interno(): void
    {
        app(SolicitudCitaService::class)->crear([
            'ciudadano_id' => $this->maria->id,
            'centro_id' => $this->centro->id,
            'canal' => 'interno',
            'tipo_cita_id' => $this->tipoSeguimiento->id,
            'urgencia' => 'ordinaria',
            'destino' => 'referencia',
            'motivo' => 'Revisar la orden de alejamiento',
            'observaciones_citacion' => 'Llamar por la mañana',
        ], $this->tsr);

        $this->actingAs($this->consulta)
            ->get(route('agenda.citas.bandeja'))
            ->assertOk()
            ->assertSee('María')
            ->assertSee('Entrevista')
            ->assertSee('Llamar por la mañana')
            ->assertDontSee('Revisar la orden de alejamiento')
            ->assertDontSee('violencia de género');
    }

    /**
     * TF-CIT-09 (negativo) — Quien no da citas no ve la bandeja.
     *
     * @return void
     */
    #[Test]
    public function la_bandeja_no_la_ve_quien_no_da_citas(): void
    {
        $this->actingAs($this->tsr)
            ->get(route('agenda.citas.bandeja'))
            ->assertForbidden();
    }

    /**
     * TF-CIT-34 — Con cita hoy, la herramienta propone vincularla, marcada, y el
     * apunte la completa.
     *
     * @return void
     */
    #[Test]
    public function la_ficha_propone_vincular_la_cita_de_hoy(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');

        Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historiaMaria])
            ->call('seleccionarHerramienta', 'anotacion')
            ->assertSee('Vincular a la cita de las 11:00')
            ->assertSet('vincularCita', true)
            ->set('formAnotacion.contenido', 'Ha venido con su hermana')
            ->call('guardarAnotacion');

        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
        $this->assertSame($cita->id, Apunte::where('historia_id', $this->historiaMaria->id)->latest('id')->value('cita_id'));
    }

    /**
     * TF-CIT-34 (negativo) — Sin cita hoy no aparece la casilla; desmarcada, no se vincula.
     *
     * @return void
     */
    #[Test]
    public function sin_cita_hoy_no_se_propone_y_desmarcada_no_se_vincula(): void
    {
        $manana = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07', '11:00');

        Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historiaMaria])
            ->call('seleccionarHerramienta', 'anotacion')
            ->assertDontSee('Vincular a la cita');

        $hoy = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '12:00');

        Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historiaMaria])
            ->call('seleccionarHerramienta', 'anotacion')
            ->set('vincularCita', false)
            ->set('formAnotacion.contenido', 'Llamada sin relación con la cita')
            ->call('guardarAnotacion');

        $this->assertSame(EstadoCita::Confirmada, $hoy->fresh()->estado);
        $this->assertSame(EstadoCita::Confirmada, $manana->fresh()->estado);
    }

    /**
     * TF-CIT-43 (ficha) — El detalle del apunte muestra las secciones Cita y
     * Coordinación; sin cita ni evento, no.
     *
     * @return void
     */
    #[Test]
    public function el_detalle_del_apunte_muestra_cita_y_coordinacion(): void
    {
        $original = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07', '11:00');
        $cita = app(CitacionService::class)->reprogramar($original, $this->slotDe($this->tsr, '2026-10-08', '11:00'), PedidoPor::Ciudadano, 'Tiene médico', $this->consulta);
        app(AtencionCitaService::class)->registrarAcompanantes($cita, [['relacion' => $this->relacion(), 'nombre' => 'Pedro Acompañante']], $this->tsr);

        $conCita = $this->apunteDeMaria(['cita_id' => $cita->id]);

        $evento = EventoAgenda::create([
            'centro_id' => $this->centro->id,
            'tipo_evento' => 'reunion_equipo',
            'titulo' => 'Mesa de caso',
            'fecha' => '2026-10-06',
            'hora_inicio' => '12:00',
            'hora_fin' => '13:00',
            'creado_por_id' => $this->supervisor->id,
        ]);
        $evento->ciudadanos()->attach($this->maria->id);
        $deEvento = $this->apunteDeMaria(['tipo' => TipoApunte::GestionCoordinacion, 'evento_agenda_id' => $evento->id]);
        $sinNada = $this->apunteDeMaria();

        $pagina = Livewire::actingAs($this->tsr)->test(CiudadanoPage::class, ['historia' => $this->historiaMaria]);

        $pagina->call('verApunte', $conCita->id)
            ->assertSee('Reprogramaciones')
            ->assertSee('Pedro Acompañante');
        $this->assertSame(1, $pagina->get('modalApunteDatos')['cita']['reprogramaciones']);

        $pagina->call('verApunte', $deEvento->id)->assertSee('Mesa de caso');
        $this->assertNull($pagina->get('modalApunteDatos')['cita']);

        $pagina->call('verApunte', $sinNada->id);
        $this->assertNull($pagina->get('modalApunteDatos')['cita']);
        $this->assertNull($pagina->get('modalApunteDatos')['coordinacion']);
    }

    /**
     * TF-CIT-43 (agenda) — Quien está convocado a una mesa de caso ve en su
     * agenda el título del evento, pero no a la persona referenciada.
     *
     * @return void
     */
    #[Test]
    public function la_agenda_muestra_el_evento_sin_la_persona_referenciada(): void
    {
        $evento = EventoAgenda::create([
            'centro_id' => $this->centro->id,
            'tipo_evento' => 'reunion_equipo',
            'titulo' => 'Mesa de caso',
            'fecha' => '2026-10-06',
            'hora_inicio' => '12:00',
            'hora_fin' => '13:00',
            'creado_por_id' => $this->supervisor->id,
        ]);
        $evento->ciudadanos()->attach($this->maria->id);
        $evento->profesionales()->attach($this->consulta->id);

        Livewire::actingAs($this->consulta)->test(AgendaPage::class)
            ->assertSee('Mesa de caso')
            ->assertDontSee('María');
    }

    /**
     * Agenda del profesional: sus citas con Atender, Incomparecencia,
     * Acompañantes y Pedir cambio; nunca reprogramar ni cancelar (RN-05).
     *
     * @return void
     */
    #[Test]
    public function la_agenda_muestra_las_citas_propias_con_sus_acciones(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');
        $this->citaConfirmada($this->juan, $this->tsr2, '2026-10-06', '11:00', $this->tipoInformacion);

        $agenda = Livewire::actingAs($this->tsr)->test(AgendaPage::class)
            ->assertSee('María')
            ->assertDontSee('Juan')
            ->assertSee('Atender')
            ->assertSee('Incomparecencia')
            ->assertSee('Acompañantes')
            ->assertSee('Pedir cambio')
            ->assertDontSee('Reprogramar')
            ->assertDontSee('Cancelar cita');

        $entrada = collect($agenda->get('citasDia')['2026-10-06'])->firstWhere('id', $cita->id);
        $this->assertStringContainsString('cita='.$cita->id, $entrada['url_atender']);
        $this->assertStringContainsString('herramienta=entrevista', $entrada['url_atender']);
        $this->assertStringContainsString('tipo=seguimiento', $entrada['url_atender']);
    }

    /**
     * Atender una cita de un tipo de atención (sin herramienta de Intervención)
     * lleva a la ficha de Ciudadanía, donde el registro de atención la cierra.
     *
     * @return void
     */
    #[Test]
    public function atender_una_cita_de_atencion_lleva_a_la_ficha_de_ciudadania(): void
    {
        $cita = $this->citaConfirmada($this->juan, $this->tsr, '2026-10-06', '11:00', $this->tipoInformacion);

        $entrada = collect(Livewire::actingAs($this->tsr)->test(AgendaPage::class)->get('citasDia')['2026-10-06'])->firstWhere('id', $cita->id);

        $this->assertSame(route('ciudadania.ciudadano.ficha', ['ciudadano' => $this->juan->id, 'cita' => $cita->id]), $entrada['url_atender']);
    }

    /**
     * Incomparecencia desde la agenda; sobre una cita ajena no hay acción.
     *
     * @return void
     */
    #[Test]
    public function la_incomparecencia_se_marca_solo_en_citas_propias(): void
    {
        $propia = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');
        $ajena = $this->citaConfirmada($this->juan, $this->tsr2, '2026-10-06', '11:00', $this->tipoInformacion);

        Livewire::actingAs($this->tsr)->test(AgendaPage::class)
            ->call('marcarIncomparecencia', $propia->id)
            ->assertHasNoErrors()
            ->assertSee('Incomparecencia registrada.');

        $this->assertSame(EstadoCita::NoShowCiudadano, $propia->fresh()->estado);

        Livewire::actingAs($this->tsr)->test(AgendaPage::class)
            ->call('marcarIncomparecencia', $ajena->id)
            ->assertNotFound();

        $this->assertSame(EstadoCita::Confirmada, $ajena->fresh()->estado);
    }

    /**
     * Acompañantes desde la agenda: con relación y nombre; sin ninguno de los dos, error.
     *
     * @return void
     */
    #[Test]
    public function los_acompanantes_se_registran_desde_la_agenda(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '11:00');

        Livewire::actingAs($this->tsr)->test(AgendaPage::class)
            ->call('abrirAccion', $cita->id, 'acompanantes')
            ->set('formAcompanante.relacion', $this->relacion())
            ->call('guardarAcompanante')
            ->assertHasErrors('acompanante')
            ->set('formAcompanante.nombre', 'Pedro Acompañante')
            ->call('guardarAcompanante')
            ->assertHasNoErrors();

        $this->assertSame(1, CitaAcompanante::where('cita_id', $cita->id)->count());
    }

    /**
     * Pedir cambio: exige texto y deja evento en la cita.
     *
     * @return void
     */
    #[Test]
    public function pedir_cambio_exige_texto_y_deja_evento(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07', '11:00');

        Livewire::actingAs($this->tsr)->test(AgendaPage::class)
            ->call('navegarSiguiente')
            ->call('abrirAccion', $cita->id, 'cambio')
            ->call('pedirCambio')
            ->assertHasErrors('cambio')
            ->set('textoCambio', 'Tengo formación esa mañana')
            ->call('pedirCambio')
            ->assertHasNoErrors();

        $this->assertTrue(CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CambioSolicitado)->exists());
    }

    /**
     * Atender una cita de atención: la ficha de Ciudadanía abre el registro con
     * la cita propuesta, y el registro la completa.
     *
     * @return void
     */
    #[Test]
    public function el_registro_de_atencion_completa_la_cita_desde_la_ficha(): void
    {
        $cita = $this->citaConfirmada($this->juan, $this->auxiliar, '2026-10-06', '11:00', $this->tipoInformacion);

        Livewire::withQueryParams(['cita' => $cita->id])
            ->actingAs($this->auxiliar)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->juan->id])
            ->assertSet('modalAtencionAbierto', true)
            ->assertSee('Vincular a la cita de las 11:00')
            ->set('atencionDemanda', 'Información sobre ayudas de comedor')
            ->call('guardarAtencion')
            ->assertHasNoErrors();

        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
        $this->assertSame($cita->id, RegistroAtencion::where('ciudadano_id', $this->juan->id)->value('cita_id'));
    }

    /**
     * Dar cita desde un registro de atención: la cita queda como su cita generada;
     * para otra persona, no.
     *
     * @return void
     */
    #[Test]
    public function la_cita_dada_desde_una_atencion_queda_como_cita_generada(): void
    {
        $registro = RegistroAtencion::create([
            'ciudadano_id' => $this->juan->id,
            'tipo' => 'informacion',
            'fecha' => today()->toDateString(),
            'profesional_id' => $this->consulta->id,
            'demanda' => 'Pide cita con la trabajadora social',
            'origen' => 'manual',
        ]);

        $this->darCitaDirecta($this->juan->id, $registro->id);
        $this->assertNotNull($registro->fresh()->cita_generada_id);

        $otro = RegistroAtencion::create([
            'ciudadano_id' => $this->maria->id,
            'tipo' => 'informacion',
            'fecha' => today()->toDateString(),
            'profesional_id' => $this->consulta->id,
            'demanda' => 'Otra consulta distinta',
            'origen' => 'manual',
        ]);

        $this->darCitaDirecta($this->juan->id, $otro->id, '12:00');
        $this->assertNull($otro->fresh()->cita_generada_id);
    }

    /**
     * Cita directa en ventanilla para Juan con el TSR, desde un registro de atención.
     *
     * @param int $ciudadanoId
     * @param int $atencionId
     * @param string $hora
     * @return void
     */
    private function darCitaDirecta(int $ciudadanoId, int $atencionId, string $hora = '11:00'): void
    {
        $pagina = Livewire::withQueryParams(['ciudadano' => $ciudadanoId, 'atencion' => $atencionId])
            ->actingAs($this->consulta)
            ->test(CitaDirectaPage::class)
            ->set('formSolicitud.tipo_cita_id', (string) $this->tipoInformacion->id)
            ->set('formSolicitud.destino', 'profesional_concreto')
            ->set('formSolicitud.profesional_destino_id', (string) $this->tsr->id)
            ->call('buscarHuecos');

        $propuesta = collect($pagina->get('propuestas'))->first(fn ($p) => $p['hora'] === $hora);
        $this->assertNotNull($propuesta, 'Debe proponerse el hueco de las '.$hora);

        $pagina->call('citar', $propuesta['slot_id'])->assertHasNoErrors();
    }

    /**
     * Colectivos protegidos (CLAUDE.md §3): quien da citas no encuentra por
     * nombre a una persona protegida que no puede ver; a una no protegida, sí.
     *
     * @return void
     */
    #[Test]
    public function el_buscador_de_citas_no_encuentra_por_nombre_a_personas_protegidas(): void
    {
        Ciudadano::factory()->create(['nombre' => 'Remedios', 'colectivo_extra_protegido' => true]);
        Ciudadano::factory()->create(['nombre' => 'Remigio']);

        $nombres = app(BuscadorPersonasCita::class)->buscar('Rem', $this->consulta)->pluck('nombre')->all();

        $this->assertContains('Remigio', $nombres);
        $this->assertNotContains('Remedios', $nombres);
    }

    /**
     * Primera relación de acompañante del catálogo.
     *
     * @return string
     */
    private function relacion(): string
    {
        return array_key_first(CatalogoSistema::opcionesParaSelect('cita.relacion_acompanante'));
    }

    /**
     * Apunte de María escrito por el TSR.
     *
     * @param array<string, mixed> $datos
     * @return Apunte
     */
    private function apunteDeMaria(array $datos = []): Apunte
    {
        return Apunte::create($datos + [
            'historia_id' => $this->historiaMaria->id,
            'autor_id' => $this->tsr->id,
            'fecha' => today()->toDateString(),
            'tipo' => TipoApunte::Entrevista,
            'contenido' => 'Entrevista de seguimiento',
            'visibilidad' => VisibilidadApunte::Profesionales,
        ]);
    }
}
