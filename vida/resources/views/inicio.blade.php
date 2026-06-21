<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio — {{ config('app.name') }}</title>
    @vite(['resources/scss/app-public.scss', 'resources/js/app.js'])
</head>
<body class="public-shell">
<nav class="public-shell__topbar" aria-label="Barra superior">
    <span class="public-shell__brand">{{ config('app.name') }}</span>
    <div class="public-shell__user">
        <span class="public-shell__user-name">{{ Auth::user()->name }}</span>
        <x-avatar :usuario="Auth::user()" />
    </div>
</nav>

<main class="public-shell__body">
    <div class="container text-center">
        <p class="public-shell__status mb-0">Redirigiendo…</p>
    </div>
</main>
</body>
</html>
