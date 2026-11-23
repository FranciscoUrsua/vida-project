<?php

namespace Modules\Intervencion\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\DerivacionEvento;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Único punto de escritura de las derivaciones a atención especializada (fase 3, F3-6, B3).
 *
 * Flujo: el TSR deriva desde el plan principal activo a un centro que ofrece el tipo de
 * plan de la prestación; la dirección o la supervisión adscrita al centro de destino la
 * acepta asignando un profesional de ese centro (se abre el plan especializado en
 * borrador, vinculado al principal) o la rechaza con motivo; quien derivó o la
 * supervisión del centro de origen la anula mientras está pendiente.
 *
 * Cada cambio deja un evento inmutable, un apunte `derivacion` en la historia cuando
 * cambia la intervención (derivar y aceptar) y una alerta a quien debe actuar. Las
 * alertas no nombran a la persona: pueden leerlas usuarios que no la ven (personas
 * protegidas).
 */
class DerivacionService
{
    /**
     * Inyecta las dependencias.
     *
     * @param CentroDeUsuario $centros
     * @param ConfiguracionEfectivaCentro $configuracion
     * @param AlertaService $alertas
     * @param PlanDeIntervencionService $planes
     */
    public function __construct(
        private readonly CentroDeUsuario $centros,
        private readonly ConfiguracionEfectivaCentro $configuracion,
        private readonly AlertaService $alertas,
        private readonly PlanDeIntervencionService $planes,
    ) {}

    /**
     * Deriva a la persona desde su plan principal a un centro de atención especializada.
     *
     * @param PlanDeIntervencion $principal Plan principal activo desde el que se deriva.
     * @param Prestacion $prestacion Prestación con vía de acceso `derivacion`.
     * @param Centro $destino Centro que debe ofrecer el tipo de plan de la prestación.
     * @param string $motivo Motivo, visible para el centro de destino.
     * @param PlanObjetivo|null $objetivo Objetivo del plan principal, si se deriva desde él.
     * @param User $actor Quien deriva (debe poder editar el plan principal).
     *
     * @return Derivacion
     *
     * @throws DomainException
     */
    public function derivar(PlanDeIntervencion $principal, Prestacion $prestacion, Centro $destino, string $motivo, ?PlanObjetivo $objetivo, User $actor): Derivacion
    {
        if (! $principal->esPrincipal() || $principal->estado !== EstadoPlan::Activo) {
            throw new DomainException('Solo se deriva desde el plan principal activo.');
        }

        if (! Gate::forUser($actor)->allows('update', $principal)) {
            throw new DomainException('Para derivar tienes que poder editar el plan principal.');
        }

        // R1: derivar es una actuación en el centro del plan principal
        $this->centros->exigirActuacion($actor, $principal->centro);

        if ($prestacion->via_acceso !== ViaAcceso::Derivacion || ! $prestacion->activa || $prestacion->tipo_plan_derivacion_id === null) {
            throw new DomainException('Elige una prestación de derivación.');
        }

        if (! $this->configuracion->tiposPlan($destino)->contains('id', $prestacion->tipo_plan_derivacion_id)) {
            throw new DomainException("{$destino->nombre} no ofrece ese tipo de plan.");
        }

        if ($objetivo !== null && $objetivo->plan_id !== $principal->id) {
            throw new DomainException('El objetivo no es de este plan.');
        }

        if (trim($motivo) === '') {
            throw new DomainException('Indica el motivo de la derivación.');
        }

        $duplicada = Derivacion::query()->pendientes()
            ->where('historia_id', $principal->historia_id)
            ->where('centro_destino_id', $destino->id)
            ->where('tipo_plan_id', $prestacion->tipo_plan_derivacion_id)
            ->exists();

        if ($duplicada) {
            throw new DomainException("Ya hay una derivación pendiente igual a {$destino->nombre}.");
        }

        return DB::transaction(function () use ($principal, $prestacion, $destino, $motivo, $objetivo, $actor): Derivacion {
            $derivacion = Derivacion::create([
                'historia_id' => $principal->historia_id,
                'plan_origen_id' => $principal->id,
                'plan_objetivo_id' => $objetivo?->id,
                'prestacion_id' => $prestacion->id,
                'tipo_plan_id' => $prestacion->tipo_plan_derivacion_id,
                'centro_destino_id' => $destino->id,
                'motivo' => trim($motivo),
                'estado' => EstadoDerivacion::Pendiente,
                'derivada_por_id' => $actor->id,
            ]);

            $this->evento($derivacion, null, $actor, null);
            $this->apunte($derivacion, $principal->id, $actor, "Derivación a {$destino->nombre}: {$prestacion->nombre}.");

            $this->alertas->crear([
                'tipo' => TipoAlerta::Alerta,
                'origen_type' => Derivacion::class,
                'origen_id' => $derivacion->id,
                'titulo' => 'Nueva derivación pendiente',
                'cuerpo' => "{$actor->nombreVisible()} deriva un caso a {$destino->nombre} ({$derivacion->tipoPlan->nombre}). Revísala en la bandeja de derivaciones.",
                'destinatario_type' => DestinatarioType::RolUo,
                'destinatario_rol' => 'supervision',
                'destinatario_uo_id' => $destino->unidad_organizativa_id,
            ]);

            return $derivacion;
        });
    }

