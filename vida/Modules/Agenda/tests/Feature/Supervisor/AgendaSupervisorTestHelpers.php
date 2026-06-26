<?php

namespace Modules\Agenda\Tests\Feature\Supervisor;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Models\TipoSlot;
use Modules\Centro\Models\Centro;

/**
 * Trait con actores y fixtures reutilizables en los tests de la UI agenda-supervisor.
 *
 * Crea un entorno completo: UO → Centro → HorarioCentro → CuadranteMes → Profesionales.
 * Todos los tests que usan este trait esperan que setUp() lo invoque.
 */
trait AgendaSupervisorTestHelpers
{
    protected UnidadOrganizativa $uoSupervisor;
    protected Centro              $centro;
    protected HorarioCentro       $horario;
    protected CuadranteMes        $cuadrante;
    protected User                $supervisor;
    protected User                $profesional1;
    protected User                $profesional2;
    protected User                $profesional3;

    /**
     * Construye todos los actores y fixtures compartidos.
     * Llama a este método al final de setUp() en cada test class.
     */
    protected function construirFixturesSupervisor(): void
    {
        $this->uoSupervisor = UnidadOrganizativa::create([
            'nombre'    => 'CSS Test Agenda',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->centro = Centro::create([
            'nombre'                  => 'Centro Test Agenda',
            'tipo_gestion'            => 'municipal_directo',
            'unidad_organizativa_id'  => $this->uoSupervisor->id,
            'fecha_alta'              => now()->toDateString(),
        ]);

        $this->horario = HorarioCentro::create([
            'centro_id'             => $this->centro->id,
            'nombre'                => 'Horario estándar',
            'dias_laborables'       => [1, 2, 3, 4, 5],
            'hora_apertura'         => '09:00',
            'hora_cierre'           => '15:00',
            'hora_inicio_atencion'  => '09:30',
            'hora_fin_atencion'     => '14:00',
            'buffer_inicio_minutos' => 15,
            'buffer_fin_minutos'    => 15,
            'vigente_desde'         => '2026-01-01',
            'vigente_hasta'         => null,
            'modo_agenda'           => 'estandar',
            'activo'                => true,
        ]);

        $this->supervisor = $this->crearUsuarioConRol('supervision', 'supervisor.agenda@vida360.test', $this->uoSupervisor);

        $this->profesional1 = $this->crearProfesionalEnCentro('prof1.agenda@vida360.test');
        $this->profesional2 = $this->crearProfesionalEnCentro('prof2.agenda@vida360.test');
        $this->profesional3 = $this->crearProfesionalEnCentro('prof3.agenda@vida360.test');

        $this->cuadrante = CuadranteMes::create([
            'centro_id'              => $this->centro->id,
            'anyo'                   => now()->year,
            'mes'                    => now()->month,
            'estado'                 => EstadoCuadrante::Borrador->value,
            'generado_con_ia'        => false,
            'generado_automaticamente' => false,
        ]);

        foreach ([$this->profesional1, $this->profesional2, $this->profesional3] as $prof) {
            $this->crearLineaCuadrante($prof);
        }
    }

    /**
     * Crea un usuario con el rol indicado adscrito a la UO dada.
     */
    protected function crearUsuarioConRol(
        string $rol,
        string $email,
        UnidadOrganizativa $uo
    ): User {
        $user = User::create([
            'name'               => $email,
            'email'              => $email,
            'password'           => 'secreto',
            'email_verified_at'  => now(),
            'primer_acceso'      => false,
        ]);

        $user->assignRole($rol);

        UsuarioUo::create([
            'usuario_id'             => $user->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'interno',
            'fecha_inicio'           => today()->toDateString(),
        ]);

        return $user;
    }

    /**
     * Crea un usuario con perfil horario activo en $this->centro.
     */
    protected function crearProfesionalEnCentro(string $email): User
    {
        $user = User::create([
            'name'               => $email,
            'email'              => $email,
            'password'           => 'secreto',
            'email_verified_at'  => now(),
            'primer_acceso'      => false,
        ]);

        $user->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id'             => $user->id,
            'unidad_organizativa_id' => $this->uoSupervisor->id,
            'tipo_vinculo'           => 'interno',
            'fecha_inicio'           => today()->toDateString(),
        ]);

        PerfilHorarioProfesional::create([
            'usuario_id'        => $user->id,
            'centro_id'         => $this->centro->id,
            'jornada_semanal_horas' => 35,
            'horario_habitual'  => [
                '1' => [['inicio' => '09:00', 'fin' => '14:00']],
                '2' => [['inicio' => '09:00', 'fin' => '14:00']],
                '3' => [['inicio' => '09:00', 'fin' => '14:00']],
                '4' => [['inicio' => '09:00', 'fin' => '14:00']],
                '5' => [['inicio' => '09:00', 'fin' => '14:00']],
            ],
            'vigente_desde'     => '2026-01-01',
            'vigente_hasta'     => null,
            'activo'            => true,
        ]);

        return $user;
    }

    /**
     * Crea una LineaCuadrante para el mes actual para el usuario indicado.
     */
    protected function crearLineaCuadrante(User $user, bool $anulada = false, ?int $excepcionId = null): LineaCuadrante
    {
        return LineaCuadrante::create([
            'cuadrante_mes_id' => $this->cuadrante->id,
            'usuario_id'       => $user->id,
            'centro_id'        => $this->centro->id,
            'fecha'            => now()->toDateString(),
            'franjas'          => json_encode([['tipo' => 'atencion', 'inicio' => '09:30', 'fin' => '14:00']]),
            'anulada'          => $anulada,
            'excepcion_id'     => $excepcionId,
        ]);
    }

    /**
     * Crea un TipoSlot básico asociado al HorarioCentro de los fixtures.
     */
    protected function crearTipoSlot(int $duracionMinutos = 30, int $pctUrgencias = 10): TipoSlot
    {
        return TipoSlot::create([
            'horario_centro_id'   => $this->horario->id,
            'nombre'              => 'Atención general',
            'duracion_minutos'    => $duracionMinutos,
            'porcentaje_urgencias'=> $pctUrgencias,
            'activo'              => true,
        ]);
    }
}
