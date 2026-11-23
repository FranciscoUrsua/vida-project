<?php

namespace App\Support\Errores;

use DomainException;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * Convierte una excepción de dominio lanzada en una acción Livewire o Filament en un aviso
 * con el mensaje de negocio, en lugar de una pantalla de error (R-03 de la ronda 1 de staging).
 *
 * La acción se interrumpe y la petición termina con normalidad: el componente conserva su
 * estado, así que lo escrito en el formulario no se pierde. Solo `DomainException` (y sus
 * subclases), que es la que usan los servicios para las reglas de negocio con mensaje en
 * español; cualquier otro error sigue su curso hasta la pantalla de error.
 */
class AvisoExcepcionDominio
{
    /**
     * Trata la excepción si es de dominio: avisa al usuario y detiene su propagación.
     *
     * @param Component $componente Componente cuya acción ha lanzado la excepción.
     * @param Throwable $excepcion Excepción lanzada.
     * @param callable $detenerPropagacion Llamada que impide que Livewire relance la excepción.
     *
     * @return void
     */
    public function manejar(Component $componente, Throwable $excepcion, callable $detenerPropagacion): void
    {
        if (! $excepcion instanceof DomainException) {
            return;
        }

        $mensaje = $excepcion->getMessage();

        // No es un fallo de la aplicación, pero se deja rastro para poder reconstruir lo que vio el usuario
        Log::info('Acción rechazada por una regla de negocio', [
            'mensaje' => $mensaje,
            'componente' => $componente::class,
            'userId' => auth()->id(),
        ]);

        // Las pantallas de Filament tienen su propio sistema de notificaciones
        if ($componente instanceof HasActions) {
            Notification::make()->title($mensaje)->danger()->send();
        } else {
            $componente->dispatch('toast', message: $mensaje, type: 'danger');
        }

        $detenerPropagacion();
    }
}
