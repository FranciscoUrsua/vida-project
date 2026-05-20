<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\ModoAgenda;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\CuadranteGeneratorService;
use Modules\Agenda\Services\SlotMaterializadorService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegridadCasosLimiteTest extends TestCase
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

    private function crearCuadrante(Centro $centro, int $anyo = 2026, int $mes = 1, array $override = []): CuadranteMes
    {
        return CuadranteMes::create(array_merge([
            'centro_id'               => $centro->id,
            'anyo'                    => $anyo,
            'mes'                     => $mes,
            'estado'                  => EstadoCuadrante::Borrador->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
        ], $override));
    }

    // =========================================================================
    // PF-10.1 — Centro sin profesionales produce cuadrante vacío sin error
    // =========================================================================

    #[Test]
    public function test_pf_10_1_centro_sin_profesionales_cuadrante_vacio(): void
    {
        $centro = $this->crearCentro();

        HorarioCentro::create([
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

        // Sin perfiles de profesionales
        $cuadrante = $this->crearCuadrante($centro, 2026, 6);

        // No debe lanzar ningún error
        (new CuadranteGeneratorService())->generarBorrador($cuadrante);

        $this->assertEquals(0, $cuadrante->lineas()->count(), 'Sin profesionales no debe generarse ninguna línea');
        $this->assertEquals(EstadoCuadrante::Borrador, $cuadrante->estado, 'El cuadrante debe permanecer en borrador');
    }

    // =========================================================================
    // PF-10.2 — Tipo de slot con duración mayor que la franja no genera slots
    // =========================================================================

    #[Test]
    public function test_pf_10_2_slot_mayor_que_franja_no_genera_slots(): void
    {
        // Franja útil de 30 min (9:30-10:00), TipoSlot de 45 min → no cabe ninguno
        $centro = $this->crearCentro();

        $horario = HorarioCentro::create([
            'centro_id'             => $centro->id,
            'nombre'                => 'Horario corto',
            'dias_laborables'       => [1, 2, 3, 4, 5],
            'hora_apertura'         => '09:00',
            'hora_cierre'           => '11:00',
            'hora_inicio_atencion'  => '09:30',
            'hora_fin_atencion'     => '10:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos'    => 0,
            'vigente_desde'         => '2026-01-01',
            'vigente_hasta'         => null,
            'modo_agenda'           => 'estandar',
            'activo'                => true,
        ]);

        TipoSlot::create([
            'horario_centro_id'        => $horario->id,
            'nombre'                   => 'Sesión larga',
            'duracion_minutos'         => 45,
            'requiere_espacio'         => false,
            'porcentaje_urgencias'     => 0,
            'origen_permitido'         => 'ambos',
            'genera_apunte_automatico' => false,
            'activo'                   => true,
        ]);

        $usuario   = User::factory()->create();
        $cuadrante = $this->crearCuadrante($centro, 2026, 6);

        // 2026-06-01 is a Monday
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $usuario->id,
            'centro_id'        => $centro->id,
            'fecha'            => '2026-06-01',
            'franjas'          => [['inicio' => '09:00', 'fin' => '11:00']],
            'anulada'          => false,
        ]);

        $creados = (new SlotMaterializadorService())->materializar($cuadrante);

        $this->assertEquals(0, $creados, 'Un TipoSlot de 45 min no cabe en una franja útil de 30 min');
    }

    // =========================================================================
    // PF-10.3 — Cambiar el modo de agenda no corrompe datos históricos
    // =========================================================================

    #[Test]
    public function test_pf_10_3_cambiar_modo_agenda_no_corrompe_datos(): void
    {
        $centro = $this->crearCentro();

        $horario = HorarioCentro::create([
            'centro_id'            => $centro->id,
            'nombre'               => 'Horario básico',
            'dias_laborables'      => [1, 2, 3, 4, 5],
            'hora_apertura'        => '08:00',
            'hora_cierre'          => '19:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion'    => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos'   => 0,
            'vigente_desde'        => now()->toDateString(),
            'vigente_hasta'        => null,
            'modo_agenda'          => ModoAgenda::Basico->value,
            'activo'               => true,
        ]);

        $cuadrante = $this->crearCuadrante($centro, 2026, 1, [
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_automaticamente' => true,
            'publicado_en'            => now(),
        ]);

        // Crear algunos slots directamente
        $slot = Slot::factory()->create([
            'centro_id' => $centro->id,
        ]);

        $totalSlots = Slot::count();

        // Cambiar modo de agenda a estándar
        $horario->update(['modo_agenda' => ModoAgenda::Estandar->value]);

        $this->assertEquals(
            ModoAgenda::Estandar,
            $horario->fresh()->modo_agenda,
            'El modo de agenda debe haber cambiado a estándar'
        );

        // Los datos existentes no se han modificado
        $this->assertEquals($totalSlots, Slot::count(), 'Los slots existentes no deben eliminarse');
        $this->assertTrue(
            CuadranteMes::where('id', $cuadrante->id)
                ->where('generado_automaticamente', true)
                ->exists(),
            'El cuadrante automático previo debe seguir existiendo'
        );
    }

    // =========================================================================
    // PF-10.4 — Soft delete de cita cancelada; slot queda intacto y cita recuperable
    // =========================================================================

    #[Test]
    public function test_pf_10_4_soft_delete_cita_slot_intacto(): void
    {
        $ciudadano   = \App\Models\Ciudadano::factory()->create();
        $slot        = Slot::factory()->reservado()->create();

        $cita = Cita::create([
            'slot_id'        => $slot->id,
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id'   => $slot->tipo_slot_id,
            'centro_id'      => $slot->centro_id,
            'fecha'          => $slot->fecha,
            'hora_inicio'    => $slot->hora_inicio,
            'hora_fin'       => $slot->hora_fin,
            'estado'         => EstadoCita::Cancelada->value,
            'origen'         => OrigenCita::Interno->value,
        ]);

        // Hacemos soft delete de la cita
        $cita->delete();

        // El slot sigue existiendo y tiene su estado
        $slotRecargado = Slot::find($slot->id);
        $this->assertNotNull($slotRecargado, 'El slot debe seguir existiendo tras el soft delete de la cita');
        $this->assertEquals(EstadoSlot::Reservado, $slotRecargado->estado);

        // La cita es recuperable con withTrashed
        $citaRecuperada = Cita::withTrashed()->find($cita->id);
        $this->assertNotNull($citaRecuperada, 'La cita debe ser recuperable con withTrashed()');
        $this->assertNotNull($citaRecuperada->deleted_at, 'La cita debe tener deleted_at tras el soft delete');
    }

    // =========================================================================
    // PF-10.5 — No pueden existir dos cuadrantes para el mismo centro y mes
    // =========================================================================

    #[Test]
    public function test_pf_10_5_unique_constraint_cuadrante_centro_mes(): void
    {
        $centro = $this->crearCentro();

        $this->crearCuadrante($centro, 2026, 1);

        $this->expectException(QueryException::class);

        // Inserción directa que viola la restricción unique (centro_id, anyo, mes)
        \Illuminate\Support\Facades\DB::table('cuadrantes_mes')->insert([
            'centro_id'               => $centro->id,
            'anyo'                    => 2026,
            'mes'                     => 1,
            'estado'                  => EstadoCuadrante::Borrador->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $this->assertEquals(
            1,
            CuadranteMes::where('centro_id', $centro->id)->where('anyo', 2026)->where('mes', 1)->count(),
            'Solo debe existir un cuadrante para ese centro y mes'
        );
    }
}
