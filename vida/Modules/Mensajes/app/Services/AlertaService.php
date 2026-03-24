<?php

namespace Modules\Mensajes\Services;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoReconocimiento;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\AlertaReconocimiento;

/**
 * Servicio de gestión del ciclo de vida de alertas del sistema.
 */
class AlertaService
{
    public function __construct(
        private readonly HorarioLaboralService $horarioLaboral
    ) {}

    /**
     * Crea una alerta y calcula su expiración si es de tipo 'alerta'.
     *
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): Alerta
    {
        $alerta = new Alerta($datos);
        $alerta->estado = EstadoAlerta::Pendiente;

        if ($alerta->tipo === TipoAlerta::Alerta) {
            $alerta->expira_en = $this->horarioLaboral->calcularExpiracion(now());
        }

        $alerta->save();

        // Si la alerta es para un rol+UO, crear registros de reconocimiento
        // para cada destinatario real en ese momento
        if ($alerta->destinatario_type === DestinatarioType::RolUo) {
            $destinatarios = $this->resolverDestinatarios($alerta);
            foreach ($destinatarios as $usuario) {
                // Los reconocimientos se crean bajo demanda cuando el usuario accede
                // La resolución aquí es solo para verificación
            }
        }

        return $alerta;
    }

    /**
     * Marca una alerta como reconocida por un usuario.
     */
    public function reconocer(Alerta $alerta, User $usuario, string $ipAddress): AlertaReconocimiento
    {
        $tipo = $alerta->tipo === TipoAlerta::Aviso
            ? TipoReconocimiento::Descartada
            : TipoReconocimiento::Reconocida;

        $reconocimiento = AlertaReconocimiento::create([
            'alerta_id'    => $alerta->id,
            'usuario_id'   => $usuario->id,
            'tipo'         => $tipo,
            'reconocida_en' => now(),
            'ip_address'   => $ipAddress,
        ]);

        $alerta->update(['estado' => EstadoAlerta::Reconocida]);

        return $reconocimiento;
    }

    /**
     * Ejecuta la escalada de una alerta vencida al supervisor de la UO.
     *
     * Si no existe supervisor activo en la UO, la alerta pasa directamente
     * a estado 'vencida'.
     */
    public function escalar(Alerta $alerta): void
    {
        $supervisor = $this->resolverSupervisor($alerta);

        if (! $supervisor) {
            Log::warning('Alerta vencida sin supervisor disponible en la UO', [
                'alerta_id'   => $alerta->id,
                'uo_id'       => $alerta->destinatario_uo_id ?? $this->resolverUoDelDestinatario($alerta)?->id,
            ]);

            $alerta->update(['estado' => EstadoAlerta::Vencida]);

            return;
        }

        $alerta->update([
            'estado'                => EstadoAlerta::Escalada,
            'escalada_en'           => now(),
            'escalada_a_usuario_id' => $supervisor->id,
        ]);

        AlertaReconocimiento::create([
            'alerta_id'    => $alerta->id,
            'usuario_id'   => $supervisor->id,
            'tipo'         => TipoReconocimiento::Escalada,
            'reconocida_en' => now(),
            'ip_address'   => null,
        ]);
    }

    /**
     * Resuelve qué usuarios son destinatarios reales de una alerta rol_uo.
     *
     * @return Collection<int, User>
     */
    public function resolverDestinatarios(Alerta $alerta): Collection
    {
        if ($alerta->destinatario_type !== DestinatarioType::RolUo) {
            return collect();
        }

        return User::whereHas('adscripciones', function ($query) use ($alerta) {
            $query->where('unidad_organizativa_id', $alerta->destinatario_uo_id)
                ->whereNull('fecha_fin')
                ->orWhere('fecha_fin', '>=', now()->toDateString());
        })
        ->role($alerta->destinatario_rol)
        ->get();
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Resuelve el supervisor activo de la UO del destinatario original.
     */
    private function resolverSupervisor(Alerta $alerta): ?User
    {
        $uo = $this->resolverUoDelDestinatario($alerta);

        if (! $uo) {
            return null;
        }

        return User::whereHas('adscripciones', function ($query) use ($uo) {
            $query->where('unidad_organizativa_id', $uo->id)
                ->where(function ($q) {
                    $q->whereNull('fecha_fin')
                        ->orWhere('fecha_fin', '>=', now()->toDateString());
                });
        })
        ->role('supervisor')
        ->first();
    }

    /**
     * Obtiene la UO del destinatario original de la alerta.
     */
    private function resolverUoDelDestinatario(Alerta $alerta): ?UnidadOrganizativa
    {
        if ($alerta->destinatario_type === DestinatarioType::RolUo) {
            return $alerta->destinatarioUo;
        }

        // Para alertas dirigidas a un usuario concreto, buscar su UO principal
        if (! $alerta->destinatario_usuario_id) {
            return null;
        }

        $adscripcion = UsuarioUo::where('usuario_id', $alerta->destinatario_usuario_id)
            ->whereNull('fecha_fin')
            ->first();

        return $adscripcion?->unidadOrganizativa;
    }
}
