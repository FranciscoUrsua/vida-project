<?php

namespace Modules\Intervencion\Tests\Feature\Encargo\Actuaciones;

use App\Filament\Resources\AreaEncargoResource;
use App\Filament\Resources\AreaEncargoResource\Pages\CreateAreaEncargo;
use App\Filament\Resources\AreaEncargoResource\Pages\EditAreaEncargo;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Modules\Intervencion\Http\Livewire\EncargosProfesionales;
use Modules\Intervencion\Http\Livewire\MisEncargosPage;
use Modules\Intervencion\Models\AreaEncargo;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\CajaHerramientasEncargo;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\ActuacionesEncargoTestSetup;
use Modules\Supervision\Http\Livewire\DerivacionesPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Área del encargo (TF-EAC-01 a 05, docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * El TSR elige el área al encargar y el área decide la caja de herramientas del profesional:
 * fichas, escalas y plantillas de informe (EA-1 a EA-3). Una plantilla que lee la Historia
 * Social no se puede ofrecer en un área (EA-9). Los negativos fallan si se quita la
 * restricción que protegen.
 */
class AreaEncargoTest extends TestCase
{
    use ActuacionesEncargoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario de las actuaciones del encargo.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActuaciones();
    }

    /** TF-EAC-01 — Encargar con área: se guarda y la muestran el modal, la bandeja y «Mis derivaciones». */
    #[Test]
    public function tf_eac_01_encargar_con_area(): void
    {
        $this->actingAs($this->tsNorte);
        Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrir')
            ->assertSee($this->areaJuridica->nombre)
            ->set('centroDestinoId', $this->ciamVallecas->id)
            ->set('areaId', $this->areaJuridica->id)
            ->set('motivo', 'Orientación jurídica sobre el desahucio')
            ->call('encargar')
            ->assertHasNoErrors()
            ->assertSee($this->areaJuridica->nombre);

        $encargo = Derivacion::query()->encargos()->sole();
        $this->assertSame($this->areaJuridica->id, $encargo->area_encargo_id);

        $this->actingAs($this->directoraVallecas);
        $fila = Livewire::test(DerivacionesPage::class)->get('pendientes')->sole();
        $this->assertStringContainsString($this->areaJuridica->nombre, $fila['tipo']);

        app(EncargoService::class)->aceptar($encargo, $this->abogadaVallecas, $this->directoraVallecas);
        $this->actingAs($this->abogadaVallecas);
        Livewire::test(MisEncargosPage::class)->assertSee($this->areaJuridica->nombre);
    }

    /** TF-EAC-02 — Sin área, o con un área inactiva o borrada, no se encarga. [negativo] */
    #[Test]
    public function tf_eac_02_area_obligatoria_y_activa(): void
    {
        $this->actingAs($this->tsNorte);
        Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrir')
            ->set('centroDestinoId', $this->ciamVallecas->id)
            ->set('motivo', 'Orientación jurídica')
            ->call('encargar')
            ->assertHasErrors('areaId');

        $inactiva = AreaEncargo::create(['codigo' => 'test_inactiva', 'nombre' => 'Inactiva', 'activa' => false]);
        $borrada = AreaEncargo::create(['codigo' => 'test_borrada', 'nombre' => 'Borrada', 'activa' => true]);
        $borrada->delete();

        foreach ([$inactiva, $borrada, new AreaEncargo] as $area) {
            try {
                app(EncargoService::class)->encargar($this->historiaAna, null, null, $this->ciamVallecas, $area, 'Motivo', [], $this->tsNorte);
                $this->fail('Se ha encargado con un área no elegible.');
            } catch (DomainException $e) {
                $this->assertStringContainsString('área', $e->getMessage());
            }
        }

        // El selector no ofrece las inactivas
        Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrir')
            ->assertDontSee('Inactiva');
        $this->assertSame(0, Derivacion::count());
    }

    /** TF-EAC-03 — Un encargo sin área (anterior) no ofrece fichas, escalas ni informes. */
    #[Test]
    public function tf_eac_03_encargo_sin_area(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $encargo->forceFill(['area_encargo_id' => null])->save();
        $encargo->refresh();

        $caja = app(CajaHerramientasEncargo::class);
        $this->assertTrue($caja->tiposFicha($encargo)->isEmpty());
        $this->assertTrue($caja->tiposEscala($encargo)->isEmpty());
        $this->assertTrue($caja->plantillasInforme($encargo)->isEmpty());
        $this->assertFalse($caja->ofreceFicha($encargo, $this->fichaJuridica->id));
    }

    /** TF-EAC-04 — Una plantilla que lee la Historia Social no se asocia a un área, ni desde Filament ni por código. [negativo] */
    #[Test]
    public function tf_eac_04_plantillas_que_leen_la_historia(): void
    {
        $conResumen = $this->plantilla('Con resumen de la historia', [
            ['id' => 'hs', 'tipo' => 'automatico', 'fuente' => 'historia_social.resumen', 'titulo' => 'Historia'],
        ]);
        $conBarthel = $this->plantilla('Con Barthel', [
            ['id' => 'b', 'tipo' => 'automatico', 'fuente' => 'escalas.barthel_ultimo', 'titulo' => 'Barthel'],
        ]);
        $conVariable = $this->plantilla('Con variable de valoración', [
            ['id' => 't', 'tipo' => 'texto_libre', 'titulo' => 'Texto', 'contenido_plantilla' => '<p>Puntuación: {{score_barthel}}</p>'],
        ]);

        foreach ([$conResumen, $conBarthel, $conVariable] as $plantilla) {
            try {
                $this->areaPsicologica->plantillasInforme()->attach($plantilla);
                $this->fail("Se ha asociado «{$plantilla->nombre}».");
            } catch (DomainException $e) {
                $this->assertStringContainsString('no sirve para un encargo', $e->getMessage());
            }
        }
        $this->assertSame(0, $this->areaPsicologica->plantillasInforme()->count());

        $admin = $this->administrador();
        Livewire::actingAs($admin)->test(EditAreaEncargo::class, ['record' => $this->areaPsicologica->getRouteKey()])
            ->fillForm(['plantillasInforme' => [$conResumen->id]])
            ->call('save')
            ->assertHasFormErrors(['plantillasInforme']);
        $this->assertSame(0, $this->areaPsicologica->plantillasInforme()->count());

        // Una plantilla que solo usa identificación, profesional y encargo, sí
        $admisible = $this->plantilla('Admisible', [
            ['id' => 'p', 'tipo' => 'automatico', 'fuente' => 'profesional.datos', 'titulo' => 'Firma'],
            ['id' => 'e', 'tipo' => 'automatico', 'fuente' => 'encargo.datos', 'titulo' => 'Encargo'],
        ]);
        Livewire::actingAs($admin)->test(CreateAreaEncargo::class)
            ->fillForm(['codigo' => 'test_formativa', 'nombre' => 'Formativa', 'activa' => true, 'plantillasInforme' => [$admisible->id], 'tiposFicha' => [$this->fichaJuridica->id]])
            ->call('create')
            ->assertHasNoFormErrors();
        $formativa = AreaEncargo::where('codigo', 'test_formativa')->sole();
        $this->assertSame([$admisible->id], $formativa->plantillasInforme()->pluck('plantillas_informe.id')->all());
        $this->assertSame([$this->fichaJuridica->id], $formativa->tiposFicha()->pluck('tipo_fichas.id')->all());

        $this->actingAs($admin)->get(AreaEncargoResource::getUrl('index'))->assertOk()->assertSee('Formativa');

        // Solo adm_sistema configura las áreas
        Livewire::actingAs($this->tsNorte)->test(CreateAreaEncargo::class)->assertForbidden();
    }

    /** TF-EAC-05 — La caja de herramientas es la del área: la jurídica ofrece su ficha y su plantilla, no la escala de la psicológica. */
    #[Test]
    public function tf_eac_05_caja_de_herramientas_del_area(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $caja = app(CajaHerramientasEncargo::class);

        $this->assertSame([$this->fichaJuridica->id], $caja->tiposFicha($encargo)->pluck('id')->all());
        $this->assertSame([$this->plantillaJuridica->id], $caja->plantillasInforme($encargo)->pluck('id')->all());
        $this->assertTrue($caja->tiposEscala($encargo)->isEmpty());
        $this->assertFalse($caja->ofreceEscala($encargo, $this->escalaPsicologica->id));

        // Lo inactivo no se ofrece aunque siga en el área
        $this->fichaJuridica->update(['activo' => false]);
        $this->assertFalse($caja->ofreceFicha($encargo->refresh(), $this->fichaJuridica->id));
    }

    /** TF-EAC-05 bis — El área tiene código estable y no se borra si tiene encargos. [negativo] */
    #[Test]
    public function tf_eac_05_bis_codigo_estable_y_sin_baja_con_encargos(): void
    {
        $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);

        try {
            $this->areaJuridica->update(['codigo' => 'otro']);
            $this->fail('Ha cambiado el código.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $this->areaJuridica->delete();
    }
}
