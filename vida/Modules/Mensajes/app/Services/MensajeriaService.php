<?php

namespace Modules\Mensajes\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
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
     * Los mensajes no admiten adjuntos: los documentos pertenecen a la
     * Historia Social y solo se referencian como contexto.
     *
     * @param User $remitente Usuario que inicia la conversación.
     * @param User $destinatario Usuario que recibe el primer mensaje.
     * @param string $asunto Asunto del hilo.
     * @param string $cuerpo Cuerpo del primer mensaje.
     * @param int[] $ciudadanoIds IDs de ciudadanos referenciados en el mensaje
     * @return MensajeHilo
     */
    public function crearHilo(
        User $remitente,
        User $destinatario,
        string $asunto,
        string $cuerpo,
        array $ciudadanoIds = []
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

        $this->crearMensaje($hilo, $remitente, $cuerpo, $ciudadanoIds);

        return $hilo->fresh(['participantes', 'mensajes']);
    }

    /**
     * Añade un mensaje de respuesta a un hilo existente.
     *
     * @param MensajeHilo $hilo Hilo al que se responde.
     * @param User $remitente Usuario que responde.
     * @param string $cuerpo Texto de la respuesta.
     * @return Mensaje
     */
    public function responder(MensajeHilo $hilo, User $remitente, string $cuerpo): Mensaje
    {
        return $this->crearMensaje($hilo, $remitente, $cuerpo, []);
    }

    /**
     * Registra un mensaje en la Historia Social de un ciudadano.
     *
     * Solo el TSR responsable del expediente del ciudadano puede ejecutar
     * esta acción. Si $tsr no lo es, lanza UnauthorizedException.
     *
     * El contenido registrado es $cuerpoEditado, que puede diferir del
     * cuerpo original del mensaje.
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
     * Crea el mensaje y sus referencias a ciudadanos.
     *
     * @param int[] $ciudadanoIds
     */
    private function crearMensaje(
        MensajeHilo $hilo,
        User $remitente,
        string $cuerpo,
        array $ciudadanoIds
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
