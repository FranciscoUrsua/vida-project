<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio — {{ config('app.name') }}</title>
    @vite(['resources/scss/app-public.scss', 'resources/js/app.js'])
</head>
<body class="inicio-page">
<nav class="topbar">
    <span class="fw-semibold">{{ config('app.name') }}</span>
    <div class="d-flex align-items-center gap-2">
        <span class="inicio-status small">{{ Auth::user()->name }}</span>
        <x-avatar :usuario="Auth::user()" />
    </div>
</nav>

<div class="container py-5 text-center">
    <p class="text-muted">Redirigiendo…</p>
</div>

</body>
</html>
