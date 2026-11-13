<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Filament\Resources\CentroResource;
use App\Filament\Resources\CentroResource\Pages\EditCentro;
use App\Filament\Resources\TipoCentroResource;
use App\Filament\Resources\TipoCentroResource\Pages\CreateTipoCentro;
use App\Filament\Resources\TipoCentroResource\Pages\EditTipoCentro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Centro\Enums\ElementoCatalogo;
use Modules\Centro\Models\CierreCalendario;
use Modules\Centro\Models\ExclusionCatalogo;
use Modules\Centro\Models\HorarioTipoCentro;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Interfaz de Tipo de Centro en Filament (paso 9): TipoCentroResource solo para
 * el administrador (parte de Filament de TF-TCE-31) y CentroResource con lo que
 * no es de la dirección deshabilitado, herencia visible y acciones por servicio.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md paso 9
 */
class InterfazTipoCentroTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario de Tipo de Centro con la fecha de referencia de los tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 10:00');
        $this->montarTipoCentro();
    }

    /** TF-TCE-31 (Filament) — La directora no entra en los tipos; el administrador los crea, edita y da de baja. */
    #[Test]
    public function tf_tce_31_filament_tipos_solo_administrador(): void
    {
        $this->actingAs($this->directoraNorte);
        $this->get(TipoCentroResource::getUrl('index'))->assertForbidden();
        $this->get(TipoCentroResource::getUrl('create'))->assertForbidden();
        $this->get(TipoCentroResource::getUrl('edit', ['record' => $this->tipoCss]))->assertForbidden();
        Livewire::test(EditTipoCentro::class, ['record' => $this->tipoVacio->getRouteKey()])->assertForbidden();

        // Cambio de usuario en el mismo test: sin vaciar la sesión, AuthenticateSession de Filament la cierra
        $this->flushSession();
        $this->actingAs($this->admin);
        $this->get(TipoCentroResource::getUrl('index'))->assertOk();

        Livewire::test(CreateTipoCentro::class)
            ->fillForm([
                'codigo' => 'centro_dia',
                'nombre' => 'Centro de Día',
                'siglas' => 'CD',
                'unidad_organizativa_id' => $this->uoPrimaria->id,
                'segmentos' => [$this->tipoCss->segmentos()->first()->id],
                'tiposPlan' => [$this->planPiso->id],
                'horario' => [
                    'dias_laborables' => [1, 2, 3, 4, 5],
                    'hora_apertura' => '08:00', 'hora_cierre' => '15:00',
                    'hora_inicio_atencion' => '09:00', 'hora_fin_atencion' => '14:00',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = TipoCentro::where('codigo', 'centro_dia')->sole();
        $this->assertSame('CD', $nuevo->siglas);
        $this->assertCount(1, $nuevo->denominaciones()->get());
        $this->assertSame([$this->planPiso->id], $nuevo->tiposPlan()->pluck('tipos_plan.id')->all());
        $this->assertSame('08:00', substr(HorarioTipoCentro::where('tipo_centro_id', $nuevo->id)->sole()->hora_apertura, 0, 5));

        Livewire::test(EditTipoCentro::class, ['record' => $this->tipoCss->getRouteKey()])
            ->assertFormFieldIsDisabled('codigo')
            ->fillForm(['ventana_reparto_meses' => 9])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(9, $this->tipoCss->fresh()->ventana_reparto_meses);
        $this->assertSame('css_general', $this->tipoCss->fresh()->codigo);

        Livewire::test(EditTipoCentro::class, ['record' => $this->tipoVacio->getRouteKey()])
            ->callAction('darDeBaja', data: ['fecha' => '2026-11-02']);
        $this->assertFalse($this->tipoVacio->fresh()->activo);

        // Con centros activos, la baja se rechaza (RN-11) y el tipo sigue activo
        Livewire::test(EditTipoCentro::class, ['record' => $this->tipoCss->getRouteKey()])
            ->callAction('darDeBaja', data: ['fecha' => '2026-11-02'])
            ->assertNotified();
        $this->assertTrue($this->tipoCss->fresh()->activo);
    }

    /** Denominación y horario del tipo se programan con fecha, sin tocar lo vigente hasta entonces. */
    #[Test]
    public function el_administrador_programa_denominacion_y_horario_del_tipo(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(EditTipoCentro::class, ['record' => $this->tipoCss->getRouteKey()])
            ->callAction('renombrar', data: ['nombre' => 'Centro de Servicios Sociales Municipal', 'corto' => 'CSSM', 'desde' => '2027-01-01'])
            ->callAction('programarHorario', data: [
                'desde' => '2027-01-01', 'dias_laborables' => [1, 2, 3, 4],
                'hora_apertura' => '08:00', 'hora_cierre' => '15:00',
                'hora_inicio_atencion' => '08:30', 'hora_fin_atencion' => '14:30',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('CSS', $this->tipoCss->fresh()->siglas);
        $this->assertSame('CSSM', $this->tipoCss->denominacionEn('2027-01-01')->nombre_corto);
        $this->assertSame(2, HorarioTipoCentro::where('tipo_centro_id', $this->tipoCss->id)->count());
    }

    /** La directora edita contacto y protocolo y personaliza cierres; lo demás, deshabilitado y sin acciones de administrador. */
    #[Test]
    public function la_directora_edita_solo_lo_suyo_en_el_formulario_del_centro(): void
    {
        $this->actingAs($this->directoraNorte);

        Livewire::test(EditCentro::class, ['record' => $this->cssNorte->getRouteKey()])
            ->assertFormFieldIsDisabled('nombre')
            ->assertFormFieldIsDisabled('tipo_gestion')
            ->assertFormFieldIsDisabled('unidad_organizativa_id')
            ->assertFormFieldIsDisabled('tipo_centro_id')
            ->assertFormFieldIsEnabled('telefono')
            ->assertFormFieldIsEnabled('protocolo')
            ->assertActionHidden('renombrar')
            ->assertActionHidden('darDeBaja')
            ->assertActionHidden('exclusiones')
            ->assertSee('Heredado del tipo')
            ->fillForm(['telefono' => '910000001', 'protocolo' => 'Acogida en planta baja'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('anadirCierre', data: [
                'tipo' => 'cierre', 'fecha_desde' => '2026-12-24', 'fecha_hasta' => '2026-12-24', 'motivo' => 'Nochebuena',
            ]);

        $centro = $this->cssNorte->fresh();
        $this->assertSame('910000001', $centro->telefono);
        $this->assertSame('Acogida en planta baja', $centro->protocolo);
        $this->assertSame($this->tipoCss->id, $centro->tipo_centro_id);
        $this->assertTrue(CierreCalendario::where('titular_id', $centro->id)->where('motivo', 'Nochebuena')->exists());

        // Con protocolo propio: insignia «Personalizado» y vuelta al valor del tipo
        Livewire::test(EditCentro::class, ['record' => $this->cssNorte->getRouteKey()])
            ->assertSee('Personalizado')
            ->callAction('volverProtocolo');
        $this->assertNull($this->cssNorte->fresh()->protocolo);

        // Otro centro: ni siquiera se puede abrir
        $this->get(CentroResource::getUrl('edit', ['record' => $this->cssSur]))->assertForbidden();
    }

    /** Las exclusiones de catálogo solo se ofrecen si el tipo lo permite (RN-08) y se guardan por servicio. */
    #[Test]
    public function exclusiones_de_catalogo_si_el_tipo_lo_permite(): void
    {
        $this->actingAs($this->directoraNorte);
        $this->tipoCss->update(['permite_restriccion_en_centro' => true]);

        Livewire::test(EditCentro::class, ['record' => $this->cssNorte->getRouteKey()])
            ->assertActionVisible('exclusiones')
            ->callAction('exclusiones', data: [ElementoCatalogo::Prestacion->value => [$this->p1->id]]);

        $this->assertTrue(ExclusionCatalogo::where('centro_id', $this->cssNorte->id)->where('elemento_id', $this->p1->id)->exists());

        Livewire::test(EditCentro::class, ['record' => $this->cssNorte->getRouteKey()])
            ->callAction('exclusiones', data: [ElementoCatalogo::Prestacion->value => []]);

        $this->assertFalse(ExclusionCatalogo::where('centro_id', $this->cssNorte->id)->exists());
    }

    /** El administrador renombra y da de baja un centro desde sus acciones. */
    #[Test]
    public function el_administrador_renombra_y_da_de_baja_un_centro(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(EditCentro::class, ['record' => $this->cssSur->getRouteKey()])
            ->assertFormFieldIsEnabled('tipo_gestion')
            ->assertFormFieldIsDisabled('nombre')
            ->callAction('renombrar', data: ['nombre' => 'CSS Sur Nuevo', 'corto' => 'Sur Nuevo', 'desde' => '2027-01-01'])
            ->callAction('darDeBaja', data: ['fecha' => '2026-11-02']);

        $this->assertSame('Sur Nuevo', $this->cssSur->denominacionEn('2027-01-01')->nombre_corto);
        $this->assertFalse($this->cssSur->fresh()->activo);
    }
}
