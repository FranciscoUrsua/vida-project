<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Intervención</title>
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/css/app-operativo.css')
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons({ 'stroke-width': 1.75 }));
        document.addEventListener('livewire:navigated', () => lucide.createIcons({ 'stroke-width': 1.75 }));
    </script>
</head>
<body>
<div class="op-layout">

    {{-- Sidebar Livewire (se refresca cada 5 min) --}}
    <livewire:intervencion.sidebar />

    {{-- Área de contenido principal --}}
    <main class="op-main">
        {{ $slot }}
    </main>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@livewireScripts
</body>
</html>
