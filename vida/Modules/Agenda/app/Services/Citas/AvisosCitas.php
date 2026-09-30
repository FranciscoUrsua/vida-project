<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Centro\Models\Centro;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Avisos del subdominio de citas (Módulo Mensajes).
 *
 * Todos son de tipo «aviso» (decisión del desarrollador, 2026-09-30): informan,
 * no expiran ni escalan. El texto solo lleva la etiqueta pública del tipo de
 * cita, la urgencia y fechas: nunca el motivo ni el nombre interno, porque los
 * lee quien no tiene acceso a la Historia Social.
 */
class AvisosCitas
{
    /**
     * @param AlertaService $alertas
     */
    public function __construct(private readonly AlertaService $alertas) {}

    /**
     * Solicitud nueva: a quienes dan citas en el centro (`consulta_basica`).
     *
     * @param SolicitudCita $solicitud
     * @return void
     */
    public function solicitudCreada(SolicitudCita $solicitud): void
    {
        $this->aRol($solicitud->centro, 'consulta_basica', $solicitud,
            'Nueva solicitud de cita',
            "Solicitud {$solicitud->urgencia->label()} de «{$solicitud->tipoCita->etiqueta_publica}», con fecha límite {$solicitud->no_despues_de->format('d/m/Y')}. Está en la bandeja de citación.");
    }

    /**
     * Solicitud sin cita pasada su fecha límite: a supervisión del centro.
     *
     * @param SolicitudCita $solicitud
     * @return void
     */
    public function solicitudFueraDePlazo(SolicitudCita $solicitud): void
    {
        $this->aRol($solicitud->centro, 'supervision', $solicitud,
            'Solicitud de cita fuera de plazo',
            "Una solicitud {$solicitud->urgencia->label()} de «{$solicitud->tipoCita->etiqueta_publica}» superó su fecha límite ({$solicitud->no_despues_de->format('d/m/Y')}) sin cita.");
    }

    /**
     * Cita pendiente de cierre: a su profesional.
     *
     * @param Cita $cita
     * @return void
     */
    public function citaPendienteCierre(Cita $cita): void
    {
        $this->aUsuario($cita->profesional_id, $cita,
            'Cita pendiente de cierre',
            "La cita del {$cita->fecha->format('d/m/Y')} a las {$this->hora($cita)} no tiene apunte ni incomparecencia. Regístralo desde tu agenda.");
    }

    /**
     * Cita que sigue pendiente de cierre pasados los días del centro: a supervisión.
     *
     * @param Cita $cita
     * @return void
     */
    public function citaPendienteCierreProlongada(Cita $cita): void
    {
        $this->aRol($cita->centro, 'supervision', $cita,
            'Cita sin cerrar',
            "La cita del {$cita->fecha->format('d/m/Y')} a las {$this->hora($cita)} sigue sin apunte ni incomparecencia.");
    }

    /**
     * Se ha dado una cita en un slot reservado para urgencias: a supervisión.
     *
     * @param Cita $cita
     * @return void
     */
    public function slotUrgenciaConsumido(Cita $cita): void
    {
        $this->aRol($cita->centro, 'supervision', $cita,
            'Slot de urgencia consumido',
            "Se ha dado una cita urgente en un slot reservado para urgencias el {$cita->fecha->format('d/m/Y')} a las {$this->hora($cita)}.");
    }

    /**
     * Supervisor del centro para mensajes uno a uno (el de id menor si hay varios).
     *
     * @param Centro $centro
     * @return User|null
     */
    public function supervisorDelCentro(Centro $centro): ?User
    {
        return User::role('supervision')
            ->whereHas('adscripcionesVigentes', fn ($q) => $q->where('unidad_organizativa_id', $centro->unidad_organizativa_id))
            ->orderBy('id')
            ->first();
    }

    /**
     * @param Cita $cita
     * @return string
     */
    private function hora(Cita $cita): string
    {
        return substr((string) $cita->hora_inicio, 0, 5);
    }

    /**
     * @param Centro $centro
     * @param string $rol
     * @param Model $origen
     * @param string $titulo
     * @param string $cuerpo
     * @return void
     */
    private function aRol(Centro $centro, string $rol, Model $origen, string $titulo, string $cuerpo): void
    {
        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => $origen::class,
            'origen_id' => $origen->getKey(),
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => $rol,
            'destinatario_uo_id' => $centro->unidad_organizativa_id,
        ]);
    }

    /**
     * @param int $usuarioId
     * @param Model $origen
     * @param string $titulo
     * @param string $cuerpo
     * @return void
     */
    private function aUsuario(int $usuarioId, Model $origen, string $titulo, string $cuerpo): void
    {
        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => $origen::class,
            'origen_id' => $origen->getKey(),
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuarioId,
        ]);
    }
}
