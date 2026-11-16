<?php

namespace Modules\Documentos\Tests\Feature\Informes;

use App\Enums\AccionAuditEnum;
use App\Models\HistoriaSocial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Http\Livewire\InformesHistorial;
use Modules\Documentos\Http\Livewire\NuevoInformeWizard;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\InformeService;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Tests\Concerns\InformesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Permisos (TF-INF-25 a 28).
 */
class PermisosInformeTest extends TestCase
{
    use InformesTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario de informes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarInformes();
    }

    #[Test]
    public function tf_inf_25_el_equipo_de_un_plan_activo_redacta_y_firma(): void
    {
        Livewire::actingAs($this->psicologaVallecas)
            ->test(NuevoInformeWizard::class, ['historia' => $this->historiaAna])
            ->call('elegirPlantilla', $this->plantillaPsico->id);

        $informe = Informe::sole();
        $this->assertSame($this->psicologaVallecas->id, $informe->autor_id);

        Livewire::actingAs($this->psicologaVallecas)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->call('guardarSeccion', 'evaluacion', '<p>Evaluación psicológica de la persona.</p>')
            ->call('irAPaso', 3)
            ->call('irAFirma')
            ->set('pdfFirmado', $this->subida($this->pdfFirmadoDe($informe)))
            ->call('subirFirmado')
            ->assertSet('errorFirma', null);

        $this->assertSame(EstadoInforme::Firmado, $informe->fresh()->estado);

        // Con el PIA cerrado deja de poder redactar (no escribe en la historia del CSS)
        $this->piaAna->update(['estado' => 'cerrado']);
        $this->assertFalse(Gate::forUser($this->psicologaVallecas)->allows('crear', [Informe::class, $this->historiaAna]));
    }

    #[Test]
    public function tf_inf_26_sin_relacion_con_la_persona_no_crea_informes(): void
    {
        $tsSur = $this->profesionalCon('ts.sur', 'intervencion', $this->cssSur, 'Trabajador/a social');

        Livewire::actingAs($tsSur)
            ->test(NuevoInformeWizard::class, ['historia' => $this->historiaAna])
            ->assertForbidden();

        $this->expectException(AuthorizationException::class);
        app(InformeService::class)->crear($this->plantillaSocial, $this->historiaAna, $tsSur);
    }

    #[Test]
    public function tf_inf_27_persona_protegida_solo_su_centro_ve_y_redacta(): void
    {
        $informe = Informe::create([
            'plantilla_id' => $this->plantillaSocial->id,
            'historia_social_id' => $this->historiaMarta->id,
            'ciudadano_id' => $this->marta->id,
            'autor_id' => $this->tsNorte->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => ['valoracion' => '<p>Valoración de Marta.</p>'],
        ]);
        $firmado = app(ServicioFirmaInforme::class)->firmar($informe, MetodoFirma::PdfFirmadoExternamente, $this->pdfFirmadoDe($informe), $this->tsNorte);

        // Su centro: ve y redacta
        $this->assertTrue(Gate::forUser($this->tsNorte2)->allows('view', $firmado));
        $this->assertTrue(Gate::forUser($this->tsNorte2)->allows('crear', [Informe::class, $this->historiaMarta]));
        Livewire::actingAs($this->tsNorte2)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaMarta->id])
            ->assertSee('Informe social de prueba');

        // Fuera de su centro: ni la coordinación de Igualdad ni otro CSS
        $tsSur = $this->profesionalCon('ts.sur', 'intervencion', $this->cssSur, 'Trabajador/a social');
        foreach ([$this->coordIgualdad, $tsSur] as $ajeno) {
            $this->assertFalse(Gate::forUser($ajeno)->allows('view', $firmado));
            $this->assertFalse(Gate::forUser($ajeno)->allows('crear', [Informe::class, $this->historiaMarta]));
            Livewire::actingAs($ajeno)
                ->test(InformesHistorial::class, ['historiaId' => $this->historiaMarta->id])
                ->assertDontSee('Informe social de prueba');
            $this->actingAs($ajeno)->get(app(LecturaDocumentoService::class)->urlTemporal($firmado->documento))->assertForbidden();
        }

        // Con un plan activo suyo en el CIAM, el equipo del CIAM sí
        $this->planActivo($this->historiaMarta, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoMarta);
        app(\App\Services\AmbitoUsuario::class)->olvidar();
        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('view', $firmado->fresh()));
        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('crear', [Informe::class, HistoriaSocial::withoutGlobalScopes()->find($this->historiaMarta->id)]));
    }

    #[Test]
    public function tf_inf_28_quien_lee_la_historia_ve_el_pdf_firmado_y_se_audita(): void
    {
        $firmado = $this->firmado($this->tsNorte);

        $historial = Livewire::actingAs($this->tsNorte2)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Ver PDF');
        $url = $historial->instance()->urlPdf($firmado);
        $this->assertNotNull($url);

        $respuesta = $this->actingAs($this->tsNorte2)->get($url);
        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $version = $firmado->documento->versionVigente;
        $this->assertSame($version->hash_sha256 ?? hash('sha256', $respuesta->getContent()), hash('sha256', $respuesta->getContent()));
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->tsNorte2->id,
            'auditable_type' => $firmado->documento->getMorphClass(),
            'auditable_id' => $firmado->documento_id,
            'accion' => AccionAuditEnum::Ver->value,
        ]);

        // tramitación, aunque una vía de documentos le diera acceso, no ve el PDF de un informe
        $adminNorte = $this->profesionalCon('admin.norte', 'tramitacion', $this->cssNorte, 'Auxiliar administrativo');
        $this->assertFalse(Gate::forUser($adminNorte)->allows('view', $firmado->documento));
    }
}
