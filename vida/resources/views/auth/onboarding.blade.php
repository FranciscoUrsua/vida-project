@extends('layouts.public', ['title' => 'Bienvenida — VIDA 360', 'bodyClass' => 'd-flex align-items-center min-vh-100 py-4'])

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <div class="small fw-bold text-uppercase">VIDA 360</div>
                            <div class="small text-body-secondary">Activación inicial</div>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis text-uppercase">{{ config('app.env_label') }}</span>
                    </div>

                    <header class="mb-4">
                        <h2 class="h4 fw-bold mb-1">
                            Bienvenido, {{ explode(' ', trim($usuario->name))[0] }} {{ explode(' ', trim($usuario->name))[1] ?? '' }}
                        </h2>
                        <p class="text-body-secondary mb-0">Tu cuenta está lista. Revisa los datos iniciales antes de entrar.</p>
                    </header>

                    <section class="list-group mb-4" aria-label="Datos de la cuenta">
                        <div class="list-group-item bg-body-tertiary">
                            <div class="small fw-semibold text-body-secondary">Nombre completo</div>
                            <div class="fw-semibold">{{ $usuario->name }}</div>
                        </div>
                        @if ($centro)
                            <div class="list-group-item bg-body-tertiary">
                                <div class="small fw-semibold text-body-secondary">Centro de adscripción</div>
                                <div class="fw-semibold">{{ $centro }}</div>
                            </div>
                        @endif
                    </section>

                    <form method="POST" action="{{ route('onboarding.completar') }}">
                        @csrf
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-semibold">Empezar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
