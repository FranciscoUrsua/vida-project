<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIDA - Visión Integral de Derechos y Atención Social</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles
    @yield('styles')
</head>
<body>
    @livewire('login-welcome')
    @livewireScripts
</body>
</html>
