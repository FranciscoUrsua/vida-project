<?php

namespace Modules\Agenda\Tests\Feature;

use App\Filament\Resources\HorarioCentroResource\Pages\EditHorarioCentro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\SlotMaterializadorService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorarioCentroTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(string $nombre = 'Centro de Prueba'): Centro
    {
        return Centro::create([
            'nombre' => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta' => now()->toDateString(),
        ]);
    }

    // =========================================================================
    // PF-01.1 — El buffer de inicio desplaza el primer slot disponible
    // =========================================================================

    #[Test]
    public function test_pf_01_1_buffer_inicio_desplaza_primer_slot(): void
    {
        $centro = $this->crearCentro();

        $horario = HorarioCentro::create([
            'centro_id' => $centro->id,
            'nombre' => 'Horario estándar',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 30,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'modo_agenda' => 'estandar',
            'activo' => true,
        ]);

        tap(TipoSlot::create([
            'nombre' => 'Entrevista',
            'duracion_minutos' => 45,
            'requiere_espacio' => false,
            'porcentaje_urgencias' => 0,
            'origen_permitido' => 'ambos',
            'activo' => true,
        ]), fn (TipoSlot $t) => $horario->tiposSlot()->attach($t));

        $usuario = User::factory()->create();
        $cuadrante = CuadranteMes::create([
            'centro_id' => $centro->id,
            'anyo' => 2026,
            'mes' => 6,
            'estado' => EstadoCuadrante::Publicado->value,
            'generado_con_ia' => false,
            'generado_automaticamente' => false,
            'publicado_en' => now(),
        ]);

        // 2026-06-01 is a Monday
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'fecha' => '2026-06-01',
            'franjas' => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada' => false,
        ]);

        (new SlotMaterializadorService)->materializar($cuadrante);

        $slots = $cuadrante->slots()->orderBy('hora_inicio')->get();

        $this->assertGreaterThan(0, $slots->count(), 'Deben generarse slots');
        $this->assertStringStartsWith('09:30', $slots->first()->hora_inicio, 'El primer slot debe empezar a las 9:30 (buffer de 30 min)');

        $slots->each(function ($slot) {
            $this->assertGreaterThanOrEqual(
                '09:30', substr($slot->hora_inicio, 0, 5),
                "Ningún slot debe empezar antes de las 9:30: encontrado {$slot->hora_inicio}"
            );
        });
    }

    // =========================================================================
    // PF-01.2 — Solo un horario vigente para un centro en una fecha
    // =========================================================================

    #[Test]
    public function test_pf_01_2_solo_un_horario_vigente_por_fecha(): void
    {
        $centro = $this->crearCentro();

        // Horario A: vigente enero-agosto
        HorarioCentro::create([
            'centro_id' => $centro->id,
            'nombre' => 'Horario A',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-08-31',
            'modo_agenda' => 'estandar',
            'activo' => true,
        ]);

        // Horario B: vigente desde septiembre, sin fecha fin
        $horarioB = HorarioCentro::create([
            'centro_id' => $centro->id,
            'nombre' => 'Horario B',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2026-09-01',
            'vigente_hasta' => null,
            'modo_agenda' => 'estandar',
            'activo' => true,
        ]);

        // scopeVigentes usa now(); para esta prueba lo forzamos con la fecha 01/09/2026
        // viajando en el tiempo
        $this->travelTo('2026-09-01');

        $vigentes = HorarioCentro::delCentro($centro->id)->vigentes()->get();

        $this->assertCount(1, $vigentes, 'Solo un horario debe estar vigente el 01/09/2026');
        $this->assertEquals($horarioB->id, $vigentes->first()->id);
    }

    // =========================================================================
    // PF-01.3 — Un horario sin fecha de fin está vigente indefinidamente
    // =========================================================================

    #[Test]
    public function test_pf_01_3_horario_sin_fecha_fin_vigente_indefinidamente(): void
    {
        $centro = $this->crearCentro();

        $horario = HorarioCentro::create([
            'centro_id' => $centro->id,
            'nombre' => 'Horario indefinido',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2020-01-01',
            'vigente_hasta' => null,
            'modo_agenda' => 'estandar',
            'activo' => true,
        ]);

        // Avanzamos 5 años en el futuro
        $this->travelTo(now()->addYears(5));

        $vigentes = HorarioCentro::delCentro($centro->id)->vigentes()->get();

        $this->assertCount(1, $vigentes, 'El horario debe seguir vigente 5 años en el futuro');
        $this->assertEquals($horario->id, $vigentes->first()->id);
    }

    // =========================================================================
    // PF-01.4 — No se generan slots para días no laborables
    // =========================================================================

    #[Test]
    public function test_pf_01_4_no_slots_para_dias_no_laborables(): void
    {
        $centro = $this->crearCentro();

        $horario = HorarioCentro::create([
            'centro_id' => $centro->id,
            'nombre' => 'Horario L-V',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'modo_agenda' => 'estandar',
            'activo' => true,
        ]);

        tap(TipoSlot::create([
            'nombre' => 'Entrevista',
            'duracion_minutos' => 45,
            'requiere_espacio' => false,
            'porcentaje_urgencias' => 0,
            'origen_permitido' => 'ambos',
            'activo' => true,
        ]), fn (TipoSlot $t) => $horario->tiposSlot()->attach($t));

        $usuario = User::factory()->create();
        $cuadrante = CuadranteMes::create([
            'centro_id' => $centro->id,
            'anyo' => 2026,
            'mes' => 6,
            'estado' => EstadoCuadrante::Publicado->value,
            'generado_con_ia' => false,
            'generado_automaticamente' => false,
            'publicado_en' => now(),
        ]);

        // 2026-06-06 is a Saturday (isoWeekday = 6)
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'fecha' => '2026-06-06',
            'franjas' => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada' => false,
        ]);

        $creados = (new SlotMaterializadorService)->materializar($cuadrante);

        $this->assertEquals(0, $creados, 'No deben generarse slots para un sábado');
        $this->assertEquals(0, $cuadrante->slots()->count());
    }

    // =========================================================================
    // Tipos de slot del centro (catálogo global, pivote horario_centro_tipo_slot)
    // =========================================================================

    /**
     * Horario estándar de 9 a 14 con un tipo de slot elegido y otro del catálogo sin elegir.
     *
     * @param Centro $centro
     * @return array{0: HorarioCentro, 1: TipoSlot, 2: TipoSlot}
     */
    private function horarioConUnTipoElegido(Centro $centro): array
    {
        $horario = HorarioCentro::factory()->create([
            'centro_id' => $centro->id,
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2026-01-01',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'modo_agenda' => 'estandar',
        ]);

        $elegido = TipoSlot::factory()->create(['nombre' => 'Entrevista', 'duracion_minutos' => 60]);
        $otro = TipoSlot::factory()->create(['nombre' => 'Grupal', 'duracion_minutos' => 60]);
        $horario->tiposSlot()->attach($elegido);

        return [$horario, $elegido, $otro];
    }

    #[Test]
    public function solo_se_materializan_los_tipos_de_slot_del_horario(): void
    {
        $centro = $this->crearCentro();
        [, $elegido, $otro] = $this->horarioConUnTipoElegido($centro);

        $cuadrante = CuadranteMes::create([
            'centro_id' => $centro->id,
            'anyo' => 2026,
            'mes' => 6,
            'estado' => EstadoCuadrante::Publicado->value,
            'generado_con_ia' => false,
            'generado_automaticamente' => false,
            'publicado_en' => now(),
        ]);
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id' => User::factory()->create()->id,
            'centro_id' => $centro->id,
            'fecha' => '2026-06-01',
            'franjas' => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada' => false,
        ]);

        (new SlotMaterializadorService)->materializar($cuadrante);

        // 9:00-14:00 con slots de 60 minutos: 5, todos del tipo elegido
        $this->assertSame(5, $cuadrante->slots()->where('tipo_slot_id', $elegido->id)->count());
        $this->assertSame(0, $cuadrante->slots()->where('tipo_slot_id', $otro->id)->count());
    }

    #[Test]
    public function filament_guarda_los_tipos_de_slot_del_horario(): void
    {
        $this->seed(\Database\Seeders\PermisosSeeder::class);
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('adm_sistema');

        [$horario, $elegido, $otro] = $this->horarioConUnTipoElegido($this->crearCentro());

        Livewire::actingAs($admin)
            ->test(EditHorarioCentro::class, ['record' => $horario->getRouteKey()])
            ->assertFormSet(['tiposSlot' => [$elegido->id]])
            ->fillForm(['tiposSlot' => [$elegido->id, $otro->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$elegido->id, $otro->id], $horario->tiposSlot()->pluck('tipos_slot.id')->all());
    }
}
