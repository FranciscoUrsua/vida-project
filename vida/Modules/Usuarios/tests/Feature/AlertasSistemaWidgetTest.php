<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Filament\Widgets\AlertasSistemaWidget;
use Database\Seeders\ConfiguracionRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Usuarios\Models\UsuarioRol;
use Modules\Usuarios\Services\AsignacionRolesService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Widget «Alertas del sistema» del escritorio de Filament (incidencia del 17 nov 2026):
 * solo las alertas pendientes del usuario, con su texto, y se pueden abrir y reconocer.
 */
class AlertasSistemaWidgetTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    private Alerta $alerta;

    /**
     * Asignación de `tramitacion` (alerta supervisada) a un usuario del CSS Norte: la
     * alerta va a la supervisión de su UO.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
        $this->seed(ConfiguracionRolesSeeder::class);

        $usuario = $this->usuario('adm1.norte', 'intervencion', $this->uoCssNorte);
        $asignacion = app(AsignacionRolesService::class)->asignar($usuario, Role::findByName('tramitacion'), $this->admin);
        $this->alerta = Alerta::where('origen_type', UsuarioRol::class)->where('origen_id', $asignacion->id)->sole();
    }

    /**
     * El destinatario la ve con título y texto, la abre y la reconoce.
     *
     * @return void
     */
    #[Test]
    public function el_destinatario_la_ve_y_la_reconoce(): void
    {
        $this->assertTrue($this->alerta->destinatarios()->where('usuario_id', $this->directoraNorte->id)->exists());

        Livewire::actingAs($this->directoraNorte)->test(AlertasSistemaWidget::class)
            ->assertSee('Rol «tramitacion» asignado a adm1.norte@vida360.test')
            ->assertSee('La asignación ya es efectiva')
            ->assertTableActionVisible('ver', $this->alerta)
            ->assertTableActionVisible('usuario', $this->alerta)
            ->callTableAction('ver', $this->alerta)
            ->assertDontSee('Rol «tramitacion» asignado');

        $this->assertSame(EstadoAlerta::Reconocida, $this->alerta->destinatarios()->where('usuario_id', $this->directoraNorte->id)->sole()->estado);
    }

    /**
     * Quien no es destinatario no la ve, aunque sea `adm_sistema`. [negativo]
     *
     * @return void
     */
    #[Test]
    public function quien_no_es_destinatario_no_la_ve(): void
    {
        $this->assertFalse($this->alerta->destinatarios()->where('usuario_id', $this->admin->id)->exists());

        Livewire::actingAs($this->admin)->test(AlertasSistemaWidget::class)
            ->assertDontSee('Rol «tramitacion» asignado')
            ->assertSee('Sin alertas pendientes');
    }
}
