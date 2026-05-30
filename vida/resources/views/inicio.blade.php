<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio — VIDA 360</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; color: #fff; cursor: default; }
        .bg-teal   { background: #0d9488; }
        .bg-blue   { background: #2563eb; }
        .bg-purple { background: #7c3aed; }
        .bg-amber  { background: #d97706; }
    </style>
</head>
<body class="bg-light">

<nav class="topbar">
    <span class="fw-semibold">VIDA 360</span>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">{{ Auth::user()->name }}</span>
        <x-avatar :usuario="Auth::user()" />
    </div>
</nav>

<div class="container py-5">
    <p class="text-muted">Pantalla de inicio (en desarrollo).</p>
</div>

</body>
</html>
