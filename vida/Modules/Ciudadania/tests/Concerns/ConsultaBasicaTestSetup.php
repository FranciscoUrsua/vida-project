<?php

namespace Modules\Ciudadania\Tests\Concerns;

use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AmbitoUsuario;
use Illuminate\Support\Carbon;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Enums\ModalidadCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Models\TipoSlot;
use Modules\Centro\Models\Centro;
use Modules\Ciudadania\Models\CiudadanoPrestacionResumen;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;

/**
 * Datos de los tests de la fase 4 (atención administrativa,
 * docs/instrucciones-cli/2026-11-fase4-consulta-basica.md, «Datos»).
 *
 * Sobre el escenario del circuito (fase 3): `$ventanillaNorte` y `$ventanillaSur`
 * (`consulta_basica` en dos CSS), `$adminNorte` (`tramitacion` en el CSS Norte), una
 * prestación de `$ana`, sus seis citas y `$pedro`, sin historia. Fecha: lunes 2 nov 2026.
 */
trait ConsultaBasicaTestSetup
{
    use CircuitoTestSetup;

    protected User $ventanillaNorte;

    protected User $ventanillaSur;

    protected User $adminNorte;

    protected Ciudadano $pedro;

    protected TipoCita $tipoCitaTs;

    /** Nombre interno del tipo de cita (no lo ve quien no lee la historia). */
    protected const TIPO_INTERNO = 'Seguimiento del PISO interno';

    /** Etiqueta pública del tipo de cita. */
    protected const TIPO_PUBLICO = 'Cita con su trabajador o trabajadora social';

    /** Motivo de la cita (nunca se muestra en el historial de la ficha). */
    protected const MOTIVO_SECRETO = 'Motivo profesional reservado 4711';

    protected Cita $citaCompletada;

    protected Cita $citaIncomparecencia;

    protected Cita $citaReprogramada;

    protected Cita $citaSustituta;

    protected Cita $citaFuturaNorte;

    protected Cita $citaFuturaCiam;

    /**
     * Monta el escenario de la fase 4.
     *
     * @return void
     */
    protected function montarConsultaBasica(): void
    {
        $this->montarCircuito();

        $this->ventanillaNorte = $this->profesionalCon('ventanilla.norte', 'consulta_basica', $this->cssNorte, 'Auxiliar de información');
        $this->ventanillaSur = $this->profesionalCon('ventanilla.sur', 'consulta_basica', $this->cssSur, 'Auxiliar de información');
        $this->adminNorte = $this->profesionalCon('admin.norte', 'tramitacion', $this->cssNorte, 'Auxiliar administrativo');

        $this->pedro = Ciudadano::factory()->create(['nombre' => 'Pedro', 'apellido1' => 'Sinhistoria']);

        CiudadanoPrestacionResumen::create([
            'ciudadano_id' => $this->ana->id, 'modulo_origen' => 'prestaciones', 'origen_id' => 1, 'tipo' => 'servicio',
            'descripcion' => 'Ayuda a domicilio de Ana', 'estado' => 'activo', 'fecha_inicio' => '2026-09-01',
        ]);

        $this->tipoCitaTs = TipoCita::create([
            'codigo' => 'seguimiento_piso', 'nombre' => self::TIPO_INTERNO, 'etiqueta_publica' => self::TIPO_PUBLICO,
            'herramienta' => HerramientaCita::EntrevistaSeguimiento, 'modalidad_defecto' => ModalidadCita::Presencial,
            'requiere_historia_social' => false, 'activo' => true,
        ]);

        $this->citaCompletada = $this->cita($this->ana, $this->tsNorte, $this->cssNorte, '2026-10-20', '10:00', EstadoCita::Completada);
        $this->citaIncomparecencia = $this->cita($this->ana, $this->tsNorte, $this->cssNorte, '2026-10-22', '10:00', EstadoCita::NoShowCiudadano);
        $this->citaReprogramada = $this->cita($this->ana, $this->tsNorte, $this->cssNorte, '2026-10-27', '11:00', EstadoCita::Reprogramada);
        $this->citaSustituta = $this->cita($this->ana, $this->tsNorte, $this->cssNorte, '2026-10-29', '12:00', EstadoCita::Completada, anterior: $this->citaReprogramada);
        $this->citaFuturaNorte = $this->cita($this->ana, $this->tsNorte, $this->cssNorte, '2026-11-05', '10:00', EstadoCita::Confirmada);
        $this->citaFuturaCiam = $this->cita($this->ana, $this->psicologaVallecas, $this->ciamVallecas, '2026-11-06', '17:00', EstadoCita::Confirmada);

        app(AmbitoUsuario::class)->olvidar();
    }

