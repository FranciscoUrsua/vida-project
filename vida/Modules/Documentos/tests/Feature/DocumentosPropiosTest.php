<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Http\Livewire\DocumentosCiudadano;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Documentos propios del día de consulta básica y tramitación (fase 4, grupo C).
 *
 * Sin `documento.ver_carpeta`, el usuario solo ve lo que ha subido él, durante el día
 * natural de la subida: puede sustituir el fichero, cambiar sus datos y retirarlo
 * (docs/roles-consulta-basica-tramitacion.md §3.3).
 *
 * TF-CBA-20 a 26.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class DocumentosPropiosTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    private User $ventanillaNorte;

    private User $ventanillaSur;

    /**
     * Prepara la custodia y dos usuarios de consulta básica en UO distintas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 10:00:00');
        $this->prepararDocumentos();

        $this->ventanillaNorte = $this->consultaBasicaEn($this->uo);
        $this->ventanillaSur = $this->consultaBasicaEn($this->otraUo);
    }

    /**
     * Usuario con rol `consulta_basica` adscrito a una UO.
     *
     * @param UnidadOrganizativa $uo
     *
     * @return User
     */
    private function consultaBasicaEn(UnidadOrganizativa $uo): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole('consulta_basica');
        UsuarioUo::create(['usuario_id' => $usuario->id, 'unidad_organizativa_id' => $uo->id, 'tipo_vinculo' => 'interno', 'fecha_inicio' => '2020-01-01']);

        return $usuario;
    }

    /**
     * Fichero subido a partir de un fixture.
     *
     * @param string $nombre
     *
     * @return UploadedFile
     */
    private function subida(string $nombre = 'justificante.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, (string) file_get_contents($this->fixture('valido.pdf')));
    }

    /**
     * Documento propio de la ventanilla del Norte, subido desde la ficha de Ana.
     *
     * @return Documento
     */
    private function documentoDeVentanilla(): Documento
    {
        return $this->alta([$this->ana], $this->tipoConConservacion, datos: ['usuario' => $this->ventanillaNorte]);
    }

    /** TF-CBA-20 — Subir: se ingesta como siempre y queda a su nombre. */
    #[Test]
    public function tf_cba_20_subir(): void
    {
        Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee('Subir documento')
            ->call('abrirAlta')
            ->set('tipoId', $this->tipoDni->id)
            ->set('fichero', $this->subida())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Documento guardado.');

        $documento = Documento::sole();
        $this->assertSame($this->ventanillaNorte->id, $documento->created_by);
        $this->assertNotNull($documento->versionVigente);
        $this->assertSame([$documento->id], Documento::vinculadosA($this->ana)->pluck('id')->all());
    }

    /** TF-CBA-21 — Solo los suyos [negativo]. */
    #[Test]
    public function tf_cba_21_solo_los_suyos(): void
    {
        $delTs = $this->alta([$this->ana], datos: ['usuario' => $this->profesional, 'nombreOriginal' => 'del_ts.pdf']);
        $deSur = $this->alta([$this->ana], datos: ['usuario' => $this->ventanillaSur]);
        $propio = $this->documentoDeVentanilla();

        $tarjeta = Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id]);
        $this->assertSame([$propio->id], $tarjeta->instance()->documentos->pluck('id')->all());

        foreach ([$delTs, $deSur] as $ajeno) {
            Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
                ->call('abrirNuevaVersion', $ajeno->id)
                ->assertForbidden();
        }

        $lectura = app(LecturaDocumentoService::class);
        foreach ([$delTs, $deSur] as $ajeno) {
            $this->actingAs($this->ventanillaNorte)->get($lectura->urlTemporal($ajeno))->assertForbidden();
        }
        $this->actingAs($this->ventanillaNorte)->get($lectura->urlTemporal($propio))->assertOk();
    }

    /** TF-CBA-22 — Solo el día de la subida [negativo]. */
    #[Test]
    public function tf_cba_22_solo_el_dia_de_la_subida(): void
    {
        $propio = $this->documentoDeVentanilla();

        Carbon::setTestNow('2026-11-03 09:00:00');

        $tarjeta = Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id]);
        $this->assertCount(0, $tarjeta->instance()->documentos);

        foreach (['abrirNuevaVersion', 'abrirEdicion', 'abrirRetirada'] as $accion) {
            Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
                ->call($accion, $propio->id)
                ->assertForbidden();
        }
        $this->assertSame(EstadoDocumento::Vigente, $propio->fresh()->estado);

        $this->actingAs($this->ventanillaNorte)->get(app(LecturaDocumentoService::class)->urlTemporal($propio))->assertForbidden();
    }

    /** TF-CBA-23 — Sustituir el fichero: versión nueva y la anterior en el historial. */
    #[Test]
    public function tf_cba_23_sustituir_el_fichero(): void
    {
        $propio = $this->documentoDeVentanilla();
        $primera = $propio->versionVigente;

        Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->assertSee('Sustituir fichero')
            ->call('abrirNuevaVersion', $propio->id)
            ->set('fichero', $this->subida('corregido.pdf'))
            ->call('guardar')
            ->assertHasNoErrors();

        $propio->refresh();
        $this->assertSame(2, $propio->versionVigente->numero);
        $this->assertCount(2, $propio->versiones);
        $this->assertNotSame($primera->id, $propio->versionVigente->id);
        $this->assertNotNull($propio->versiones->firstWhere('id', $primera->id));
    }

    /** TF-CBA-24 — Cambiar los datos: tipo y miembros de la unidad de convivencia. */
    #[Test]
    public function tf_cba_24_cambiar_metadatos(): void
    {
        $propio = $this->documentoDeVentanilla();

        $tarjeta = Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirEdicion', $propio->id)
            ->assertSet('tipoId', $this->tipoConConservacion->id)
            ->set('tipoId', $this->tipoEmpadronamiento->id)
            ->set('otrosVinculados', [$this->luis->id])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Datos del documento guardados.');

        $propio->refresh();
        $this->assertSame($this->tipoEmpadronamiento->id, $propio->tipo_documental_id);
        $this->assertSame([$propio->id], Documento::vinculadosA($this->luis)->pluck('id')->all());

        // Una persona que no es de la unidad de convivencia se rechaza
        $tarjeta->call('abrirEdicion', $propio->id)
            ->set('otrosVinculados', [$this->ciudadanoAjeno->id])
            ->call('guardar')
            ->assertHasErrors(['otrosVinculados']);
        $this->assertSame(0, Documento::vinculadosA($this->ciudadanoAjeno)->count());
        $this->assertSame([$propio->id], Documento::vinculadosA($this->luis)->pluck('id')->all());
    }

    /** TF-CBA-25 — Retirar: con motivo, deja de estar disponible y queda trazado. */
    #[Test]
    public function tf_cba_25_retirar(): void
    {
        $propio = $this->documentoDeVentanilla();

        $tarjeta = Livewire::actingAs($this->ventanillaNorte)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirRetirada', $propio->id)
            ->set('motivoRetirada', '')
            ->call('retirar')
            ->assertHasErrors(['motivoRetirada']);
        $this->assertSame(EstadoDocumento::Vigente, $propio->fresh()->estado);

        $tarjeta->set('motivoRetirada', 'Era de otra persona')
            ->call('retirar')
            ->assertHasNoErrors()
            ->assertSee('Documento retirado.');

        $propio->refresh();
        $this->assertSame(EstadoDocumento::Retirado, $propio->estado);
        $this->assertSame($this->ventanillaNorte->id, $propio->retirado_por_id);
        $this->assertNotNull($propio->retirado_en);
        $this->assertSame('Era de otra persona', $propio->motivo_retirada);
        $this->assertNotNull($propio->versionVigente, 'Las versiones se conservan');
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->ventanillaNorte->id,
            'auditable_type' => $propio->getMorphClass(),
            'auditable_id' => $propio->id,
            'accion' => 'editar',
        ]);

        // No se lista para nadie ni se sirve
        $this->assertCount(0, $tarjeta->instance()->documentos);
        $tarjetaTs = Livewire::actingAs($this->profesional)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id]);
        $this->assertCount(0, $tarjetaTs->instance()->documentos);
        $url = app(LecturaDocumentoService::class)->urlTemporal($propio);
        $this->actingAs($this->profesional)->get($url)->assertForbidden();
        $this->actingAs($this->ventanillaNorte)->get($url)->assertForbidden();
    }

    /** TF-CBA-26 — Los demás roles, sin cambios: carpeta completa salvo retirados. */
    #[Test]
    public function tf_cba_26_otros_roles_sin_cambios(): void
    {
        $delTs = $this->alta([$this->ana], datos: ['usuario' => $this->profesional]);
        $deSur = $this->alta([$this->ana], datos: ['usuario' => $this->ventanillaSur]);
        $retirado = $this->documentoDeVentanilla();
        $retirado->update(['estado' => EstadoDocumento::Retirado, 'retirado_en' => now(), 'retirado_por_id' => $this->ventanillaNorte->id, 'motivo_retirada' => 'Error']);

        $tarjeta = Livewire::actingAs($this->profesional)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id])
            ->assertSet('veCarpeta', true)
            ->assertSet('puedeGestionarCarpeta', true)
            ->assertSee('Nueva versión')
            ->assertSee('Desvincular')
            ->assertDontSee('Retirar');

        $this->assertEqualsCanonicalizing([$delTs->id, $deSur->id], $tarjeta->instance()->documentos->pluck('id')->all());
    }
}
