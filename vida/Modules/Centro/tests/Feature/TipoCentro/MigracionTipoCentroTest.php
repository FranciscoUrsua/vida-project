<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo H — Migración (TF-TCE-34).
 *
 * Deshace las migraciones desde el 12 nov 2026 (las de la fase y las
 * posteriores), carga datos con el esquema anterior y vuelve a migrar. Todo
 * ocurre dentro de la transacción del test: PostgreSQL admite DDL
 * transaccional, así que el esquema vuelve a su estado al terminar.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class MigracionTipoCentroTest extends TestCase
{
    use RefreshDatabase;

    /** Migraciones desde `2026_11_12_100001_create_tipos_centro_table` hasta la última. */
    private const MIGRACIONES_DESDE_LA_FASE = 11;

    /** TF-TCE-34 — Tras migrar: un tipo por clave, todo con tipo, catálogo antiguo inactivo, horarios intactos y columnas antiguas fuera. */
    #[Test]
    public function tf_tce_34_estado_tras_la_migracion(): void
    {
        Carbon::setTestNow('2026-11-02 10:00');

        $primera = DB::table('migrations')->orderByDesc('migration')->skip(self::MIGRACIONES_DESDE_LA_FASE - 1)->value('migration');
        $this->assertSame('2026_11_12_100001_create_tipos_centro_table', $primera, 'Ajusta MIGRACIONES_DESDE_LA_FASE: hay migraciones nuevas.');

        Artisan::call('migrate:rollback', ['--step' => self::MIGRACIONES_DESDE_LA_FASE, '--force' => true]);
        $this->assertTrue(Schema::hasColumn('centros', 'tipo_centro'));
        $this->assertFalse(Schema::hasTable('tipos_centro'));

        // Datos con el esquema anterior
        DB::table('catalogos_sistema')->where('grupo', 'centro.tipo')->update(['activo' => true]);
        foreach (['css_general' => 'Centro de Servicios Sociales', 'centro_dia' => 'Centro de Día'] as $clave => $etiqueta) {
            DB::table('catalogos_sistema')->updateOrInsert(
                ['grupo' => 'centro.tipo', 'clave' => $clave],
                ['etiqueta' => $etiqueta, 'activo' => true, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            );
        }
        $claves = DB::table('catalogos_sistema')->where('grupo', 'centro.tipo')->pluck('clave');

        $raiz = DB::table('unidades_organizativas')->insertGetId(['nombre' => 'Ayuntamiento', 'tipo' => 'ayuntamiento', 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);
        $uo = fn (string $nombre) => DB::table('unidades_organizativas')->insertGetId(['nombre' => $nombre, 'tipo' => 'centro', 'parent_id' => $raiz, 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);

        $css = DB::table('centros')->insertGetId([
            'nombre' => 'CSS Norte', 'tipo_centro' => 'css_general', 'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $uo('UO CSS Norte'), 'fecha_alta' => '2020-01-01', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $dia = DB::table('centros')->insertGetId([
            'nombre' => 'Centro de día Sur', 'tipo_centro' => 'centro_dia', 'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $uo('UO CD Sur'), 'fecha_alta' => '2020-01-01', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $prestacion = DB::table('prestaciones')->insertGetId(['nombre' => 'Ayuda a domicilio', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['css_general', 'centro_dia'] as $clave) {
            DB::table('prestacion_tipo_centro')->insert(['prestacion_id' => $prestacion, 'tipo_centro' => $clave, 'created_at' => now(), 'updated_at' => now()]);
        }

        $horario = DB::table('horarios_centro')->insertGetId([
            'centro_id' => $css, 'nombre' => 'Horario general', 'dias_laborables' => json_encode([1, 2, 3, 4, 5]),
            'hora_apertura' => '08:00', 'hora_cierre' => '15:00', 'hora_inicio_atencion' => '09:00', 'hora_fin_atencion' => '14:00',
            'vigente_desde' => '2025-01-01', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('migrate', ['--force' => true]);

        // Un tipo por clave, con una denominación vigente
        $this->assertEqualsCanonicalizing($claves->all(), TipoCentro::pluck('codigo')->all());
        foreach (TipoCentro::all() as $tipo) {
            $this->assertNotNull($tipo->denominacionEn(today()), "El tipo {$tipo->codigo} no tiene denominación vigente");
        }

        // Centros y catálogo con su tipo, coherente con la clave
        $tipoDe = TipoCentro::pluck('id', 'codigo');
        $this->assertSame($tipoDe['css_general'], Centro::find($css)->tipo_centro_id);
        $this->assertSame($tipoDe['centro_dia'], Centro::find($dia)->tipo_centro_id);
        $this->assertSame(0, DB::table('centros')->whereNull('tipo_centro_id')->count());
        $this->assertEqualsCanonicalizing(
            [$tipoDe['css_general'], $tipoDe['centro_dia']],
            DB::table('prestacion_tipo_centro')->where('prestacion_id', $prestacion)->pluck('tipo_centro_id')->all(),
        );

        // El catálogo antiguo sigue, inactivo
        $this->assertSame($claves->count(), DB::table('catalogos_sistema')->where('grupo', 'centro.tipo')->where('activo', false)->count());

        // Los horarios existentes no heredan y no cambian
        $propio = HorarioCentro::find($horario);
        $this->assertFalse($propio->hereda_horario_tipo);
        $this->assertSame(['08:00', '15:00', '09:00', '14:00'], array_map(
            fn (string $campo) => substr((string) $propio->{$campo}, 0, 5),
            ['hora_apertura', 'hora_cierre', 'hora_inicio_atencion', 'hora_fin_atencion'],
        ));

        // Columnas y tablas de la addenda §6.2, fuera
        foreach (['inscripcion_libre', 'tipo_centro', 'modo_asignacion_referencia', 'ventana_reparto_meses', 'meses_inactividad_caso', 'horario'] as $columna) {
            $this->assertFalse(Schema::hasColumn('centros', $columna), "centros.{$columna} debía desaparecer");
        }
        $this->assertFalse(Schema::hasTable('centro_prestacion'));
        $this->assertFalse(Schema::hasTable('centro_segmento_poblacion'));
        $this->assertFalse(Schema::hasColumn('prestacion_tipo_centro', 'tipo_centro'));

        // `centros.horario` sigue leyéndose como accesor y es el horario efectivo
        $this->assertSame(
            ['dias_laborables' => [1, 2, 3, 4, 5], 'apertura' => '08:00', 'cierre' => '15:00', 'inicio_atencion' => '09:00', 'fin_atencion' => '14:00', 'origen' => 'centro'],
            Centro::find($css)->horario,
        );
    }
}
