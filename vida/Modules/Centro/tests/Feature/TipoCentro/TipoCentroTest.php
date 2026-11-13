<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Models\Version;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Modules\Centro\Models\Denominacion;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Services\TipoCentro\TipoCentroService;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Tipo de centro (TF-TCE-01 a 06).
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class TipoCentroTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario común de Tipo de Centro.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
    }

    /** TF-TCE-01 — Crear un tipo: valores por defecto y denominación vigente; sin UO funcional, rechazado. */
    #[Test]
    public function tf_tce_01_crear_un_tipo(): void
    {
        $tipo = app(TipoCentroService::class)->crear([
            'codigo' => 'centro_dia', 'nombre' => 'Centro de Día', 'siglas' => 'CD',
            'unidad_organizativa_id' => $this->uoPrimaria->id,
        ], $this->admin);

        $tipo->refresh();
        $this->assertTrue($tipo->activo);
        $this->assertFalse($tipo->permite_restriccion_en_centro);
        $this->assertSame(12, $tipo->ventana_reparto_meses);
        $this->assertSame(6, $tipo->meses_inactividad_caso);
        $this->assertSame('CD', $tipo->denominacionVigente()?->nombre_corto);
        $this->assertTrue($tipo->denominacionVigente()->vigente_desde->isSameDay(today()));

        $this->expectException(DomainException::class);
        app(TipoCentroService::class)->crear(['codigo' => 'sin_uo', 'nombre' => 'Sin UO', 'siglas' => 'SU'], $this->admin);
    }

    /** TF-TCE-02 — El código no cambia. */
    #[Test]
    public function tf_tce_02_codigo_inmutable(): void
    {
        try {
            $this->tipoCss->update(['codigo' => 'otro']);
            $this->fail('Se esperaba LogicException');
        } catch (LogicException) {
            // esperado
        }

        $this->assertSame('css_general', $this->tipoCss->fresh()->codigo);
    }

    /** TF-TCE-03 — Renombrado programado: la caché cambia el día de entrada en vigor; la identidad no. */
    #[Test]
    public function tf_tce_03_renombrado_programado(): void
    {
        $this->tipoCss->programarDenominacion('Centro Especializado', 'CEF', Carbon::parse('2027-01-01'), $this->admin);

        $this->assertSame('CSS', $this->tipoCss->denominacionEn('2026-12-31')->nombre_corto);
        $this->assertSame('CEF', $this->tipoCss->denominacionEn('2027-01-01')->nombre_corto);
        $this->assertSame('CSS', $this->tipoCss->fresh()->siglas);

        Carbon::setTestNow('2027-01-01 00:10:00');
        Artisan::call('denominaciones:aplicar');

        $tipo = $this->tipoCss->fresh();
        $this->assertSame('CEF', $tipo->siglas);
        $this->assertSame('Centro Especializado', $tipo->nombre);
        $this->assertSame('css_general', $tipo->codigo);
        $this->assertSame($this->tipoCss->id, $tipo->id);
        $this->assertSame($this->tipoCss->id, $this->cssNorte->fresh()->tipo_centro_id);
        $this->assertSame($this->tipoCss->id, $this->cssSur->fresh()->tipo_centro_id);
    }

    /** TF-TCE-04 — Sin solape: directa, rechazada; con programarDenominacion(), la anterior se cierra. */
    #[Test]
    public function tf_tce_04_denominaciones_sin_solape(): void
    {
        try {
            $this->tipoCss->denominaciones()->create(['nombre' => 'Otro', 'nombre_corto' => 'OT', 'vigente_desde' => '2026-06-01']);
            $this->fail('Se esperaba DomainException');
        } catch (DomainException) {
            // esperado
        }

        $this->tipoCss->programarDenominacion('Otro', 'OT', Carbon::parse('2026-06-01'), $this->admin);

        $primera = Denominacion::where('denominable_id', $this->tipoCss->id)
            ->where('denominable_type', TipoCentro::class)->orderBy('vigente_desde')->first();
        $this->assertSame('2026-05-31', $primera->vigente_hasta->toDateString());
    }

    /** TF-TCE-05 — Un tipo con centros activos no se da de baja; uno vacío, sí (y sigue existiendo). */
    #[Test]
    public function tf_tce_05_baja_de_tipo(): void
    {
        try {
            app(TipoCentroService::class)->darDeBaja($this->tipoCss, today(), $this->admin);
            $this->fail('Se esperaba LogicException');
        } catch (LogicException) {
            // esperado
        }
        $this->assertTrue($this->tipoCss->fresh()->activo);

        app(TipoCentroService::class)->darDeBaja($this->tipoVacio, today(), $this->admin);

        $vacio = TipoCentro::find($this->tipoVacio->id);
        $this->assertNotNull($vacio);
        $this->assertFalse($vacio->activo);
        $this->assertSame(today()->toDateString(), $vacio->fecha_baja->toDateString());
    }

    /** TF-TCE-06 — Cambiar el tipo deja versión con el valor anterior, el usuario y la fecha. */
    #[Test]
    public function tf_tce_06_versionado_del_tipo(): void
    {
        $this->actingAs($this->admin);
        $this->tipoCss->update(['meses_inactividad_caso' => 9]);

        $version = Version::where('versionable_type', $this->tipoCss->getMorphClass())
            ->where('versionable_id', $this->tipoCss->id)->latest('id')->first();

        $this->assertNotNull($version);
        $this->assertSame(6, (int) $version->datos['meses_inactividad_caso']);
        $this->assertSame($this->admin->id, $version->usuario_id);
        $this->assertNotNull($version->created_at);
    }
}
