{{--
    Marco mínimo de las pantallas de error sin superficie operativa (5xx, sin sesión, peticiones
    Livewire): solo el bundle público, sin consultas a la base de datos ni componentes Livewire.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} — {{ config('app.name', 'VIDA') }}</title>
    @vite(['resources/scss/app-public.scss'])
</head>
<body>
    {{ $slot }}
</body>
</html>
