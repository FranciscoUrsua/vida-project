@extends('layouts.public', ['title' => 'Inicio — ' . config('app.name'), 'bodyClass' => 'public-shell'])

@section('content')
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
@endsection