<?php

namespace Modules\Intervencion\Tests\Feature\Encargo;

use App\Models\User;
use App\Support\Menu\EntradaMenu;
use App\Support\Menu\RegistroMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Mensajes\Livewire\PanelRedaccion;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Services\ContextoMensajeService;
use Modules\Intervencion\Tests\Concerns\EncargoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Grupo F — Mensajes desde el encargo y centro activo por la hora (TF-ENC-38 a 40,
 * docs/instrucciones-cli/2026-11-encargo-profesional.md, bloque F).
 *
 * El profesional de encargo tiene Mensajes en el menú aunque no tenga agenda, y escribe al
 * TSR desde el encargo con el encargo como contexto (TipoContextoMensaje::Encargo). Con un
 * perfil horario de mañana en un centro y otro de tarde en otro, el centro activo es el de la
 * franja de la hora actual. Los negativos fallan si se quita la regla que protegen.
 */
class MensajesCentroEncargoTest extends TestCase
{
    use EncargoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del encargo profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEncargo();
    }

    /**
     * Restaura el reloj.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** TF-ENC-38 — Mensajes en el menú; «Escribir al TSR» abre el panel con el TSR sugerido y el contexto del encargo. [negativo] */
    #[Test]
    public function tf_enc_38_mensajes_y_escribir_al_tsr(): void
    {
        // Aunque el rol se configure sin agenda, Mensajes sigue en el menú y su ruta abre
        Role::findByName('consulta_profesional')->revokePermissionTo('agenda.ver_propia');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $abogada = $this->abogadaVallecas->fresh();

        $claves = collect(RegistroMenu::para($abogada))->flatten()->map(fn (EntradaMenu $e) => $e->clave)->all();
        $this->assertContains('bandeja.mensajes', $claves);
        $this->actingAs($abogada)->get(route('intervencion.mensajes.index', 'mensajes'))->assertOk();

        $encargo = $this->encargoAceptado($this->historiaAna, $abogada);
        Livewire::test(EncargoPage::class, ['encargo' => $encargo->id])->assertSee('Escribir al TSR');

        $panel = Livewire::test(PanelRedaccion::class)
            ->call('abrir', ['tipo' => 'encargo', 'id' => $encargo->id])
            ->assertSet('destinatarioSugerido.id', $this->tsNorte->id)
            ->assertSet('contexto.tipo', 'encargo')
            ->assertSet('contexto.url', route('intervencion.encargos.show', $encargo->id))
            ->assertSet('ciudadanoIds', [$this->ana->id]);

        $panel->call('confirmarSugerencia')
            ->set('asunto', 'Duda sobre el encargo')
            ->set('cuerpo', '¿Tienes la sentencia de separación?')
            ->call('enviar')
            ->assertHasNoErrors();

        $hilo = MensajeHilo::sole();
        $this->assertSame(['encargo', $encargo->id], [$hilo->contexto_tipo, (int) $hilo->contexto_id]);

        // El TSR que recibe el mensaje llega a la herramienta «Encargo profesional» de la Historia
        $delTsr = app(ContextoMensajeService::class)->resolver('encargo', $encargo->id, $this->tsNorte);
        $this->assertSame(route('intervencion.ciudadano.show', ['historia' => $this->historiaAna->id, 'herramienta' => 'encargo']), $delTsr['url']);
        $this->assertSame($abogada->id, $delTsr['autor_id']);

        // Otro profesional de encargos no resuelve un encargo ajeno
        $this->assertNull(app(ContextoMensajeService::class)->resolver('encargo', $encargo->id, $this->educadorMulti));
    }

    /** TF-ENC-38 bis — Con la persona protegida y el encargo cerrado, el encargo ya no sirve de contexto. [negativo] */
    #[Test]
    public function tf_enc_38_bis_protegida_cerrada_sin_contexto(): void
    {
        $encargo = $this->encargoAceptado($this->historiaMarta, $this->abogadaVallecas);
        $this->assertNotNull(app(ContextoMensajeService::class)->resolver('encargo', $encargo->id, $this->abogadaVallecas));

        // Con otro encargo en curso, para que no la proteja solo un ámbito vacío
        $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        app(\Modules\Intervencion\Services\EncargoService::class)->cerrar($encargo, 'Hecho.', $this->abogadaVallecas);

        $this->assertNull(app(ContextoMensajeService::class)->resolver('encargo', $encargo->id, $this->abogadaVallecas->fresh()));
    }

    /** TF-ENC-39 — Centro activo por la hora: mañana en un centro y tarde en otro. [negativo] */
    #[Test]
    public function tf_enc_39_centro_activo_por_la_hora(): void
    {
        // El de tarde se crea primero: sin mirar la hora, saldría siempre el CIAM
        $this->perfil($this->educadorMulti, $this->ciamVallecas, '15:00', '20:00');
        $this->perfil($this->educadorMulti, $this->cssNorte, '09:00', '14:00');

        Carbon::setTestNow(today()->setTime(10, 0));
        $this->assertSame($this->cssNorte->id, app(CentroDeUsuario::class)->centroActivo($this->educadorMulti)?->id);

        Carbon::setTestNow(today()->setTime(17, 0));
        $this->assertSame($this->ciamVallecas->id, app(CentroDeUsuario::class)->centroActivo($this->educadorMulti)?->id);
    }

    /** TF-ENC-40 — Sin franja a esa hora, el primer perfil con franjas hoy (comportamiento anterior). */
    #[Test]
    public function tf_enc_40_sin_franja_a_esa_hora(): void
    {
        $this->perfil($this->educadorMulti, $this->ciamVallecas, '15:00', '20:00');
        $this->perfil($this->educadorMulti, $this->cssNorte, '09:00', '14:00');

        Carbon::setTestNow(today()->setTime(21, 30));
        $this->assertSame($this->ciamVallecas->id, app(CentroDeUsuario::class)->centroActivo($this->educadorMulti)?->id);

        // A las 14:00 en punto termina la franja de mañana: tampoco hay franja
        Carbon::setTestNow(today()->setTime(14, 0));
        $this->assertSame($this->ciamVallecas->id, app(CentroDeUsuario::class)->centroActivo($this->educadorMulti)?->id);
    }

    /**
     * Perfil horario vigente con una única franja hoy en un centro.
     *
     * @param User $usuario
     * @param Centro $centro
     * @param string $inicio
     * @param string $fin
     *
     * @return PerfilHorarioProfesional
     */
    private function perfil(User $usuario, Centro $centro, string $inicio, string $fin): PerfilHorarioProfesional
    {
        return PerfilHorarioProfesional::factory()->create([
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'horario_habitual' => [(string) today()->isoWeekday() => [['inicio' => $inicio, 'fin' => $fin]]],
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'activo' => true,
        ]);
    }
}
