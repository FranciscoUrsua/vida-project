<?php

namespace Modules\Agenda\Tests\Concerns;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Enums\ModalidadCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Actores y datos comunes de los tests de citas (TF-CIT-01 a 43).
 *
 * Hoy es martes 6 de octubre de 2026 a las 10:00. Cada profesional tiene, en
 * los días laborables de las próximas tres semanas, slots de entrevista a las
 * 09:00, 11:00 y 12:00 y uno reservado para urgencias a las 13:00.
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md «Actores y datos reutilizados»
 */
trait CitasTestSetup
{
    protected Centro $centro;

    protected HorarioCentro $horario;

    protected User $admin;

    protected User $supervisor;

    protected User $consulta;

    protected User $auxiliar;

    protected User $tsr;

    protected User $tsr2;

    protected User $tsrOtroPerfil;

    protected Ciudadano $maria;

    protected Ciudadano $juan;

    protected HistoriaSocial $historiaMaria;

    protected TipoSlot $slotEntrevista;

    protected TipoSlot $slotGrupal;

    protected TipoCita $tipoSeguimiento;

    protected TipoCita $tipoInformacion;

    /**
     * Monta el escenario completo.
     *
     * @return void
     */
    protected function montarEscenarioCitas(): void
    {
        Carbon::setTestNow('2026-10-06 10:00:00');
        Queue::fake();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $uo = UnidadOrganizativa::create(['nombre' => 'UO CSS Citas', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->centro = Centro::create([
            'nombre' => 'CSS Citas',
            'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $uo->id,
            'activo' => true,
            'fecha_alta' => '2020-01-01',
        ]);

        $this->horario = HorarioCentro::create([
            'centro_id' => $this->centro->id,
            'nombre' => 'Horario estándar',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '15:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2026-01-01',
            'modo_agenda' => 'estandar',
            'activo' => true,
            'dias_ausencia_prolongada' => 15,
            'plazos_urgencia' => ['ordinaria' => 20, 'preferente' => 7, 'urgente' => 2],
            'dias_aviso_cierre_supervisor' => 3,
        ]);

        $this->slotEntrevista = TipoSlot::factory()->create(['nombre' => 'Entrevista', 'duracion_minutos' => 45, 'origen_permitido' => OrigenPermitidoSlot::Ambos->value]);
        $this->slotGrupal = TipoSlot::factory()->create(['nombre' => 'Grupal', 'duracion_minutos' => 60]);
        $this->horario->tiposSlot()->attach([$this->slotEntrevista->id, $this->slotGrupal->id]);

        $this->tipoSeguimiento = TipoCita::create([
            'codigo' => 'seguimiento_pia_vg',
            'nombre' => 'Seguimiento PIA violencia de género',
            'etiqueta_publica' => 'Entrevista',
            'herramienta' => HerramientaCita::EntrevistaSeguimiento,
            'modalidad_defecto' => ModalidadCita::Presencial,
            'requiere_historia_social' => true,
            'activo' => true,
        ]);
        $this->tipoSeguimiento->tiposSlot()->attach($this->slotEntrevista);

        $this->tipoInformacion = TipoCita::create([
            'codigo' => 'informacion',
            'nombre' => 'Información',
            'etiqueta_publica' => 'Información',
            'herramienta' => HerramientaCita::Atencion,
            'modalidad_defecto' => ModalidadCita::Presencial,
            'requiere_historia_social' => false,
            'activo' => true,
        ]);
        $this->tipoInformacion->tiposSlot()->attach($this->slotEntrevista);

        $ts = Cargo::create(['nombre' => 'Trabajador/a Social', 'slug' => 'ts', 'activo' => true]);
        $educador = Cargo::create(['nombre' => 'Educador/a Social', 'slug' => 'educadorsocial', 'activo' => true]);
        $auxiliar = Cargo::create(['nombre' => 'Auxiliar de servicios sociales', 'slug' => 'auxss', 'activo' => true]);

        $this->admin = $this->usuarioCitas('admin', ['adm_sistema']);
        $this->supervisor = $this->usuarioCitas('supervisor', ['supervision']);
        $this->consulta = $this->usuarioCitas('consulta', ['consulta_basica']);
        $this->auxiliar = $this->usuarioCitas('auxiliar', ['intervencion', 'consulta_basica'], $auxiliar);
        $this->tsr = $this->usuarioCitas('tsr', ['intervencion'], $ts);
        $this->tsr2 = $this->usuarioCitas('tsr2', ['intervencion'], $ts);
        $this->tsrOtroPerfil = $this->usuarioCitas('educador', ['intervencion'], $educador);

        $this->maria = Ciudadano::factory()->create(['nombre' => 'María']);
        $this->juan = Ciudadano::factory()->create(['nombre' => 'Juan']);

        $this->historiaMaria = HistoriaSocial::factory()->create([
            'ciudadano_id' => $this->maria->id,
            'unidad_organizativa_id' => $uo->id,
            'estado' => 'abierta',
        ]);
        AsignacionProfesional::create([
            'historia_id' => $this->historiaMaria->id,
            'profesional_id' => $this->tsr->id,
            'centro_id' => $this->centro->id,
            'origen' => OrigenAsignacionReferencia::QuienAbre,
            'fecha_inicio' => '2026-01-15',
        ]);

        foreach ([$this->tsr, $this->tsr2, $this->tsrOtroPerfil, $this->auxiliar] as $profesional) {
            $this->materializarSlots($profesional);
        }
    }

    /**
     * Usuario con roles y adscripción a la UO del centro.
     *
     * @param string $alias
     * @param list<string> $roles
     * @param Cargo|null $cargo Si se indica, con perfil profesional de ese cargo.
     * @return User
     */
    protected function usuarioCitas(string $alias, array $roles, ?Cargo $cargo = null): User
    {
        $profesional = null;

        if ($cargo !== null) {
            $profesional = Profesional::create([
                'nombre' => ucfirst($alias),
                'apellido1' => 'Prueba',
                'sexo' => 'F',
                'cargo_id' => $cargo->id,
                'tipo_relacion_id' => TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a de carrera'], ['es_externo' => false, 'activo' => true])->id,
                'fecha_inicio' => '2020-01-01',
                'activo' => true,
            ]);
        }

        $usuario = User::create([
            'email' => "{$alias}@citas.test",
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
            'profesional_id' => $profesional?->id,
        ]);
        $usuario->syncRoles($roles);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $this->centro->unidad_organizativa_id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => '2020-01-01',
        ]);

        return $usuario;
    }

