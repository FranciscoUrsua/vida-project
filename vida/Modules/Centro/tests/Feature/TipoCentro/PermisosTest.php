<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Filament\Resources\CentroResource;
use App\Filament\Resources\HorarioCentroResource\Pages\EditHorarioCentro;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\CierreCalendario;
use Modules\Centro\Services\TipoCentro\CentroService;
use Modules\Centro\Services\TipoCentro\CierreCalendarioService;
use Modules\Centro\Services\TipoCentro\TipoCentroService;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo G — Permisos (TF-TCE-31 a 33; docs/modulo-tipo-centro.md §9).
 *
 * La parte de Filament de TF-TCE-31 (TipoCentroResource) está en InterfazTipoCentroTest.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class PermisosTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario común (lunes 2026-11-02).
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
    }

    /**
     * Si la operación se rechaza por permisos.
     *
     * @param callable $operacion
     *
     * @return bool
     */
    private function denegada(callable $operacion): bool
    {
        try {
            $operacion();
        } catch (AuthorizationException) {
            return true;
        }

        return false;
    }

    /** TF-TCE-31 — Crear, editar y dar de baja tipos es solo del administrador. */
    #[Test]
    public function tf_tce_31_tipos_solo_administrador(): void
    {
        $tipos = app(TipoCentroService::class);
        $datos = ['codigo' => 'nuevo', 'nombre' => 'Tipo nuevo', 'siglas' => 'TN', 'unidad_organizativa_id' => $this->uoArea->id];

        foreach ([
            fn () => $tipos->crear($datos, $this->directoraNorte),
            fn () => $tipos->actualizar($this->tipoCss, ['ventana_reparto_meses' => 6], $this->directoraNorte),
            fn () => $tipos->darDeBaja($this->tipoVacio, today(), $this->directoraNorte),
        ] as $i => $operacion) {
            $this->assertTrue($this->denegada($operacion), "La operación {$i} de la directora debía rechazarse");
        }
        $this->assertNotSame(6, $this->tipoCss->fresh()->ventana_reparto_meses);
        $this->assertTrue($this->tipoVacio->fresh()->activo);
        $this->assertFalse(Gate::forUser($this->directoraNorte)->allows('renombrar', $this->tipoCss));

        $this->assertSame('nuevo', $tipos->crear($datos, $this->admin)->codigo);
        $this->assertSame(6, $tipos->actualizar($this->tipoCss, ['ventana_reparto_meses' => 6], $this->admin)->fresh()->ventana_reparto_meses);
        $this->assertFalse($tipos->darDeBaja($this->tipoVacio, today(), $this->admin)->fresh()->activo);
    }

    /**
     * TF-TCE-32 — La directora edita teléfono, protocolo, un cierre propio y el
     * horario propio de su centro; no el nombre ni lo del tipo, ni otro centro.
     */
    #[Test]
    public function tf_tce_32_la_directora_edita_lo_suyo_y_solo_lo_suyo(): void
    {
        $centros = app(CentroService::class);
        $this->actingAs($this->directoraNorte);

        // Lo suyo
        $centros->actualizar($this->cssNorte, ['telefono' => '910000001', 'protocolo' => 'Acogida en planta baja'], $this->directoraNorte);
        $this->assertSame('910000001', $this->cssNorte->fresh()->telefono);
        $this->assertSame('Acogida en planta baja', $this->cssNorte->fresh()->protocolo);

        app(CierreCalendarioService::class)->crear($this->cssNorte, [
            'fecha_desde' => '2026-12-24', 'fecha_hasta' => '2026-12-24', 'tipo' => 'cierre', 'motivo' => 'Nochebuena',
        ], $this->directoraNorte);
        $this->assertTrue(CierreCalendario::where('titular_id', $this->cssNorte->id)->where('motivo', 'Nochebuena')->exists());

        $horarioNorte = $this->horarioPropio($this->cssNorte);
        Livewire::test(EditHorarioCentro::class, ['record' => $horarioNorte->getRouteKey()])
            ->fillForm(['hora_cierre' => '16:00'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('16:00', substr((string) $horarioNorte->fresh()->hora_cierre, 0, 5));

        // Lo que no es suyo, aunque llegue al servidor: no cambia nada
        $antes = $this->cssNorte->fresh()->only(['nombre', 'telefono']);
        $this->assertTrue($this->denegada(fn () => $centros->actualizar($this->cssNorte, ['nombre' => 'Otro nombre', 'telefono' => '919999999'], $this->directoraNorte)));
        $this->assertSame($antes, $this->cssNorte->fresh()->only(['nombre', 'telefono']));
        $this->assertTrue($this->denegada(fn () => $centros->renombrar($this->cssNorte, 'CSS Norte Nuevo', 'Norte Nuevo', Carbon::parse('2027-01-01'), $this->directoraNorte)));
        foreach (['inscripcion_libre' => true, 'tipo_centro_id' => $this->tipoCiam->id] as $campo => $valor) {
            try {
                $centros->actualizar($this->cssNorte, [$campo => $valor], $this->directoraNorte);
                $this->fail("«{$campo}» no debía poder cambiarse desde el centro");
            } catch (\DomainException) {
            }
        }
        $this->assertSame($this->tipoCss->id, $this->cssNorte->fresh()->tipo_centro_id);

        // Otro centro
        $this->assertTrue($this->denegada(fn () => $centros->actualizar($this->cssSur, ['telefono' => '910000002'], $this->directoraNorte)));
        $this->assertNotSame('910000002', $this->cssSur->fresh()->telefono);
        $this->assertTrue($this->denegada(fn () => app(CierreCalendarioService::class)->crear($this->cssSur, [
            'fecha_desde' => '2026-12-31', 'fecha_hasta' => '2026-12-31', 'tipo' => 'cierre', 'motivo' => 'Nochevieja',
        ], $this->directoraNorte)));
        $this->assertTrue($this->denegada(fn () => app(CierreCalendarioService::class)->crear($this->tipoCss, [
            'fecha_desde' => '2026-12-31', 'fecha_hasta' => '2026-12-31', 'tipo' => 'cierre', 'motivo' => 'Nochevieja',
        ], $this->directoraNorte)));
        // El horario de otro centro ni siquiera aparece para ella (404, sin revelar que existe)
        Livewire::test(EditHorarioCentro::class, ['record' => $this->horarioPropio($this->cssSur)->getRouteKey()])
            ->assertNotFound();
    }

    /** TF-TCE-33 — Alta y baja de centros, solo el administrador. */
    #[Test]
    public function tf_tce_33_alta_y_baja_de_centros_solo_administrador(): void
    {
        $centros = app(CentroService::class);
        $datos = ['nombre' => 'CSS Este', 'nombre_corto' => 'Este', 'tipo_gestion' => 'municipal_directo'];

        $this->actingAs($this->directoraNorte);
        $this->assertFalse(CentroResource::canCreate());
        $this->assertTrue($this->denegada(fn () => $centros->darDeAlta($datos, $this->tipoCss, null, $this->directoraNorte)));
        $this->assertTrue($this->denegada(fn () => $centros->darDeBaja($this->cssSur, today(), $this->directoraNorte)));
        $this->assertFalse(Centro::where('nombre', 'CSS Este')->exists());
        $this->assertTrue($this->cssSur->fresh()->activo);

        $this->actingAs($this->admin);
        $this->assertTrue(CentroResource::canCreate());
        $this->assertSame('CSS Este', $centros->darDeAlta($datos, $this->tipoCss, null, $this->admin)->nombre);
        $this->assertFalse($centros->darDeBaja($this->cssSur, today(), $this->admin)->fresh()->activo);
    }

    /**
     * Horario propio (no heredado) de un centro.
     *
     * @param Centro $centro
     *
     * @return HorarioCentro
     */
    private function horarioPropio(Centro $centro): HorarioCentro
    {
        return HorarioCentro::create([
            'centro_id' => $centro->id, 'nombre' => 'Propio '.$centro->nombre_corto, 'vigente_desde' => '2026-01-01',
            'hereda_horario_tipo' => false, 'dias_laborables' => [1, 2, 3, 4, 5], 'hora_apertura' => '08:00', 'hora_cierre' => '15:00',
            'hora_inicio_atencion' => '08:00', 'hora_fin_atencion' => '15:00', 'buffer_inicio_minutos' => 10,
        ]);
    }
}
