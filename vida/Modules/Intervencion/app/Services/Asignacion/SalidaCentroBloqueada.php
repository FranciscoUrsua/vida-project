<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\User;
use DomainException;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;

/**
 * Rechazo de una salida de centro o de una desactivación por casos o citas
 * pendientes (RN-21 y RN-22). Lleva el detalle por centro, el usuario afectado y los
 * enlaces a «Repartir casos» y a la gestión de la ausencia para que la interfaz los
 * ofrezca a quien puede usarlos (R3: el reparto es de la dirección del centro, D5).
 */
class SalidaCentroBloqueada extends DomainException
{
    /**
     * @param list<PendientesSalida> $pendientes Centros con algo pendiente.
     * @param string $accion «cerrar la adscripción al centro», «desactivar al usuario» o «retirar el rol intervención», para el mensaje.
     * @param User|null $usuario Profesional al que se intenta desactivar o sacar del centro.
     */
    public function __construct(public readonly array $pendientes, public readonly string $accion, public readonly ?User $usuario = null)
    {
        $detalle = implode('; ', array_map(fn (PendientesSalida $p) => $p->descripcion(), $pendientes));

        // La indicación solo nombra lo que de verdad queda pendiente
        $hayCitas = collect($pendientes)->contains(fn (PendientesSalida $p) => $p->citas > 0);
        $hayCasos = collect($pendientes)->contains(fn (PendientesSalida $p) => $p->casos > 0);
        $indicacion = match (true) {
            $hayCasos && $hayCitas => 'Reparte antes sus casos y reasigna sus citas desde la gestión de ausencias.',
            $hayCitas => 'Reasigna antes sus citas desde la gestión de ausencias.',
            default => 'Reparte antes sus casos.',
        };

        parent::__construct("No se puede {$accion} mientras tenga casos o citas pendientes ({$detalle}). {$indicacion}");
    }

    /**
     * Enlaces para resolver lo pendiente que el usuario puede abrir (R3): el reparto por
     * salida (pestaña de actividad de Asignaciones) si hay casos y la gestión de ausencias si
     * hay citas, cada uno solo si supervisa alguno de los centros afectados, que es lo que
     * exigen esas páginas (`role:supervision` y `RepartoCasosService::exigirSupervision()`).
     *
     * @param User $usuario Quien ve el rechazo.
     *
     * @return array<string, string> Etiqueta => URL.
     */
    public function enlaces(User $usuario): array
    {
        $centros = app(CentroDeUsuario::class);
        $supervisaAlguno = fn (string $campo): bool => collect($this->pendientes)
            ->contains(fn (PendientesSalida $p) => $p->{$campo} > 0 && $centros->supervisa($usuario, $p->centro));

        $enlaces = [];

        if ($supervisaAlguno('casos')) {
            $enlaces['Repartir casos'] = route('supervision.asignaciones', ['pestana' => 'actividad']);
        }

        if ($supervisaAlguno('citas')) {
            $enlaces['Reasignar citas'] = route('agenda.supervisor.ausencias');
        }

        return $enlaces;
    }
}
