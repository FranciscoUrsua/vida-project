@extends('layouts.public', ['title' => 'Inicio — ' . config('app.name')])

@section('content')
<nav class="d-flex align-items-center justify-content-between px-4 py-3 bg-white border-bottom" aria-label="Barra superior">
    <span class="fw-bold">{{ config('app.name') }}</span>
    <div class="d-flex align-items-center gap-2">
        <span class="small text-body-secondary">{{ Auth::user()->name }}</span>
        <x-avatar :usuario="Auth::user()" />
    </div>
</nav>

<main class="py-5">
    <div class="container text-center">
        <p class="text-body-secondary mb-0">Redirigiendo…</p>
    </div>
</main>
@endsection
