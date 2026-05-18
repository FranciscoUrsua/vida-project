<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\SlotMaterializadorService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PerfilHorarioTest extends TestCase
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

    private function crearPerfil(User $usuario, Centro $centro, array $override = []): PerfilHorarioProfesional
    {
        return PerfilHorarioProfesional::create(array_merge([
            'usuario_id'            => $usuario->id,
            'centro_id'             => $centro->id,
            'jornada_semanal_horas' => 35,
            'horario_habitual'      => [
                1 => [['inicio' => '09:00', 'fin' => '14:00']],
                2 => [['inicio' => '09:00', 'fin' => '14:00']],
                3 => [['inicio' => '09:00', 'fin' => '14:00']],
                4 => [['inicio' => '09:00', 'fin' => '14:00']],
                5 => [['inicio' => '09:00', 'fin' => '14:00']],
            ],
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => null,
            'activo'        => true,
        ], $override));
    }

    // =========================================================================
    // PF-02.1 — El horario reducido limita los slots al rango del perfil
    // =========================================================================

    #[Test]
    public function test_pf_02_1_horario_reducido_limita_slots(): void
    {
        // Centro: atención 8:30-15:00. Profesional con jornada reducida 9:30-14:00.
        // La LineaCuadrante ya refleja el perfil del profesional (franjas = 9:30-14:00).
        // Resultado: slots desde 9:30, nunca antes de 9:30.

        $centro = $this->crearCentro();

        $horario = HorarioCentro::create([
            'centro_id'             => $centro->id,
            'nombre'                => 'Horario amplio',
            'dias_laborables'       => [1, 2, 3, 4, 5],
            'hora_apertura'         => '08:00',
            'hora_cierre'           => '18:00',
            'hora_inicio_atencion'  => '08:30',
            'hora_fin_atencion'     => '15:00',
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
            'duracion_minutos'         => 45,
            'requiere_espacio'         => false,
            'porcentaje_urgencias'     => 0,
            'origen_permitido'         => 'ambos',
            'genera_apunte_automatico' => false,
            'activo'                   => true,
        ]);

        $usuario   = User::factory()->create();
        $cuadrante = CuadranteMes::create([
            'centro_id'               => $centro->id,
            'anyo'                    => 2026,
            'mes'                     => 6,
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'publicado_en'            => now(),
        ]);

        // Línea del profesional con jornada reducida: solo 9:30-14:00
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $usuario->id,
            'centro_id'        => $centro->id,
            'fecha'            => '2026-06-01',
            'franjas'          => [['inicio' => '09:30', 'fin' => '14:00']],
            'anulada'          => false,
        ]);

        (new SlotMaterializadorService())->materializar($cuadrante);

        $slots = $cuadrante->slots()->orderBy('hora_inicio')->get();

        $this->assertGreaterThan(0, $slots->count(), 'Deben generarse slots');
        $this->assertStringStartsWith('09:30', $slots->first()->hora_inicio, 'El primer slot debe empezar a las 9:30');
        $slots->each(function ($slot) {
            $this->assertGreaterThanOrEqual('09:30', substr($slot->hora_inicio, 0, 5));
            $this->assertLessThanOrEqual('14:00', substr($slot->hora_fin, 0, 5));
        });
    }

    // =========================================================================
    // PF-02.2 — Profesional itinerante tiene slots en cada centro según su perfil
    // =========================================================================

    #[Test]
    public function test_pf_02_2_profesional_itinerante_slots_por_centro(): void
    {
        // Profesional con perfil en Centro A (L-X) y Centro B (J-V).
        // Tras materializar ambos cuadrantes, los slots de cada centro solo
        // corresponden a los días del perfil de ese centro.

        $centroA = $this->crearCentro('Centro A');
        $centroB = $this->crearCentro('Centro B');

        foreach ([$centroA, $centroB] as $centro) {
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
        }

        $usuario = User::factory()->create();

        // Cuadrante Centro A — semana con 2026-06-01 (lunes), 06-02 (martes), 06-03 (miércoles)
        $cuadranteA = CuadranteMes::create([
            'centro_id'               => $centroA->id,
            'anyo'                    => 2026,
            'mes'                     => 6,
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'publicado_en'            => now(),
        ]);

        foreach (['2026-06-01', '2026-06-02', '2026-06-03'] as $fecha) {
            LineaCuadrante::create([
                'cuadrante_mes_id' => $cuadranteA->id,
                'usuario_id'       => $usuario->id,
                'centro_id'        => $centroA->id,
                'fecha'            => $fecha,
                'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
                'anulada'          => false,
            ]);
        }

        // Cuadrante Centro B — jueves y viernes (2026-06-04, 2026-06-05)
        $cuadranteB = CuadranteMes::create([
            'centro_id'               => $centroB->id,
            'anyo'                    => 2026,
            'mes'                     => 6,
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'publicado_en'            => now(),
        ]);

        foreach (['2026-06-04', '2026-06-05'] as $fecha) {
            LineaCuadrante::create([
                'cuadrante_mes_id' => $cuadranteB->id,
                'usuario_id'       => $usuario->id,
                'centro_id'        => $centroB->id,
                'fecha'            => $fecha,
                'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
                'anulada'          => false,
            ]);
        }

        $servicio = new SlotMaterializadorService();
        $servicio->materializar($cuadranteA);
        $servicio->materializar($cuadranteB);

        // 5 slots/día × 3 días = 15 slots en Centro A
        $this->assertEquals(15, $cuadranteA->slots()->count(), 'Centro A debe tener 15 slots (3 días × 5 slots)');
        // 5 slots/día × 2 días = 10 slots en Centro B
        $this->assertEquals(10, $cuadranteB->slots()->count(), 'Centro B debe tener 10 slots (2 días × 5 slots)');

        // Sin solapamiento: ninguna fecha de Centro A aparece en Centro B
        $fechasA = $cuadranteA->slots()->select('slots.fecha')->pluck('fecha')
            ->map(fn ($f) => (string) $f)->unique()->sort()->values()->toArray();
        $fechasB = $cuadranteB->slots()->select('slots.fecha')->pluck('fecha')
            ->map(fn ($f) => (string) $f)->unique()->sort()->values()->toArray();
        $this->assertEmpty(array_intersect($fechasA, $fechasB), 'No debe haber solapamiento de fechas entre centros');
    }

    // =========================================================================
    // PF-02.3 — No puede existir un segundo perfil activo solapado para el mismo profesional y centro
    // =========================================================================

    #[Test]
    public function test_pf_02_3_no_duplicado_perfil_activo(): void
    {
        $usuario = User::factory()->create();
        $centro  = $this->crearCentro();

        // Primer perfil activo: ok
        $this->crearPerfil($usuario, $centro);

        // Segundo perfil activo para el mismo profesional y centro: debe fallar en capa de aplicación.
        // La validación no existe aún en el modelo; se marca como pendiente.
        $this->markTestIncomplete(
            'PF-02.3: pendiente de implementar validación de solapamiento en PerfilHorarioProfesional'
        );
    }
}
