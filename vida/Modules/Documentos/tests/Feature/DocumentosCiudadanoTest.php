<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Modules\Documentos\Exceptions\FalloHerramientaExterna;
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
    public function tf_doc_ing_01_un_fichero_de_0_bytes_desde_la_pantalla_no_crea_documento(): void
    {
        Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', UploadedFile::fake()->createWithContent('vacio.pdf', ''))
            ->call('guardar')
            ->assertSet('modalAbierto', true)
            ->assertSee('El fichero está vacío.');

        $this->assertSame(0, Documento::count());
    }

    #[Test]
    public function un_fallo_de_qpdf_con_salida_de_error_se_rechaza_y_queda_en_el_log(): void
    {
        // qpdf --requires-password devuelve 2 tanto para «sin cifrar» como cuando no
        // puede abrir el fichero (p. ej. AppArmor le niega la lectura). Simulamos lo segundo.
        $binario = storage_path('framework/testing/qpdf-falla-'.uniqid().'.sh');
        file_put_contents($binario, "#!/bin/sh\necho 'qpdf: open /tmp/x.pdf: Permission denied' >&2\nexit 2\n");
        chmod($binario, 0755);
        $this->beforeApplicationDestroyed(fn () => @unlink($binario));
        config(['documentos.binarios.qpdf' => $binario]);

        $componente = Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', $this->subida())
            ->instance();

        // Livewire::test() sustituye el manejador de excepciones por uno que no registra
        // nada mientras atiende cada ->call(); se invoca guardar() directamente para que
        // report() llegue al registro real.
        Log::spy();
        $componente->guardar();

        $this->assertTrue($componente->modalAbierto);
        $this->assertSame('No se ha podido procesar el PDF. Comprueba que no esté dañado.', $componente->errorIngesta);
        $this->assertSame(0, Documento::count());

        Log::shouldHaveReceived('error')->withArgs(function (string $mensaje, array $contexto) use ($binario): bool {
            $causa = $contexto['exception'] ?? null;

            return $causa instanceof FalloHerramientaExterna
                && $causa->codigoSalida === 2
                && $causa->comando[0] === basename($binario)
                && in_array('--requires-password', $causa->comando, true)
                && str_contains($causa->salidaError, 'Permission denied');
        })->once();
    }

    #[Test]
    public function tf_doc_ing_08_un_fallo_de_ghostscript_desde_la_pantalla_deja_la_causa_en_el_log(): void
    {
        $binario = storage_path('framework/testing/gs-falla-'.uniqid().'.sh');
        file_put_contents($binario, "#!/bin/sh\necho 'GPL Ghostscript: Unrecoverable error, exit code 1' >&2\nexit 1\n");
        chmod($binario, 0755);
        $this->beforeApplicationDestroyed(fn () => @unlink($binario));
        config(['documentos.binarios.gs' => $binario]);

        $componente = Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', $this->subida())
            ->instance();

        // Igual que en el test de qpdf: guardar() directo para que report() llegue al registro.
        Log::spy();
        $componente->guardar();

        // El profesional ve el mensaje de siempre, sin detalles técnicos.
        $this->assertSame('No se ha podido procesar el PDF. Comprueba que no esté dañado.', $componente->errorIngesta);
        $this->assertSame(0, Documento::count());

        Log::shouldHaveReceived('error')->withArgs(function (string $mensaje, array $contexto) use ($binario): bool {
            $causa = $contexto['exception'] ?? null;

            return $causa instanceof FalloHerramientaExterna
                && $causa->codigoSalida === 1
                && $causa->comando[0] === basename($binario)
                && in_array('-sDEVICE=pdfwrite', $causa->comando, true)
                && str_contains($causa->salidaError, 'Unrecoverable error');
        })->once();
    }

    #[Test]
    public function un_rechazo_sin_causa_tecnica_no_se_registra_en_el_log(): void
    {
        $componente = Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', $this->subida('comprimido.zip', 'papeles.zip'))
            ->instance();

        Log::spy();
        $componente->guardar();

        $this->assertStringStartsWith('Este tipo de fichero no se admite.', (string) $componente->errorIngesta);
        Log::shouldNotHaveReceived('error');
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
