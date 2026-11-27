<?php

namespace Modules\Intervencion\Policies;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Services\ActuacionesEncargo;

/**
 * Quién ve, registra y corrige una ficha.
 *
 * Fichas de valoración, dos vías:
 * - **historia**: quien puede leer la Historia Social de la ficha (`HistoriaSocialPolicy::view`);
 * - **encargo** (EA-7 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md): el profesional
 *   de encargo, que no lee la Historia, solo ve las fichas que registró él desde un encargo suyo
 *   (`ActuacionesEncargo`). Nunca las de otros profesionales de la Historia.
 *
 * Fichas de la persona (SD-10 de la ficha sociodemográfica): por permiso
 * (`ciudadano.leer_fichas_persona`, `ciudadano.registrar_fichas_persona`) y siempre dentro de lo
 * que el usuario puede ver de la persona (`CiudadanoPolicy::view`: ámbito, RN-17 y colectivo
 * protegido). Con una **persona protegida**, quien solo ve datos objetivos (consulta básica) no
 * ve ni registra estas fichas: hace falta ver la Historia Social (`historia.leer`) o, para leer,
 * aprobar accesos a protegidos (`colectivo_protegido.aprobar_acceso`, supervisión). Se decide
 * por permisos, no por rol.
 */
class FichaPolicy
{
    /** Leer las fichas de la persona. */
    public const PERMISO_LEER_PERSONA = 'ciudadano.leer_fichas_persona';

    /** Registrar y corregir las fichas de la persona. */
    public const PERMISO_REGISTRAR_PERSONA = 'ciudadano.registrar_fichas_persona';

    /**
     * Decide si el usuario puede ver la ficha.
     *
     * @param User $usuario
     * @param Ficha $ficha
     *
     * @return bool
     */
    public function view(User $usuario, Ficha $ficha): bool
    {
        // Ficha de la persona: solo por su propia vía, nunca por la de historia o encargo
        if ($ficha->esDePersona()) {
            $ciudadano = Ciudadano::withoutGlobalScopes()->find($ficha->ciudadano_id);

            return $ciudadano !== null && $this->verDePersona($usuario, $ciudadano);
        }

        // Vía encargo: lo suyo dentro de sus encargos
        if (app(ActuacionesEncargo::class)->esVisible($usuario, $ficha)) {
            return true;
        }

        // Vía historia: sin el scope de ámbito, para que decida la policy de la historia
        $historia = $ficha->historia_id !== null ? HistoriaSocial::withoutGlobalScopes()->find($ficha->historia_id) : null;

        return $historia !== null && Gate::forUser($usuario)->allows('view', $historia);
    }

    /**
     * Si el usuario puede ver las fichas de la persona de este ciudadano (la tarjeta de la ficha).
     *
     * @param User $usuario
     * @param Ciudadano $ciudadano
     *
     * @return bool
     */
    public function verDePersona(User $usuario, Ciudadano $ciudadano): bool
    {
        if (! $usuario->can(self::PERMISO_LEER_PERSONA) || ! Gate::forUser($usuario)->allows('view', $ciudadano)) {
            return false;
        }

        return ! $ciudadano->colectivo_extra_protegido
            || $usuario->can('historia.leer')
            || $usuario->can('colectivo_protegido.aprobar_acceso');
    }

    /**
     * Si el usuario puede registrar una ficha de la persona para este ciudadano.
     *
     * @param User $usuario
     * @param Ciudadano $ciudadano
     *
     * @return bool
     */
    public function crearDePersona(User $usuario, Ciudadano $ciudadano): bool
    {
        if (! $usuario->can(self::PERMISO_REGISTRAR_PERSONA) || ! Gate::forUser($usuario)->allows('view', $ciudadano)) {
            return false;
        }

        // Con una persona protegida, solo quien ve su Historia Social (SD-10)
        return ! $ciudadano->colectivo_extra_protegido || $usuario->can('historia.leer');
    }

    /**
     * Si el usuario puede corregir una ficha de la persona (completar un borrador o enmendarla).
     *
     * @param User $usuario
     * @param Ficha $ficha
     *
     * @return bool
     */
    public function corregirDePersona(User $usuario, Ficha $ficha): bool
    {
        if (! $ficha->esDePersona()) {
            return false;
        }

        $ciudadano = Ciudadano::withoutGlobalScopes()->find($ficha->ciudadano_id);

        return $ciudadano !== null && $this->crearDePersona($usuario, $ciudadano);
    }
}
