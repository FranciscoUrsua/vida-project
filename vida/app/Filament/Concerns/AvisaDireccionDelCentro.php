<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Modules\Intervencion\Services\Asignacion\AvisoDireccionSalida;
use Modules\Intervencion\Services\Asignacion\SalidaCentroService;

/**
 * Recibe el botón «Avisar a la dirección del centro» de la notificación de una desactivación o
 * un cierre de adscripción bloqueados por casos o citas pendientes (R3). Lo usan las páginas de
 * Filament desde las que se desactiva a un usuario o a un profesional.
 */
trait AvisaDireccionDelCentro
{
    /**
     * Envía el aviso a la dirección de los centros afectados y confirma a quién se ha avisado.
     *
     * Solo para quien podía intentar la operación: el mismo permiso que desactivar al usuario.
     *
     * @param int $usuarioId Profesional al que se intentaba desactivar o sacar del centro.
     * @param string $accion Operación intentada, para el texto del aviso.
     * @param list<int> $centroIds Centros con lo pendiente.
     *
     * @return void
     */
    #[On('avisar-direccion-salida')]
    public function avisarDireccionSalida(int $usuarioId, string $accion, array $centroIds = []): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $profesional = User::findOrFail($usuarioId);

        abort_unless(app(SalidaCentroService::class)->puedeDesactivar($actor, $profesional), 403);

        $avisados = app(AvisoDireccionSalida::class)->avisar(
            $profesional,
            $accion,
            $actor,
            array_map('intval', $centroIds),
        );

        $avisados > 0
            ? Notification::make()->success()->title('Aviso enviado a la dirección del centro')->send()
            : Notification::make()->warning()->title('No hay a quién avisar: el centro no tiene dirección ni supervisión con cuenta en VIDA, o ya no queda nada pendiente')->send();
    }
}
