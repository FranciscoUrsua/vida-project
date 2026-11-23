<?php

namespace App\Filament\Acciones;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Intervencion\Services\Asignacion\AvisoDireccionSalida;
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
     * Notificación persistente con el motivo del rechazo y cómo resolverlo (R3).
     *
     * A quien puede resolverlo (la supervisión de alguno de los centros), los enlaces al reparto de
     * casos y a la gestión de ausencias. A quien no (p. ej. `adm_sistema`), qué dirección de centro
     * tiene que hacerlo y el botón «Avisar a la dirección del centro», que envía el aviso con el
     * profesional y sus casos y citas (lo recibe `AvisaDireccionDelCentro` en la página).
     *
     * @param SalidaCentroBloqueada $e
     *
     * @return void
     */
    public static function notificarBloqueo(SalidaCentroBloqueada $e): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $acciones = [];
        $cuerpo = $e->getMessage();

        foreach ($e->enlaces($actor) as $etiqueta => $url) {
            $acciones[] = Action::make(str($etiqueta)->slug()->toString())
                ->label($etiqueta)
                ->url($url)
                ->openUrlInNewTab();
        }

        if ($acciones === []) {
            $cuerpo .= ' Lo tiene que resolver la dirección del centro: '
                .app(AvisoDireccionSalida::class)->describirResponsables($e).'.';

            if ($e->usuario !== null) {
                $acciones[] = Action::make('avisar-direccion')
                    ->label('Avisar a la dirección del centro')
                    ->button()
                    ->dispatch('avisar-direccion-salida', [
                        'usuarioId' => $e->usuario->id,
                        'accion' => $e->accion,
                        'centroIds' => array_map(fn ($p) => $p->centro->id, $e->pendientes),
                    ])
                    ->close();
            }
        }

        Notification::make()
            ->danger()
            ->title('Hay casos o citas pendientes')
            ->body($cuerpo)
            ->actions($acciones)
            ->persistent()
            ->send();
    }
}
