<?php

namespace Modules\Ciudadania\Tests\Feature\ConsultaBasica;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Tests\Concerns\ConsultaBasicaTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Relaciones y alta de ciudadanos por consulta básica (fase 4, grupo A).
 *
 * TF-CBA-06 y 07.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class RelacionesAltaTest extends TestCase
{
    use ConsultaBasicaTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario y los tipos de relación.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarConsultaBasica();
        $this->seed(TipoRelacionSeeder::class);
    }

    /** TF-CBA-06 — Relaciones: alta, edición y cierre; la unidad de convivencia, no. */
    #[Test]
    public function tf_cba_06_relaciones_si_unidad_de_convivencia_no(): void
    {
        $ficha = Livewire::actingAs($this->ventanillaNorte)->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ana->id])
            ->assertSet('puedeEditarRelaciones', true)
            ->call('abrirModalNuevaRelacion')
            ->call('seleccionarCiudadanoRelacion', $this->pedro->id)
            ->set('relacionTipo', 'conyuge')
            ->set('relacionFechaInicio', '2026-11-02')
            ->call('guardarRelacion')
            ->assertHasNoErrors();

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ana->id)->where('ciudadano_relacionado_id', $this->pedro->id)->firstOrFail();

        $ficha->call('abrirModalEditarRelacion', $relacion->id)
            ->set('relacionObservaciones', 'Viven juntos desde 2020')
            ->call('guardarRelacion')
            ->assertHasNoErrors();
        $this->assertSame('Viven juntos desde 2020', $relacion->fresh()->observaciones);

        $ficha->call('cerrarRelacion', $relacion->id);
        $this->assertNotNull($relacion->fresh()->fecha_fin);

        // La unidad de convivencia se ve en solo lectura: su edición está en Intervención
        $uc = UnidadConvivencia::create(['domicilio' => 'Calle Norte 1', 'fecha_constitucion' => '2026-01-01']);
        $uc->agregarMiembro($this->ana->id);
        $uc->agregarMiembro($this->pedro->id);

        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee('Unidad de convivencia')
            ->assertDontSee('agregarMiembro')
            ->assertDontSee('darDeBajaMiembro');
        $this->actingAs($this->ventanillaNorte)
            ->get(route('intervencion.ciudadano.show', $this->historiaAna->id))
            ->assertForbidden();
    }

    /** TF-CBA-07 — Alta de ciudadanos con el control de duplicados. */
    #[Test]
    public function tf_cba_07_alta_de_ciudadanos_con_control_de_duplicados(): void
    {
        CiudadanoIdentificador::create([
            'ciudadano_id' => $this->pedro->id, 'tipo' => 'nif', 'valor' => '11111111H',
            'fecha_inicio' => '2026-01-01', 'verificado' => false, 'fuente' => 'manual',
        ]);

        // Coincide con Pedro: se le ofrece la persona existente
        $alta = Livewire::actingAs($this->ventanillaNorte)->test(AltaCiudadano::class)
            ->assertOk()
            ->set('busquedaTipoDoc', 'nif')
            ->set('busquedaValorDoc', '11111111H')
            ->call('buscar');
        $this->assertSame($this->pedro->id, $alta->get('resultadosBusqueda')[0]['ciudadanoId']);
        $this->assertTrue($alta->get('resultadosBusqueda')[0]['bloquea']);

        // Persona nueva: se da de alta
        Livewire::actingAs($this->ventanillaNorte)->test(AltaCiudadano::class)
            ->set('fase', 'formulario')
            ->set('nombre', 'Lucía')
            ->set('apellido1', 'Nueva')
            ->set('sexo', 'F')
            ->set('tipoDocumento', 'nif')
            ->set('valorDocumento', '22222222J')
            ->call('guardar')
            ->assertSet('fase', 'confirmacion');
        $this->assertDatabaseHas('ciudadano_identificadores', ['tipo' => 'nif']);

        // Contraste: supervisión sola no da altas (sin ciudadano.crear efectivo)
        Livewire::actingAs($this->supNorte)->test(AltaCiudadano::class)->assertForbidden();
    }
}
