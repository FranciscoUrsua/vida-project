<?php

namespace Modules\Mensajes\Services;

use App\Models\User;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\MensajeParticipante;

/**
 * Contadores de las tres entradas de menú de la bandeja (Alertas, Avisos,
 * Mensajes). Los comparten los menús laterales de Intervención y Supervisión
 * y las pestañas de la propia bandeja, para que nunca muestren cifras distintas.
 */
class ContadoresBandejaService
{
    /**
     * Pendientes del usuario en cada pestaña.
     *
     * @param User $usuario Usuario autenticado.
     * @return array{alertas: int, avisos: int, mensajes: int}
     */
    public function para(User $usuario): array
    {
        $porTipo = Alerta::pendientesPara($usuario)
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        return [
            'alertas' => (int) ($porTipo[TipoAlerta::Alerta->value] ?? 0),
            'avisos' => (int) ($porTipo[TipoAlerta::Aviso->value] ?? 0),
            'mensajes' => $this->mensajesNoLeidos($usuario),
        ];
    }

    /**
     * Mensajes no leídos del usuario en hilos no archivados.
     *
     * @param User $usuario Usuario autenticado.
     * @return int
     */
    public function mensajesNoLeidos(User $usuario): int
    {
        return MensajeParticipante::where('usuario_id', $usuario->id)
            ->whereNull('archivado_en')
            ->get()
            ->sum(fn (MensajeParticipante $p) => $p->mensajesNoLeidos());
    }
}
