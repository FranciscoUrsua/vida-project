<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\HistoriaSocial;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Models\Cita;

/**
 * Adónde lleva «Atender» una cita desde la agenda del profesional
 * (docs/modulo-citas.md §3.5.1): la ficha de la persona con la herramienta del
 * tipo de cita ya abierta y la cita enlazada (`?cita=`), para que el apunte que
 * se registre la complete. Sin Historia Social, o si el tipo de cita es de
 * atención, la ficha de Ciudadanía, donde el registro de atención la cierra.
 */
class EnlaceAtencionCita
{
    /**
     * URL de atención de la cita, o null si aún no tiene persona identificada.
     *
     * @param Cita $cita
     * @return string|null
     */
    public function url(Cita $cita): ?string
    {
        if ($cita->ciudadano_id === null) {
            return null;
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $cita->ciudadano_id)->first();
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
}
