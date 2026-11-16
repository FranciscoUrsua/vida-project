<?php

namespace Modules\Documentos\Tests\Feature\Informes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Http\Livewire\PisoFirmadoUpload;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\PisoFirmado;
use Modules\Documentos\Tests\Concerns\InformesTestSetup;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — PISO firmado (TF-INF-21 a 24).
 */
class PisoFirmadoTest extends TestCase
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
    public function tf_inf_21_el_responsable_sube_el_piso_firmado(): void
    {
        $this->subirComo($this->tsNorte, $this->pisoAna, 'Firmado en la entrevista del 10 de noviembre')
            ->assertHasNoErrors()
            ->assertSet('errorSubida', null)
            ->assertSee('PISO firmado el '.now()->format('d/m/Y'))
            ->assertSee('subido por Ts.norte Prueba')
            ->assertSee('Ver documento');

        $piso = PisoFirmado::activos()->where('plan_de_intervencion_id', $this->pisoAna->id)->sole();
        $this->assertSame($this->tsNorte->id, $piso->subido_por);
        $this->assertSame('Firmado en la entrevista del 10 de noviembre', $piso->observaciones);

        $documento = $piso->documento;
        $this->assertSame('piso_firmado', $documento->tipo->codigo);
        $this->assertTrue(Documento::vinculadosA($this->ana)->whereKey($documento->id)->exists());
        $this->assertTrue(Documento::vinculadosA($this->pisoAna)->whereKey($documento->id)->exists());
        $this->assertSame('escaneo', $documento->versionVigente->canal->value);
    }

    #[Test]
    public function tf_inf_22_subir_otro_lo_sustituye_y_el_anterior_se_conserva(): void
    {
        $this->subirComo($this->tsNorte, $this->pisoAna);
        $primero = PisoFirmado::activos()->sole();

        $this->subirComo($this->tsNorte, $this->pisoAna, 'Versión corregida')
            ->assertSee('Sustituir PISO firmado');

        $this->assertSame(2, PisoFirmado::where('plan_de_intervencion_id', $this->pisoAna->id)->count());
        $activo = PisoFirmado::activos()->sole();
        $this->assertNotSame($primero->id, $activo->id);
        $this->assertNotNull($primero->fresh()->sustituido_en);

        // El escaneo nuevo es una versión nueva del mismo documento; la anterior sigue custodiada
        $this->assertSame($primero->documento_id, $activo->documento_id);
        $versiones = Documento::findOrFail($activo->documento_id)->versiones()->reorder('numero')->get();
        $this->assertCount(2, $versiones);
        $this->assertSame(EstadoVersion::Sustituida, $versiones[0]->estado);
        $this->assertSame(EstadoVersion::Vigente, $versiones[1]->estado);
    }

    #[Test]
    public function tf_inf_23_solo_el_responsable_o_la_supervision_del_centro(): void
    {
        foreach ([$this->tsNorte2, $this->psicologaVallecas] as $usuario) {
            Livewire::actingAs($usuario)
                ->test(PisoFirmadoUpload::class, ['planId' => $this->pisoAna->id])
                ->assertDontSee('Subir PISO firmado')
                ->call('abrir')
                ->assertForbidden();

            Livewire::actingAs($usuario)
                ->test(PisoFirmadoUpload::class, ['planId' => $this->pisoAna->id])
                ->set('fichero', $this->escaneo())
                ->call('subir')
                ->assertForbidden();
        }
        $this->assertSame(0, PisoFirmado::count());

        $this->subirComo($this->supNorte, $this->pisoAna)->assertHasNoErrors();
        $this->assertSame($this->supNorte->id, PisoFirmado::activos()->sole()->subido_por);
    }

    #[Test]
    public function tf_inf_24_solo_en_planes_generales_activos(): void
    {
        // PIA (no principal): ni el bloque ni la acción
        Livewire::actingAs($this->psicologaVallecas)
            ->test(PisoFirmadoUpload::class, ['planId' => $this->piaAna->id])
            ->assertDontSee('PISO firmado')
            ->set('fichero', $this->escaneo())
            ->call('subir')
            ->assertForbidden();

        // PISO cerrado
        $this->pisoAna->update(['estado' => EstadoPlan::Cerrado]);
        Livewire::actingAs($this->tsNorte)
            ->test(PisoFirmadoUpload::class, ['planId' => $this->pisoAna->id])
            ->assertDontSee('Subir PISO firmado')
            ->set('fichero', $this->escaneo())
            ->call('subir')
            ->assertForbidden();

        $this->assertSame(0, PisoFirmado::count());
    }

    /**
     * Sube el escaneo del PISO como el usuario dado.
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     * @param string $observaciones
     *
     * @return Testable
     */
    private function subirComo(User $usuario, PlanDeIntervencion $plan, string $observaciones = '')
    {
        return Livewire::actingAs($usuario)
            ->test(PisoFirmadoUpload::class, ['planId' => $plan->id])
            ->call('abrir')
            ->set('fichero', $this->escaneo())
            ->set('observaciones', $observaciones)
            ->call('subir');
    }

    /**
     * Escaneo de prueba (PDF válido de los fixtures de Documentos).
     *
     * @return UploadedFile
     */
    private function escaneo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('piso-firmado.pdf', (string) file_get_contents(module_path('Documentos', 'tests/fixtures/valido.pdf')));
    }
}
