<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'VIDA') }}</title>
    @vite(['resources/scss/app-public.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body @class([$bodyClass ?? ''])>
    @yield('content')
    @stack('scripts')
</body>
</html>