<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Models\Cita;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Models\Derivacion;

/**
 * Adónde lleva «Atender» una cita desde la agenda del profesional
 * (docs/modulo-citas.md §3.5.1): la ficha de la persona con la herramienta del
 * tipo de cita ya abierta y la cita enlazada (`?cita=`), para que el apunte que
 * se registre la complete. Sin Historia Social, o si el tipo de cita es de
 * atención, la ficha de Ciudadanía, donde el registro de atención la cierra.
 *
 * El profesional de encargo no abre la Historia Social: su cita lleva al encargo
 * (bloque E de docs/instrucciones-cli/2026-11-encargo-profesional.md), también con la cita
 * enlazada (`?cita=`, EA-10 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md). Es el encargo
 * desde el que se dio la cita o, si la dio la ventanilla, el suyo sobre esa historia,
 * el que está en curso antes que uno cerrado.
 */
class EnlaceAtencionCita
{
    /**
     * URL de atención de la cita, o null si aún no tiene persona identificada.
     *
     * @param Cita $cita
     *
     * @return string|null
     */
    public function url(Cita $cita): ?string
    {
        if ($cita->ciudadano_id === null) {
            return null;
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $cita->ciudadano_id)->first();

        $encargo = $historia !== null ? $this->encargoDe($cita, $historia) : null;
        if ($encargo !== null) {
            // Con la cita, para que la actuación que se registre la complete (EA-10)
            return route('intervencion.encargos.show', ['encargo' => $encargo->id, 'cita' => $cita->id]);
        }

        $herramienta = $cita->tipoCita->herramienta;

        if ($historia === null || $herramienta === HerramientaCita::Atencion) {
            return route('ciudadania.ciudadano.ficha', ['ciudadano' => $cita->ciudadano_id, 'cita' => $cita->id]);
        }

        $parametros = match ($herramienta) {
            HerramientaCita::EntrevistaInicial => ['herramienta' => 'entrevista', 'tipo' => 'inicial'],
            HerramientaCita::EntrevistaSeguimiento => ['herramienta' => 'entrevista', 'tipo' => 'seguimiento'],
            HerramientaCita::Valoracion => ['herramienta' => 'valoracion'],
            // Plan o ninguna: la ficha con la cita enlazada; cualquier herramienta propone vincularla
            default => [],
        };

        return route('intervencion.ciudadano.show', ['historia' => $historia->id, 'cita' => $cita->id] + $parametros);
    }

    /**
     * Encargo al que lleva la cita si su profesional no abre la Historia Social; null si la abre.
     *
     * @param Cita $cita
     * @param HistoriaSocial $historia Historia Social de la persona de la cita.
     *
     * @return Derivacion|null
     */
    private function encargoDe(Cita $cita, HistoriaSocial $historia): ?Derivacion
    {
        $profesional = User::find($cita->profesional_id);
        if ($profesional === null || Gate::forUser($profesional)->allows('view', $historia)) {
            return null;
        }

        $encargos = Derivacion::query()->deProfesional($profesional)->where('historia_id', $historia->id);

        // El encargo desde el que se dio la cita (contexto de la solicitud)
        $contexto = $cita->solicitud;
        if ($contexto !== null && $contexto->contexto_type === (new Derivacion)->getMorphClass()) {
            $desde = (clone $encargos)->find($contexto->contexto_id);
            if ($desde !== null) {
                return $desde;
            }
        }

        return $encargos
            ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [EstadoDerivacion::Aceptada->value])
            ->latest('id')
            ->first();
    }
}
