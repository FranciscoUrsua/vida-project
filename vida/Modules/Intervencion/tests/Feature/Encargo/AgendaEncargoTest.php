<?php

namespace Modules\Intervencion\Tests\Feature\Encargo;

use App\Models\User;
use App\Support\Menu\EntradaMenu;
use App\Support\Menu\RegistroMenu;
use App\Support\Superficie\DestinoInicial;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Enums\ModalidadCita;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Livewire\Citas\CitaDirectaPage;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\Citas\EnlaceAtencionCita;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Http\Livewire\AgendaPage;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Services\CitasEncargo;
use Modules\Intervencion\Tests\Concerns\EncargoTestSetup;
use Modules\Organizacion\Services\ConfiguracionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Agenda y citas del profesional de encargo (TF-ENC-34 a 37,
 * docs/instrucciones-cli/2026-11-encargo-profesional.md, EP-9).
 *
 * `consulta_profesional` tiene agenda, pero no «Mis casos» ni «Recursos» (exigen `plan.leer`).
 * Cita a la persona de su encargo en sus huecos del centro del encargo; la ventanilla del
 * centro también la cita en esos huecos. «Atender» lleva al encargo, no a la Historia Social.
 * Los negativos fallan si se quita la restricción que protegen.
 */
class AgendaEncargoTest extends TestCase
{
    use EncargoTestSetup;
    use RefreshDatabase;

    private TipoCita $tipoCita;

    private TipoSlot $tipoSlot;

    private Carbon $dia;

    /**
     * Escenario del encargo profesional con un tipo de cita y el próximo día laborable.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEncargo();

        $this->dia = today()->nextWeekday();
        $this->tipoSlot = TipoSlot::factory()->create(['nombre' => 'Entrevista', 'duracion_minutos' => 45, 'origen_permitido' => OrigenPermitidoSlot::Ambos->value]);
        $this->tipoCita = TipoCita::create([
            'codigo' => 'orientacion_juridica',
            'nombre' => 'Orientación jurídica',
            'etiqueta_publica' => 'Entrevista',
            'herramienta' => HerramientaCita::Ninguna,
            'modalidad_defecto' => ModalidadCita::Presencial,
            'requiere_historia_social' => false,
            'activo' => true,
        ]);
        $this->tipoCita->tiposSlot()->attach($this->tipoSlot);
    }

    /** TF-ENC-34 — `consulta_profesional` ve su agenda; «Mis casos» y «Recursos» no aparecen. [negativo] */
    #[Test]
    public function tf_enc_34_agenda_sin_mis_casos_ni_recursos(): void
    {
        app(ConfiguracionService::class)->set('tiene_plazas', true);

        $claves = fn (User $u) => collect(RegistroMenu::para($u))->flatten()->map(fn (EntradaMenu $e) => $e->clave)->all();

        $this->assertContains('agenda', $claves($this->abogadaVallecas));
        $this->assertNotContains('casos', $claves($this->abogadaVallecas));
        $this->assertNotContains('recursos', $claves($this->abogadaVallecas));
        // Quien lleva casos los sigue viendo
        $this->assertContains('casos', $claves($this->tsNorte));
        $this->assertContains('recursos', $claves($this->tsNorte));

        $this->actingAs($this->abogadaVallecas);
        $this->get(route('intervencion.agenda.index'))->assertOk();
        $this->get(route('intervencion.casos.index'))->assertForbidden();
        $this->get(route('intervencion.recursos.index'))->assertForbidden();

        // La página de inicio sigue siendo «Mis derivaciones» (EP-10, decisión del 25 nov)
        $this->assertSame(route('intervencion.encargos.index'), DestinoInicial::para($this->abogadaVallecas));
    }

