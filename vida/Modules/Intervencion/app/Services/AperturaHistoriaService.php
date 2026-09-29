<?php

namespace Modules\Intervencion\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Intervencion\Models\AsignacionProfesional;

/**
 * Apertura de la Historia Social de un ciudadano por un profesional.
 *
 * Abrir la historia es un acto profesional explícito (docs/modulo-ciudadania.md):
 * quien la abre queda como profesional de referencia, con su primera asignación
 * vigente, y el caso aparece en su «Mis casos» (docs/modulo-intervencion.md
 * §1.1.3). Lo usan el botón de la ficha y la confirmación del alta.
 */
class AperturaHistoriaService
{
    /**
     * Abre la historia del ciudadano en la UO activa del profesional y lo asigna
     * como profesional de referencia. Si el ciudadano ya tiene historia, la
     * devuelve sin cambiar nada (la historia social es única).
     *
     * @param int $ciudadanoId Ciudadano cuya historia se abre.
     * @param User $profesional Profesional que la abre y queda de referencia.
     * @return HistoriaSocial
     *
     * @throws AuthorizationException Si el profesional no puede crear historias.
     */
    public function abrir(int $ciudadanoId, User $profesional): HistoriaSocial
    {
        Gate::forUser($profesional)->authorize('create', HistoriaSocial::class);

        return DB::transaction(function () use ($ciudadanoId, $profesional) {
            // Sin scopes: la historia puede estar en otra UO y sigue siendo la única del ciudadano
            $existente = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $ciudadanoId)->first();
            if ($existente !== null) {
                return $existente;
            }

            $historia = HistoriaSocial::create([
                'ciudadano_id' => $ciudadanoId,
                'unidad_organizativa_id' => $profesional->uosActivas()->first()?->id,
                'estado' => 'abierta',
            ]);

            AsignacionProfesional::create([
                'historia_id' => $historia->id,
                'profesional_id' => $profesional->id,
                'fecha_inicio' => today()->toDateString(),
            ]);

            return $historia;
        });
    }
}
