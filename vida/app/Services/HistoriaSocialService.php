<?php

namespace App\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Mensajes\Models\MensajeRegistroHistoria;

/**
 * Servicio de Historia Social (stub).
 *
 * Centraliza las consultas sobre la línea de tiempo del expediente
 * de un ciudadano. La implementación completa corresponde al módulo
 * Intervencion; este stub expone únicamente lo necesario para la
 * integración con el módulo de Mensajes.
 *
 * @todo Mover a Modules\Intervencion\Services\HistoriaSocialService
 *       cuando el módulo de Intervención esté operativo.
 */
class HistoriaSocialService
{
    /**
     * Obtiene todas las entradas de la Historia Social de un ciudadano
     * visibles para el profesional dado, incluyendo los mensajes que el
     * TSR registró explícitamente en el expediente.
     *
     * Los registros de tipo 'comunicacion_interna' se obtienen de
     * mensajes_registro_historia. La visibilidad filtra los registros
     * privados si el profesional no es el autor.
     *
     * @param Ciudadano $ciudadano Ciudadano cuyo expediente se consulta.
     * @param User $profesional Profesional que consulta la historia.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function obtenerEntradas(Ciudadano $ciudadano, User $profesional): Collection
    {
        // Apuntes de la Historia Social (módulo Intervencion — pendiente de implementar)
        $apuntes = collect(); // TODO: obtener de Modules\Intervencion cuando esté disponible

        // Registros de mensajes internos incorporados al expediente
        $comunicacionesInternas = MensajeRegistroHistoria::where('ciudadano_id', $ciudadano->id)
            ->where(function ($query) use ($profesional) {
                // Visibles para todos los profesionales con acceso
                $query->where('visibilidad', 'profesionales')
                    // O privados pero creados por el mismo profesional
                    ->orWhere(function ($q) use ($profesional) {
                        $q->where('visibilidad', 'privada')
                            ->where('registrado_por_id', $profesional->id);
                    });
            })
            ->with(['registradoPor', 'mensaje.remitente'])
            ->orderBy('registrado_en')
            ->get()
            ->map(fn (MensajeRegistroHistoria $registro) => [
                'tipo' => 'comunicacion_interna',
                'fecha' => $registro->registrado_en,
                'autor' => $registro->registradoPor->name,
                'contenido' => $registro->cuerpo_registrado,
                'visibilidad' => $registro->visibilidad->value,
                'origen' => $registro,
            ]);

        return $apuntes->merge($comunicacionesInternas)->sortBy('fecha')->values();
    }

    /**
     * Verifica si un usuario es el TSR responsable de la Historia Social
     * de un ciudadano concreto.
     *
     * @param User $usuario Usuario a comprobar.
     * @param Ciudadano $ciudadano Ciudadano cuya historia social se evalua.
     *
     * @return bool True si el usuario pertenece a la UO de la historia social.
     */
    public function esTsr(User $usuario, Ciudadano $ciudadano): bool
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