    /**
     * Acepta la derivación: abre el plan especializado en borrador con el responsable elegido.
     *
     * @param Derivacion $derivacion Derivación pendiente.
     * @param User $responsable Profesional adscrito al centro de destino que llevará el plan.
     * @param User $actor Dirección o supervisión adscrita al centro de destino.
     *
     * @return PlanDeIntervencion El plan especializado abierto.
     *
     * @throws DomainException
     */
    public function aceptar(Derivacion $derivacion, User $responsable, User $actor): PlanDeIntervencion
    {
        $this->exigirPendiente($derivacion);
        $this->exigirDestino($derivacion, $actor);

        $destino = $derivacion->centroDestino;

        if (! $this->centros->adscritoA($responsable, $destino) || ! $responsable->can('plan.editar')) {
            throw new DomainException("El responsable tiene que ser un profesional de intervención de {$destino->nombre}.");
        }

        $this->planes->exigirSinPlanVigenteDelTipo($derivacion->historia_id, $derivacion->tipoPlan);

        return DB::transaction(function () use ($derivacion, $responsable, $actor, $destino): PlanDeIntervencion {
            $plan = PlanDeIntervencion::create([
                'historia_id' => $derivacion->historia_id,
                'tipo_plan_id' => $derivacion->tipo_plan_id,
                'plan_asp_id' => $derivacion->plan_origen_id,
                'centro_id' => $destino->id,
                'profesional_responsable_id' => $responsable->id,
                'estado' => EstadoPlan::Borrador,
                'fecha_inicio' => today()->toDateString(),
                'version' => 1,
            ]);

            $this->cambiarEstado($derivacion, EstadoDerivacion::Aceptada, $actor, null, [
                'profesional_responsable_id' => $responsable->id,
                'plan_especializado_id' => $plan->id,
            ]);
            $this->apunte($derivacion, $plan->id, $actor, "{$destino->nombre} acepta la derivación y abre el {$derivacion->tipoPlan->nombre} con {$responsable->nombreVisible()}.");

            $cuerpo = "{$destino->nombre} ha aceptado la derivación ({$derivacion->tipoPlan->nombre}). Responsable: {$responsable->nombreVisible()}.";
            $this->alertaA($derivacion->derivada_por_id, $derivacion, 'Derivación aceptada', $cuerpo);
            if ($responsable->id !== $derivacion->derivada_por_id) {
                $this->alertaA($responsable->id, $derivacion, 'Te han asignado un plan derivado', $cuerpo);
            }

            return $plan;
        });
    }

    /**
     * Rechaza la derivación con motivo.
     *
     * @param Derivacion $derivacion Derivación pendiente.
     * @param string $motivo Motivo del rechazo (obligatorio).
     * @param User $actor Dirección o supervisión adscrita al centro de destino.
     *
     * @return void
     *
     * @throws DomainException
     */
    public function rechazar(Derivacion $derivacion, string $motivo, User $actor): void
    {
        $this->exigirPendiente($derivacion);
        $this->exigirDestino($derivacion, $actor);
        $motivo = $this->exigirMotivo($motivo, 'rechazo');

        DB::transaction(function () use ($derivacion, $motivo, $actor): void {
            $this->cambiarEstado($derivacion, EstadoDerivacion::Rechazada, $actor, $motivo, ['motivo_rechazo' => $motivo]);
            $this->alertaA($derivacion->derivada_por_id, $derivacion, 'Derivación rechazada',
                "{$derivacion->centroDestino->nombre} ha rechazado la derivación. Motivo: {$motivo}");
        });
    }

    /**
     * Anula una derivación pendiente: quien derivó o la supervisión del centro de origen.
     *
     * @param Derivacion $derivacion Derivación pendiente.
     * @param string $motivo Motivo de la anulación (obligatorio).
     * @param User $actor
     *
     * @return void
     *
     * @throws DomainException
     */
    public function anular(Derivacion $derivacion, string $motivo, User $actor): void
    {
        $this->exigirPendiente($derivacion);

        $origen = $derivacion->planOrigen->centro ?? $derivacion->historia->centro;
        $supervisaOrigen = $origen !== null && $actor->hasRole('supervision') && $this->centros->adscritoA($actor, $origen);

        if ($derivacion->derivada_por_id !== $actor->id && ! $supervisaOrigen) {
            throw new DomainException('Solo anula la derivación quien la hizo o la supervisión del centro de origen.');
        }

        $motivo = $this->exigirMotivo($motivo, 'anulación');

        DB::transaction(fn () => $this->cambiarEstado($derivacion, EstadoDerivacion::Anulada, $actor, $motivo, ['motivo_anulacion' => $motivo]));
    }

