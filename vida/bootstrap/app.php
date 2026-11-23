<?php

use App\Http\Middleware\AuditarAccesoCiudadano;
use App\Http\Middleware\EnsureTieneRol;
use App\Http\Middleware\PrimerAcceso;
use App\Support\Errores\IdentificadorError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // tus alias existentes...
        $middleware->alias([
            'primer.acceso' => PrimerAcceso::class,
            'audit.ciudadano' => AuditarAccesoCiudadano::class,
            'tiene.rol' => EnsureTieneRol::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // R-03: cada error registrado lleva el identificador que ve el usuario en la pantalla
        $exceptions->context(fn (): array => ['error_id' => IdentificadorError::actual()]);

        // Los 4xx no se registran como excepciones (Laravel los ignora); si se ha pintado una
        // pantalla de error, se deja una línea con su identificador para poder encontrarla.
        $exceptions->respond(function (Response $respuesta, Throwable $e, Request $peticion): Response {
            $estado = $respuesta->getStatusCode();

            if ($estado >= 400 && $estado < 500 && IdentificadorError::generado()) {
                Log::info('Pantalla de error mostrada', [
                    'error_id' => IdentificadorError::actual(),
                    'estado' => $estado,
                    'excepcion' => $e::class,
                    'url' => $peticion->fullUrl(),
                    'userId' => $peticion->user()?->getAuthIdentifier(),
                ]);
            }

            return $respuesta;
        });
    })->create();
