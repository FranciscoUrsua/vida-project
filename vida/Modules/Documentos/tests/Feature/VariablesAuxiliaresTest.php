<?php

namespace Modules\Documentos\Tests\Feature;

use App\Filament\Resources\ParametroInformeResource\Pages\CreateParametroInforme;
use App\Filament\Resources\ParametroInformeResource\Pages\ListParametrosInforme;
use App\Models\Ciudadano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Documentos\Models\ParametroInforme;
use Modules\Documentos\Services\ResolverFuentesInforme;
use Modules\Documentos\Support\MergeTagsCatalogo;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Variables auxiliares de las plantillas de informe (fase 5, paso 6;
 * docs/instrucciones-cli/documentos-variables-auxiliares.md §9): TF-DOC-22 a 25.
 */
class VariablesAuxiliaresTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    /**
     * Prepara actores y custodia.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDocumentos();
    }

    #[Test]
    public function tf_doc_22_las_variables_dinamicas_se_resuelven(): void
    {
        Carbon::setTestNow('2026-03-09 10:00:00');

        $resultado = $this->resolver('<p>Hoy {{ fecha_hoy }}, año {{ año_actual }}, mes {{mes_actual}}.</p>');

        $this->assertStringContainsString('Hoy 09/03/2026', $resultado);
        $this->assertStringContainsString('año 2026', $resultado);
        $this->assertStringContainsString('mes Marzo', $resultado);
        $this->assertStringNotContainsString('{{', $resultado);
        $this->assertArrayHasKey('fecha_hoy', MergeTagsCatalogo::todos());

        Carbon::setTestNow();
    }

    #[Test]
    public function tf_doc_23_un_parametro_configurable_se_resuelve(): void
    {
        ParametroInforme::create(['clave' => 'ciudad', 'etiqueta' => 'Municipio', 'valor' => 'Madrid']);

        $this->assertStringContainsString('en Madrid', $this->resolver('<p>en {{ ciudad }}</p>'));
        $this->assertSame('Parámetro — Municipio', MergeTagsCatalogo::todos()['ciudad']);

        // La caché se invalida al cambiar el valor
        ParametroInforme::where('clave', 'ciudad')->first()->update(['valor' => 'Getafe']);
        $this->assertStringContainsString('en Getafe', $this->resolver('<p>en {{ ciudad }}</p>'));
    }

    #[Test]
    public function tf_doc_24_el_tag_contextual_gana_al_parametro(): void
    {
        $maria = Ciudadano::factory()->create(['nombre' => 'María', 'apellido1' => 'López', 'apellido2' => null]);
        ParametroInforme::create(['clave' => 'nombre_ciudadano', 'etiqueta' => 'Trampa', 'valor' => 'VALOR_TRAMPA']);
        ParametroInforme::create(['clave' => 'fecha_hoy', 'etiqueta' => 'Trampa', 'valor' => 'FECHA_TRAMPA']);

        $resultado = app(ResolverFuentesInforme::class)
            ->resolverMergeTags('<p>{{ nombre_ciudadano }} · {{ fecha_hoy }}</p>', $maria->id, $this->profesional->id, now());

        $this->assertStringContainsString('María López', $resultado);
        $this->assertStringNotContainsString('VALOR_TRAMPA', $resultado);
        $this->assertStringNotContainsString('FECHA_TRAMPA', $resultado, 'La variable dinámica gana al parámetro');
    }

    #[Test]
    public function tf_doc_25_una_clave_con_formato_invalido_no_se_guarda(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateParametroInforme::class)
            ->fillForm(['clave' => 'Mi Ciudad', 'etiqueta' => 'Ciudad', 'valor' => 'Madrid'])
            ->call('create')
            ->assertHasFormErrors(['clave' => 'regex']);

        $this->assertSame(0, ParametroInforme::count());

        Livewire::actingAs($this->admin)
            ->test(CreateParametroInforme::class)
            ->fillForm(['clave' => 'mi_ciudad', 'etiqueta' => 'Ciudad', 'valor' => 'Madrid'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(1, ParametroInforme::count());

        // Un profesional no gestiona parámetros
        Livewire::actingAs($this->profesional)->test(ListParametrosInforme::class)->assertForbidden();
    }

    /**
     * Sustituye los merge tags para Ana con el profesional de los tests.
     *
     * @param string $html
     *
     * @return string
     */
    private function resolver(string $html): string
    {
        return app(ResolverFuentesInforme::class)->resolverMergeTags($html, $this->ana->id, $this->profesional->id, now());
    }
}