    /** TF-ENC-35 — Cita desde el encargo en un hueco suyo del centro del encargo. */
    #[Test]
    public function tf_enc_35_cita_desde_el_encargo(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $suyo = $this->slotDe($this->abogadaVallecas, $this->ciamVallecas, '10:00');
        $ajeno = $this->slotDe($this->psicologaVallecas, $this->ciamVallecas, '10:00');

        $this->actingAs($this->abogadaVallecas);
        $pagina = Livewire::test(EncargoPage::class, ['encargo' => $encargo->id])
            ->assertSeeHtml('id="encargo-citar"')
            ->set('tipoCitaId', $this->tipoCita->id)
            ->call('buscarHuecos')
            ->assertHasNoErrors();

        $propuestos = collect($pagina->get('huecos'))->pluck('slot_id');
        $this->assertTrue($propuestos->contains($suyo->id));
        $this->assertFalse($propuestos->contains($ajeno->id));

        $pagina->call('citar', $suyo->id)->assertHasNoErrors()->assertSee('Está en tu agenda');

        $cita = Cita::sole();
        $this->assertSame(
            [$this->abogadaVallecas->id, $this->ana->id, $this->ciamVallecas->id, $suyo->id],
            [$cita->profesional_id, $cita->ciudadano_id, $cita->centro_id, $cita->slot_id],
        );
        $this->assertSame([$encargo->getMorphClass(), $encargo->id], [$cita->solicitud->contexto_type, $cita->solicitud->contexto_id]);
        $this->assertNotSame(EstadoSlot::Disponible, $suyo->fresh()->estado);
    }

