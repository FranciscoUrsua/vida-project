<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticateBase;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Sobrescribe el comportamiento de Filament cuando el usuario autenticado
 * no tiene acceso al panel.
 *
 * La implementación por defecto llama a abort(403), lo que impide al usuario
 * acceder a /admin/login (la página de login redirige a /admin si hay sesión
 * activa, generando un bucle 302 → 403). Este middleware cierra la sesión del
 * guard de Filament y redirige al login, igual que si el usuario no estuviera
 * autenticado.
 */
class FilamentAuthenticate extends FilamentAuthenticateBase
{
    /**
     * @param array<string> $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        $sinAcceso = $user instanceof FilamentUser
            ? (! $user->canAccessPanel($panel))
            : (config('app.env') !== 'local');

        if ($sinAcceso) {
            $guard->logout();
            $this->unauthenticated($request, $guards);
        }
    }
}
