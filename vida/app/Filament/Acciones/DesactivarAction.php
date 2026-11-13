<?php

namespace App\Filament\Acciones;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Intervencion\Services\Asignacion\SalidaCentroBloqueada;
use Modules\Intervencion\Services\Asignacion\SalidaCentroService;
use Modules\Usuarios\Models\Profesional;

/**
 * Acciones «Desactivar» del backoffice, que sustituyen al borrado de usuarios y
 * profesionales (RN-20). Delegan en SalidaCentroService, que exige RN-22; si
 * hay casos o citas pendientes, avisan con el recuento y los enlaces para resolverlos.
 */
final class DesactivarAction
{
    /**
     * Acción de desactivar un usuario.
     *
     * @return Action
     */
    public static function usuario(): Action
    {
        return Action::make('desactivar')
            ->label('Desactivar')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Desactivar usuario')
            ->modalDescription('El usuario no podrá iniciar sesión. Se cierran sus adscripciones y se conservan todos sus registros. No se puede hacer si aún es referencia de casos o tiene citas confirmadas.')
            ->visible(fn (User $record): bool => ! $record->estaDesactivado()
                && auth()->user() !== null
                && app(SalidaCentroService::class)->puedeDesactivar(auth()->user(), $record))
            ->action(fn (User $record) => self::ejecutar(
                fn () => app(SalidaCentroService::class)->desactivarUsuario($record, auth()->user()),
                'Usuario desactivado',
            ));
    }

    /**
     * Acción de desactivar un profesional (y su cuenta, si la tiene).
     *
     * @return Action
     */
    public static function profesional(): Action
    {
        return Action::make('desactivar')
            ->label('Desactivar')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Desactivar profesional')
            ->modalDescription('El profesional queda inactivo y, si tiene cuenta, se desactiva. Se conservan todos sus registros. No se puede hacer si aún es referencia de casos o tiene citas confirmadas.')
            ->visible(fn (Profesional $record): bool => $record->activo
                && (auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false))
            ->action(fn (Profesional $record) => self::ejecutar(
                fn () => app(SalidaCentroService::class)->desactivarProfesional($record, auth()->user()),
                'Profesional desactivado',
            ));
    }

    /**
     * Ejecuta la desactivación y notifica el resultado o el motivo del rechazo.
     *
     * @param callable(): mixed $desactivar
     * @param string $exito Título de la notificación de éxito.
     *
     * @return void
     */
    private static function ejecutar(callable $desactivar, string $exito): void
    {
        try {
            $desactivar();
            Notification::make()->success()->title($exito)->send();
        } catch (SalidaCentroBloqueada $e) {
            self::notificarBloqueo($e);
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    /**
     * Notificación persistente con el motivo del rechazo y los enlaces para resolverlo.
     *
     * @param SalidaCentroBloqueada $e
     *
     * @return void
     */
    public static function notificarBloqueo(SalidaCentroBloqueada $e): void
    {
        $acciones = [];

        foreach ($e->enlaces() as $etiqueta => $url) {
            $acciones[] = Action::make(str($etiqueta)->slug()->toString())
                ->label($etiqueta)
                ->url($url)
                ->openUrlInNewTab();
        }

        Notification::make()
            ->danger()
            ->title('Hay casos o citas pendientes')
            ->body($e->getMessage())
            ->actions($acciones)
            ->persistent()
            ->send();
    }
}
