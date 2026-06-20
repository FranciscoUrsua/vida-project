<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin perfil de acceso — {{ config('app.name') }}</title>
    @vite(['resources/scss/app-public.scss', 'resources/js/app.js'])
</head>
<body class="sinrol-page">
@php
    $usuario = Auth::user();
    $nombreProfesional = $usuario->profesional
        ? trim(($usuario->profesional->nombre ?? '') . ' ' . ($usuario->profesional->apellido1 ?? ''))
        : null;
@endphp

<div class="sinrol-card">
    <div class="sinrol-icon">
        <i data-lucide="lock" class="sinrol-icon-glyph" aria-hidden="true"></i>
    </div>

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
