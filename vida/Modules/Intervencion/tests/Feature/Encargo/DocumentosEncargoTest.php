<?php

namespace Modules\Intervencion\Tests\Feature\Encargo;

use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Services\ResolucionAccesoProtegido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Http\Livewire\MisEncargosPage;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\EncargoTestSetup;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — Documentos del encargo y personas protegidas (TF-ENC-30 a 33,
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * El profesional de encargo no ve la carpeta de la persona: solo los documentos de sus
 * encargos y los que sube él (AccesoDocumentosEncargo, EP-8). De una persona protegida, solo
 * mientras el encargo está aceptado (EP-7); cerrado, aparece enmascarada con «Solicitar
 * acceso» (RN-18). Los negativos fallan si se quita la restricción que protegen.
 */
class DocumentosEncargoTest extends TestCase
{
    use EncargoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del encargo profesional con disco y zona de ingesta de pruebas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        $temporal = $this->carpetaTemporalDeTest('ingesta-encargo');
        File::deleteDirectory($temporal);
        config(['documentos.ingesta.directorio_temporal' => $temporal]);

        $this->montarEncargo();
    }

    /** TF-ENC-30 — La abogada ve los documentos vinculados al encargo y los suyos; no otros de la carpeta. [negativo] */
    #[Test]
    public function tf_enc_30_documentos_que_ve_la_abogada(): void
    {
        $adjunto = $this->documentoEnCarpeta($this->ana, 'Notificación del juzgado');
        $otro = $this->documentoEnCarpeta($this->ana, 'Informe médico');
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas, [$adjunto->id]);
        $propio = $this->documentoDe($this->abogadaVallecas->id, $this->ana, 'Escrito de la abogada');

        $abogada = Gate::forUser($this->abogadaVallecas);
        $this->assertTrue($abogada->allows('view', $adjunto));
        $this->assertTrue($abogada->allows('view', $propio));
        $this->assertFalse($abogada->allows('view', $otro));

        // Otro profesional de encargos del mismo centro no ve los del encargo ajeno.
        $this->assertFalse(Gate::forUser($this->educadorMulti)->allows('view', $adjunto));

        // Un documento subido por la abogada de una persona que no es de sus encargos, tampoco.
        [$luis] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $this->assertFalse($abogada->allows('view', $this->documentoDe($this->abogadaVallecas->id, $luis, 'De otra persona')));

        // Con el encargo pendiente no hay acceso.
        [, $historiaEva] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $deEva = $this->documentoEnCarpeta(Ciudadano::find($historiaEva->ciudadano_id), 'De Eva');
        $pendiente = $this->encargoPendiente($historiaEva, $this->ciamVallecas, [$deEva->id]);
        $this->assertFalse($abogada->allows('view', $deEva));

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $encargo->id])
            ->assertSee('Notificación del juzgado')
            ->assertDontSee('Informe médico');
        $this->assertNotNull($pendiente);
    }

    /** TF-ENC-31 — Sube desde el encargo: carpeta de Ana y vínculo al encargo; el TSR lo ve; ella, también otro día. */
    #[Test]
    public function tf_enc_31_subida_desde_el_encargo(): void
    {
        $tipo = $this->tipoInformeExterno();
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $encargo->id])
            ->assertSeeHtml('id="encargo-subir"')
            ->set('tipoId', $tipo->id)
            ->set('titulo', 'Escrito al juzgado')
            ->set('fichero', $this->subida())
            ->call('subir')
            ->assertHasNoErrors()
            ->assertSet('errorIngesta', null)
            ->assertSee('Escrito al juzgado');

        $documento = Documento::where('created_by', $this->abogadaVallecas->id)->sole();
        $vinculos = DocumentoVinculo::query()->activos()->where('documento_id', $documento->id)->get();
        $this->assertTrue($vinculos->contains(fn ($v) => $v->vinculable_type === $this->ana->getMorphClass() && $v->vinculable_id === $this->ana->id));
        $this->assertTrue($vinculos->contains(fn ($v) => $v->vinculable_type === $encargo->getMorphClass() && $v->vinculable_id === $encargo->id));

        $this->assertTrue(Gate::forUser($this->tsNorte)->allows('view', $documento));

        // Otro día, y ya cerrado el encargo, la abogada lo sigue viendo (sin el límite del día).
        Carbon::setTestNow(now()->addDays(3));
        app(EncargoService::class)->cerrar($encargo->refresh(), 'Hecho.', $this->abogadaVallecas);
        $this->assertTrue(Gate::forUser($this->abogadaVallecas)->allows('view', $documento->fresh()));
        Carbon::setTestNow();
    }

    /** TF-ENC-31 bis — Sin encargo en curso o sin centro (RN-15) no se sube. [negativo] */
    #[Test]
    public function tf_enc_31_bis_sin_encargo_en_curso_no_sube(): void
    {
        $tipo = $this->tipoInformeExterno();
        $encargo = $this->encargoCerrado($this->historiaAna, $this->abogadaVallecas);

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EncargoPage::class, ['encargo' => $encargo->id])
            ->assertDontSeeHtml('id="encargo-subir"')
            ->set('tipoId', $tipo->id)
            ->set('fichero', $this->subida())
            ->call('subir')
            ->assertForbidden();

        $this->assertFalse(Documento::where('created_by', $this->abogadaVallecas->id)->exists());
    }

    /** TF-ENC-32 — Protegida con encargo aceptado: la abogada ve a Marta, su ficha y los documentos del encargo. [negativo] */
    #[Test]
    public function tf_enc_32_protegida_con_encargo_aceptado(): void
    {
        $adjunto = $this->documentoEnCarpeta($this->marta, 'Orden de protección');
        $encargo = $this->encargoAceptado($this->historiaMarta, $this->abogadaVallecas, [$adjunto->id]);
        $marta = Ciudadano::withoutGlobalScopes()->find($this->marta->id);

        $this->actingAs($this->abogadaVallecas);
        $this->get(route('ciudadania.ciudadano.ficha', $marta->id))->assertOk();
        Livewire::test(MisEncargosPage::class)->assertSee($marta->nombre_completo);
        $this->assertTrue(Gate::allows('view', $adjunto));

        // Cerrado el encargo, deja de ver también sus documentos (la identifican). Con otro encargo
        // en curso (Ana), para que solo la restricción EP-7 la proteja y no el ámbito vacío.
        $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        app(EncargoService::class)->cerrar($encargo, 'Hecho.', $this->abogadaVallecas);
        $this->assertFalse(Gate::forUser($this->abogadaVallecas->fresh())->allows('view', $adjunto->fresh()));
    }

    /** TF-ENC-33 — Protegida con encargo cerrado: enmascarada, sin enlace, con «Solicitar acceso»; la ficha → 403. [negativo] */
    #[Test]
    public function tf_enc_33_protegida_cerrada_con_solicitar_acceso(): void
    {
        $encargo = $this->encargoCerrado($this->historiaMarta, $this->abogadaVallecas);
        $marta = Ciudadano::withoutGlobalScopes()->find($this->marta->id);

        $this->actingAs($this->abogadaVallecas);
        $this->get(route('ciudadania.ciudadano.ficha', $marta->id))->assertForbidden();

        $pagina = Livewire::test(MisEncargosPage::class)
            ->assertSee('Persona con protección especial')
            ->assertDontSee($marta->nombre_completo)
            ->assertDontSeeHtml(route('intervencion.encargos.show', $encargo->id))
            ->assertSee('Solicitar acceso');

        $pagina->call('prepararSolicitud', $encargo->id)
            ->set('justificacion', 'Corta')
            ->call('solicitarAcceso')
            ->assertHasErrors('justificacion');
        $this->assertSame(0, AccesoProtegido::count());

        $pagina->set('justificacion', 'Hay que completar la demanda que se inició en el encargo.')
            ->call('solicitarAcceso')
            ->assertHasNoErrors()
            ->assertSee('Acceso solicitado')
            ->assertDontSee($marta->nombre_completo);

        $acceso = AccesoProtegido::sole();
        $this->assertSame([$this->abogadaVallecas->id, $marta->id, 'pendiente'], [$acceso->usuario_id, $acceso->ciudadano_id, $acceso->estado]);
        $resolutores = app(ResolucionAccesoProtegido::class)->resolutores($marta->id);
        $this->assertNotEmpty($resolutores);
        foreach ($resolutores as $resolutor) {
            $this->assertTrue(Alerta::pendientesPara($resolutor)->where('origen_type', AccesoProtegido::class)->where('origen_id', $acceso->id)->exists());
        }

        // Aprobado el acceso, vuelve a verla.
        app(ResolucionAccesoProtegido::class)->aprobar($acceso, $resolutores->first());
        $this->get(route('ciudadania.ciudadano.ficha', $marta->id))->assertOk();
    }

    /** TF-ENC-33 bis — «Solicitar acceso» solo para sus encargos cerrados con la persona enmascarada. [negativo] */
    #[Test]
    public function tf_enc_33_bis_solicitud_solo_de_sus_encargos_enmascarados(): void
    {
        $ajeno = $this->encargoCerrado($this->historiaMarta, $this->educadorMulti);
        $visible = $this->encargoCerrado($this->historiaAna, $this->abogadaVallecas);

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(MisEncargosPage::class)->call('prepararSolicitud', $ajeno->id)->assertForbidden();
        Livewire::test(MisEncargosPage::class)->call('prepararSolicitud', $visible->id)->assertForbidden();
        $this->assertSame(0, AccesoProtegido::count());
    }

    /**
     * Documento de la carpeta de una persona dado de alta por un usuario concreto.
     *
     * @param int $autorId
     * @param Ciudadano $persona
     * @param string $titulo
     *
     * @return Documento
     */
    private function documentoDe(int $autorId, Ciudadano $persona, string $titulo): Documento
    {
        $documento = $this->documentoEnCarpeta($persona, $titulo);
        $documento->forceFill(['created_by' => $autorId])->save();

        return $documento->fresh();
    }

    /**
     * PDF válido para subir.
     *
     * @return UploadedFile
     */
    private function subida(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('escrito.pdf', (string) file_get_contents(base_path('Modules/Documentos/tests/fixtures/valido.pdf')));
    }
}
