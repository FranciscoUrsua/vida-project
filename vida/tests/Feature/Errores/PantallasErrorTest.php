<?php

namespace Tests\Feature\Errores;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Pantallas de error propias y avisos de reglas de negocio (R-03 de la ronda 1 de staging,
 * TF-STG-05 a 07).
 */
class PantallasErrorTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Prepara el escenario común de Tipo de Centro.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
    }

    /**
     * Usuario solo con `consulta_basica` adscrito al CSS Norte.
     *
     * @return User
     */
    private function ventanilla(): User
    {
        return $this->usuario('ventanilla.norte', 'consulta_basica', $this->uoCssNorte);
    }

    #[Test]
    public function tf_stg_05_el_403_es_propio_y_en_espanol(): void
    {
        // Dado un usuario sin rol de intervención; Cuando entra en una ruta de la Historia Social
        $respuesta = $this->actingAs($this->ventanilla())->get('/intervencion/agenda');

        // Entonces ve el 403 propio, sin el texto en inglés de Spatie
        $respuesta->assertForbidden()
            ->assertSee('No tienes permiso para ver esta página.')
            ->assertSee('Volver al inicio')
            ->assertSee('ERR-', false)
            ->assertDontSee('User does not have the right roles')
            ->assertDontSee('Forbidden')
            ->assertDontSee('This action is unauthorized');
    }

    #[Test]
    public function tf_stg_05_bis_la_denegacion_de_una_policy_es_el_mismo_403(): void
    {
        // Dado un usuario de ventanilla; Cuando una policy le deniega (AuthorizationException)
        Route::middleware('web')->get('/_prueba/policy', function () {
            throw new AuthorizationException('This action is unauthorized.');
        });

        $this->actingAs($this->ventanilla())->get('/_prueba/policy')
            ->assertForbidden()
            ->assertSee('No tienes permiso para ver esta página.')
            ->assertDontSee('This action is unauthorized');
    }

    #[Test]
    public function tf_stg_06_404_propio_con_identificador(): void
    {
        $this->actingAs($this->ventanilla())->get('/no-existe-esta-pagina')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Volver al inicio')
            ->assertSee('ERR-', false)
            ->assertDontSee('Not Found');
    }

    #[Test]
    public function tf_stg_06_bis_500_sin_trazas_ni_rutas_de_fichero(): void
    {
        // Con la depuración apagada (producción); en staging APP_DEBUG=true sigue mostrando la traza
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_prueba/fallo', function () {
            throw new RuntimeException('Fallo interno en /var/www/secreto.php');
        });

        $respuesta = $this->actingAs($this->ventanilla())->get('/_prueba/fallo');

        $respuesta->assertStatus(500)
            ->assertSee('Se ha producido un error')
            ->assertSee('ERR-', false)
            ->assertDontSee('secreto.php')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('vendor/laravel');
    }

    #[Test]
    public function tf_stg_06_ter_sin_sesion_tambien_es_propio(): void
    {
        $this->get('/no-existe-esta-pagina')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Volver al inicio');
    }

    #[Test]
    public function tf_stg_07_excepcion_de_dominio_en_una_accion_avisa_y_conserva_el_formulario(): void
    {
        // Dado un formulario a medio escribir; Cuando la acción incumple una regla de negocio
        Livewire::actingAs($this->ventanilla())
            ->test(ComponenteConReglaDeNegocio::class)
            ->set('texto', 'Lo que he escrito')
            ->call('guardar')
            // Entonces se avisa con el mensaje de negocio y el formulario conserva lo escrito
            ->assertDispatched('toast', message: 'Para atender a ciudadanos tienes que estar adscrito a un centro.', type: 'danger')
            ->assertSet('texto', 'Lo que he escrito')
            ->assertOk();
    }

    #[Test]
    public function tf_stg_07_bis_un_error_que_no_es_de_dominio_no_se_disfraza_de_aviso(): void
    {
        // [negativo] Solo DomainException se convierte en aviso: un fallo técnico sigue siendo un error
        $this->expectException(RuntimeException::class);

        Livewire::actingAs($this->ventanilla())
            ->test(ComponenteConReglaDeNegocio::class)
            ->call('fallar');
    }
}

/**
 * Componente de prueba con una acción que incumple una regla de negocio y otra que falla.
 */
class ComponenteConReglaDeNegocio extends Component
{
    /** Texto del formulario. */
    public string $texto = '';

    /**
     * Acción que incumple una regla de negocio.
     *
     * @return void
     */
    public function guardar(): void
    {
        throw new DomainException('Para atender a ciudadanos tienes que estar adscrito a un centro.');
    }

    /**
     * Acción con un fallo técnico.
     *
     * @return void
     */
    public function fallar(): void
    {
        throw new RuntimeException('Fallo técnico');
    }

    /**
     * Formulario mínimo.
     *
     * @return string
     */
    public function render(): string
    {
        return '<div><input wire:model="texto"></div>';
    }
}
