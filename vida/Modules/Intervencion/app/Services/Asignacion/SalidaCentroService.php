<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;
use Modules\Usuarios\Models\Profesional;

/**
 * Salida de un profesional de un centro y desactivación de usuarios y
 * profesionales (addenda D5, RN-20 a RN-23 de docs/modulo-tipo-centro.md).
 *
 * Restricción de dominio: los casos son del centro, no del profesional (RN-23).
 * Nadie sale de un centro mientras sea referencia de casos en él o tenga citas
 * confirmadas futuras en sus slots (RN-21), y nadie se desactiva con eso
 * pendiente en alguno de sus centros (RN-22). Esta clase nunca reparte casos ni
 * reasigna citas: lo hace el supervisor con el reparto por salida y la gestión
 * de ausencias. El cierre de adscripciones se comprueba aquí, y el modelo
 * UsuarioUo lo exige en cualquier camino que cambie su fecha de fin.
 */
class SalidaCentroService
{
    /**
     * @param ActividadCasosService $actividad Casos vigentes de un profesional en un centro.
     */
    public function __construct(private readonly ActividadCasosService $actividad) {}

    /**
     * Casos y citas que impiden al profesional salir del centro.
     *
     * @param User $profesional
     * @param Centro $centro
     *
     * @return PendientesSalida
     */
    public function pendientes(User $profesional, Centro $centro): PendientesSalida
    {
        return new PendientesSalida(
            $centro,
            count($this->actividad->historiasDe($centro, $profesional)),
            Cita::confirmadas()
                ->where('profesional_id', $profesional->id)
                ->where('centro_id', $centro->id)
                ->whereDate('fecha', '>=', today())
                ->count(),
        );
    }

    /**
     * Exige RN-21 para cerrar una adscripción: si su UO es la de un centro, el
     * profesional no puede tener casos ni citas pendientes en él. Lo llama el
     * modelo UsuarioUo al cambiar la fecha de fin.
     *
     * @param UsuarioUo $adscripcion
     *
     * @return void
     *
     * @throws SalidaCentroBloqueada Si queda algo pendiente.
     */
    public function exigirSalidaLibre(UsuarioUo $adscripcion): void
    {
        $usuario = User::withTrashed()->find($adscripcion->usuario_id);

        if ($usuario === null) {
            return;
        }

        $pendientes = $this->centrosDeLaUo($adscripcion->unidad_organizativa_id)
            ->map(fn (Centro $centro) => $this->pendientes($usuario, $centro))
            ->reject(fn (PendientesSalida $p) => $p->libre())
            ->values()
            ->all();

        if ($pendientes !== []) {
            throw new SalidaCentroBloqueada($pendientes, 'cerrar la adscripción al centro');
        }
    }

    /**
     * Cierra una adscripción (RN-21) y los perfiles horarios del profesional en
     * el centro de esa UO, para que deje de entrar en el sorteo y el reparto.
     * No toca sus casos (RN-23).
     *
     * @param UsuarioUo $adscripcion
     * @param Carbon|null $fecha Fecha de fin; hoy por defecto.
     *
     * @return UsuarioUo
     *
     * @throws SalidaCentroBloqueada Si queda algo pendiente en el centro.
     */
    public function cerrarAdscripcion(UsuarioUo $adscripcion, ?Carbon $fecha = null): UsuarioUo
    {
        $fecha ??= today();

        return DB::transaction(function () use ($adscripcion, $fecha) {
            // La comprobación de RN-21 la hace el modelo al guardar la fecha de fin
            $adscripcion->update(['fecha_fin' => $fecha->toDateString()]);

            return $adscripcion;
        });
    }

    /**
     * Cierra los perfiles horarios del usuario en los centros de la UO de una
     * adscripción que se cierra. Lo llama el modelo UsuarioUo tras guardarla.
     *
     * @param UsuarioUo $adscripcion
     *
     * @return void
     */
    public function cerrarPerfilesHorarios(UsuarioUo $adscripcion): void
    {
        $fecha = $adscripcion->fecha_fin;

        if ($fecha === null) {
            return;
        }

        PerfilHorarioProfesional::query()
            ->where('usuario_id', $adscripcion->usuario_id)
            ->whereIn('centro_id', $this->centrosDeLaUo($adscripcion->unidad_organizativa_id)->pluck('id'))
            ->where('activo', true)
            ->get()
            ->each(fn (PerfilHorarioProfesional $perfil) => $perfil->update([
                'activo' => false,
                'vigente_hasta' => $perfil->vigente_hasta !== null && $perfil->vigente_hasta->lt($fecha)
                    ? $perfil->vigente_hasta
                    : $fecha,
            ]));
    }