    /**
     * Si el usuario resuelve derivaciones de un centro: rol `supervision` y adscrito directamente
     * a él (la dirección lo está). La supervisión de UO superiores no resuelve (TF-CIR-21).
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function resuelveEn(User $usuario, Centro $centro): bool
    {
        return $usuario->hasRole('supervision') && $this->centros->adscritoA($usuario, $centro);
    }

    /**
     * Rechaza operar sobre una derivación que ya no está pendiente.
     *
     * @param Derivacion $derivacion
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirPendiente(Derivacion $derivacion): void
    {
        if ($derivacion->estado !== EstadoDerivacion::Pendiente) {
            throw new DomainException("La derivación ya está {$derivacion->estado->label()}.");
        }
    }

    /**
     * Rechaza que resuelva quien no es la dirección o la supervisión adscrita al centro de destino.
     *
     * @param Derivacion $derivacion
     * @param User $actor
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirDestino(Derivacion $derivacion, User $actor): void
    {
        if (! $this->resuelveEn($actor, $derivacion->centroDestino)) {
            throw new DomainException('Solo la dirección o la supervisión del centro de destino resuelven la derivación.');
        }
    }

    /**
     * Devuelve el motivo sin espacios sobrantes o rechaza uno vacío.
     *
     * @param string $motivo
     * @param string $que Para el mensaje: «rechazo», «anulación».
     *
     * @return string
     *
     * @throws DomainException
     */
    private function exigirMotivo(string $motivo, string $que): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new DomainException("Indica el motivo del {$que}.");
        }

        return $motivo;
    }

    /**
     * Cambia el estado, guarda los datos de la resolución y deja el evento.
     *
     * @param Derivacion $derivacion
     * @param EstadoDerivacion $nuevo
     * @param User $actor
     * @param string|null $motivo
     * @param array<string, mixed> $datos Campos de la resolución.
     *
     * @return void
     */
    private function cambiarEstado(Derivacion $derivacion, EstadoDerivacion $nuevo, User $actor, ?string $motivo, array $datos): void
    {
        $antes = $derivacion->estado;

        $derivacion->update($datos + [
            'estado' => $nuevo,
            'resuelta_por_id' => $actor->id,
            'resuelta_en' => now(),
        ]);

        $this->evento($derivacion, $antes, $actor, $motivo);
    }

    /**
     * Inserta un evento en el historial inmutable.
     *
     * @param Derivacion $derivacion
     * @param EstadoDerivacion|null $antes
     * @param User $actor
     * @param string|null $motivo
     *
     * @return void
     */
    private function evento(Derivacion $derivacion, ?EstadoDerivacion $antes, User $actor, ?string $motivo): void
    {
        DerivacionEvento::create([
            'derivacion_id' => $derivacion->id,
            'actor_id' => $actor->id,
            'estado_antes' => $antes,
            'estado_despues' => $derivacion->estado,
            'motivo' => $motivo,
        ]);
    }

    /**
     * Apunte `derivacion` en la historia, vinculado al plan indicado.
     *
     * @param Derivacion $derivacion
     * @param int $planId
     * @param User $autor
     * @param string $contenido
     *
     * @return void
     */
    private function apunte(Derivacion $derivacion, int $planId, User $autor, string $contenido): void
    {
        Apunte::create([
            'historia_id' => $derivacion->historia_id,
            'plan_id' => $planId,
            'autor_id' => $autor->id,
            'fecha' => today()->toDateString(),
            'tipo' => TipoApunte::Derivacion,
            'apuntable_type' => Derivacion::class,
            'apuntable_id' => $derivacion->id,
            'contenido' => $contenido,
            'visibilidad' => VisibilidadApunte::Profesionales,
        ]);
    }

    /**
     * Alerta a un usuario sobre la derivación.
     *
     * @param int $usuarioId
     * @param Derivacion $derivacion
     * @param string $titulo
     * @param string $cuerpo
     *
     * @return void
     */
    private function alertaA(int $usuarioId, Derivacion $derivacion, string $titulo, string $cuerpo): void
    {
        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => Derivacion::class,
            'origen_id' => $derivacion->id,
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuarioId,
        ]);
    }
}
