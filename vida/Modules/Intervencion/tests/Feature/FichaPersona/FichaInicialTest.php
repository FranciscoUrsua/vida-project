<?php

namespace Modules\Intervencion\Tests\Feature\FichaPersona;

use App\Models\CatalogoSistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Database\Seeders\FichaSociodemograficaSeeder;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ficha inicial «Datos sociodemográficos» (TF-SDE-32 a 34, bloque E): la siembra la migración de
 * datos, el seeder no pisa lo editado en el backoffice y no se ofrece como valoración.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque E
 */
class FichaInicialTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    #[Test]
    public function tf_sde_32_tras_la_migracion_existe_la_ficha_sociodemografica_con_sus_diez_campos(): void
    {
        $tipo = TipoFicha::where('codigo', 'sociodemografica')->first();

        $this->assertNotNull($tipo, 'La migración de datos siembra la ficha inicial.');
        $this->assertSame('persona', $tipo->uso);
        $this->assertTrue($tipo->activo);

        $campos = collect($tipo->schema['campos']);
        $this->assertSame([
            'pais_nacimiento', 'nacionalidad', 'segunda_nacionalidad', 'anio_llegada_espana', 'idioma_principal',
            'nivel_estudios', 'situacion_laboral', 'ingresos_netos_mensuales', 'numero_pagas', 'fecha_referencia_ingresos',
        ], $campos->pluck('id')->all());

        $this->assertSame(
            ['pais_nacimiento' => 'comun.pais', 'nacionalidad' => 'comun.pais', 'segunda_nacionalidad' => 'comun.pais', 'idioma_principal' => 'comun.idioma', 'nivel_estudios' => 'comun.nivel_estudios', 'situacion_laboral' => 'comun.situacion_laboral'],
            $campos->where('tipo', 'catalogo')->pluck('catalogo', 'id')->all(),
        );
        $this->assertSame('Estudios terminados de mayor nivel', $campos->firstWhere('id', 'nivel_estudios')['etiqueta']);
        $this->assertSame('€', $campos->firstWhere('id', 'ingresos_netos_mensuales')['unidad']);
        $this->assertSame('fecha', $campos->firstWhere('id', 'fecha_referencia_ingresos')['tipo']);
        $this->assertFalse($campos->contains(fn (array $c) => $c['obligatorio']), 'Todo es voluntario (SD-9).');

        // Cada grupo de catálogo tiene valores con los que contestar
        foreach ($campos->where('tipo', 'catalogo')->pluck('catalogo')->unique() as $grupo) {
            $this->assertGreaterThan(1, CatalogoSistema::where('grupo', $grupo)->vigentes()->count(), $grupo);
        }
    }

    #[Test]
    public function tf_sde_33_volver_a_ejecutar_el_seeder_no_sobrescribe_un_schema_editado(): void
    {
        $tipo = TipoFicha::where('codigo', 'sociodemografica')->firstOrFail();
        $schema = $tipo->schema;
        array_pop($schema['campos']);
        $schema['campos'][0]['etiqueta'] = 'País donde nació';
        $tipo->update(['schema' => $schema, 'nombre' => 'Datos sociodemográficos (editada)', 'activo' => false]);

        $this->seed(FichaSociodemograficaSeeder::class);

        $this->assertSame(1, TipoFicha::where('codigo', 'sociodemografica')->count(), 'No duplica.');
        $tipo->refresh();
        $this->assertCount(9, $tipo->schema['campos']);
        $this->assertSame('País donde nació', $tipo->schema['campos'][0]['etiqueta']);
        $this->assertSame('Datos sociodemográficos (editada)', $tipo->nombre);
        $this->assertFalse($tipo->activo, 'Tampoco la reactiva.');

        // Sin el tipo, el seeder la vuelve a crear entera
        $tipo->delete();
        $this->seed(FichaSociodemograficaSeeder::class);
        $this->assertCount(10, TipoFicha::where('codigo', 'sociodemografica')->firstOrFail()->schema['campos']);
    }

    #[Test]
    public function tf_sde_34_la_ficha_inicial_no_aparece_en_el_selector_de_valoraciones_de_ningun_centro(): void
    {
        $this->montarCircuito();
        $tipo = TipoFicha::where('codigo', 'sociodemografica')->firstOrFail();

        // Aunque un tipo de centro la tuviera en su catálogo de fichas (SD-11 y uso persona)
        $this->tipoCss->tiposFicha()->syncWithoutDetaching([$tipo->id]);

        foreach (Centro::all() as $centro) {
            $this->assertNotContains($tipo->id, app(ConfiguracionEfectivaCentro::class)->tiposFicha($centro)->pluck('id')->all(), $centro->nombre);
        }
        $this->assertNotContains($tipo->id, TipoFicha::activos()->deValoracion()->pluck('id')->all());

        $this->actingAs($this->tsNorte);
        $selector = Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historiaAna])->instance()->fichasDisponibles();
        $this->assertArrayNotHasKey($tipo->id, $selector);
    }
}
