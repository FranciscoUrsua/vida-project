<?php

namespace Modules\Intervencion\Tests\Feature\Encargo\Actuaciones;

use App\Models\Ciudadano;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Http\Livewire\NuevoInformeWizard;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\InformeService;
use Modules\Documentos\Services\ResolverFuentesInforme;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Modules\Documentos\Tests\Support\FirmadorSimulado;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Http\Livewire\MisEncargosPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\ActuacionesEncargoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Informes (TF-EAC-25 a 30, docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * La profesional de encargo redacta informes desde el encargo con las plantillas del área
 * (EA-9): solo fuentes de la persona, del profesional y del encargo (`encargo.datos`); las que
 * leen la Historia se rechazan también al resolver. El firmado va a la carpeta de la persona,
 * vinculado al encargo, con apunte en la Historia. No ve ni edita informes ajenos, y de una
 * persona protegida con el encargo cerrado no le queda ningún acceso. Los negativos fallan si
 * se quita la restricción que protegen.
 */
class InformesEncargoTest extends TestCase
{
    use ActuacionesEncargoTestSetup;
    use RefreshDatabase;

    private Derivacion $encargo;

    private FirmadorSimulado $firmador;

    /**
     * Escenario de las actuaciones con un encargo jurídico en curso de `$ana` y la custodia de PDFs.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
        $temporal = $this->carpetaTemporalDeTest('ingesta');
        File::deleteDirectory($temporal);
        config(['documentos.ingesta.directorio_temporal' => $temporal]);

        $this->montarActuaciones();

        TipoDocumental::firstOrCreate(['codigo' => 'informe_profesional'], [
            'nombre' => 'Informe profesional',
            'familia' => FamiliaDocumental::InformeProfesional,
            'origen_eni' => OrigenEni::Administracion,
            'requiere_firma' => true,
            'vinculables' => ['ciudadano'],
        ]);

        $this->firmador = new FirmadorSimulado;
        $this->encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
    }

    /** TF-EAC-25 — Informe desde el encargo con una plantilla del área: borrador con `derivacion_id`. */
    #[Test]
    public function tf_eac_25_borrador_desde_el_encargo(): void
    {
        $this->actingAs($this->abogadaVallecas);
        $this->get(route('intervencion.encargos.informe.nuevo', $this->encargo->id))
            ->assertOk()
            ->assertSee('Informe jurídico (test)');

        Livewire::test(NuevoInformeWizard::class, ['encargo' => $this->encargo->id])
            ->call('elegirPlantilla', $this->plantillaJuridica->id)
            ->assertRedirect(route('intervencion.encargos.informe.editar', ['encargo' => $this->encargo->id, 'informe' => Informe::sole()->id]));

        $informe = Informe::sole();
        $this->assertSame(EstadoInforme::Borrador, $informe->estado);
        $this->assertSame($this->encargo->id, $informe->derivacion_id);
        $this->assertSame($this->abogadaVallecas->id, $informe->autor_id);
        $this->assertSame($this->ana->id, $informe->ciudadano_id);

        // Se retoma desde el encargo, con el texto de la plantilla y los datos de la persona
        $this->get(route('intervencion.encargos.informe.editar', ['encargo' => $this->encargo->id, 'informe' => $informe->id]))
            ->assertOk()
            ->assertSee('Valoración jurídica')
            ->assertSee('Volver al encargo');

        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Informe jurídico (test)')
            ->assertSeeHtml(route('intervencion.encargos.informe.editar', ['encargo' => $this->encargo->id, 'informe' => $informe->id]));
    }

    /** TF-EAC-26 — La fuente `encargo.datos` resuelve área, centro, motivo, TSR y fechas. */
    #[Test]
    public function tf_eac_26_fuente_datos_del_encargo(): void
    {
        $datos = app(ResolverFuentesInforme::class)->resolver('encargo.datos', $this->ana->id, $this->abogadaVallecas->id, $this->encargo);

        $this->assertSame('Jurídica (test)', $datos['Área']);
        $this->assertSame($this->ciamVallecas->nombre, $datos['Centro']);
        $this->assertSame('Orientación jurídica sobre el desahucio', $datos['Motivo del encargo']);
        $this->assertSame($this->tsNorte->nombreVisible(), $datos['Encargado por']);
        $this->assertSame($this->encargo->created_at->format('d/m/Y'), $datos['Fecha del encargo']);
        $this->assertSame($this->encargo->resuelta_en->format('d/m/Y'), $datos['Fecha de asignación']);

        // Sin encargo no se resuelve: es una fuente del contexto del encargo
        $this->assertSame([], app(ResolverFuentesInforme::class)->resolver('encargo.datos', $this->ana->id, $this->abogadaVallecas->id));

        // Y en el asistente, en una sección automática de una plantilla del área
        $plantilla = $this->plantilla('Informe con datos del encargo (test)', [
            ['id' => 'encargo', 'tipo' => 'automatico', 'fuente' => 'encargo.datos', 'titulo' => 'Encargo', 'editable' => false],
            ['id' => 'texto', 'tipo' => 'texto_libre', 'titulo' => 'Texto', 'obligatorio' => false],
        ]);
        $this->areaJuridica->plantillasInforme()->attach($plantilla);
        $this->actingAs($this->abogadaVallecas);
        $informe = app(InformeService::class)->crearDesdeEncargo($plantilla, $this->encargo, $this->abogadaVallecas);
        Livewire::test(NuevoInformeWizard::class, ['encargo' => $this->encargo->id, 'informe' => $informe->id])
            ->assertSee('Orientación jurídica sobre el desahucio')
            ->assertSee('Jurídica (test)');
    }

    /** TF-EAC-27 — Fuentes prohibidas en contexto de encargo y plantillas fuera del área → rechazadas. [negativo] */
    #[Test]
    public function tf_eac_27_fuentes_y_plantillas_prohibidas(): void
    {
        $resolver = app(ResolverFuentesInforme::class);
        foreach (['historia_social.resumen', 'historia_social.prestaciones_activas', 'escalas.barthel_ultimo'] as $fuente) {
            try {
                $resolver->resolver($fuente, $this->ana->id, $this->abogadaVallecas->id, $this->encargo);
                $this->fail("La fuente {$fuente} no debía resolverse en un informe del encargo");
            } catch (DomainException $e) {
                $this->assertStringContainsString('no se puede usar en un informe del encargo', $e->getMessage());
            }
        }

        // Una plantilla del área editada después para leer la Historia: no se resuelve al generar
        $this->actingAs($this->abogadaVallecas);
        $informe = app(InformeService::class)->crearDesdeEncargo($this->plantillaJuridica, $this->encargo, $this->abogadaVallecas);
        $secciones = $this->plantillaJuridica->secciones;
        $secciones[0]['fuente'] = 'historia_social.resumen';
        PlantillaInforme::whereKey($this->plantillaJuridica->id)->update(['secciones' => json_encode($secciones)]);
        try {
            app(ServicioGeneracionPDF::class)->datosAutomaticos($informe->fresh());
            $this->fail('La sección con fuente prohibida no debía resolverse');
        } catch (DomainException $e) {
            $this->assertStringContainsString('historia_social.resumen', $e->getMessage());
        }

        // Una plantilla que no es del área: ni se ofrece ni se crea, tampoco forjada
        $ajena = $this->plantilla('Informe de otra área (test)', [
            ['id' => 'texto', 'tipo' => 'texto_libre', 'titulo' => 'Texto', 'obligatorio' => false],
        ]);
        Livewire::test(NuevoInformeWizard::class, ['encargo' => $this->encargo->id])
            ->assertDontSee('Informe de otra área (test)')
            ->call('elegirPlantilla', $ajena->id)
            ->assertForbidden();
        try {
            app(InformeService::class)->crearDesdeEncargo($ajena, $this->encargo, $this->abogadaVallecas);
            $this->fail('La plantilla fuera del área no debía usarse');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no es del área', $e->getMessage());
        }

        $this->assertSame(1, Informe::count());
    }

    /** TF-EAC-28 — Firmar: el documento queda en la carpeta de `$ana` vinculado al encargo; el TSR lo ve. */
    #[Test]
    public function tf_eac_28_firma_vinculada_al_encargo(): void
    {
        $this->actingAs($this->abogadaVallecas);
        $informe = app(InformeService::class)->crearDesdeEncargo($this->plantillaJuridica, $this->encargo, $this->abogadaVallecas);
        app(InformeService::class)->guardarSeccion($informe, 'valoracion', '<p>Procede recurso de apelación.</p>', $this->abogadaVallecas);

        $pdf = $this->firmador->firmar(app(ServicioGeneracionPDF::class)->generarParaFirmar($informe->fresh()));
        Livewire::test(NuevoInformeWizard::class, ['encargo' => $this->encargo->id, 'informe' => $informe->id])
            ->call('irAFirma')
            ->set('pdfFirmado', UploadedFile::fake()->createWithContent('firmado.pdf', $pdf))
            ->call('subirFirmado')
            ->assertSet('errorFirma', null)
            ->assertRedirect(route('intervencion.encargos.show', $this->encargo->id));

        $informe->refresh();
        $this->assertSame(EstadoInforme::Firmado, $informe->estado);
        $documento = $informe->documento;
        $vinculos = DocumentoVinculo::query()->activos()->where('documento_id', $documento->id)->get();
        $this->assertTrue($vinculos->contains(fn ($v) => $v->vinculable_type === $this->ana->getMorphClass() && $v->vinculable_id === $this->ana->id));
        $this->assertTrue($vinculos->contains(fn ($v) => $v->vinculable_type === $this->encargo->getMorphClass() && $v->vinculable_id === $this->encargo->id));

        $apunte = Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Documento)->where('apuntable_id', $informe->id)->sole();
        $this->assertSame($this->encargo->id, $apunte->derivacion_id);
        $this->assertNull($apunte->plan_id);

        // La abogada lo ve en el encargo; el TSR, el informe y su documento
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])->assertSee('Firmado');
        $this->assertTrue(Gate::forUser($this->abogadaVallecas)->allows('view', $documento));
        $this->assertTrue(Gate::forUser($this->tsNorte)->allows('view', $informe));
        $this->assertTrue(Gate::forUser($this->tsNorte)->allows('view', $documento));
    }

    /** TF-EAC-29 — La abogada no ve otros informes de `$ana` ni puede editar uno ajeno. [negativo] */
    #[Test]
    public function tf_eac_29_informes_ajenos(): void
    {
        $plantillaTsr = $this->plantilla('Informe social del TSR (test)', [
            ['id' => 'texto', 'tipo' => 'texto_libre', 'titulo' => 'Texto', 'obligatorio' => false],
        ]);
        $this->actingAs($this->tsNorte);
        $deTsr = app(InformeService::class)->crear($plantillaTsr, $this->historiaAna, $this->tsNorte);

        // Un borrador de otro profesional del mismo encargo (forjado en datos): tampoco es suyo
        $deOtro = Informe::create([
            'plantilla_id' => $this->plantillaJuridica->id,
            'historia_social_id' => $this->historiaAna->id,
            'ciudadano_id' => $this->ana->id,
            'autor_id' => $this->educadorMulti->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => [],
            'derivacion_id' => $this->encargo->id,
        ]);

        $this->actingAs($this->abogadaVallecas);
        foreach ([$deTsr, $deOtro] as $ajeno) {
            $this->assertFalse(Gate::allows('view', $ajeno));
            $this->assertFalse(Gate::allows('update', $ajeno));
            $this->get(route('intervencion.encargos.informe.editar', ['encargo' => $this->encargo->id, 'informe' => $ajeno->id]))->assertForbidden();
            $this->get(route('documentos.informes.vista-previa', $ajeno->id))->assertForbidden();
        }

        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertDontSee('Informe social del TSR (test)')
            ->assertDontSeeHtml(route('intervencion.encargos.informe.editar', ['encargo' => $this->encargo->id, 'informe' => $deOtro->id]));

        // La ruta de informes de la Historia sigue cerrada para ella
        $this->get(route('intervencion.informes.nuevo', $this->historiaAna->id))->assertForbidden();
    }

    /** TF-EAC-30 — Protegida con el encargo cerrado: sus informes quedan enmascarados y sin enlace. [negativo] */
    #[Test]
    public function tf_eac_30_protegida_cerrada_sin_acceso_a_sus_informes(): void
    {
        $encargoMarta = $this->encargoAceptado($this->historiaMarta, $this->abogadaVallecas);
        $marta = Ciudadano::withoutGlobalScopes()->find($this->marta->id);

        $this->actingAs($this->abogadaVallecas);
        $informe = app(InformeService::class)->crearDesdeEncargo($this->plantillaJuridica, $encargoMarta, $this->abogadaVallecas);
        app(InformeService::class)->guardarSeccion($informe, 'valoracion', '<p>Informe de Marta.</p>', $this->abogadaVallecas);
        $firmado = app(ServicioFirmaInforme::class)->firmar(
            $informe->fresh(),
            MetodoFirma::PdfFirmadoExternamente,
            $this->firmador->firmar(app(ServicioGeneracionPDF::class)->generarParaFirmar($informe->fresh())),
            $this->abogadaVallecas,
        );
        $this->assertTrue(Gate::allows('view', $firmado));

        app(EncargoService::class)->cerrar($encargoMarta, 'Hecho.', $this->abogadaVallecas);
        $abogada = $this->abogadaVallecas->fresh();
        $this->actingAs($abogada);

        $this->assertFalse(Gate::forUser($abogada)->allows('view', $firmado->fresh()));
        $this->assertFalse(Gate::forUser($abogada)->allows('view', $firmado->documento));
        $this->get(route('intervencion.encargos.show', $encargoMarta->id))->assertForbidden();
        $this->get(route('documentos.informes.vista-previa', $firmado->id))->assertForbidden();

        Livewire::test(MisEncargosPage::class)
            ->assertSee('Persona con protección especial')
            ->assertDontSee($marta->nombre_completo)
            ->assertDontSee('Informe jurídico (test)')
            ->assertDontSeeHtml(route('intervencion.encargos.show', $encargoMarta->id));
    }
}
