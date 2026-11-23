<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Filament\Acciones\DesactivarAction;
use App\Filament\Resources\UsuarioResource\Pages\EditUsuario;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\Asignacion\SalidaCentroBloqueada;
use Modules\Intervencion\Services\Asignacion\SalidaCentroService;
use Modules\Mensajes\Models\AlertaDestinatario;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Desactivar un profesional desde administración (R3 de la ronda 1 de staging, TF-STG-22): el
 * reparto de casos es de la dirección del centro (D5). A quien no puede repartir no se le
 * ofrecen enlaces que no puede abrir; se le dice qué dirección tiene que hacerlo y puede avisarla.
 */
class DesactivarDesdeAdministracionTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /** TS del CSS Norte con dos casos de referencia. */
    private User $tsNorte;

    /**
     * Escenario de Tipo de Centro con una TS con casos en el CSS Norte.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 10:00');
        $this->montarTipoCentro();

        $this->tsNorte = $this->usuario('ts.norte', 'intervencion', $this->uoCssNorte);

        foreach (range(1, 2) as $i) {
            $historia = HistoriaSocial::create([
                'ciudadano_id' => Ciudadano::factory()->create()->id,
                'unidad_organizativa_id' => $this->uoCssNorte->id,
                'estado' => 'abierta',
            ]);
            AsignacionProfesional::create([
                'historia_id' => $historia->id,
                'profesional_id' => $this->tsNorte->id,
                'centro_id' => $this->cssNorte->id,
                'fecha_inicio' => '2026-01-01',
            ]);
        }
    }

    /**
     * Intenta desactivar a la TS como el actor dado y devuelve el rechazo.
     *
     * @param User $actor Quien lo intenta.
     *
     * @return SalidaCentroBloqueada
     */
    private function rechazo(User $actor): SalidaCentroBloqueada
    {
        try {
            app(SalidaCentroService::class)->desactivarUsuario($this->tsNorte, $actor);
        } catch (SalidaCentroBloqueada $e) {
            return $e;
        }

        $this->fail('La desactivación con casos pendientes debe rechazarse.');
    }

    /** TF-STG-22 — Sin enlaces que no puede abrir; dice qué dirección y la avisa. [negativo] */
    #[Test]
    public function tf_stg_22_desactivar_desde_administracion(): void
    {
        $e = $this->rechazo($this->admin);

        // La administración no supervisa el centro: ni «Repartir casos» ni «Reasignar citas»
        $this->assertSame([], $e->enlaces($this->admin));
        // [negativo] La directora del centro sí los tiene
        $this->assertArrayHasKey('Repartir casos', $e->enlaces($this->directoraNorte));

        // La notificación nombra el centro y su dirección, y ofrece avisarla
        $this->actingAs($this->admin);
        DesactivarAction::notificarBloqueo($e);
        $notificacion = collect(session('filament.notifications'))->last();
        $this->assertStringContainsString('Lo tiene que resolver la dirección del centro: CSS Norte (', $notificacion['body']);
        $this->assertStringContainsString($this->directoraNorte->nombreVisible(), $notificacion['body']);
        $etiquetas = collect($notificacion['actions'])->pluck('label')->all();
        $this->assertSame(['Avisar a la dirección del centro'], $etiquetas);

        // «Avisar a la dirección del centro» envía el aviso a la directora con los casos
        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $this->tsNorte->getKey()])
            ->dispatch('avisar-direccion-salida', usuarioId: $this->tsNorte->id, accion: 'desactivar al usuario', centroIds: [$this->cssNorte->id])
            ->assertNotified('Aviso enviado a la dirección del centro');

        $aviso = AlertaDestinatario::where('usuario_id', $this->directoraNorte->id)->with('alerta')->sole()->alerta;
        $this->assertStringContainsString('2 casos de referencia', $aviso->cuerpo);
        $this->assertStringContainsString('CSS Norte', $aviso->cuerpo);
        $this->assertFalse($this->tsNorte->fresh()->estaDesactivado());
    }

    /** TF-STG-22 bis — Quien supervisa el centro sigue viendo los enlaces, sin el botón de aviso. */
    #[Test]
    public function tf_stg_22_bis_la_direccion_sigue_con_sus_enlaces(): void
    {
        $e = $this->rechazo($this->directoraNorte);

        $this->actingAs($this->directoraNorte);
        DesactivarAction::notificarBloqueo($e);
        $notificacion = collect(session('filament.notifications'))->last();
        $etiquetas = collect($notificacion['actions'])->pluck('label')->all();

        $this->assertContains('Repartir casos', $etiquetas);
        $this->assertNotContains('Avisar a la dirección del centro', $etiquetas);
    }
}
