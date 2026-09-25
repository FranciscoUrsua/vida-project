<?php

namespace Modules\Usuarios\Services;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Usuarios\Models\ConfiguracionRol;
use Modules\Usuarios\Models\UsuarioRol;
use Spatie\Permission\Models\Role;

/**
 * Asigna y retira roles a un usuario a través de usuario_rol, aplicando la supervisión de 2.8.
 *
 * Es el único camino por el que el backoffice cambia los roles de un usuario:
 * - Aprobación previa: se crea la solicitud en estado pendiente_aprobacion y el
 *   rol no es efectivo hasta que un supervisor la aprueba.
 * - Alerta supervisada: el rol es efectivo al momento y se genera una alerta
 *   para los supervisores de la UO del usuario.
 * El UsuarioRolObserver mantiene model_has_roles de Spatie sincronizado.
 *
 * @see docs/modulo-usuarios-permisos.md secciones 2.8 y 4.3
 */
class AsignacionRolesService
{
    /**
     * Inyecta el servicio de alertas.
     *
     * @param AlertaService $alertas Servicio de ciclo de vida de alertas.
     */
    public function __construct(private readonly AlertaService $alertas) {}

    /**
     * Ids de los roles que el usuario tiene efectivos o solicitados (pendientes de aprobación).
     *
     * Es el estado que muestra el selector de roles al editar un usuario.
     *
     * @param User $usuario Usuario consultado.
     *
     * @return list<int>
     */
    public function rolesSeleccionados(User $usuario): array
    {
        $efectivos = $usuario->roles()->pluck('id')->all();
        $pendientes = $usuario->rolesPendientes()->pluck('rol_id')->all();

        return array_values(array_unique(array_map('intval', [...$efectivos, ...$pendientes])));
    }

    /**
     * Deja al usuario con exactamente los roles indicados, asignando y retirando por el flujo supervisado.
     *
     * @param User $usuario Usuario destinatario.
     * @param list<int> $rolIds Roles que deben quedar efectivos o solicitados.
     * @param User|null $asignadoPor Usuario que realiza el cambio.
     *
     * @return bool true si ha habido algún cambio.
     */
    public function sincronizar(User $usuario, array $rolIds, ?User $asignadoPor): bool
    {
        $deseados = array_values(array_unique(array_map('intval', $rolIds)));
        $actuales = $this->rolesSeleccionados($usuario);

        $aAsignar = array_diff($deseados, $actuales);
        $aRetirar = array_diff($actuales, $deseados);

        if ($aAsignar === [] && $aRetirar === []) {
            return false;
        }

        DB::transaction(function () use ($usuario, $aAsignar, $aRetirar, $asignadoPor): void {
            foreach (Role::whereIn('id', $aRetirar)->get() as $rol) {
                $this->retirar($usuario, $rol);
            }

            foreach (Role::whereIn('id', $aAsignar)->get() as $rol) {
                $this->asignar($usuario, $rol, $asignadoPor);
            }
        });

        return true;
    }

    /**
     * Registra la asignación de un rol con el nivel de supervisión que le corresponde.
     *
     * @param User $usuario Usuario destinatario.
     * @param Role $rol Rol asignado.
     * @param User|null $asignadoPor Usuario que realiza la asignación.
     */
    public function asignar(User $usuario, Role $rol, ?User $asignadoPor): UsuarioRol
    {
        $requiereAprobacion = ConfiguracionRol::nivelPara($rol) === ConfiguracionRol::APROBACION_PREVIA;

        $asignacion = UsuarioRol::create([
            'usuario_id' => $usuario->id,
            'rol_id' => $rol->id,
            'fecha_inicio' => today(),
            'fecha_fin' => null,
            'asignado_por' => $asignadoPor?->id,
            'estado' => $requiereAprobacion ? 'pendiente_aprobacion' : 'activo',
        ]);

        if (! $requiereAprobacion) {
            $this->alertarSupervision($usuario, $rol, $asignadoPor, $asignacion);
        }

        return $asignacion;
    }

    /**
     * Retira un rol: cierra sus asignaciones vigentes o pendientes y lo quita de Spatie.
     *
     * @param User $usuario Usuario afectado.
     * @param Role $rol Rol retirado.
     */
    public function retirar(User $usuario, Role $rol): void
    {
        $abiertas = $usuario->historialRoles()
            ->where('rol_id', $rol->id)
            ->whereIn('estado', ['activo', 'pendiente_aprobacion'])
            ->get();

        foreach ($abiertas as $asignacion) {
            // El Observer quita el rol de Spatie al pasar a inactivo
            $asignacion->update(['estado' => 'inactivo', 'fecha_fin' => today()]);
        }

        // Roles de Spatie sin historial (p. ej. consulta_basica que User::booted()
        // asigna al crear) no pasan por el Observer: se retiran directamente.
        if ($usuario->hasRole($rol)) {
            $usuario->removeRole($rol);
        }
    }

    /**
     * Genera la alerta supervisada para los supervisores de la UO del usuario.
     *
     * Sin adscripción vigente se dirige a la supervisión de la UO raíz. Si no
     * existe ninguna UO (instalación vacía), se registra en el log y el rol queda
     * igualmente trazado en usuario_rol.
     *
     * @param User $usuario Usuario destinatario del rol.
     * @param Role $rol Rol asignado.
     * @param User|null $asignadoPor Usuario que realizó la asignación.
     * @param UsuarioRol $asignacion Registro de historial creado.
     */
    private function alertarSupervision(User $usuario, Role $rol, ?User $asignadoPor, UsuarioRol $asignacion): void
    {
        $uoId = $usuario->adscripcionesVigentes()->orderBy('fecha_inicio')->value('unidad_organizativa_id')
            ?? UnidadOrganizativa::whereNull('parent_id')->orderBy('id')->value('id');

        // alertas exige UO para destinatarios rol_uo (chk_alertas_destinatario)
        if ($uoId === null) {
            Log::warning('Asignación de rol sin UO a la que alertar', [
                'usuario_rol_id' => $asignacion->id,
                'rol' => $rol->name,
            ]);

            return;
        }

        $autor = $asignadoPor->email ?? 'el sistema';

        $this->alertas->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => UsuarioRol::class,
            'origen_id' => $asignacion->id,
            'titulo' => "Rol «{$rol->name}» asignado a {$usuario->email}",
            'cuerpo' => "{$autor} ha asignado el rol «{$rol->name}» a {$usuario->email}. La asignación ya es efectiva; revísala y reconoce esta alerta.",
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => 'supervision',
            'destinatario_uo_id' => $uoId,
        ]);
    }
}
