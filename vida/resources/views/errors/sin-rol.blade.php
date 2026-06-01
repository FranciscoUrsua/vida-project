<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin perfil de acceso — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { min-height: 100vh; background: #f5f6fa; display: flex; align-items: center; justify-content: center; }
        .sinrol-card { background: #fff; border-radius: 12px; padding: 2.5rem 2rem; max-width: 440px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,0.07); }
        .sinrol-icon { font-size: 2.5rem; color: #534AB7; margin-bottom: 1rem; display: block; text-align: center; }
        .sinrol-title { font-size: 1.15rem; font-weight: 700; color: #1D160E; text-align: center; margin-bottom: 0.5rem; }
        .sinrol-body { font-size: 0.875rem; color: #6B7280; text-align: center; line-height: 1.6; margin-bottom: 1.5rem; }
        .sinrol-divider { border-top: 1px solid #E5E7EB; margin: 1.25rem 0; }
        .sinrol-field { display: flex; gap: 0.5rem; justify-content: space-between; font-size: 0.8rem; padding: 0.3rem 0; }
        .sinrol-label { color: #9CA3AF; font-weight: 500; flex-shrink: 0; }
        .sinrol-value { color: #374151; font-weight: 600; text-align: right; word-break: break-all; }
    </style>
</head>
<body>
@php
    $usuario = Auth::user();
    $nombreProfesional = $usuario->profesional
        ? trim(($usuario->profesional->nombre ?? '') . ' ' . ($usuario->profesional->apellido1 ?? ''))
        : null;
@endphp

<div class="sinrol-card">

    <span class="sinrol-icon">🔒</span>

    <h1 class="sinrol-title">Sin perfil de acceso</h1>

    <p class="sinrol-body">
        Tu cuenta no tiene un perfil de acceso asignado.<br>
        Para acceder a la aplicación, contacta con tu responsable de unidad
        y pídele que te asigne un perfil.
    </p>

    @if($nombreProfesional || $usuario->email)
        <div class="sinrol-divider"></div>

        @if($nombreProfesional)
            <div class="sinrol-field">
                <span class="sinrol-label">Nombre</span>
                <span class="sinrol-value">{{ $nombreProfesional }}</span>
            </div>
        @endif

        @if($usuario->email)
            <div class="sinrol-field">
                <span class="sinrol-label">Email</span>
                <span class="sinrol-value">{{ $usuario->email }}</span>
            </div>
        @endif

        <div class="sinrol-divider"></div>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm px-4">
            Cerrar sesión
        </button>
    </form>

</div>
</body>
</html>
