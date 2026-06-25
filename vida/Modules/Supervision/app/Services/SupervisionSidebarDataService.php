<?php

namespace Modules\Supervision\Services;

use App\Models\AccesoProtegido;
use App\Models\User;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Servicio de datos para el sidebar del interfaz operativo de Supervisión.
 *
 * Proporciona los contadores para los badges del sidebar:
 * - Total de solicitudes de rol pendientes de aprobación en el ámbito del supervisor
 * - Total de accesos a expedientes protegidos pendientes de revisión (si aplica)
 */
class SupervisionSidebarDataService
{
    /**
     * Número total de solicitudes pendientes en el ámbito del supervisor.
     *
     * Suma asignaciones de rol con estado=pendiente_aprobacion cuyo usuario
     * está adscrito a una UO bajo el subárbol del supervisor, más accesos
     * a colectivos protegidos pendientes de ese mismo ámbito.
     *
     * @param int $usuarioId ID del usuario supervisor
     * @return int
     */
    public function aprobacionesPendientes(int $usuarioId): int
    {
        /** @var User|null $supervisor */
        $supervisor = User::find($usuarioId);

        if ($supervisor === null) {
            return 0;
        }

        $uoIds = $supervisor->uoSubtreeIds();

        if (empty($uoIds)) {
            return 0;
        }

        $totalRoles = UsuarioRol::where('estado', 'pendiente_aprobacion')
            ->whereHas('usuario', function ($q) use ($uoIds) {
                $q->whereHas('adscripcionesVigentes', function ($q2) use ($uoIds) {
                    $q2->whereIn('unidad_organizativa_id', $uoIds);
                });
            })
            ->count();

        $totalAccesosProtegidos = AccesoProtegido::where('estado', 'pendiente')
            ->count();

        return $totalRoles + $totalAccesosProtegidos;
    }
}
