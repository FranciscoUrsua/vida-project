<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'VIDA - Visión Integral de Derechos y Atención Social')</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <!-- Livewire Styles (para componentes reactivos) -->
    @livewireStyles
    @yield('styles')  <!-- Para estilos extras por página -->
</head>
<body>
    <!-- Navbar (Header) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="{{ asset('images/VIDALogo.png') }}" alt="VIDA logo" class="me-2 rounded">  <!-- Sube tu imagen aquí -->
                <span class="fw-bold">VIDA</span>
                <small class="text-light ms-1">Visión Integral de Derechos y Atención Social</small>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard">Dashboard</a>  <!-- Ruta futura para Livewire -->
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://github.com/tu-usuario/vida-project">GitHub</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal (Yield) -->
    <main class="flex-shrink-0">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="mt-auto py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 VIDA - Proyecto open-source basado en el Ayuntamiento de Madrid. Licencia GPL-3.0.</p>
            <p class="small text-muted">Inspirado en el Plan Estratégico de Servicios Sociales 2023-2027 y Guía de Prestaciones 2024.</p>
        </div>
    </footer>

    @livewireScripts
    @yield('scripts')  <!-- Para scripts extras por página -->
</body>
</html>