    /**
     * Slot de un profesional en un centro, con su cuadrante publicado y su línea.
     *
     * @param User $profesional
     * @param Centro $centro
     * @param string $fecha
     * @param string $hora
     * @param EstadoSlot $estado
     *
     * @return Slot
     */
    protected function slotEn(User $profesional, Centro $centro, string $fecha, string $hora, EstadoSlot $estado = EstadoSlot::Disponible): Slot
    {
        $dia = Carbon::parse($fecha);
        $cuadrante = CuadranteMes::firstOrCreate(
            ['centro_id' => $centro->id, 'anyo' => $dia->year, 'mes' => $dia->month],
            ['estado' => EstadoCuadrante::Publicado->value, 'generado_con_ia' => false, 'generado_automaticamente' => false, 'publicado_en' => now()],
        );
        $linea = LineaCuadrante::firstOrCreate(
            ['cuadrante_mes_id' => $cuadrante->id, 'usuario_id' => $profesional->id, 'fecha' => $fecha],
            ['centro_id' => $centro->id, 'franjas' => [['inicio' => '09:00', 'fin' => '19:00']]],
        );
        $tipoSlot = TipoSlot::firstOrCreate(['nombre' => 'Atención general'], ['duracion_minutos' => 45, 'requiere_espacio' => false, 'porcentaje_urgencias' => 0, 'origen_permitido' => 'ambos', 'activo' => true]);

        return Slot::create([
            'linea_cuadrante_id' => $linea->id, 'usuario_id' => $profesional->id, 'centro_id' => $centro->id,
            'tipo_slot_id' => $tipoSlot->id, 'fecha' => $fecha, 'hora_inicio' => $hora,
            'hora_fin' => $dia->copy()->setTimeFromTimeString($hora)->addMinutes(45)->format('H:i'),
            'estado' => $estado->value,
        ]);
    }

    /**
     * Cita de una persona en el slot de un profesional, creada directamente (datos de partida).
     *
     * @param Ciudadano $persona
     * @param User $profesional
     * @param Centro $centro
     * @param string $fecha
     * @param string $hora
     * @param EstadoCita $estado
     * @param Cita|null $anterior Cita a la que sustituye (reprogramación).
     *
     * @return Cita
     */
    protected function cita(Ciudadano $persona, User $profesional, Centro $centro, string $fecha, string $hora, EstadoCita $estado, ?Cita $anterior = null): Cita
    {
        $dia = Carbon::parse($fecha);
        $slot = $this->slotEn($profesional, $centro, $fecha, $hora, EstadoSlot::Reservado);
        $fin = $slot->hora_fin;
        $tipoSlot = $slot->tipoSlot;

        return Cita::create([
            'slot_id' => $slot->id, 'ciudadano_id' => $persona->id, 'profesional_id' => $profesional->id,
            'tipo_slot_id' => $tipoSlot->id, 'centro_id' => $centro->id, 'fecha' => $fecha,
            'hora_inicio' => $hora, 'hora_fin' => $fin, 'estado' => $estado->value, 'origen' => OrigenCita::Interno->value,
            'tipo_cita_id' => $this->tipoCitaTs->id, 'modalidad' => ModalidadCita::Presencial->value,
            'modo_asignacion' => ModoAsignacionCita::ProfesionalConcreto->value, 'motivo' => self::MOTIVO_SECRETO,
            'cita_anterior_id' => $anterior?->id,
            'completada_en' => $estado === EstadoCita::Completada ? $dia->copy()->setTimeFromTimeString($hora) : null,
        ]);
    }
}
