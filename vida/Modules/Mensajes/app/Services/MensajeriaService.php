<?php

namespace Modules\Mensajes\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Mensajes\Enums\RolParticipante;
use Modules\Mensajes\Enums\VisibilidadMensaje;
use Modules\Mensajes\Exceptions\UnauthorizedException;
use Modules\Mensajes\Models\Mensaje;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Models\MensajeParticipante;
use Modules\Mensajes\Models\MensajeReferenciaCiudadano;
use Modules\Mensajes\Models\MensajeRegistroHistoria;

/**
 * Servicio de gestión de la mensajería interna entre profesionales.
 */
class MensajeriaService
{
    /**
     * Crea un hilo nuevo y envía el primer mensaje.
     *
     * @param User $remitente
     * @param User $destinatario
     * @param string $asunto
     * @param string $cuerpo
     * @param int[] $ciudadanoIds IDs de ciudadanos referenciados en el mensaje
     * @param UploadedFile[] $adjuntos
     *
     * @return MensajeHilo
     */
    public function crearHilo(
        User $remitente,
        User $destinatario,
        string $asunto,
        string $cuerpo,
        array $ciudadanoIds = [],
        array $adjuntos = []
    ): MensajeHilo {
        $hilo = MensajeHilo::create([
            'asunto' => $asunto,
            'creado_por_id' => $remitente->id,
        ]);

        // Crear los dos participantes del hilo
        MensajeParticipante::create([
            'hilo_id' => $hilo->id,
            'usuario_id' => $remitente->id,
            'rol' => RolParticipante::RemitenteInicial,
        ]);

        MensajeParticipante::create([
            'hilo_id' => $hilo->id,
            'usuario_id' => $destinatario->id,
            'rol' => RolParticipante::Participante,
        ]);

        $this->crearMensaje($hilo, $remitente, $cuerpo, $ciudadanoIds, $adjuntos);

        return $hilo->fresh(['participantes', 'mensajes']);
    }

    /**
     * Añade un mensaje de respuesta a un hilo existente.
     *
     * @param MensajeHilo $hilo
     * @param User $remitente
     * @param string $cuerpo
     * @param UploadedFile[] $adjuntos
     *
     * @return Mensaje
     */
    public function responder(
        MensajeHilo $hilo,
        User $remitente,
        string $cuerpo,
        array $adjuntos = []
    ): Mensaje {
        return $this->crearMensaje($hilo, $remitente, $cuerpo, [], $adjuntos);
    }

    /**
     * Registra un mensaje en la Historia Social de un ciudadano.
     *
     * Solo el TSR responsable del expediente del ciudadano puede ejecutar
     * esta acción. Si $tsr no lo es, lanza UnauthorizedException.
     *
     * El contenido registrado es $cuerpoEditado, que puede diferir del
     * cuerpo original del mensaje.
     *
     * @param Mensaje $mensaje
     * @param Ciudadano $ciudadano
     * @param User $tsr
     * @param string $cuerpoEditado
     * @param string $visibilidad
     *
     * @return MensajeRegistroHistoria
     */
    public function registrarEnHistoria(
        Mensaje $mensaje,
        Ciudadano $ciudadano,
        User $tsr,
        string $cuerpoEditado,
        string $visibilidad = 'profesionales'
    ): MensajeRegistroHistoria {
        if (! $this->esTsrResponsable($tsr, $ciudadano)) {
            throw UnauthorizedException::noEsTsr($tsr->id, $ciudadano->id);
        }

        if (! in_array($visibilidad, ['privada', 'profesionales'], true)) {
            throw new \InvalidArgumentException(
                "La visibilidad '$visibilidad' no está permitida para registros en Historia Social."
            );
        }

        return MensajeRegistroHistoria::create([
            'mensaje_id' => $mensaje->id,
            'ciudadano_id' => $ciudadano->id,
            'registrado_por_id' => $tsr->id,
            'cuerpo_registrado' => $cuerpoEditado,
            'visibilidad' => VisibilidadMensaje::from($visibilidad),
            'registrado_en' => now(),
        ]);
    }

    /**
     * Marca todos los mensajes del hilo como leídos para un usuario.
     *
     * @param MensajeHilo $hilo
     * @param User $usuario
     *
     * @return void
     */
    public function marcarComoLeido(MensajeHilo $hilo, User $usuario): void
    {
        MensajeParticipante::where('hilo_id', $hilo->id)
            ->where('usuario_id', $usuario->id)
            ->update(['fecha_ultima_lectura' => now()]);
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * @param int[] $ciudadanoIds
     * @param UploadedFile[] $adjuntos
     */
    private function crearMensaje(
        MensajeHilo $hilo,
        User $remitente,
        string $cuerpo,
        array $ciudadanoIds,
        array $adjuntos
    ): Mensaje {
        $mensaje = Mensaje::create([
            'hilo_id' => $hilo->id,
            'remitente_id' => $remitente->id,
            'cuerpo' => $cuerpo,
        ]);

        foreach ($ciudadanoIds as $ciudadanoId) {
            MensajeReferenciaCiudadano::create([
                'mensaje_id' => $mensaje->id,
                'ciudadano_id' => $ciudadanoId,
            ]);
        }

        foreach ($adjuntos as $adjunto) {
            $mensaje->addMedia($adjunto)->toMediaCollection('adjuntos_mensaje');
        }

        return $mensaje;
    }

    /**
     * Verifica si el usuario es el TSR responsable del expediente del ciudadano.
     *
     * Un profesional es TSR del expediente cuando existe una Historia Social
     * abierta del ciudadano asignada a la UO del profesional.
     *
     * // TODO: cuando el módulo Intervencion esté operativo, delegar esta
     *           comprobación en su servicio de Historia Social.
     */
    private function esTsrResponsable(User $usuario, Ciudadano $ciudadano): bool
    {
        return HistoriaSocial::where('ciudadano_id', $ciudadano->id)
            ->whereHas('unidadOrganizativa', function ($query) use ($usuario) {
                $query->whereHas('usuarios', function ($q) use ($usuario) {
                    $q->where('usuario_id', $usuario->id);
                });
            })
            ->exists();
    }
}
