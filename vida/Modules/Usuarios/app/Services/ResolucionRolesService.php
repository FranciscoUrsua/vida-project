<?php

namespace Modules\Usuarios\Services;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Único punto donde se aprueban o deniegan las solicitudes de rol con aprobación previa
 * (docs/modulo-usuarios-permisos.md §2.8).
 *
 * Resuelve la supervisión con la UO del usuario en su ámbito. Si no hay ningún supervisor
 * competente (el primero de una rama no podría aprobarse nunca), la resuelve `adm_sistema`
 * (decisión del desarrollador del 17 nov 2026). Nadie resuelve su propia solicitud y
 * `adm_sistema` tampoco una que haya asignado él: eso anularía la aprobación previa.
 */
class ResolucionRolesService
{
    /**
     * Arranca con el servicio de alertas, para avisar al usuario del resultado.
     *
     * @param AlertaService $alertas
     */
    public function __construct(private readonly AlertaService $alertas) {}

    /**
     * Supervisores que tienen alguna UO de adscripción del usuario en su ámbito, sin
     * contar al propio usuario.
     *
     * @param UsuarioRol $solicitud
     *
     * @return Collection<int, User>
     */
    public function supervisoresCompetentes(UsuarioRol $solicitud): Collection
    {
        $uos = $this->uosDelUsuario($solicitud);

        if ($uos === []) {
            return collect();
        }

        return User::role('supervision')
            ->whereKeyNot($solicitud->usuario_id)
            ->get()
            ->filter(fn (User $supervisor) => array_intersect($supervisor->uoSubtreeIds(), $uos) !== [])
            ->values();
    }

    /**
     * Si el usuario puede aprobar o denegar la solicitud.
     *
     * @param User $actor
     * @param UsuarioRol $solicitud
     *
     * @return bool
     */
    public function puedeResolver(User $actor, UsuarioRol $solicitud): bool
    {
        if ($solicitud->estado !== 'pendiente_aprobacion' || $solicitud->usuario_id === $actor->id) {
            return false;
        }

        if ($actor->hasRole('supervision') && array_intersect($actor->uoSubtreeIds(), $this->uosDelUsuario($solicitud)) !== []) {
            return true;
        }

        return $actor->hasRole('adm_sistema')
            && $solicitud->asignado_por !== $actor->id
            && $this->supervisoresCompetentes($solicitud)->isEmpty();
    }

    /**
     * Solicitudes pendientes que resuelve `adm_sistema` porque nadie más puede.
     *
     * @param User $actor
     *
     * @return Collection<int, UsuarioRol>
     */
    public function resolublesPorAdmSistema(User $actor): Collection
    {
        if (! $actor->hasRole('adm_sistema')) {
            return collect();
        }

        return UsuarioRol::pendientes()->with(['usuario', 'rol'])->orderBy('created_at')->get()
            ->filter(fn (UsuarioRol $solicitud) => $this->puedeResolver($actor, $solicitud))
            ->values();
    }

    /**
     * Aprueba la solicitud: el rol pasa a efectivo (el observer sincroniza Spatie) y se
     * avisa al usuario.
     *
     * @param UsuarioRol $solicitud
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    public function aprobar(UsuarioRol $solicitud, User $actor): void
    {
        $this->exigir($actor, $solicitud);

        DB::transaction(function () use ($solicitud): void {
            $solicitud->update(['estado' => 'activo']);
            $this->avisar($solicitud, 'Tu solicitud de rol «'.$solicitud->rol?->name.'» ha sido aprobada.');
        });
    }

    /**
     * Deniega la solicitud con motivo: el usuario no obtiene el rol y se le avisa.
     *
     * @param UsuarioRol $solicitud
     * @param string $motivo Obligatorio.
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function denegar(UsuarioRol $solicitud, string $motivo, User $actor): void
    {
        $this->exigir($actor, $solicitud);

        if (trim($motivo) === '') {
            throw new DomainException('El motivo es obligatorio para denegar una solicitud.');
        }

        DB::transaction(function () use ($solicitud, $motivo): void {
            $solicitud->update(['estado' => 'denegado']);

            // Revocar el rol Spatie para que el acceso quede efectivamente bloqueado
            if ($solicitud->rol !== null) {
                $solicitud->usuario?->removeRole($solicitud->rol->name);
            }

            $this->avisar($solicitud, 'Tu solicitud de rol «'.$solicitud->rol?->name.'» ha sido denegada. Motivo: '.trim($motivo));
        });
    }

    /**
     * Rechaza a quien no puede resolver la solicitud, con el motivo concreto.
     *
     * @param User $actor
     * @param UsuarioRol $solicitud
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    private function exigir(User $actor, UsuarioRol $solicitud): void
    {
        if ($this->puedeResolver($actor, $solicitud)) {
            return;
        }

        throw new AuthorizationException(match (true) {
            $solicitud->usuario_id === $actor->id => 'No puedes resolver tus propias solicitudes de rol.',
            $solicitud->estado !== 'pendiente_aprobacion' => 'La solicitud ya está resuelta.',
            $actor->hasRole('adm_sistema') && $solicitud->asignado_por === $actor->id => 'No puedes aprobar una asignación que has hecho tú.',
            $actor->hasRole('adm_sistema') => 'Hay supervisores competentes para esta solicitud: la resuelven ellos.',
            default => 'La solicitud está fuera del ámbito de supervisión.',
        });
    }

    /**
     * UO de adscripción vigente del usuario de la solicitud.
     *
     * @param UsuarioRol $solicitud
     *
     * @return list<int>
     */
    private function uosDelUsuario(UsuarioRol $solicitud): array
    {
        return $solicitud->usuario?->adscripcionesVigentes()->pluck('unidad_organizativa_id')->map(fn ($id) => (int) $id)->all() ?? [];
    }

    /**
     * Aviso al usuario con el resultado de su solicitud.
     *
     * @param UsuarioRol $solicitud
     * @param string $titulo
     *
     * @return void
     */
    private function avisar(UsuarioRol $solicitud, string $titulo): void
    {
        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => UsuarioRol::class,
            'origen_id' => $solicitud->id,
            'titulo' => $titulo,
            'cuerpo' => $titulo,
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $solicitud->usuario_id,
        ]);
    }
}
