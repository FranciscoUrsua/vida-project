<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Documentos\Http\Livewire\DocumentosCiudadano;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UI operativa de documentos del ciudadano (paso 7): la tarjeta «Documentos» de la ficha.
 *
 * Subir, subir versiones y desvincular exige poder editar al ciudadano; ver la lista,
 * poder verlo. Los rechazos de la ingesta se muestran en castellano llano.
 */
class DocumentosCiudadanoTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    /**
     * Prepara actores y tipos comunes, autenticado como profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDocumentos();
        $this->actingAs($this->profesional);
    }

    /**
     * Fichero subido a partir de un fixture.
     *
     * @param string $fixture Fixture.
     * @param string $nombre Nombre que ve el servidor.
     *
     * @return UploadedFile
     */
    private function subida(string $fixture = 'valido.pdf', string $nombre = 'dni_ana.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, (string) file_get_contents($this->fixture($fixture)));
    }

    #[Test]
    public function un_profesional_sube_un_documento_y_lo_asocia_a_otro_miembro_de_la_unidad_de_convivencia(): void
    {
        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee('Subir documento')
            ->call('abrirAlta')
            ->assertSee($this->luis->nombre_completo)
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', $this->subida())
            ->set('otrosVinculados', [$this->luis->id])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('modalAbierto', false)
            ->assertSee('Documento guardado.');

        $documento = Documento::sole();
        $this->assertSame([$documento->id], Documento::vinculadosA($this->ana)->pluck('id')->all());
        $this->assertSame([$documento->id], Documento::vinculadosA($this->luis)->pluck('id')->all());
        $this->assertSame(0, Documento::vinculadosA($this->eva)->count());
        $this->assertSame('dni_ana.pdf', $documento->versionVigente->nombre_original);
    }

    #[Test]
    public function solo_se_puede_asociar_a_miembros_de_la_unidad_de_convivencia(): void
    {
        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', $this->subida())
            ->set('otrosVinculados', [$this->ciudadanoAjeno->id])
            ->call('guardar');

        $this->assertSame(0, Documento::vinculadosA($this->ciudadanoAjeno)->count());
        $this->assertSame(1, Documento::vinculadosA($this->ana)->count());
    }

    #[Test]
    public function un_rechazo_de_la_ingesta_se_muestra_en_castellano_llano(): void
    {
        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', $this->subida('comprimido.zip', 'papeles.zip'))
            ->call('guardar')
            ->assertSet('modalAbierto', true)
            ->assertSee('Este tipo de fichero no se admite.');

        $this->assertSame(0, Documento::count());
    }

    #[Test]
    public function los_datos_que_exige_el_tipo_son_obligatorios(): void
    {
        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoInformeMedico->id)
            ->set('fichero', $this->subida())
            ->call('guardar')
            ->assertHasErrors(['fechaEmision', 'organoEmisor']);

        $this->assertSame(0, Documento::count());
    }

    #[Test]
    public function se_sube_una_nueva_version_y_se_ve_el_historial(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoInformeMedico, datos: ['fechaEmision' => now(), 'organoEmisor' => 'SERMAS']);

        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirNuevaVersion', $documento->id)
            ->assertSee('Subir nueva versión')
            ->set('fichero', $this->subida())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Versión 2')
            ->call('alternarHistorial', $documento->id)
            ->assertSee('Versión 1 · subida el');

        $this->assertSame(2, $documento->versionVigente()->first()->numero);
    }

    #[Test]
    public function desvincular_quita_el_documento_de_la_ficha_sin_borrarlo(): void
    {
        $documento = $this->alta([$this->ana, $this->luis]);

        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee($this->tipoDni->nombre)
            ->call('desvincular', $documento->id)
            ->assertSee('No hay documentos asociados a esta persona.');

        $this->assertSame(0, Documento::vinculadosA($this->ana)->count());
        $this->assertSame([$documento->id], Documento::vinculadosA($this->luis)->pluck('id')->all());
        $this->assertNotNull($documento->fresh()->versionVigente);
    }

    #[Test]
    public function no_se_actua_sobre_documentos_de_otra_persona(): void
    {
        $ajeno = $this->alta([$this->ciudadanoAjeno]);

        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('desvincular', $ajeno->id)
            ->assertNotFound();

        $this->assertSame(1, Documento::vinculadosA($this->ciudadanoAjeno)->count());
    }

    #[Test]
    public function supervision_ve_los_documentos_pero_no_puede_subir_ni_desvincular(): void
    {
        $documento = $this->alta([$this->ana]);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervision');
        $this->actingAs($supervisor);

        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee($this->tipoDni->nombre)
            ->assertSee('Descargar')
            ->assertDontSee('Subir documento')
            ->assertDontSee('Desvincular')
            ->call('abrirAlta')
            ->assertForbidden();

        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('desvincular', $documento->id)
            ->assertForbidden();

        $this->assertSame(1, Documento::vinculadosA($this->ana)->count());
    }

    #[Test]
    public function un_documento_caducado_se_marca_como_caducado(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoEmpadronamiento);
        $documento->update(['fecha_validez' => today()->subDay()]);

        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee('Caducado el '.today()->subDay()->format('d/m/Y'));
    }

    #[Test]
    public function la_ficha_del_ciudadano_incluye_la_tarjeta_de_documentos(): void
    {
        $this->alta([$this->ana]);

        $this->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee('ficha-documentos', false)
            ->assertSee($this->tipoDni->nombre);
    }
}