    /**
     * Slots de las tres próximas semanas (laborables) del profesional.
     *
     * @param User $profesional
     * @return void
     */
    protected function materializarSlots(User $profesional): void
    {
        $dia = today();
        $fin = today()->addWeeks(3);

        for (; $dia->lte($fin); $dia->addDay()) {
            if ($dia->isWeekend()) {
                continue;
            }

            $linea = LineaCuadrante::create([
                'cuadrante_mes_id' => $this->cuadrante($dia)->id,
                'usuario_id' => $profesional->id,
                'centro_id' => $this->centro->id,
                'fecha' => $dia->toDateString(),
                'franjas' => [['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '14:00']],
                'anulada' => false,
            ]);

            foreach (['09:00' => EstadoSlot::Disponible, '11:00' => EstadoSlot::Disponible, '12:00' => EstadoSlot::Disponible, '13:00' => EstadoSlot::BloqueadoUrgencia] as $hora => $estado) {
                $this->slot($linea, $profesional, $this->slotEntrevista, $dia->toDateString(), $hora, $estado);
            }
        }
    }

    /**
     * Crea un slot.
     *
     * @param LineaCuadrante $linea
     * @param User $profesional
     * @param TipoSlot $tipo
     * @param string $fecha
     * @param string $hora
     * @param EstadoSlot $estado
     * @return Slot
     */
    protected function slot(LineaCuadrante $linea, User $profesional, TipoSlot $tipo, string $fecha, string $hora, EstadoSlot $estado = EstadoSlot::Disponible): Slot
    {
        return Slot::create([
            'linea_cuadrante_id' => $linea->id,
            'usuario_id' => $profesional->id,
            'centro_id' => $this->centro->id,
            'tipo_slot_id' => $tipo->id,
            'fecha' => $fecha,
            'hora_inicio' => $hora,
            'hora_fin' => Carbon::parse($hora)->addMinutes(45)->format('H:i'),
            'estado' => $estado->value,
        ]);
    }

    /**
     * Cuadrante publicado del mes de una fecha (se crea si no existe).
     *
     * @param Carbon $dia
     * @return CuadranteMes
     */
    protected function cuadrante(Carbon $dia): CuadranteMes
    {
        return CuadranteMes::firstOrCreate(
            ['centro_id' => $this->centro->id, 'anyo' => $dia->year, 'mes' => $dia->month],
            ['estado' => EstadoCuadrante::Publicado->value, 'generado_con_ia' => false, 'generado_automaticamente' => false, 'publicado_en' => now()],
        );
    }

    /**
     * Slot libre de un profesional en una fecha y hora.
     *
     * @param User $profesional
     * @param string $fecha
     * @param string $hora
     * @return Slot
     */
    protected function slotDe(User $profesional, string $fecha, string $hora): Slot
    {
        return Slot::where('usuario_id', $profesional->id)->where('fecha', $fecha)->where('hora_inicio', $hora)->firstOrFail();
    }

    /**
     * Da una cita confirmada (vía CitacionService, como consulta) en el slot de
     * un profesional.
     *
     * @param Ciudadano $ciudadano
     * @param User $profesional
     * @param string $fecha
     * @param string $hora
     * @param TipoCita|null $tipo Por defecto, seguimiento.
     * @return Cita
     */
    protected function citaConfirmada(Ciudadano $ciudadano, User $profesional, string $fecha, string $hora = '11:00', ?TipoCita $tipo = null): Cita
    {
        $slot = $this->slotDe($profesional, $fecha, $hora);

        // Una cita de hoy a una hora ya pasada se da «antes»: se mueve el reloj y se restaura
        $ahora = now();
        Carbon::setTestNow(Carbon::parse($fecha)->setTime(8, 0));

        try {
            return app(CitacionService::class)->citarDirecto([
                'ciudadano_id' => $ciudadano->id,
                'centro_id' => $this->centro->id,
                'tipo_cita_id' => ($tipo ?? $this->tipoSeguimiento)->id,
                'urgencia' => 'ordinaria',
                'destino' => 'profesional_concreto',
                'profesional_destino_id' => $profesional->id,
            ], $slot, ModoAsignacionCita::ProfesionalConcreto, $this->consulta);
        } finally {
            Carbon::setTestNow($ahora);
        }
    }
}
