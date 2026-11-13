<?php

namespace Modules\Centro\Services\Asignacion;

use App\Models\User;
use App\Services\AmbitoUsuario;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

/**
 * Centro de trabajo de un usuario y supervisión de centros (RN-16, D1.6 y D1.8
 * de docs/modulo-tipo-centro.md).
 *
 * Los centros de un usuario son los de sus adscripciones directas vigentes (no
 * los de su ámbito). Las operaciones toman el centro de su contexto; sin
 * contexto, el centro activo: el elegido en la sesión o, por defecto, el de su
 * perfil horario de hoy, o el único que tenga.
 */
class CentroDeUsuario
{
    /** Clave de sesión con el centro elegido. */
    public const CLAVE_SESION = 'centro_activo_id';

    /**
     * @param AmbitoUsuario $ambito
     */
    public function __construct(private readonly AmbitoUsuario $ambito) {}

    /**
     * Centros activos cuya UO es una adscripción vigente directa del usuario.
     *
     * @param User|null $usuario
     *
     * @return Collection<int, Centro>
     */
    public function centros(?User $usuario): Collection
    {
        if ($usuario === null) {
            return new Collection;
        }

        return Centro::whereIn('unidad_organizativa_id', $usuario->adscripcionesVigentes()->select('unidad_organizativa_id'))
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Centro en que trabaja el usuario cuando la operación no tiene centro de contexto.
     *
     * Orden: el elegido en la sesión si sigue entre sus centros; el de su perfil
     * horario de hoy; el único que tenga; si no, null.
     *
     * @param User|null $usuario
     *
     * @return Centro|null
     */
    public function centroActivo(?User $usuario): ?Centro
    {
        $centros = $this->centros($usuario);
        if ($centros->isEmpty()) {
            return null;
        }

        $elegido = Session::get(self::CLAVE_SESION);
        if ($elegido !== null && ($centro = $centros->firstWhere('id', (int) $elegido)) !== null) {
            return $centro;
        }

        // Perfil horario con franjas hoy (los keys del JSON son el día ISO como cadena)
        $hoy = (string) today()->isoWeekday();
        $deHoy = PerfilHorarioProfesional::activos()
            ->where('usuario_id', $usuario->id)
            ->whereIn('centro_id', $centros->pluck('id'))
            ->whereDate('vigente_desde', '<=', today()->toDateString())
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', today()->toDateString()))
            ->get()
            ->first(fn (PerfilHorarioProfesional $perfil) => ! empty($perfil->horario_habitual[$hoy] ?? null));
        if ($deHoy !== null) {
            return $centros->firstWhere('id', $deHoy->centro_id);
        }

        return $centros->count() === 1 ? $centros->first() : null;
    }

    /**
     * Guarda en la sesión el centro elegido por el usuario.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @throws DomainException Si no es uno de sus centros.
     *
     * @return void
     */
    public function elegir(User $usuario, Centro $centro): void
    {
        if (! $this->centros($usuario)->contains('id', $centro->id)) {
            throw new DomainException('Solo puedes trabajar en un centro al que estás adscrito.');
        }

        Session::put(self::CLAVE_SESION, $centro->id);
    }

    /**
     * Si el usuario supervisa el centro: rol `supervision` y el centro en su ámbito (D1.8).
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function supervisa(User $usuario, Centro $centro): bool
    {
        return $usuario->hasRole('supervision') && $this->ambito->incluyeCentro($usuario, $centro);
    }

    /**
     * Si el usuario está adscrito directamente a la UO del centro (RN-15).
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function adscritoA(User $usuario, Centro $centro): bool
    {
        return $this->centros($usuario)->contains('id', $centro->id);
    }

    /**
     * Rechaza una operación de atención individual si el usuario no está adscrito
     * al centro en que se hace (RN-15). Ocultar el botón no basta.
     *
     * @param User $usuario
     * @param Centro|null $centro Centro de la operación; null si no lo hay.
     *
     * @throws DomainException
     *
     * @return void
     */
    public function exigirAtencion(User $usuario, ?Centro $centro): void
    {
        if ($centro !== null && $this->adscritoA($usuario, $centro)) {
            return;
        }

        throw new DomainException($this->centros($usuario)->isEmpty()
            ? 'Para atender a ciudadanos tienes que estar adscrito a un centro.'
            : 'Para atender a ciudadanos tienes que estar adscrito a este centro.');
    }
}
