<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Centro\Support\MigracionTipoCentro;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo F — Reglas de asignación por tipo (TF-TCE-28 a 30, rama A).
 *
 * Usa el escenario de asignación (profesionales con perfil y reparto) en lugar
 * del de Tipo de Centro.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class ConfiguracionAsignacionTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /**
     * Escenario de asignación.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioAsignacion();
    }

    /** TF-TCE-28 — Cambiar la adscripción en el tipo cambia la de todos sus centros. */
    #[Test]
    public function tf_tce_28_las_reglas_se_leen_del_tipo(): void
    {
        $this->cssNorte->tipoCentro->update(['inscripcion_libre' => true]);

        $config = app(ConfiguracionEfectivaCentro::class);
        $this->assertTrue($config->inscripcionLibre($this->cssNorte->fresh()));
        $this->assertTrue($config->inscripcionLibre($this->cssSur->fresh()));

        $asignacion = app(AsignacionCentroService::class)->asignarPorEleccion($this->pedro, $this->cssNorte->fresh(), $this->ts1);
        $this->assertSame($this->cssNorte->id, $asignacion->centro_id);
    }

    /** TF-TCE-29 — Cambiar el modo de referencia no toca las asignaciones vigentes; la siguiente usa el nuevo. */
    #[Test]
    public function tf_tce_29_cambiar_la_regla_no_reasigna(): void
    {
        $vigentes = collect(range(1, 5))->map(fn (int $i) => AsignacionProfesional::create([
            'historia_id' => $this->historiaDe($this->crearCiudadano("Persona {$i}"), $this->cssNorte)->id,
            'profesional_id' => $i % 2 ? $this->ts1->id : $this->ts2->id,
            'centro_id' => $this->cssNorte->id,
            'origen' => OrigenAsignacionReferencia::Sorteo,
            'cuenta_en_reparto' => true,
            'fecha_inicio' => today()->subMonth(),
        ]));
        $antes = $vigentes->map(fn ($a) => [$a->id, $a->profesional_id])->all();

        $this->cssNorte->tipoCentro->update(['modo_asignacion_referencia' => ModoAsignacionReferenciaCentro::QuienAbre]);

        $despues = AsignacionProfesional::whereIn('id', $vigentes->pluck('id'))->whereNull('fecha_fin')->orderBy('id')
            ->get()->map(fn ($a) => [$a->id, $a->profesional_id])->all();
        $this->assertSame($antes, $despues);

        $nueva = $this->historiaDe($this->crearCiudadano('Nueva'), $this->cssNorte);
        $asignacion = app(AsignacionReferenciaService::class)->asignarInicial($nueva, $this->cssNorte->fresh(), $this->ts3);
        $this->assertSame(OrigenAsignacionReferencia::QuienAbre, $asignacion->origen);
        $this->assertSame($this->ts3->id, $asignacion->profesional_id);
    }

    /**
     * TF-TCE-30 — La migración de datos no aborta por diferencias entre centros: el
     * tipo queda con el valor de la addenda §6.1 y el informe nombra lo descartado.
     *
     * Recrea dentro de la transacción del test las columnas antiguas del centro.
     */
    #[Test]
    public function tf_tce_30_migracion_de_la_configuracion_de_asignacion(): void
    {
        DB::statement('ALTER TABLE centros ALTER COLUMN tipo_centro_id DROP NOT NULL');
        DB::statement('ALTER TABLE asignaciones_centro ALTER COLUMN tipo_centro_id DROP NOT NULL');
        DB::statement('ALTER TABLE prestacion_tipo_centro ALTER COLUMN tipo_centro_id DROP NOT NULL');
        Schema::table('centros', function ($table) {
            $table->string('tipo_centro', 100)->nullable();
            $table->boolean('inscripcion_libre')->default(false);
            $table->string('modo_asignacion_referencia', 20)->default('sorteo');
            $table->unsignedSmallInteger('ventana_reparto_meses')->default(12);
            $table->unsignedSmallInteger('meses_inactividad_caso')->default(6);
        });
        Schema::create('centro_segmento_poblacion', fn ($t) => [$t->unsignedBigInteger('centro_id'), $t->unsignedBigInteger('segmento_poblacion_id')]);
        Schema::create('centro_prestacion', fn ($t) => [$t->unsignedBigInteger('centro_id'), $t->unsignedBigInteger('prestacion_id')]);
        Schema::table('prestacion_tipo_centro', fn ($t) => $t->string('tipo_centro', 100)->nullable());
        Schema::table('asignaciones_centro', fn ($t) => $t->string('tipo_centro', 100)->nullable());

        // Base «antes de la fase»: sin tipos y con la configuración en cada centro
        DB::table('asignaciones_centro')->delete();
        DB::table('centros')->update(['tipo_centro_id' => null]);
        DB::table('denominaciones')->delete();
        DB::table('tipo_centro_segmento_poblacion')->delete();
        DB::table('tipos_centro')->delete();
        DB::table('unidades_organizativas')->insert(['nombre' => 'Dirección General de Servicios Sociales', 'tipo' => 'dg', 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('catalogos_sistema')->updateOrInsert(['grupo' => 'centro.tipo', 'clave' => 'css_general'], ['etiqueta' => 'CSS', 'orden' => 1, 'activo' => true]);
        DB::table('catalogos_sistema')->updateOrInsert(['grupo' => 'centro.tipo', 'clave' => 'ciam'], ['etiqueta' => 'CIAM', 'orden' => 2, 'activo' => true]);
        DB::table('centros')->where('id', $this->cssNorte->id)->update(['tipo_centro' => 'css_general', 'modo_asignacion_referencia' => 'sorteo']);
        DB::table('centros')->where('id', $this->cssSur->id)->update(['tipo_centro' => 'css_general', 'modo_asignacion_referencia' => 'libre_eleccion']);
        DB::table('centros')->where('id', $this->ciam->id)->update(['tipo_centro' => 'ciam', 'inscripcion_libre' => true, 'modo_asignacion_referencia' => 'quien_abre']);

        $informe = app(MigracionTipoCentro::class)->ejecutar();

        $css = DB::table('tipos_centro')->where('codigo', 'css_general')->first();
        $this->assertSame('quien_abre', $css->modo_asignacion_referencia);
        $this->assertSame($css->id, DB::table('centros')->where('id', $this->cssSur->id)->value('tipo_centro_id'));
        $this->assertContains(
            ['tipo' => 'css_general', 'centro' => 'CSS Sur', 'propiedad' => 'modo_asignacion_referencia', 'valor' => "'libre_eleccion'"],
            $informe['descartes'],
        );
        $this->assertContains(
            ['tipo' => 'css_general', 'centro' => 'CSS Norte', 'propiedad' => 'modo_asignacion_referencia', 'valor' => "'sorteo'"],
            $informe['descartes'],
        );
        $this->assertTrue((bool) DB::table('tipos_centro')->where('codigo', 'ciam')->value('inscripcion_libre'));
    }
}
