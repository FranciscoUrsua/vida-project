<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\CuadranteGeneratorService;
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

    /** Crea un HorarioCentro estándar L-V, 9:00-14:00, vigente desde 2026-01-01. */
    private function crearHorario(Centro $centro, string $modo = 'estandar'): HorarioCentro
    {
        return HorarioCentro::create([
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
            'modo_agenda'           => $modo,
            'activo'                => true,
        ]);
    }

    /** Crea un PerfilHorarioProfesional matutino L-V para el centro dado. */
    private function crearPerfil(User $usuario, Centro $centro): PerfilHorarioProfesional
    {
        return PerfilHorarioProfesional::create([
            'usuario_id'            => $usuario->id,
            'centro_id'             => $centro->id,
            'jornada_semanal_horas' => 35,
            'horario_habitual'      => [
                '1' => [['inicio' => '09:00', 'fin' => '14:00']],
                '2' => [['inicio' => '09:00', 'fin' => '14:00']],
                '3' => [['inicio' => '09:00', 'fin' => '14:00']],
                '4' => [['inicio' => '09:00', 'fin' => '14:00']],
                '5' => [['inicio' => '09:00', 'fin' => '14:00']],
            ],
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'activo'        => true,
        ]);
    }

    // =========================================================================
    // PF-03.1 — Modo estándar crea un borrador sin publicar
    // =========================================================================

    #[Test]
    public function test_pf_03_1_generacion_modo_estandar_crea_borrador(): void
    {
        $centro = $this->crearCentro();
        $this->crearHorario($centro, 'estandar');

        // Tres profesionales con perfiles activos
        $usuarios = User::factory()->count(3)->create();
        foreach ($usuarios as $usuario) {
            $this->crearPerfil($usuario, $centro);
        }

        $cuadrante = $this->crearCuadrante($centro, ['anyo' => 2026, 'mes' => 6]);

        (new CuadranteGeneratorService())->generarBorrador($cuadrante);

        $cuadrante->refresh();

        $this->assertEquals(EstadoCuadrante::Borrador, $cuadrante->estado, 'El cuadrante debe seguir en borrador');
        $this->assertFalse($cuadrante->generado_automaticamente, 'No debe marcarse como generado automáticamente');

        // Junio 2026 tiene 22 días laborables L-V
        $this->assertEquals(22 * 3, $cuadrante->lineas()->count(), '3 profesionales × 22 días laborables = 66 líneas');

        // Sin publicación no hay slots materializados
        $this->assertEquals(0, $cuadrante->slots()->count(), 'En borrador no debe haber slots');
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
    // PF-03.3 — No se puede publicar un cuadrante si ya existe uno publicado para ese centro y mes
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
        $centro = $this->crearCentro();
        $this->crearHorario($centro, 'basico');

        $usuario = User::factory()->create();
        $this->crearPerfil($usuario, $centro);

        $cuadrante = (new CuadranteGeneratorService())
            ->generarYPublicarAutomaticamente($centro, 2026, 6);

        $this->assertEquals(EstadoCuadrante::Publicado, $cuadrante->estado, 'El cuadrante debe quedar publicado');
        $this->assertTrue($cuadrante->generado_automaticamente, 'Debe marcarse como generado automáticamente');
        $this->assertNotNull($cuadrante->publicado_en, 'Debe registrar la fecha de publicación');

        // Junio 2026: 22 días laborables × 1 profesional = 22 líneas
        $this->assertEquals(22, $cuadrante->lineas()->count(), '1 profesional × 22 días laborables = 22 líneas');
    }

    // =========================================================================
    // PF-03.5 — Las excepciones conocidas se incorporan como líneas anuladas
    // =========================================================================

    #[Test]
    public function test_pf_03_5_excepciones_previas_generan_lineas_anuladas(): void
    {
        $centro = $this->crearCentro();
        $this->crearHorario($centro, 'estandar');

        $supervisor = User::factory()->create();
        $profesional = User::factory()->create();
        $this->crearPerfil($profesional, $centro);

        // Vacaciones del 10 al 20 de junio
        $excepcion = ExcepcionProfesional::create([
            'usuario_id'            => $profesional->id,
            'centro_id'             => $centro->id,
            'tipo'                  => 'vacaciones',
            'fecha_inicio'          => '2026-06-10',
            'fecha_fin'             => '2026-06-20',
            'afecta_disponibilidad' => true,
            'franja_afectada'       => null,
            'origen'                => 'manual',
            'creado_por_id'         => $supervisor->id,
        ]);

        $cuadrante = $this->crearCuadrante($centro, ['anyo' => 2026, 'mes' => 6]);

        (new CuadranteGeneratorService())->generarBorrador($cuadrante);

        // Días L-V entre el 10 y el 20 de junio:
        // 10(X), 11(J), 12(V), 15(L), 16(M), 17(X), 18(J), 19(V) → 8 días laborables
        $lineasAnuladas = $cuadrante->lineas()->where('anulada', true)->get();
        $this->assertCount(8, $lineasAnuladas, 'Deben anularse los 8 días laborables dentro del período de excepción');

        foreach ($lineasAnuladas as $linea) {
            $this->assertEquals($excepcion->id, $linea->excepcion_id, 'Cada línea anulada debe referenciar la excepción');
        }

        // Junio 2026: 22 días laborables - 8 anulados = 14 líneas activas
        $this->assertCount(14, $cuadrante->lineas()->where('anulada', false)->get());
    }
}