    /**
     * Desactiva un usuario (RN-20 y RN-22): exige RN-21 en todos sus centros,
     * cierra sus adscripciones vigentes y le quita el acceso. Sus registros siguen
     * apuntando a él.
     *
     * @param User $usuario
     * @param User $actor
     *
     * @return User
     *
     * @throws AuthorizationException Si el actor no puede desactivarlo.
     * @throws SalidaCentroBloqueada Si queda algo pendiente en alguno de sus centros.
     */
    public function desactivarUsuario(User $usuario, User $actor): User
    {
        if (! $this->puedeDesactivar($actor, $usuario)) {
            throw new AuthorizationException('No puedes desactivar a este usuario.');
        }

        if ($usuario->estaDesactivado()) {
            return $usuario;
        }

        $adscripciones = $usuario->adscripcionesVigentes()->get();
        $pendientes = $this->pendientesDeUsuario($usuario);

        if ($pendientes !== []) {
            throw new SalidaCentroBloqueada($pendientes, 'desactivar al usuario');
        }

        return DB::transaction(function () use ($usuario, $actor, $adscripciones) {
            $adscripciones->each(fn (UsuarioUo $a) => $this->cerrarAdscripcion($a));

            $usuario->forceFill([
                'desactivado_en' => now(),
                'desactivado_por_id' => $actor->id,
            ])->save();

            return $usuario;
        });
    }

    /**
     * Centros del usuario (los de sus adscripciones vigentes) con casos o citas
     * pendientes: lo que impide desactivarlo (RN-22). Vacío si puede desactivarse.
     *
     * @param User $usuario
     *
     * @return list<PendientesSalida>
     */
    public function pendientesDeUsuario(User $usuario): array
    {
        return $usuario->adscripcionesVigentes()->get()
            ->flatMap(fn (UsuarioUo $a) => $this->centrosDeLaUo($a->unidad_organizativa_id))
            ->unique('id')
            ->map(fn (Centro $centro) => $this->pendientes($usuario, $centro))
            ->reject(fn (PendientesSalida $p) => $p->libre())
            ->values()
            ->all();
    }

    /**
     * Desactiva un profesional (RN-20): si tiene cuenta, la desactiva antes con
     * RN-22; después lo marca inactivo con fecha de fin.
     *
     * @param Profesional $profesional
     * @param User $actor
     *
     * @return Profesional
     *
     * @throws AuthorizationException Si el actor no puede desactivarlo.
     * @throws SalidaCentroBloqueada Si su cuenta tiene casos o citas pendientes.
     */
    public function desactivarProfesional(Profesional $profesional, User $actor): Profesional
    {
        $usuario = $profesional->usuario;

        if ($usuario === null && ! $this->puedeDesactivarProfesionalSinCuenta($actor, $profesional)) {
            throw new AuthorizationException('No puedes desactivar a este profesional.');
        }

        return DB::transaction(function () use ($profesional, $usuario, $actor) {
            if ($usuario !== null) {
                $this->desactivarUsuario($usuario, $actor);
            }

            $profesional->update([
                'activo' => false,
                'fecha_fin' => $profesional->fecha_fin ?? today(),
            ]);

            return $profesional;
        });
    }

    /**
     * Si el actor puede desactivar al usuario: nunca a sí mismo; administración
     * del sistema, a cualquiera; administración de usuarios y supervisión, a
     * quien tenga una adscripción vigente en su ámbito.
     *
     * @param User $actor
     * @param User $usuario
     *
     * @return bool
     */
    public function puedeDesactivar(User $actor, User $usuario): bool
    {
        if ($actor->is($usuario)) {
            return false;
        }

        if ($actor->hasRole('adm_sistema')) {
            return true;
        }

        if (! $actor->hasAnyRole(['adm_usuarios', 'supervision'])) {
            return false;
        }

        return $usuario->adscripcionesVigentes()->whereIn('unidad_organizativa_id', $actor->uoSubtreeIds())->exists();
    }

    /**
     * Si el actor puede desactivar a un profesional sin cuenta: por la UO de
     * adscripción directa del profesional.
     *
     * @param User $actor
     * @param Profesional $profesional
     *
     * @return bool
     */
    private function puedeDesactivarProfesionalSinCuenta(User $actor, Profesional $profesional): bool
    {
        if ($actor->hasRole('adm_sistema')) {
            return true;
        }

        return $actor->hasAnyRole(['adm_usuarios', 'supervision'])
            && $profesional->unidad_organizativa_id !== null
            && in_array($profesional->unidad_organizativa_id, $actor->uoSubtreeIds(), true);
    }

    /**
     * Centros de una UO (uno, por D1; ninguno si la UO no es de centro).
     *
     * @param int $uoId
     *
     * @return Collection<int, Centro>
     */
    private function centrosDeLaUo(int $uoId): Collection
    {
        return Centro::where('unidad_organizativa_id', $uoId)->get();
    }
}
