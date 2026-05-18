<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\SlotMaterializadorService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CuadranteMesTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(string $nombre = 'Centro de Prueba'): Centro
    {
        return Centro::create([
            'nombre'       => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => now()->toDateString(),
        ]);
    }

    private function crearCuadrante(Centro $centro, array $override = []): CuadranteMes
    {
        return CuadranteMes::create(array_merge([
            'centro_id'               => $centro->id,
            'anyo'                    => 2026,
            'mes'                     => 1,
            'estado'                  => EstadoCuadrante::Borrador->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
        ], $override));
    }

    // =========================================================================
    // PF-03.1 — Modo estándar crea un borrador sin publicar
    // =========================================================================

    #[Test]
    public function test_pf_03_1_generacion_modo_estandar_crea_borrador(): void
    {
        $this->markTestIncomplete(
            'PF-03.1: pendiente de implementar CuadranteGeneratorService::generarBorrador()'
        );
    }

    // =========================================================================
    // PF-03.2 — Publicar el cuadrante materializa los slots
    // =========================================================================

    #[Test]
    public function test_pf_03_2_publicar_cuadrante_materializa_slots(): void
    {
        $centro = $this->crearCentro();

        $horario = HorarioCentro::create([
            'centro_id'             => $centro->id,
            'nombre'                => 'Horario',
            'dias_laborables'       => [1, 2, 3, 4, 5],
            'hora_apertura'         => '08:00',
            'hora_cierre'           => '19:00',
            'hora_inicio_atencion'  => '09:00',
            'hora_fin_atencion'     => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos'    => 0,
            'vigente_desde'         => '2026-01-01',
            'vigente_hasta'         => null,
            'modo_agenda'           => 'estandar',
            'activo'                => true,
        ]);

        TipoSlot::create([
            'horario_centro_id'        => $horario->id,
            'nombre'                   => 'Cita',
            'duracion_minutos'         => 60,
            'requiere_espacio'         => false,
            'porcentaje_urgencias'     => 0,
            'origen_permitido'         => 'ambos',
            'genera_apunte_automatico' => false,
            'activo'                   => true,
        ]);

        $usuario   = User::factory()->create();
        $cuadrante = $this->crearCuadrante($centro, [
            'anyo'        => 2026,
            'mes'         => 6,
            'estado'      => EstadoCuadrante::Borrador->value,
        ]);

        // 2026-06-01 Monday, 9:00-14:00 = 300 min / 60 min = 5 slots
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $usuario->id,
            'centro_id'        => $centro->id,
            'fecha'            => '2026-06-01',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);

        $this->assertEquals(0, $cuadrante->slots()->count(), 'Antes de publicar no hay slots');

        // Publish + materialize
        $cuadrante->update([
            'estado'       => EstadoCuadrante::Publicado->value,
            'publicado_en' => now(),
        ]);
        $creados = (new SlotMaterializadorService())->materializar($cuadrante);

        $this->assertEquals(5, $creados, 'Deben crearse 5 slots (300 min / 60 min)');
        $this->assertEquals(5, $cuadrante->slots()->count());
    }

    // =========================================================================
    // PF-03.3 — No puede existir más de un cuadrante por centro y mes
    // =========================================================================

    #[Test]
    public function test_pf_03_3_unique_cuadrante_por_centro_y_mes(): void
    {
        $centro = $this->crearCentro();

        $this->crearCuadrante($centro, ['anyo' => 2026, 'mes' => 1]);

        $this->expectException(QueryException::class);

        // Segundo cuadrante para el mismo centro y mes: viola la restricción unique
        $this->crearCuadrante($centro, ['anyo' => 2026, 'mes' => 1]);
    }

    // =========================================================================
    // PF-03.4 — Modo básico genera y publica automáticamente
    // =========================================================================

    #[Test]
    public function test_pf_03_4_modo_basico_genera_y_publica_automaticamente(): void
    {
        $this->markTestIncomplete(
            'PF-03.4: pendiente de implementar CuadranteGeneratorService::generarYPublicarAutomaticamente()'
        );
    }

    // =========================================================================
    // PF-03.5 — Las excepciones conocidas se incorporan como líneas anuladas
    // =========================================================================

    #[Test]
    public function test_pf_03_5_excepciones_previas_generan_lineas_anuladas(): void
    {
        $this->markTestIncomplete(
            'PF-03.5: pendiente de implementar CuadranteGeneratorService con integración de excepciones'
        );
    }
}
