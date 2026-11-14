<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use App\Models\Ciudadano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Migración de datos de la fase 3 (TF-CIR-03 y 07).
 *
 * Deshace las migraciones de la fase, siembra datos con el esquema anterior y vuelve
 * a migrar. Decisiones del 14 nov 2026: los planes activos sin tipo pasan al PIA si
 * la historia es de un CIAM y al principal en el resto; los cerrados no se tocan.
 */
class MigracionCircuitoTest extends TestCase
{
    use RefreshDatabase;

    /** Primera migración de la fase 3. */
    private const PRIMERA = '2026_11_14_100001_add_es_principal_to_tipos_plan_table';

    /** TF-CIR-03 y TF-CIR-07 — Tipos corregidos, planes activos sin tipo tipados, cerrados intactos y centro relleno. */
    #[Test]
    public function tf_cir_03_y_07_migracion_de_tipos_y_centro(): void
    {
        $pasos = DB::table('migrations')->where('migration', '>=', self::PRIMERA)->count();
        Artisan::call('migrate:rollback', ['--step' => $pasos, '--force' => true]);
        $this->assertFalse(Schema::hasColumn('planes_intervencion', 'centro_id'));
        $this->assertFalse(Schema::hasColumn('tipos_plan', 'es_principal'));

        $ahora = now();
        $uo = fn (string $nombre) => DB::table('unidades_organizativas')->insertGetId(['nombre' => $nombre, 'tipo' => 'centro', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        $uoFuncional = $uo('Departamento');
        $tipoCentro = fn (string $codigo) => DB::table('tipos_centro')->insertGetId(['codigo' => $codigo, 'nombre' => $codigo, 'siglas' => strtoupper($codigo), 'unidad_organizativa_id' => $uoFuncional, 'fecha_alta' => '2026-01-01', 'created_at' => $ahora, 'updated_at' => $ahora]);
        $tcCss = $tipoCentro('css_general');
        $tcCiam = $tipoCentro('ciam');
        $centro = fn (string $nombre, int $uoId, int $tipo) => DB::table('centros')->insertGetId(['nombre' => $nombre, 'tipo_gestion' => 'municipal_directo', 'unidad_organizativa_id' => $uoId, 'tipo_centro_id' => $tipo, 'fecha_alta' => '2026-01-01', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        $uoCss = $uo('UO CSS');
        $uoCiam = $uo('UO CIAM');
        $uoSinCentro = $uo('UO sin centro');
        $css = $centro('CSS', $uoCss, $tcCss);
        $ciam = $centro('CIAM', $uoCiam, $tcCiam);

        $tipoPlan = fn (string $slug, string $ambito, bool $directa = false) => DB::table('tipos_plan')->insertGetId(['slug' => $slug, 'nombre' => strtoupper($slug), 'ambito' => $ambito, 'admite_entrada_directa' => $directa, 'activo' => true, 'eliminable' => false, 'created_at' => $ahora, 'updated_at' => $ahora]);
        $piso = $tipoPlan('piso', 'asp');
        $pia = $tipoPlan('pia', 'especializado', true);

        $usuario = DB::table('users')->insertGetId(['name' => 'ts', 'email' => 'ts@vida360.test', 'password' => 'x', 'created_at' => $ahora, 'updated_at' => $ahora]);
        $historia = function (int $uoId) use ($ahora): int {
            // La tabla de ciudadanos no cambia en la fase: se usa la factoría (datos cifrados).
            $ciudadano = Ciudadano::factory()->create()->id;

            return DB::table('historias_sociales')->insertGetId(['ciudadano_id' => $ciudadano, 'unidad_organizativa_id' => $uoId, 'estado' => 'abierta', 'ciudadano_protegido' => false, 'created_at' => $ahora, 'updated_at' => $ahora]);
        };
        $plan = fn (int $historiaId, string $estado, ?int $tipoPlanId, string $tipo = 'general_asp') => DB::table('planes_intervencion')->insertGetId([
            'historia_id' => $historiaId, 'tipo_plan_id' => $tipoPlanId, 'tipo' => $tipo, 'profesional_responsable_id' => $usuario,
            'estado' => $estado, 'fecha_inicio' => '2026-06-26', 'version' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);

        $activoCss = $plan($historia($uoCss), 'activo', null);
        $activoCiam = $plan($historia($uoCiam), 'activo', null);
        $cerradoSinTipo = $plan($historia($uoCss), 'cerrado', null);
        $incoherente = $plan($historia($uoCiam), 'activo', $pia, 'general_asp');
        $sinCentro = $plan($historia($uoSinCentro), 'activo', $piso);

        Artisan::call('migrate', ['--force' => true]);

        $fila = fn (int $id) => DB::table('planes_intervencion')->find($id);

        // TF-CIR-03
        $this->assertTrue((bool) DB::table('tipos_plan')->where('id', $piso)->value('es_principal'));
        $this->assertSame([$piso, 'general_asp'], [$fila($activoCss)->tipo_plan_id, $fila($activoCss)->tipo]);
        $this->assertSame([$pia, 'especializado'], [$fila($activoCiam)->tipo_plan_id, $fila($activoCiam)->tipo]);
        $this->assertSame([null, 'general_asp'], [$fila($cerradoSinTipo)->tipo_plan_id, $fila($cerradoSinTipo)->tipo]);
        $this->assertSame('especializado', $fila($incoherente)->tipo);
        $this->assertTrue(DB::table('tipo_centro_tipo_plan')->where(['tipo_centro_id' => $tcCiam, 'tipo_plan_id' => $pia])->exists());
        $this->assertTrue(DB::table('tipo_centro_tipo_plan')->where(['tipo_centro_id' => $tcCss, 'tipo_plan_id' => $piso])->exists());

        // TF-CIR-07
        $this->assertSame($css, $fila($activoCss)->centro_id);
        $this->assertSame($ciam, $fila($activoCiam)->centro_id);
        $this->assertSame($css, $fila($cerradoSinTipo)->centro_id);
        $this->assertNull($fila($sinCentro)->centro_id);
        $this->assertSame(1, DB::table('planes_intervencion')->whereNull('centro_id')->count());
    }
}
