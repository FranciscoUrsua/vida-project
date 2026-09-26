<?php

namespace Modules\Mensajes\Services;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoReconocimiento;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\AlertaDestinatario;
use Modules\Mensajes\Models\AlertaReconocimiento;

/**
 * Ciclo de vida de alertas y avisos: creación con sus destinatarios,
 * reconocimiento individual de cada destinatario y escalada al supervisor.
 */
class AlertaService
{
    /**
     * Inyecta el servicio de horario laboral.
     *
     * @param HorarioLaboralService $horarioLaboral Servicio de horario laboral.
     */
    public function __construct(
        private readonly HorarioLaboralService $horarioLaboral
    ) {}

    /**
     * Crea una alerta, fija sus destinatarios y calcula su expiración si es de tipo 'alerta'.
     *
     * Todo el código que genere alertas debe pasar por aquí: crear la fila
     * directamente deja la alerta sin destinatarios (nadie la ve) y sin
     * `expira_en` (el job no la escala nunca).
     *
     * Los destinatarios de una alerta a un colectivo se fijan en este momento:
     * cada uno debe reconocerla por su cuenta. Si el colectivo está vacío, la
     * alerta queda vencida y se deja aviso en el log.
     *
     * @param array<string, mixed> $datos Datos de la alerta.
     * @return Alerta
     */
    public function crear(array $datos): Alerta
    {
        return DB::transaction(function () use ($datos): Alerta {
            $alerta = new Alerta($datos);
            $alerta->estado = EstadoAlerta::Pendiente;

            if ($alerta->tipo === TipoAlerta::Alerta) {
                $alerta->expira_en = $this->horarioLaboral->calcularExpiracion(now());
            }

            $alerta->save();

            $usuarioIds = $alerta->destinatario_type === DestinatarioType::RolUo
                ? $this->resolverDestinatarios($alerta)->pluck('id')
                : collect([$alerta->destinatario_usuario_id])->filter();

            foreach ($usuarioIds->unique() as $usuarioId) {
                $alerta->destinatarios()->create([
                    'usuario_id' => $usuarioId,
                    'estado' => EstadoAlerta::Pendiente,
                ]);
            }

            if ($usuarioIds->isEmpty()) {
                Log::warning('Alerta sin destinatarios: nadie tiene el rol en la UO', [
                    'alerta_id' => $alerta->id,
                    'rol' => $alerta->destinatario_rol,
                    'uo_id' => $alerta->destinatario_uo_id,
                ]);
                $alerta->update(['estado' => EstadoAlerta::Vencida]);
            }

            return $alerta;
        });
    }

    /**
     * Registra que un destinatario reconoce (o descarta, si es un aviso) su parte de la alerta.
     *
     * No afecta a los demás destinatarios: la alerta solo deja de estar
     * pendiente cuando todos la han atendido.
     *
     * @param Alerta $alerta Alerta objetivo.
     * @param User $usuario Destinatario que reconoce.
     * @param string $ipAddress IP de origen.
     * @return AlertaReconocimiento
     *
     * @throws LogicException Si el usuario no es destinatario o ya no tiene la alerta pendiente.
     */
    public function reconocer(Alerta $alerta, User $usuario, string $ipAddress): AlertaReconocimiento
    {
        return DB::transaction(function () use ($alerta, $usuario, $ipAddress): AlertaReconocimiento {
            $destinatario = $alerta->destinatarios()->where('usuario_id', $usuario->id)->first();

            if (! $destinatario) {
                throw new LogicException("El usuario {$usuario->id} no es destinatario de la alerta {$alerta->id}.");
            }

            // Actualización condicionada al estado: si dos peticiones llegan a la
            // vez, solo una encuentra la fila pendiente.
            $actualizadas = AlertaDestinatario::whereKey($destinatario->id)
                ->where('estado', EstadoAlerta::Pendiente)
                ->update(['estado' => EstadoAlerta::Reconocida, 'atendida_en' => now(), 'updated_at' => now()]);

            if ($actualizadas === 0) {
                throw new LogicException("El usuario {$usuario->id} ya no tiene pendiente la alerta {$alerta->id}.");
            }

            $reconocimiento = AlertaReconocimiento::create([
                'alerta_id' => $alerta->id,
                'alerta_destinatario_id' => $destinatario->id,
                'usuario_id' => $usuario->id,
                'tipo' => $alerta->tipo === TipoAlerta::Aviso
                    ? TipoReconocimiento::Descartada
                    : TipoReconocimiento::Reconocida,
                'reconocida_en' => now(),
                'ip_address' => $ipAddress,
            ]);

            $this->recalcularEstado($alerta);

            return $reconocimiento;
        });
    }