    /** TF-ENC-35 bis — Solo el profesional asignado, con el encargo en curso y en sus huecos del centro del encargo. [negativo] */
    #[Test]
    public function tf_enc_35_bis_limites_de_la_cita_desde_el_encargo(): void
    {
        $citas = app(CitasEncargo::class);

        // Encargo cerrado: ni se ofrece ni se puede
        $cerrado = $this->encargoCerrado($this->historiaAna, $this->abogadaVallecas);
        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $cerrado->id])->assertDontSeeHtml('id="encargo-citar"');
        $this->assertFalse($citas->puedeCitar($cerrado, $this->abogadaVallecas));

        // En curso, pero el hueco es de otro profesional del centro (la policy de la agenda propia)
        [, $historiaEva] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $encargo = $this->encargoAceptado($historiaEva, $this->abogadaVallecas);
        $ajeno = $this->slotDe($this->psicologaVallecas, $this->ciamVallecas, '10:00');
        try {
            $citas->citar($encargo, $this->tipoCita->id, $ajeno, $this->abogadaVallecas);
            $this->fail('Ha citado en la agenda de otro profesional.');
        } catch (AuthorizationException) {
        }

        // Otro profesional de encargos no cita en un encargo que no es suyo
        $this->assertFalse($citas->puedeCitar($encargo, $this->educadorMulti));

        // Hueco propio, pero de otro centro: el educador está en el CSS y en el CIAM
        $deEducador = $this->encargoAceptado($this->historiaMarta, $this->educadorMulti);
        $enOtroCentro = $this->slotDe($this->educadorMulti, $this->cssNorte, '10:00');
        try {
            $citas->citar($deEducador, $this->tipoCita->id, $enOtroCentro, $this->educadorMulti);
            $this->fail('Ha citado en un hueco de otro centro.');
        } catch (DomainException) {
        }

        $this->assertSame(0, Cita::count());
    }

    /** TF-ENC-36 — La ventanilla del centro cita a la persona en un hueco del profesional. */
    #[Test]
    public function tf_enc_36_la_ventanilla_cita_en_un_hueco_del_profesional(): void
    {
        $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $hueco = $this->slotDe($this->abogadaVallecas, $this->ciamVallecas, '11:00');
        $ventanilla = $this->profesionalCon('ventanilla.vallecas', 'consulta_basica', $this->ciamVallecas, 'Auxiliar administrativo/a');

        $this->actingAs($ventanilla);
        $pagina = Livewire::test(CitaDirectaPage::class, ['ciudadano' => $this->ana->id])
            ->set('ciudadanoId', $this->ana->id);

        // La abogada es elegible como «profesional concreto» porque atiende citas (citas.atender)
        $this->assertArrayHasKey($this->abogadaVallecas->id, $pagina->instance()->opcionesProfesionalesCita);

        $pagina->set('formSolicitud.tipo_cita_id', (string) $this->tipoCita->id)
            ->set('formSolicitud.destino', 'profesional_concreto')
            ->set('formSolicitud.profesional_destino_id', (string) $this->abogadaVallecas->id)
            ->call('buscarHuecos');
        $this->assertTrue(collect($pagina->get('propuestas'))->pluck('slot_id')->contains($hueco->id));

        $pagina->call('citar', $hueco->id)->assertHasNoErrors();

        $cita = Cita::sole();
        $this->assertSame([$this->abogadaVallecas->id, $this->ana->id], [$cita->profesional_id, $cita->ciudadano_id]);
    }

    /** TF-ENC-37 — Atender la cita lleva al encargo; la de quien abre la Historia Social, a la Historia. [negativo] */
    #[Test]
    public function tf_enc_37_atender_lleva_al_encargo(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $this->actingAs($this->abogadaVallecas);
        $deEncargo = app(CitasEncargo::class)->citar($encargo, $this->tipoCita->id, $this->slotDe($this->abogadaVallecas, $this->ciamVallecas, '10:00'), $this->abogadaVallecas);

        $alEncargo = route('intervencion.encargos.show', $encargo->id);
        $this->assertSame($alEncargo, app(EnlaceAtencionCita::class)->url($deEncargo));

        Livewire::test(AgendaPage::class)
            ->set('vista', 'dia')
            ->set('fechaAncla', $this->dia->toDateString())
            ->assertSeeHtml($alEncargo);

        // La que da la ventanilla (sin el encargo como contexto) también lleva al encargo
        $ventanilla = $this->profesionalCon('ventanilla.vallecas', 'consulta_basica', $this->ciamVallecas, 'Auxiliar administrativo/a');
        $this->actingAs($ventanilla);
        $huecoVentanilla = $this->slotDe($this->abogadaVallecas, $this->ciamVallecas, '11:00');
        $deVentanilla = Livewire::test(CitaDirectaPage::class, ['ciudadano' => $this->ana->id])
            ->set('ciudadanoId', $this->ana->id)
            ->set('formSolicitud.tipo_cita_id', (string) $this->tipoCita->id)
            ->set('formSolicitud.destino', 'profesional_concreto')
            ->set('formSolicitud.profesional_destino_id', (string) $this->abogadaVallecas->id)
            ->call('buscarHuecos');
        $deVentanilla->call('citar', $huecoVentanilla->id)->assertHasNoErrors();
        $this->assertSame($alEncargo, app(EnlaceAtencionCita::class)->url(Cita::where('slot_id', $huecoVentanilla->id)->sole()));

        // La TSR de referencia abre la Historia Social: su cita lleva a la Historia, no al encargo
        $slotTsr = $this->slotDe($this->tsNorte, $this->cssNorte, '12:00');
        $citaTsr = Cita::create([
            'slot_id' => $slotTsr->id,
            'tipo_slot_id' => $slotTsr->tipo_slot_id,
            'modo_asignacion' => 'profesional_concreto',
            'ciudadano_id' => $this->ana->id,
            'profesional_id' => $this->tsNorte->id,
            'centro_id' => $this->cssNorte->id,
            'tipo_cita_id' => $this->tipoCita->id,
            'fecha' => $this->dia->toDateString(),
            'hora_inicio' => '12:00',
            'hora_fin' => '12:45',
            'estado' => 'confirmada',
            'origen' => 'interno',
            'modalidad' => 'presencial',
        ]);
        $this->assertStringStartsWith(route('intervencion.ciudadano.show', $this->historiaAna->id), app(EnlaceAtencionCita::class)->url($citaTsr));
    }

    /**
     * Slot libre de un profesional en un centro el próximo día laborable (con su cuadrante publicado).
     *
     * @param User $profesional
     * @param Centro $centro
     * @param string $hora
     *
     * @return Slot
     */
    private function slotDe(User $profesional, Centro $centro, string $hora): Slot
    {
        $cuadrante = CuadranteMes::firstOrCreate(
            ['centro_id' => $centro->id, 'anyo' => $this->dia->year, 'mes' => $this->dia->month],
            ['estado' => EstadoCuadrante::Publicado->value, 'generado_con_ia' => false, 'generado_automaticamente' => false, 'publicado_en' => now()],
        );
        $linea = LineaCuadrante::firstOrCreate(
            ['cuadrante_mes_id' => $cuadrante->id, 'usuario_id' => $profesional->id, 'fecha' => $this->dia->toDateString()],
            ['centro_id' => $centro->id, 'franjas' => [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '14:00']], 'anulada' => false],
        );

        return Slot::create([
            'linea_cuadrante_id' => $linea->id,
            'usuario_id' => $profesional->id,
            'centro_id' => $centro->id,
            'tipo_slot_id' => $this->tipoSlot->id,
            'fecha' => $this->dia->toDateString(),
            'hora_inicio' => $hora,
            'hora_fin' => Carbon::parse($hora)->addMinutes(45)->format('H:i'),
            'estado' => EstadoSlot::Disponible->value,
        ]);
    }
}
