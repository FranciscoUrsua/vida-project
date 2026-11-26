<?php

namespace Modules\Documentos\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Services\AmbitoUsuario;
use Illuminate\Support\Facades\Gate;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Models\Informe;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\ActuacionesEncargo;

/**
 * Autorización de los informes profesionales (fase 5, paso 5).
 *
 * Un informe es contenido de la Historia Social:
 * - **ver**: quien puede leer la historia de la persona (`HistoriaSocialPolicy::view`),
 *   que ya incluye al equipo de un plan en curso y la regla de personas protegidas
 *   (RN-17 ampliada: solo quien la gestiona o tiene un acceso aprobado). Por eso
 *   `consulta_basica` y `tramitacion`, sin `historia.leer`, no ven informes.
 *   Vía adicional **encargo** (EA-7 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md):
 *   el profesional de encargo, que no lee la Historia, ve solo los informes que redactó él
 *   desde un encargo suyo (`ActuacionesEncargo`); nunca otros informes de la persona.
 * - **crear** (F5-3): escritura en la historia (TSR y profesionales de su centro) o
 *   ser responsable o equipo de un plan activo de la persona.
 * - **editar, firmar y eliminar** el borrador: solo su autor.
 * - **anular** un firmado: solo su autor.
 *
 * Todo se vuelve a comprobar en el servidor (componentes y servicios).
 */
class InformePolicy
{
    /**
     * Decide si el usuario puede ver el informe (y su PDF firmado).
     *
     * @param User $usuario Usuario autenticado.
     * @param Informe $informe Informe.
     *
     * @return bool
     */
    public function view(User $usuario, Informe $informe): bool
    {
        // Vía encargo: lo suyo dentro de sus encargos
        if (app(ActuacionesEncargo::class)->esVisible($usuario, $informe)) {
            return true;
        }

        $historia = $this->historiaDe($informe);

        return $historia !== null && Gate::forUser($usuario)->allows('view', $historia);
    }

    /**
     * Decide si el usuario puede ver los informes de una historia (el historial).
     *
     * @param User $usuario Usuario autenticado.
     * @param HistoriaSocial $historia Historia Social de la persona.
     *
     * @return bool
     */
    public function verDeHistoria(User $usuario, HistoriaSocial $historia): bool
    {
        return Gate::forUser($usuario)->allows('view', $historia);
    }

    /**
     * Decide si el usuario puede redactar un informe de la persona de esta historia (F5-3).
     *
     * @param User $usuario Usuario autenticado.
     * @param HistoriaSocial $historia Historia Social de la persona.
     *
     * @return bool
     */
    public function crear(User $usuario, HistoriaSocial $historia): bool
    {
        if (Gate::forUser($usuario)->allows('update', $historia)) {
            return true;
        }

        return $usuario->can('plan.editar') && $this->equipoDePlanActivo($usuario, $historia);
    }

    /**
     * Decide si el usuario puede seguir redactando el borrador (y verlo en el asistente).
     *
     * @param User $usuario Usuario autenticado.
     * @param Informe $informe Informe.
     *
     * @return bool
     */
    public function update(User $usuario, Informe $informe): bool
    {
        if ($informe->estado !== EstadoInforme::Borrador || $informe->autor_id !== $usuario->id || ! $this->view($usuario, $informe)) {
            return false;
        }

        // Desde un encargo, solo mientras pueda actuar en él (EA-4); cerrado, solo lectura (EA-11)
        $encargo = $informe->derivacion_id !== null ? $informe->derivacion : null;

        return $encargo === null || app(ActuacionesEncargo::class)->puedeActuar($usuario, $encargo);
    }

    /**
     * Decide si el usuario puede firmar el informe: el autor, sobre su borrador.
     *
     * @param User $usuario Usuario autenticado.
     * @param Informe $informe Informe.
     *
     * @return bool
     */
    public function firmar(User $usuario, Informe $informe): bool
    {
        return $this->update($usuario, $informe);
    }

    /**
     * Decide si el usuario puede eliminar el borrador (no es un documento custodiado).
     *
     * @param User $usuario Usuario autenticado.
     * @param Informe $informe Informe.
     *
     * @return bool
     */
    public function delete(User $usuario, Informe $informe): bool
    {
        return $informe->estado === EstadoInforme::Borrador && $informe->autor_id === $usuario->id;
    }

    /**
     * Decide si el usuario puede anular el informe: el autor, sobre su firmado.
     *
     * @param User $usuario Usuario autenticado.
     * @param Informe $informe Informe.
     *
     * @return bool
     */
    public function anular(User $usuario, Informe $informe): bool
    {
        return $informe->estado === EstadoInforme::Firmado && $informe->autor_id === $usuario->id;
    }

    /**
     * Indica si el usuario es responsable o equipo de un plan activo de la historia.
     *
     * Equipo = adscrito a la UO del centro del plan (como `AmbitoUsuario::leePorPlan`),
     * contando solo planes activos. Para personas protegidas la adscripción es la misma
     * que exige RN-17 ampliada (`uoIdsProtegidos`).
     *
     * @param User $usuario Usuario.
     * @param HistoriaSocial $historia Historia.
     *
     * @return bool
     */
    private function equipoDePlanActivo(User $usuario, HistoriaSocial $historia): bool
    {
        $planes = PlanDeIntervencion::withoutGlobalScopes()
            ->with('centro')
            ->where('historia_id', $historia->id)
            ->where('estado', EstadoPlan::Activo->value)
            ->get();

        $uoIds = app(AmbitoUsuario::class)->uoIdsProtegidos($usuario);

        return $planes->contains(fn (PlanDeIntervencion $plan): bool => $plan->profesional_responsable_id === $usuario->id
            || in_array($plan->centro?->unidad_organizativa_id, $uoIds, true));
    }

    /**
     * Historia del informe; si no la tiene, la última de la persona.
     *
     * @param Informe $informe Informe.
     *
     * @return HistoriaSocial|null
     */
    private function historiaDe(Informe $informe): ?HistoriaSocial
    {
        return $informe->historia_social_id !== null
            ? HistoriaSocial::withoutGlobalScopes()->find($informe->historia_social_id)
            : HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $informe->ciudadano_id)->latest()->first();
    }
}
