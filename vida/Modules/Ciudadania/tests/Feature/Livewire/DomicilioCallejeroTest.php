<?php

namespace Modules\Ciudadania\Tests\Feature\Livewire;

use App\Enums\OrigenDireccion;
use App\Models\Callejero\CallejeroPortal;
use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use App\Services\Geocodificacion\Callejero\ImportadorCallejero;
use App\Services\Geocodificacion\GeocodificadorInterface;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Ciudadania\Http\Livewire\DomicilioCallejero;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Organizacion\Models\Configuracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FicheroCallejeroDePrueba as Fichero;
use Tests\TestCase;

/**
 * Autocompletado del domicilio con el callejero oficial (TF-CAL-10 y 11).
 *
 * El domicilio se elige, no se adivina: elegido en el callejero se guarda ya
 * normalizado (origen `callejero`) sin pasar por el geocodificador de texto; el texto
 * libre queda como alternativa.
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md (paso 3)
 */
class DomicilioCallejeroTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private UnidadOrganizativa $uo;

    private int $albufera120;

    /**
     * Profesional de intervención en un centro y un callejero con la avenida de la
     * Albufera 120 y 122 y la calle Mayor 15. El geocodificador activo es el del callejero.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Domicilio', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->centroParaUo($this->uo);

        $this->usuario = User::create([
            'name' => 'TS Domicilio', 'email' => 'ts.domicilio@vida360.test', 'password' => 'secreto',
            'email_verified_at' => now(), 'primer_acceso' => false,
        ]);
        $this->usuario->assignRole('intervencion');
        UsuarioUo::create([
            'usuario_id' => $this->usuario->id, 'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno', 'fecha_inicio' => today()->toDateString(),
        ]);

        $albufera = [
            'Codigo de via' => '120300', 'Clase de la via' => 'AVENIDA', 'Partícula de la vía' => 'DE LA', 'Nombre de la vía' => 'ALBUFERA',
            'Codigo de distrito' => '13', 'Codigo de barrio' => '02', 'Seccion censal' => '045', 'Codigo postal' => '28038',
        ];
        $ruta = Fichero::escribir([
            Fichero::fila($albufera + ['Codigo de numero' => '13012001', 'Literal de numeracion' => 'NUM000120', 'Direccion completa para el numero' => 'AVDA ALBUFERA, 000120']),
            Fichero::fila($albufera + ['Codigo de numero' => '13012002', 'Literal de numeracion' => 'NUM000122', 'Direccion completa para el numero' => 'AVDA ALBUFERA, 000122']),
            Fichero::fila(),
        ], $this->carpetaTemporalDeTest('callejero').'/callejero.csv');
        app(ImportadorCallejero::class)->importar($ruta, 'local', $ruta);

        $this->albufera120 = CallejeroPortal::where('cod_ndp', '13012001')->value('id');
        Configuracion::create(['clave' => 'geocoder.proveedor', 'valor' => 'callejero', 'tipo' => 'texto']);
    }

    /**
     * Sustituye el geocodificador de texto por uno que falla el test si se le llama.
     *
     * @return void
     */
    private function prohibirGeocodificadorDeTexto(): void
    {
        $geocodificador = $this->createMock(GeocodificadorInterface::class);
        $geocodificador->expects($this->never())->method('normalizar');
        $this->app->instance(GeocodificadorInterface::class, $geocodificador);
    }

    /**
     * Alta con lo mínimo y el domicilio indicado.
     *
     * @param array<string, mixed> $domicilio
     *
     * @return Ciudadano
     */
    private function darDeAltaCon(array $domicilio): Ciudadano
    {
        $alta = Livewire::actingAs($this->usuario)
            ->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Rosa')
            ->set('apellido1', 'Prueba')
            ->set('sexo', 'F')
            ->set('domicilio', $domicilio)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('fase', 'confirmacion');

        // Sin el scope de ámbito: la persona aún no tiene historia en el centro
        return Ciudadano::withoutGlobalScopes()->findOrFail($alta->get('ciudadanoIdCreado'));
    }

    #[Test]
    public function tf_cal_10_el_profesional_busca_la_via_y_elige_el_numero(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(DomicilioCallejero::class)
            ->set('busquedaVia', 'al')
            ->assertSet('vias', [])
            ->set('busquedaVia', 'albuf')
            ->assertSee('Avenida de la Albufera')
            ->assertDontSee('Calle Mayor')
            ->call('elegirVia', '120300')
            ->assertSet('codVia', '120300')
            ->set('busquedaNumero', '120')
            ->assertSee('120 · 28038')
            ->assertDontSee('122 · 28038')
            ->call('elegirNumero', $this->albufera120)
            ->assertSet('domicilio.portal_id', $this->albufera120)
            ->assertSet('domicilio.manual', false)
            ->assertSee('Avenida de la Albufera 120 · 28038');

        // Una vía sin partícula, sin hueco doble
        Livewire::actingAs($this->usuario)
            ->test(DomicilioCallejero::class)
            ->set('busquedaVia', 'mayor')
            ->assertSee('Calle Mayor');
    }

    #[Test]
    public function tf_cal_10_bis_no_se_puede_elegir_un_numero_de_otra_via(): void
    {
        $mayor15 = CallejeroPortal::where('cod_ndp', '20104713')->value('id');

        Livewire::actingAs($this->usuario)
            ->test(DomicilioCallejero::class)
            ->call('elegirVia', '120300')
            ->call('elegirNumero', $mayor15)
            ->assertSet('domicilio.portal_id', null);
    }

    #[Test]
    public function tf_cal_10_ter_el_alta_guarda_el_numero_elegido_sin_llamar_al_geocodificador(): void
    {
        $this->prohibirGeocodificadorDeTexto();

        $persona = $this->darDeAltaCon(['portal_id' => $this->albufera120, 'piso' => '3', 'puerta' => 'izq', 'manual' => false]);

        $this->assertSame(OrigenDireccion::Callejero, $persona->origen_direccion);
        $this->assertTrue($persona->direccion_normalizada);
        $this->assertSame('callejero', $persona->geocoder_proveedor);
        $this->assertSame('13012001', $persona->codigo_ndp);
        $this->assertSame('132', $persona->barrio_codigo);
        $this->assertSame('2807913045', $persona->seccion_censal_codigo);
        $this->assertSame('Avenida', $persona->tipo_via);
        $this->assertSame('de la Albufera', $persona->nombre_via);
        $this->assertSame('120', $persona->numero);
        $this->assertSame('3', $persona->piso);
        $this->assertSame('izq', $persona->puerta);
        $this->assertSame('Avenida de la Albufera 120, 3º izq', $persona->direccion_texto);
    }

    #[Test]
    public function tf_cal_11_el_texto_libre_se_guarda_sin_normalizar_y_sin_error(): void
    {
        $persona = $this->darDeAltaCon(['manual' => true, 'texto' => 'Sin domicilio fijo']);

        $this->assertSame('Sin domicilio fijo', $persona->direccion_texto);
        $this->assertSame(OrigenDireccion::Profesional, $persona->origen_direccion);
        $this->assertFalse($persona->direccion_normalizada);
        $this->assertNull($persona->seccion_censal_codigo);
    }

    #[Test]
    public function tf_cal_11_bis_escribir_a_mano_parte_de_lo_elegido(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(DomicilioCallejero::class)
            ->call('elegirVia', '120300')
            ->call('elegirNumero', $this->albufera120)
            ->set('domicilio.piso', '2')
            ->call('escribirAMano')
            ->assertSet('domicilio.manual', true)
            ->assertSet('domicilio.portal_id', null)
            ->assertSet('domicilio.texto', 'Avenida de la Albufera 120, 2º');
    }

    #[Test]
    public function tf_cal_11_ter_en_la_ficha_guardar_otros_datos_no_toca_el_domicilio_elegido(): void
    {
        // Elegido en el callejero cuando el número existía; ya no está en el callejero vigente
        $persona = Ciudadano::create([
            'nombre' => 'Rosa', 'apellido1' => 'Prueba', 'sexo' => 'F', 'activo' => true,
            'direccion_texto' => 'Calle Desaparecida 1', 'origen_direccion' => OrigenDireccion::Callejero,
            'direccion_normalizada' => true, 'codigo_ndp' => '99999999', 'seccion_censal_codigo' => '2807901001',
            'geocoder_proveedor' => 'callejero',
        ]);
        $this->prohibirGeocodificadorDeTexto();

        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $persona->id])
            ->call('activarEdicion')
            ->set('telefono', '600111222')
            ->call('guardar')
            ->assertHasNoErrors();

        $persona = Ciudadano::withoutGlobalScopes()->findOrFail($persona->id);
        $this->assertSame('+34600111222', $persona->telefono);
        $this->assertSame(OrigenDireccion::Callejero, $persona->origen_direccion);
        $this->assertSame('99999999', $persona->codigo_ndp);
        $this->assertSame('2807901001', $persona->seccion_censal_codigo);
    }

    #[Test]
    public function tf_cal_11_cuater_en_la_ficha_se_cambia_el_domicilio_por_uno_del_callejero(): void
    {
        $persona = Ciudadano::create([
            'nombre' => 'Rosa', 'apellido1' => 'Prueba', 'sexo' => 'F', 'activo' => true,
            'direccion_texto' => 'Sin domicilio fijo', 'origen_direccion' => OrigenDireccion::Profesional,
        ]);

        Livewire::actingAs($this->usuario)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $persona->id])
            ->call('activarEdicion')
            ->set('domicilio', ['portal_id' => $this->albufera120, 'manual' => false, 'portal' => '', 'escalera' => '', 'piso' => '', 'puerta' => '', 'texto' => 'Sin domicilio fijo'])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('direccionTexto', 'Avenida de la Albufera 120');

        $persona->refresh();
        $this->assertSame(OrigenDireccion::Callejero, $persona->origen_direccion);
        $this->assertSame('2807913045', $persona->seccion_censal_codigo);
    }
}
