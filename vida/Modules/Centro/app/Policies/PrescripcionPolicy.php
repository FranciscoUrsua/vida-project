<?php

namespace Modules\Centro\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;

/**
 * Quién actúa sobre una prescripción de recurso desde la Historia Social (usabilidad U2.2).
 *
 * Cancelar una prescripción es una actuación sobre la persona: exige poder ver su Historia
 * Social (ámbito y personas protegidas, RN-17) y poder actuar en el centro de la operación,
 * que es el centro activo del usuario (RN-15, R1). Sin centro (modo lectura) no se cancela.
 */
class PrescripcionPolicy
{
    /**
     * @param CentroDeUsuario $centros
     */
    public function __construct(private readonly CentroDeUsuario $centros) {}

    /**
     * Si el usuario puede cancelar la prescripción: está en curso, ve la Historia Social de la
     * persona y puede actuar en su centro activo.
     *
     * @param User $usuario
     * @param Prescripcion $prescripcion
     *
     * @return bool
     */
    public function cancel(User $usuario, Prescripcion $prescripcion): bool
    {
        if (! in_array($prescripcion->estado, ['pendiente', 'en_lista_espera', 'asignada', 'activa'], true)) {
            return false;
        }

        if (! $this->centros->puedeActuar($usuario)) {
            return false;
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $prescripcion->ciudadano_id)->first();

        return $historia !== null && Gate::forUser($usuario)->allows('view', $historia);
    }
}
