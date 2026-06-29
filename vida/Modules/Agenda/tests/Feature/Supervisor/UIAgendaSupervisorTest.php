<?php

namespace Modules\Agenda\Tests\Feature\Supervisor;

use App\Filament\Resources\TipoSlotResource\Pages\CreateTipoSlot;
use App\Filament\Resources\TipoSlotResource\Pages\ListTiposSlot;
use App\Models\UnidadOrganizativa;
use App\Models\UsuarioUo;
use Carbon\Carbon;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Enums\OrigenExcepcion;
use Modules\Agenda\Livewire\CuadranteMesComponent;
use Modules\Agenda\Livewire\ExcepcionesComponent;
use Modules\Agenda\Livewire\PerfilHorarioComponent;
use Modules\Agenda\Livewire\SemanaTypoComponent;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales T-AGS-01 a T-AGS-33 — UI de supervisión del módulo Agenda.
 *
 * Cubre TipoSlotResource (Filament), SemanaTypoComponent, PerfilHorarioComponent,
 * ExcepcionesComponent, CuadranteMesComponent y control de acceso.
 */
class UIAgendaSupervisorTest extends TestCase
{
    use RefreshDatabase;
    use AgendaSupervisorTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->construirFixturesSupervisor();
    }

    // =========================================================================
    // Grupo 1 — TipoSlot Resource (Filament)
    // =========================================================================

    /**
     * T-AGS-01 — El supervisor puede listar los tipos de slot de su centro.
     */
    #[Test]
    public function supervisor_puede_listar_tipos_slot(): void
    {
        $tipo1 = $this->crearTipoSlotConNombre('Reunión de equipo');
        $tipo2 = $this->crearTipoSlotConNombre('Mesa de coordinación');

        Livewire::actingAs($this->supervisor)
            ->test(ListTiposSlot::class)
            ->assertOk()
            ->assertCanSeeTableRecords(TipoSlot::whereIn('id', [$tipo1->id, $tipo2->id])->get());
    }

    /**
     * T-AGS-02 — El supervisor puede crear un tipo de slot válido.
     */
    #[Test]
    public function supervisor_puede_crear_tipo_slot_valido(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(CreateTipoSlot::class)
            ->fillForm([
                'nombre'                   => 'Reunión de equipo',
                'duracion_minutos'         => 60,
                'bloquea_todos_convocados' => true,
                'activo'                   => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tipos_slot', [
            'nombre'                   => 'Reunión de equipo',
            'duracion_minutos'         => 60,
            'bloquea_todos_convocados' => true,
        ]);
    }

    /**
     * T-AGS-03 — No se puede crear un tipo de slot sin nombre.
     */
    #[Test]
    public function no_se_puede_crear_tipo_slot_sin_nombre(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(CreateTipoSlot::class)
            ->fillForm([
                'horario_centro_id' => $this->horario->id,
                'nombre'            => '',
                'duracion_minutos'  => 30,
            ])
            ->call('create')
            ->assertHasFormErrors(['nombre' => 'required']);
    }

    /**
     * T-AGS-04 — No se puede crear un tipo de slot sin duración.
     */
    #[Test]
    public function no_se_puede_crear_tipo_slot_sin_duracion(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(CreateTipoSlot::class)
            ->fillForm([
                'horario_centro_id' => $this->horario->id,
                'nombre'            => 'Reunión de equipo',
                'duracion_minutos'  => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['duracion_minutos' => 'required']);
    }

    /**
     * T-AGS-05 — Un profesional sin rol supervisor no puede acceder al resource.
     */
    #[Test]
    public function profesional_sin_supervision_no_puede_acceder_al_resource(): void
    {
        Livewire::actingAs($this->profesional1)
            ->test(ListTiposSlot::class)
            ->assertForbidden();
    }

    // =========================================================================
    // Grupo 2 — Semana tipo (SemanaTypoComponent)
    // =========================================================================

    /**
     * T-AGS-06 — La pantalla de semana tipo carga correctamente para el supervisor.
     */
    #[Test]
    public function semana_tipo_carga_para_el_supervisor(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(SemanaTypoComponent::class, ['centro' => $this->centro])
            ->assertOk()
            ->assertSee('Semana tipo');
    }

    /**
     * T-AGS-07 — Guardar la semana tipo persiste el JSON en HorarioCentro.
     */
    #[Test]
    public function guardar_semana_tipo_persiste_json_en_horario(): void
    {
        $semana = [
            'base' => [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '13:00']],
            '5'    => [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '11:00']],
        ];

        Livewire::actingAs($this->supervisor)
            ->test(SemanaTypoComponent::class, ['centro' => $this->centro])
            ->set('semana', $semana)
            ->call('guardar')
            ->assertDispatched('toast');

        $semanaGuardada = $this->horario->fresh()->semana_tipo;
        $this->assertArrayHasKey('base', $semanaGuardada);
        $this->assertArrayHasKey('5', $semanaGuardada);
    }

    /**
     * T-AGS-08 — Una franja con hora fin anterior a hora inicio es rechazada.
     */
    #[Test]
    public function franja_con_hora_fin_anterior_a_inicio_es_rechazada(): void
    {
        $semana = ['base' => [['tipo' => 'atencion', 'inicio' => '13:00', 'fin' => '09:00']]];

        Livewire::actingAs($this->supervisor)
            ->test(SemanaTypoComponent::class, ['centro' => $this->centro])
            ->set('semana', $semana)
            ->call('guardar')
            ->assertHasErrors();
    }

    /**
     * T-AGS-09 — Los slots estimados se calculan en tiempo real.
     */
    #[Test]
    public function slots_estimados_se_calculan_en_tiempo_real(): void
    {
        // 240 min de atención / 30 min = 8 slots × 3 profesionales = 24
        $semana = ['base' => [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '13:00']]];

        $component = Livewire::actingAs($this->supervisor)
            ->test(SemanaTypoComponent::class, ['centro' => $this->centro])
            ->set('semana', $semana);

        $slots = $component->get('slotsEstimados');
        $this->assertEquals(24, $slots[1]);
        $this->assertEquals(24, $slots[3]);
    }

    /**
     * T-AGS-10 — Copiar un día replica sus franjas en los días destino.
     */
    #[Test]
    public function copiar_dia_replica_franjas_en_dias_destino(): void
    {
        $franjas = [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '13:00']];

        $component = Livewire::actingAs($this->supervisor)
            ->test(SemanaTypoComponent::class, ['centro' => $this->centro])
            ->set('semana', ['1' => $franjas])
            ->call('copiarDia', 1, [2, 3]);

        $semana = $component->get('semana');
        $this->assertEquals($franjas, $semana['2']);
        $this->assertEquals($franjas, $semana['3']);
    }

    /**
     * T-AGS-11 — Se muestra aviso si hay un cuadrante en borrador al guardar.
     */
    #[Test]
    public function se_muestra_aviso_si_hay_borrador_al_guardar_semana_tipo(): void
    {
        $nextMonth = now()->addMonth();
        CuadranteMes::create([
            'centro_id'                => $this->centro->id,
            'anyo'                     => $nextMonth->year,
            'mes'                      => $nextMonth->month,
            'estado'                   => EstadoCuadrante::Borrador->value,
            'generado_con_ia'          => false,
            'generado_automaticamente' => false,
        ]);

        $semana = ['base' => [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '13:00']]];

        Livewire::actingAs($this->supervisor)
            ->test(SemanaTypoComponent::class, ['centro' => $this->centro])
            ->set('semana', $semana)
            ->call('guardar')
            ->assertSet('avisoBorrador', true);
    }

    // =========================================================================
    // Grupo 3 — Perfil horario del profesional (PerfilHorarioComponent)
    // =========================================================================

    /**
     * T-AGS-12 — La pestaña carga con los datos del perfil activo actual.
     */
    #[Test]
    public function perfil_horario_carga_con_datos_del_perfil_activo(): void
    {
        PerfilHorarioProfesional::where('usuario_id', $this->profesional1->id)
            ->where('centro_id', $this->centro->id)
            ->update(['jornada_semanal_horas' => 17.5]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(PerfilHorarioComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ]);

        $this->assertEquals(17.5, (float) $component->get('jornadaSemanal'));
    }

    /**
     * T-AGS-13 — Guardar con la misma fecha de vigencia actualiza el perfil existente.
     */
    #[Test]
    public function guardar_con_misma_fecha_actualiza_perfil_existente(): void
    {
        $component = Livewire::actingAs($this->supervisor)
            ->test(PerfilHorarioComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ]);

        $component
            ->set('vigenteDesde', '2026-01-01')
            ->set('jornadaSemanal', 35)
            ->call('guardar');

        $this->assertEquals(
            1,
            PerfilHorarioProfesional::where('usuario_id', $this->profesional1->id)
                ->where('centro_id', $this->centro->id)
                ->where('activo', true)
                ->count()
        );
    }

    /**
     * T-AGS-14 — Guardar con nueva fecha de vigencia crea un nuevo perfil y cierra el anterior.
     */
    #[Test]
    public function guardar_con_nueva_fecha_crea_nuevo_perfil_y_cierra_el_anterior(): void
    {
        $component = Livewire::actingAs($this->supervisor)
            ->test(PerfilHorarioComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ]);

        $component
            ->set('vigenteDesde', '2026-08-01')
            ->set('jornadaSemanal', 35)
            ->call('guardar');

        $anterior = PerfilHorarioProfesional::where('usuario_id', $this->profesional1->id)
            ->where('centro_id', $this->centro->id)
            ->where('vigente_desde', '2026-01-01')
            ->first();
        $this->assertEquals('2026-07-31', $anterior->vigente_hasta->toDateString());

        $nuevo = PerfilHorarioProfesional::where('usuario_id', $this->profesional1->id)
            ->where('centro_id', $this->centro->id)
            ->where('vigente_desde', '2026-08-01')
            ->where('activo', true)
            ->first();
        $this->assertNotNull($nuevo);
    }

    /**
     * T-AGS-15 — Desactivar un día lo elimina de los días activos.
     */
    #[Test]
    public function toggle_dia_desactiva_el_dia(): void
    {
        $component = Livewire::actingAs($this->supervisor)
            ->test(PerfilHorarioComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ])
            ->call('toggleDia', 5);

        $this->assertNotContains(5, $component->get('diasActivos'));
    }

    /**
     * T-AGS-16 — Añadir tarde agrega una franja vespertina al día correspondiente.
     */
    #[Test]
    public function add_tarde_agrega_franja_vespertina(): void
    {
        $component = Livewire::actingAs($this->supervisor)
            ->test(PerfilHorarioComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ])
            ->call('addTarde', 2);

        $franjas = $component->get('franjasPorDia');
        $this->assertEquals('15:00', $franjas[2]['tIni']);
        $this->assertEquals('19:00', $franjas[2]['tFin']);
    }

    // =========================================================================
    // Grupo 4 — Excepciones del profesional (ExcepcionesComponent)
    // =========================================================================

    /**
     * T-AGS-17 — Las excepciones futuras aparecen en la sección "Próximas".
     */
    #[Test]
    public function excepciones_futuras_aparecen_en_proximas(): void
    {
        $excepcion = ExcepcionProfesional::create([
            'usuario_id'            => $this->profesional1->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => 'vacaciones',
            'fecha_inicio'          => now()->addDays(10)->toDateString(),
            'fecha_fin'             => now()->addDays(17)->toDateString(),
            'afecta_disponibilidad' => true,
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => $this->supervisor->id,
        ]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(ExcepcionesComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ]);

        $proximas = $component->get('proximas');
        $this->assertTrue($proximas->contains('id', $excepcion->id));
    }

    /**
     * T-AGS-18 — Crear una excepción válida la persiste en base de datos.
     */
    #[Test]
    public function crear_excepcion_valida_persiste_en_bd(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ExcepcionesComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ])
            ->call('abrirModal')
            ->set('form.tipo', 'formacion')
            ->set('form.fecha_inicio', '2026-09-10')
            ->set('form.fecha_fin', '2026-09-11')
            ->set('form.afecta_disponibilidad', true)
            ->call('guardar')
            ->assertSet('modalAbierto', false);

        $this->assertDatabaseHas('excepciones_profesional', [
            'usuario_id'   => $this->profesional1->id,
            'tipo'         => 'formacion',
            'fecha_inicio' => '2026-09-10',
        ]);
    }

    /**
     * T-AGS-19 — Fecha fin anterior a fecha inicio es rechazada.
     */
    #[Test]
    public function fecha_fin_anterior_a_inicio_es_rechazada_en_excepcion(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ExcepcionesComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ])
            ->call('abrirModal')
            ->set('form.tipo', 'vacaciones')
            ->set('form.fecha_inicio', '2026-09-15')
            ->set('form.fecha_fin', '2026-09-10')
            ->call('guardar')
            ->assertHasErrors(['form.fecha_fin']);
    }

    /**
     * T-AGS-20 — Se muestra aviso si hay citas confirmadas en el período de la excepción.
     */
    #[Test]
    public function se_muestra_aviso_si_hay_citas_confirmadas_en_periodo(): void
    {
        $tipoSlot = $this->crearTipoSlot(30, 0);

        $cuadrante = CuadranteMes::create([
            'centro_id'                => $this->centro->id,
            'anyo'                     => 2026,
            'mes'                      => 9,
            'estado'                   => EstadoCuadrante::Publicado->value,
            'generado_con_ia'          => false,
            'generado_automaticamente' => false,
            'publicado_en'             => now(),
            'publicado_por_id'         => $this->supervisor->id,
        ]);

        $linea = LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $this->profesional1->id,
            'centro_id'        => $this->centro->id,
            'fecha'            => '2026-09-10',
            'franjas'          => json_encode([['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '14:00']]),
            'anulada'          => false,
        ]);

        $slot = Slot::create([
            'linea_cuadrante_id' => $linea->id,
            'usuario_id'         => $this->profesional1->id,
            'centro_id'          => $this->centro->id,
            'tipo_slot_id'       => $tipoSlot->id,
            'fecha'              => '2026-09-10',
            'hora_inicio'        => '09:00',
            'hora_fin'           => '09:30',
            'estado'             => EstadoSlot::Reservado->value,
        ]);

        $ciudadano = \App\Models\Ciudadano::factory()->create();
        Cita::create([
            'slot_id'      => $slot->id,
            'ciudadano_id' => $ciudadano->id,
            'profesional_id' => $this->profesional1->id,
            'tipo_slot_id' => $tipoSlot->id,
            'centro_id'    => $this->centro->id,
            'fecha'        => '2026-09-10',
            'hora_inicio'  => '09:00',
            'hora_fin'     => '09:30',
            'estado'       => EstadoCita::Confirmada->value,
            'origen'       => OrigenCita::Interno->value,
        ]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(ExcepcionesComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ])
            ->call('abrirModal')
            ->set('form.tipo', 'vacaciones')
            ->set('form.fecha_inicio', '2026-09-10')
            ->set('form.fecha_fin', '2026-09-10')
            ->set('form.afecta_disponibilidad', true)
            ->call('guardar');

        $this->assertGreaterThan(0, $component->get('citasAfectadas'));
    }

    /**
     * T-AGS-21 — Eliminar una excepción futura la borra de base de datos.
     */
    #[Test]
    public function eliminar_excepcion_futura_la_borra_de_bd(): void
    {
        $excepcion = ExcepcionProfesional::create([
            'usuario_id'            => $this->profesional1->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => 'vacaciones',
            'fecha_inicio'          => now()->addMonth()->startOfMonth()->toDateString(),
            'fecha_fin'             => now()->addMonth()->startOfMonth()->addDays(5)->toDateString(),
            'afecta_disponibilidad' => true,
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => $this->supervisor->id,
        ]);

        Livewire::actingAs($this->supervisor)
            ->test(ExcepcionesComponent::class, [
                'profesional' => $this->profesional1,
                'centro'      => $this->centro,
            ])
            ->call('eliminar', $excepcion->id);

        $this->assertDatabaseMissing('excepciones_profesional', ['id' => $excepcion->id]);
    }

    // =========================================================================
    // Grupo 5 — Cuadrante mensual (CuadranteMesComponent)
    // =========================================================================

    /**
     * T-AGS-22 — El cuadrante en borrador muestra el botón de publicación.
     */
    #[Test]
    public function cuadrante_borrador_muestra_badge_y_boton_publicar(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->assertSee('Borrador')
            ->assertSee('Publicar cuadrante');
    }

    /**
     * T-AGS-23 — Las excepciones del mes aparecen como celdas diferenciadas.
     */
    #[Test]
    public function excepciones_aparecen_como_celdas_diferenciadas(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        ExcepcionProfesional::create([
            'usuario_id'            => $this->profesional1->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => 'vacaciones',
            'fecha_inicio'          => '2026-07-21',
            'fecha_fin'             => '2026-07-21',
            'afecta_disponibilidad' => true,
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => $this->supervisor->id,
        ]);

        // July 21 is in semana index 3; the blade renders a 'Vacaciones' badge on that cell
        Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->call('goSemana', 3)
            ->assertSee('Vacaciones');
    }

    /**
     * T-AGS-24 — Hacer clic en celda con excepción abre el modal de detalle.
     */
    #[Test]
    public function abrir_modal_exc_abre_modal_con_detalle(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        ExcepcionProfesional::create([
            'usuario_id'            => $this->profesional1->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => 'formacion',
            'fecha_inicio'          => '2026-07-14',
            'fecha_fin'             => '2026-07-14',
            'afecta_disponibilidad' => true,
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => $this->supervisor->id,
        ]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->call('abrirModalExc', $this->profesional1->id, 14);

        $component->assertSet('modalExcAbierto', true);
        $this->assertEquals('formacion', $component->get('excDetalle')['tipo']);
    }

    /**
     * T-AGS-25 — Registrar ausencia desde celda del cuadrante crea ExcepcionProfesional.
     */
    #[Test]
    public function registrar_ausencia_desde_cuadrante_crea_excepcion_profesional(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->call('abrirModalAusencia', $this->profesional1->id, 7)
            ->assertSet('modalAusenciaAbierto', true)
            ->set('ausenciaForm.tipo', 'baja_medica')
            ->set('ausenciaForm.fecha_fin', '2026-07-07')
            ->call('registrarAusencia');

        $this->assertDatabaseHas('excepciones_profesional', [
            'usuario_id'  => $this->profesional1->id,
            'centro_id'   => $this->centro->id,
            'tipo'        => 'baja_medica',
            'fecha_inicio' => '2026-07-07',
            'fecha_fin'   => '2026-07-07',
            'origen'      => 'manual',
        ]);
    }

    /**
     * T-AGS-26 — Publicar el cuadrante cambia su estado a publicado.
     */
    #[Test]
    public function publicar_cuadrante_cambia_estado_a_publicado(): void
    {
        $cuadrante = CuadranteMes::create($this->datosCuadranteJulio());
        $this->crearTipoSlot();

        Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->call('publicar');

        $cuadrante->refresh();
        $this->assertEquals(EstadoCuadrante::Publicado, $cuadrante->estado);
        $this->assertNotNull($cuadrante->publicado_en);
        $this->assertEquals($this->supervisor->id, $cuadrante->publicado_por_id);
    }

    /**
     * T-AGS-27 — Publicar el cuadrante materializa slots de cita ciudadana.
     */
    #[Test]
    public function publicar_cuadrante_materializa_slots_de_30_minutos(): void
    {
        $cuadrante = CuadranteMes::create($this->datosCuadranteJulio());
        $tipoSlot  = $this->crearTipoSlot(30, 0);

        // Crear líneas de cuadrante para un día laborable de julio (lunes 6)
        foreach ([$this->profesional1, $this->profesional2, $this->profesional3] as $prof) {
            LineaCuadrante::create([
                'cuadrante_mes_id' => $cuadrante->id,
                'usuario_id'       => $prof->id,
                'centro_id'        => $this->centro->id,
                'fecha'            => '2026-07-06',
                'franjas'          => [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '14:00']],
                'anulada'          => false,
            ]);
        }

        Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->call('publicar');

        $slots = Slot::where('centro_id', $this->centro->id)
            ->where('fecha', '2026-07-06')
            ->where('estado', EstadoSlot::Disponible->value)
            ->get();

        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $ini = Carbon::parse($slot->hora_inicio);
            $fin = Carbon::parse($slot->hora_fin);
            $this->assertEquals(30, $ini->diffInMinutes($fin));
        }
    }

    /**
     * T-AGS-28 — El cuadrante publicado no muestra el botón de publicación.
     */
    #[Test]
    public function cuadrante_publicado_no_muestra_boton_publicar(): void
    {
        CuadranteMes::create(array_merge($this->datosCuadranteJulio(), [
            'estado'           => EstadoCuadrante::Publicado->value,
            'publicado_en'     => now(),
            'publicado_por_id' => $this->supervisor->id,
        ]));

        Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->assertSee('Publicado')
            ->assertDontSee('Publicar cuadrante');
    }

    /**
     * T-AGS-29 — La navegación entre semanas actualiza los días visibles.
     */
    #[Test]
    public function navegacion_entre_semanas_actualiza_semana_actual(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7]);

        $component->call('nextSemana')->assertSet('semanaActual', 1);
        $component->call('prevSemana')->assertSet('semanaActual', 0);
        $component->call('goSemana', 3)->assertSet('semanaActual', 3);
    }

    /**
     * T-AGS-30 — Las métricas del cuadrante reflejan las excepciones incorporadas.
     */
    #[Test]
    public function metricas_reflejan_excepciones_incorporadas(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        // Lunes 13 a miércoles 15 de julio = 3 días laborables
        ExcepcionProfesional::create([
            'usuario_id'            => $this->profesional1->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => 'vacaciones',
            'fecha_inicio'          => '2026-07-13',
            'fecha_fin'             => '2026-07-15',
            'afecta_disponibilidad' => true,
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => $this->supervisor->id,
        ]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7]);

        $metricas = $component->get('metricas');
        $this->assertEquals(3, $metricas['dias_con_excepciones']);
    }

    // =========================================================================
    // Grupo 6 — Control de acceso
    // =========================================================================

    /**
     * T-AGS-31 — Usuario no autenticado es redirigido al login.
     */
    #[Test]
    public function usuario_no_autenticado_es_redirigido_al_login(): void
    {
        $this->get(route('agenda.semana-tipo', ['centro' => $this->centro->id]))
            ->assertRedirect();
    }

    /**
     * T-AGS-32 — Un profesional sin rol supervisor no puede montar el componente de cuadrante.
     */
    #[Test]
    public function profesional_sin_supervision_no_puede_montar_cuadrante(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        Livewire::actingAs($this->profesional1)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->assertForbidden();
    }

    /**
     * T-AGS-33 — Un supervisor de otro centro no puede ver el cuadrante.
     */
    #[Test]
    public function supervisor_de_otro_centro_no_puede_ver_el_cuadrante(): void
    {
        CuadranteMes::create($this->datosCuadranteJulio());

        $otraUo = UnidadOrganizativa::create([
            'nombre'    => 'Otra UO',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $otroSupervisor = $this->crearUsuarioConRol('supervision', 'otro.super@vida360.test', $otraUo);

        Livewire::actingAs($otroSupervisor)
            ->test(CuadranteMesComponent::class, ['centro' => $this->centro, 'anyo' => 2026, 'mes' => 7])
            ->assertForbidden();
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Crea un TipoSlot con el nombre indicado asociado al horario del centro.
     */
    private function crearTipoSlotConNombre(string $nombre, int $duracion = 60): TipoSlot
    {
        return TipoSlot::create([
            'horario_centro_id'        => $this->horario->id,
            'nombre'                   => $nombre,
            'duracion_minutos'         => $duracion,
            'porcentaje_urgencias'     => 0,
            'bloquea_todos_convocados' => false,
            'activo'                   => true,
        ]);
    }

    /**
     * Devuelve los datos base para crear un CuadranteMes en borrador para julio 2026.
     *
     * @return array<string, mixed>
     */
    private function datosCuadranteJulio(): array
    {
        return [
            'centro_id'                => $this->centro->id,
            'anyo'                     => 2026,
            'mes'                      => 7,
            'estado'                   => EstadoCuadrante::Borrador->value,
            'generado_con_ia'          => false,
            'generado_automaticamente' => false,
        ];
    }
}
