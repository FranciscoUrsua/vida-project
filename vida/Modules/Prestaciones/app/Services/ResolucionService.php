<?php

namespace Modules\Prestaciones\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Prestaciones\Data\ResolucionDto;
use Modules\Prestaciones\Enums\AccionEventoSolicitud;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\OrigenActualizacion;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\ResolucionSolicitud;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Models\SolicitudPrestacionEvento;

/**
 * Incorpora la resolución de una solicitud (RN-08 y RN-09).
 *
 * Crea la resolución con sus líneas, una prestación de la persona concedida por cada línea
 * concedida (con el plan de la solicitud), un apunte automático en la historia con su fuente
 * y un aviso al trabajador social. No pide validación: la resolución consta en cuanto llega.
 */
class ResolucionService
{
    /**
     * Inyecta las dependencias.
     *
     * @param PrestacionPersonaService $prestaciones
     * @param AlertaService $alertas
     */
    public function __construct(
        private readonly PrestacionPersonaService $prestaciones,
        private readonly AlertaService $alertas,
    ) {}

    /**
     * Incorpora la resolución y deja la solicitud resuelta.
     *
     * @param SolicitudPrestacion $solicitud Solicitud enviada sin resolución.
     * @param ResolucionDto $dto
     * @param OrigenActualizacion $origen Gestor (aviso o consulta) o registro manual.
     * @param User|null $actor Quien la registra (null si llegó del gestor).
     *
     * @return ResolucionSolicitud
     *
     * @throws DomainException
     */
    public function incorporar(SolicitudPrestacion $solicitud, ResolucionDto $dto, OrigenActualizacion $origen, ?User $actor): ResolucionSolicitud
    {
        if ($solicitud->resolucion()->exists()) {
            throw new DomainException('La solicitud ya tiene resolución.');
        }

        if ($solicitud->estado !== EstadoSolicitudPrestacion::Enviada) {
            throw new DomainException('Solo se resuelve una solicitud enviada.');
        }

        foreach ($dto->lineas as $linea) {
            if (! $linea->concedida && trim((string) $linea->motivoDenegacion) === '') {
                throw new DomainException('Una línea denegada necesita motivo.');
            }
        }

        return DB::transaction(function () use ($solicitud, $dto, $origen, $actor): ResolucionSolicitud {
            $resolucion = ResolucionSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'fecha' => $dto->fecha,
                'sentido' => $dto->sentido,
                'origen' => $origen,
                'registrada_por_id' => $actor?->id,
            ]);

            $concedidas = [];
            foreach ($dto->lineas as $l) {
                $linea = $resolucion->lineas()->create([
                    'prestacion_id' => Prestacion::withTrashed()->findOrFail($l->prestacionId)->id,
                    'concedida' => $l->concedida,
                    'motivo_denegacion' => $l->concedida ? null : trim((string) $l->motivoDenegacion),
                    'importe' => $l->importe,
                    'intensidad' => $l->intensidad,
                    'fecha_desde' => $l->fechaDesde,
                    'fecha_hasta' => $l->fechaHasta,
                    'observaciones' => $l->observaciones,
                ]);

                if ($l->concedida) {
                    $concedidas[] = $this->prestaciones->desdeResolucion(
                        $linea->setRelation('resolucion', $resolucion), $solicitud->ciudadano_id, $solicitud->plan,
                        $solicitud->plan_objetivo_id, $actor, $actor?->id ?? $solicitud->creada_por_id,
                    );
                }
            }

            $antes = $solicitud->estado;
            $solicitud->update(['estado' => EstadoSolicitudPrestacion::Resuelta, 'ultima_actualizacion_en' => now()]);

            SolicitudPrestacionEvento::create([
                'solicitud_id' => $solicitud->id,
                'accion' => AccionEventoSolicitud::Resolucion,
                'estado_antes' => $antes,
                'estado_despues' => EstadoSolicitudPrestacion::Resuelta,
                'origen' => $origen,
                'usuario_id' => $actor?->id,
                'detalle' => ['resolucion_id' => $resolucion->id, 'sentido' => $dto->sentido->value, 'concedidas' => count($concedidas)],
            ]);

            $fuente = $origen === OrigenActualizacion::RegistroManual ? 'registro_manual' : 'gestor_expedientes';
            $prestacion = $solicitud->prestacion->nombre;

            Apunte::create([
                'historia_id' => $solicitud->historia_id,
                'plan_id' => $solicitud->plan_id,
                'autor_id' => $actor?->id ?? $solicitud->creada_por_id,
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::GestionCoordinacion,
                'apuntable_type' => $resolucion->getMorphClass(),
                'apuntable_id' => $resolucion->id,
                'contenido' => "Resolución {$dto->sentido->label()} de la solicitud de {$prestacion}"
                    .($solicitud->numero_expediente ? " (expediente {$solicitud->numero_expediente})" : '').'. Concedidas: '.count($concedidas).'.',
                'visibilidad' => VisibilidadApunte::Profesionales,
                'fuente' => $fuente,
            ]);

            $this->alertas->crear([
                'tipo' => TipoAlerta::Aviso,
                'origen_type' => SolicitudPrestacion::class,
                'origen_id' => $solicitud->id,
                'titulo' => 'Solicitud resuelta',
                'cuerpo' => "La solicitud de {$prestacion} se ha resuelto ({$dto->sentido->label()}).",
                'destinatario_type' => DestinatarioType::Usuario,
                'destinatario_usuario_id' => $solicitud->creada_por_id,
            ]);

            return $resolucion;
        });
    }
}
