<?php

namespace Modules\Mensajes\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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

        [$historia, $autorId, $url] = match ($tipoContexto) {
            TipoContextoMensaje::Historia => $this->deHistoria($id),
            TipoContextoMensaje::Ficha => $this->deFicha($id),
            TipoContextoMensaje::Plan => $this->dePlan($id),
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
}
