<?php

namespace App\Services;

use App\Enums\AccionAuditEnum;
use App\Models\AccesoProtegido;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Punto único de lectura intencional de una persona: ficha, expediente, plan y valoraciones.
 *
 * Carga el registro sin AmbitoUoScope (para responder 403 y no 404), lo autoriza
 * con la policy y deja la fila de auditoría de la lectura, gane o pierda la
 * policy (docs/instrucciones-cli/instrucciones-cli-acceso-auditoria.md):
 *
 *   - Persona de colectivo protegido → `acceso_restringido`, autorizado o no,
 *     con el `acceso_protegido_id` si hay aprobación vigente.
 *   - Resto → `ver`. Un 403 ordinario (sin permiso, o fuera de ámbito) también
 *     se anota como `ver` con `autorizado = false`: no se inventa otra acción.
 *
 * Una apertura, una fila: lo ya anotado en la petición actual no se repite, y
 * los computed que solo recargan el registro pasan `registrar: false`, de modo
 * que las acciones Livewire posteriores autorizan sin volver a anotar la lectura.
 * Las denegaciones se anotan siempre.
 *
 * Ninguna página debe cargar Ciudadano o HistoriaSocial sin scope por su cuenta.
 */
class AccesoExpediente
{
    /** Atributo de la petición con las lecturas ya anotadas en ella. */
    private const ANOTADOS = 'acceso_expediente.anotados';

    /**
     * Inyecta el servicio de auditoría, único que escribe en `audits`.
     *
     * @param AuditService $auditoria
     */
    public function __construct(private readonly AuditService $auditoria) {}

    /**
     * Ciudadano autorizado para el usuario, con la lectura auditada.
     *
     * @param User $user Quien lee.
     * @param int $ciudadanoId Persona.
     * @param bool $registrar false en las recargas que no son una apertura.
     * @return Ciudadano
     *
     * @throws AuthorizationException Si la policy deniega la lectura (tras anotarla).
     */
    public function ciudadano(User $user, int $ciudadanoId, bool $registrar = true): Ciudadano
    {
        $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadanoId);

        $this->autorizar($user, $ciudadano, $ciudadano, $registrar);

        return $ciudadano;
    }

    /**
     * Historia social autorizada para el usuario, con la lectura auditada.
     *
     * @param User $user Quien lee.
     * @param int $historiaId Historia social.
     * @param bool $registrar false en las recargas que no son una apertura.
     * @return HistoriaSocial
     *
     * @throws AuthorizationException Si la policy deniega la lectura (tras anotarla).
     */
    public function historia(User $user, int $historiaId, bool $registrar = true): HistoriaSocial
    {
        $historia = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($historiaId);
        $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->find($historia->ciudadano_id);

        $this->autorizar($user, $historia, $ciudadano, $registrar);

        return $historia;
    }

    /**
     * Historia social de una persona que el usuario puede ver, o null si no tiene.
     *
     * Para la ficha del ciudadano, que solo necesita saber si la historia existe
     * y a qué UO pertenece: autoriza a la persona (sin anotar otra lectura) y no
     * exige `historia.leer`, que tramitación no tiene.
     *
     * @param User $user Quien lee.
     * @param int $ciudadanoId Persona.
     * @return HistoriaSocial|null
     *
     * @throws AuthorizationException Si la policy deniega la lectura de la persona.
     */
    public function historiaDe(User $user, int $ciudadanoId): ?HistoriaSocial
    {
        $this->ciudadano($user, $ciudadanoId, registrar: false);

        return HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)
            ->where('ciudadano_id', $ciudadanoId)
            ->first();
    }

    /**
     * Deniega la lectura de un ciudadano por una regla de la pantalla (p. ej. rol
     * no operativo), dejando la misma fila que una denegación de la policy.
     *
     * @param User $user Quien lee.
     * @param int $ciudadanoId Persona.
     * @return never
     *
     * @throws AuthorizationException Siempre.
     */
    public function denegarCiudadano(User $user, int $ciudadanoId): never
    {
        $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadanoId);

        $this->anotar($user, $ciudadano, $ciudadano, false);

        throw new AuthorizationException;
    }

    /**
     * Aplica la policy y anota el resultado.
     *
     * @param User $user
     * @param Model $modelo Registro que se abre (ciudadano o historia).
     * @param Ciudadano|null $ciudadano Persona a la que pertenece.
     * @param bool $registrar
     * @return void
     *
     * @throws AuthorizationException
     */
    private function autorizar(User $user, Model $modelo, ?Ciudadano $ciudadano, bool $registrar): void
    {
        try {
            Gate::forUser($user)->authorize('view', $modelo);
        } catch (AuthorizationException $e) {
            $this->anotar($user, $modelo, $ciudadano, false);

            throw $e;
        }

        if ($registrar) {
            $this->anotar($user, $modelo, $ciudadano, true);
        }
    }

    /**
     * Deja la fila de auditoría de la lectura, una sola vez por registro y petición.
     *
     * @param User $user
     * @param Model $modelo
     * @param Ciudadano|null $ciudadano
     * @param bool $autorizado
     * @return void
     */
    private function anotar(User $user, Model $modelo, ?Ciudadano $ciudadano, bool $autorizado): void
    {
        $clave = $user->id.'|'.$modelo::class.'|'.$modelo->getKey().'|'.($autorizado ? 1 : 0);
        $anotados = request()->attributes->get(self::ANOTADOS, []);

        if (isset($anotados[$clave])) {
            return;
        }

        request()->attributes->set(self::ANOTADOS, $anotados + [$clave => true]);

        $protegido = (bool) $ciudadano?->colectivo_extra_protegido;
        $contexto = ['autorizado' => $autorizado];

        if (! $autorizado) {
            $contexto['motivo'] = 'denegado';
        }

        if ($protegido && $ciudadano !== null) {
            $accesoId = AccesoProtegido::vigentePara($user->id, $ciudadano->id)->latest('id')->value('id');
            if ($accesoId !== null) {
                $contexto['acceso_protegido_id'] = $accesoId;
            }
        }

        $this->auditoria->registrarAcceso(
            user: $user,
            modelo: $modelo,
            accion: $protegido ? AccionAuditEnum::AccesoRestringido : AccionAuditEnum::Ver,
            ciudadanoId: $ciudadano?->id,
            contexto: $contexto,
        );
    }
}
