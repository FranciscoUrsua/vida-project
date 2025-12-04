{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIDA - @yield('title', 'Visión Integral de Derechos y Atención Social')</title>
    
    {{-- Livewire Styles --}}
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles
    @yield('styles')
    {{-- Bootstrap Icons para iconos en sidebar/menús --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    {{-- Meta adicionales para SEO/compatibilidad --}}
    <meta name="description" content="Plataforma de gestión de servicios sociales del Ayuntamiento de Madrid.">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    {{-- Header: Incluido como partial para reutilización --}}
    @include('partials.header')
    
    <div class="d-flex flex-grow-1">
        {{-- Sidebar: Colapsable, incluido como partial --}}
        @include('partials.sidebar')
        
        {{-- Main Content: Área principal con breadcrumbs opcional --}}
        <main class="flex-grow-1 p-0">
            {{-- Breadcrumbs dinámico (opcional, via yield) --}}
            @yield('breadcrumbs')
            
            {{-- Contenido principal --}}
            <div class="container-fluid p-4">
                @yield('content')
            </div>
        </main>
    </div>
    
    {{-- Footer: Incluido como partial --}}
    @include('partials.footer')
    
    {{-- Scripts: Al final para performance --}}    
    {{-- Livewire Scripts --}}
    @livewireScripts
    
    {{-- Opcional: Alpine.js para interacciones en sidebar (toggle, etc.) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- JS adicional por página --}}
    @yield('scripts')
</body>
</html>
