<?php

namespace Modules\Mensajes\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Policies\AlcanceCentro;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Mensajes\Enums\TipoContextoMensaje;

/**
 * Resuelve en el servidor el elemento vinculado a un mensaje (expediente,
 * ficha de valoración o plan).
 *
 * El navegador solo envía tipo e id: la etiqueta, el ciudadano, el autor que
 * se sugiere como destinatario y el enlace salen de aquí, y solo si el
 * usuario puede ver la Historia Social del elemento (`HistoriaSocialPolicy::view`).
 * La etiqueta no lleva datos personales: la leerá el destinatario aunque no
 * tenga acceso al expediente.
 */
class ContextoMensajeService
{
    /**
     * Datos del elemento si existe y el usuario puede verlo; si no, null.
     *
     * @param string $tipo Valor de TipoContextoMensaje.
     * @param int $id ID del elemento.
     * @param User $usuario Usuario que abre el panel o lee el hilo.
     * @return array{tipo: string, id: int, etiqueta: string, ciudadano_id: int|null, autor_id: int|null, url: string}|null
     */
    public function resolver(string $tipo, int $id, User $usuario): ?array
    {
        $tipoContexto = TipoContextoMensaje::tryFrom($tipo);

        if ($tipoContexto === null) {
            return null;
        }

        // Cita y slot de agenda no se autorizan por la Historia Social sino por la agenda
        if (in_array($tipoContexto, [TipoContextoMensaje::Cita, TipoContextoMensaje::Slot], true)) {
            return $this->deAgenda($tipoContexto, $id, $usuario);
        }

        [$historia, $autorId, $url] = match ($tipoContexto) {
            TipoContextoMensaje::Historia => $this->deHistoria($id),
            TipoContextoMensaje::Ficha => $this->deFicha($id),
            TipoContextoMensaje::Plan => $this->dePlan($id),
            default => [null, null, ''],
        };

        if ($historia === null || ! Gate::forUser($usuario)->allows('view', $historia)) {
            return null;
        }

        return [
            'tipo' => $tipoContexto->value,
            'id' => $id,
            'etiqueta' => $tipoContexto->etiqueta().' #'.$id,
            'ciudadano_id' => $historia->ciudadano_id,
            'autor_id' => $autorId,
            'url' => $url,
        ];
    }

    /**
     * Expediente: se sugiere el TSR con asignación vigente.
     *
     * @return array{0: HistoriaSocial|null, 1: int|null, 2: string}
     */
    private function deHistoria(int $id): array
    {
        $historia = HistoriaSocial::find($id);

        return [
            $historia,
            $historia?->asignacionVigente?->profesional_id,
            $historia ? route('intervencion.ciudadano.show', $historia) : '',
        ];
    }

    /**
     * Ficha de valoración: se sugiere quien la cumplimentó.
     *
     * @return array{0: HistoriaSocial|null, 1: int|null, 2: string}
     */
    private function deFicha(int $id): array
    {
        $ficha = Ficha::find($id);
        $historia = $ficha?->historia_id ? HistoriaSocial::find($ficha->historia_id) : null;

        return [
            $historia,
            $ficha?->profesional_id,
            $historia ? route('intervencion.ficha.show', [$historia, $ficha]) : '',
        ];
    }

    /**
     * Plan de intervención: se sugiere su responsable.
     *
     * @return array{0: HistoriaSocial|null, 1: int|null, 2: string}
     */
    private function dePlan(int $id): array
    {
        $plan = PlanDeIntervencion::find($id);
        $historia = $plan ? HistoriaSocial::find($plan->historia_id) : null;

        return [
            $historia,
            $plan?->profesional_responsable_id,
            $plan ? route('intervencion.plan.show', $plan) : '',
        ];
    }

    /**
     * Cita o slot de agenda: visible para su profesional y para quien gestiona o
     * supervisa las citas del centro. Se sugiere al profesional como destinatario.
     *
     * @param TipoContextoMensaje $tipo
     * @param int $id
     * @param User $usuario
     * @return array{tipo: string, id: int, etiqueta: string, ciudadano_id: int|null, autor_id: int|null, url: string}|null
     */
    private function deAgenda(TipoContextoMensaje $tipo, int $id, User $usuario): ?array
    {
        if ($tipo === TipoContextoMensaje::Cita) {
            $cita = Cita::find($id);

            if ($cita === null || ! Gate::forUser($usuario)->allows('view', $cita)) {
                return null;
            }

            return [
                'tipo' => $tipo->value,
                'id' => $id,
                'etiqueta' => $tipo->etiqueta().' #'.$id,
                'ciudadano_id' => $cita->ciudadano_id,
                'autor_id' => $cita->profesional_id,
                'url' => route('agenda.citas.show', $cita),
            ];
        }

        $slot = Slot::find($id);

        if ($slot === null || ($slot->usuario_id !== $usuario->id && ! AlcanceCentro::incluye($usuario, $slot->centro_id))) {
            return null;
        }

        return [
            'tipo' => $tipo->value,
            'id' => $id,
            'etiqueta' => $tipo->etiqueta().' #'.$id,
            'ciudadano_id' => null,
            'autor_id' => $slot->usuario_id,
            'url' => $usuario->hasRole('supervision') ? route('agenda.supervisor.cuadrante') : route('intervencion.agenda.index'),
        ];
    }
}