    /**
     * Escala al supervisor de la UO la parte de cada destinatario que no reconoció en plazo.
     *
     * - Destinatario pendiente → escalada al supervisor (nunca a sí mismo);
     *   si no hay otro supervisor activo, vencida y aviso en el log.
     * - Destinatario ya escalado → vencida: no hay segundo nivel de escalada.
     *
     * @param Alerta $alerta Alerta vencida.
     * @return void
     */
    public function escalar(Alerta $alerta): void
    {
        DB::transaction(function () use ($alerta): void {
            // Primero las ya escaladas, para no vencer en esta misma llamada
            // las que se escalan a continuación.
            $alerta->destinatarios()
                ->where('estado', EstadoAlerta::Escalada)
                ->update(['estado' => EstadoAlerta::Vencida, 'updated_at' => now()]);

            foreach ($alerta->destinatarios()->pendientes()->get() as $destinatario) {
                $this->escalarDestinatario($alerta, $destinatario);
            }

            $this->recalcularEstado($alerta);
        });
    }

    /**
     * Resuelve qué usuarios son destinatarios reales de una alerta rol_uo.
     *
     * @param Alerta $alerta Alerta dirigida a un rol en una UO.
     * @return Collection<int, User>
     */
    public function resolverDestinatarios(Alerta $alerta): Collection
    {
        if ($alerta->destinatario_type !== DestinatarioType::RolUo) {
            return collect();
        }

        // El filtro de vigencia va agrupado: un orWhere suelto dentro de
        // whereHas anula la condición de UO y la correlación con el usuario.
        return User::whereHas('adscripciones', function ($query) use ($alerta) {
            $query->where('unidad_organizativa_id', $alerta->destinatario_uo_id)
                ->vigentes();
        })
            ->role($alerta->destinatario_rol)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Escala la parte de un destinatario al supervisor de la UO.
     */
    private function escalarDestinatario(Alerta $alerta, AlertaDestinatario $destinatario): void
    {
        $supervisor = $this->resolverSupervisor($alerta, $destinatario->usuario_id);

        if (! $supervisor) {
            Log::warning('Alerta vencida sin supervisor disponible en la UO', [
                'alerta_id' => $alerta->id,
                'usuario_id' => $destinatario->usuario_id,
                'uo_id' => $this->resolverUoDelDestinatario($alerta, $destinatario->usuario_id)?->id,
            ]);
            $destinatario->update(['estado' => EstadoAlerta::Vencida]);

            return;
        }

        $destinatario->update([
            'estado' => EstadoAlerta::Escalada,
            'escalada_en' => now(),
            'escalada_a_usuario_id' => $supervisor->id,
        ]);

        AlertaReconocimiento::create([
            'alerta_id' => $alerta->id,
            'alerta_destinatario_id' => $destinatario->id,
            'usuario_id' => $supervisor->id,
            'tipo' => TipoReconocimiento::Escalada,
            'reconocida_en' => now(),
            'ip_address' => null,
        ]);

        // La alerta guarda la primera escalada (lo muestra el log de alertas de Filament).
        if ($alerta->escalada_en === null) {
            $alerta->update(['escalada_en' => now(), 'escalada_a_usuario_id' => $supervisor->id]);
        }
    }

    /**
     * Recalcula el estado resumen de la alerta a partir de sus destinatarios:
     * pendiente mientras alguno lo esté; si no, el peor desenlace
     * (vencida > escalada > reconocida).
     */
    private function recalcularEstado(Alerta $alerta): void
    {
        $estados = $alerta->destinatarios()->pluck('estado')
            ->map(fn ($e) => $e instanceof EstadoAlerta ? $e : EstadoAlerta::from($e));

        if ($estados->isEmpty()) {
            return;
        }

        $resumen = match (true) {
            $estados->contains(EstadoAlerta::Pendiente) => EstadoAlerta::Pendiente,
            $estados->contains(EstadoAlerta::Vencida) => EstadoAlerta::Vencida,
            $estados->contains(EstadoAlerta::Escalada) => EstadoAlerta::Escalada,
            default => EstadoAlerta::Reconocida,
        };

        $alerta->update(['estado' => $resumen]);
    }

    /**
     * Resuelve un supervisor activo de la UO del destinatario, distinto de él.
     */
    private function resolverSupervisor(Alerta $alerta, int $destinatarioId): ?User
    {
        $uo = $this->resolverUoDelDestinatario($alerta, $destinatarioId);

        if (! $uo) {
            return null;
        }

        return User::whereHas('adscripciones', function ($query) use ($uo) {
            $query->where('unidad_organizativa_id', $uo->id)->vigentes();
        })
            ->role('supervision')
            ->where('id', '!=', $destinatarioId)
            ->orderBy('id')
            ->first();
    }

    /**
     * Obtiene la UO en la que se busca supervisor: la del colectivo, o la
     * adscripción vigente del destinatario si la alerta era directa.
     */
    private function resolverUoDelDestinatario(Alerta $alerta, int $destinatarioId): ?UnidadOrganizativa
    {
        if ($alerta->destinatario_type === DestinatarioType::RolUo) {
            return $alerta->destinatarioUo;
        }

        return UsuarioUo::where('usuario_id', $destinatarioId)
            ->vigentes()
            ->orderBy('id')
            ->first()
            ?->unidadOrganizativa;
    }
}
