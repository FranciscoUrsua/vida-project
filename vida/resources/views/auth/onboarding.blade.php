<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenida — VIDA 360</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { min-height: 100vh; background: #f5f6fa; display: flex; align-items: center; justify-content: center; }
        .onboarding-card { max-width: 480px; width: 100%; }
    </style>
</head>
<body>
<div class="onboarding-card card shadow-sm p-4 p-md-5">
    <div class="text-center mb-4">
        <span class="fs-1">👋</span>
    </div>
    <h2 class="fw-bold mb-1">
        Bienvenido, {{ explode(' ', trim($usuario->name))[0] }} {{ explode(' ', trim($usuario->name))[1] ?? '' }}
    </h2>
    <p class="text-muted mb-4">Tu cuenta está lista. Estos son tus datos de acceso configurados:</p>

    <ul class="list-unstyled mb-4">
        <li class="mb-2">
            <span class="text-muted small">Nombre completo</span><br>
            <strong>{{ $usuario->name }}</strong>
        </li>
        @if ($centro)
        <li class="mb-2">
            <span class="text-muted small">Centro de adscripción</span><br>
            <strong>{{ $centro }}</strong>
        </li>
        @endif
    </ul>

    <form method="POST" action="{{ route('onboarding.completar') }}">
        @csrf
        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">Empezar</button>
        </div>
    </form>
</div>
</body>
</html>
