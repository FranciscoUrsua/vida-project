@extends('layouts.public', ['title' => 'Bienvenida — VIDA 360', 'bodyClass' => 'auth-page auth-page--centered'])

@section('content')
<div class="container auth-page__center-wrap">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7 col-xl-6">
            <div class="auth-card onboarding-card border-0 shadow-sm">
                <div class="auth-card__body card-body p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <div class="auth-card__mobile-brand">VIDA 360</div>
                            <div class="auth-card__mobile-copy">Activación inicial</div>
                        </div>
                        <span class="badge auth-card__env">{{ config('app.env_label') }}</span>
                    </div>

                    <header class="mb-4">
                        <h2 class="auth-card__title mb-1">
                            Bienvenido, {{ explode(' ', trim($usuario->name))[0] }} {{ explode(' ', trim($usuario->name))[1] ?? '' }}
                        </h2>
                        <p class="auth-card__subtitle mb-0">Tu cuenta está lista. Revisa los datos iniciales antes de entrar.</p>
                    </header>

                    <section class="onboarding-card__summary mb-4" aria-label="Datos de la cuenta">
                        <div class="onboarding-card__row">
                            <div class="onboarding-card__label">Nombre completo</div>
                            <div class="onboarding-card__value">{{ $usuario->name }}</div>
                        </div>
                        @if ($centro)
                            <div class="onboarding-card__row">
                                <div class="onboarding-card__label">Centro de adscripción</div>
                                <div class="onboarding-card__value">{{ $centro }}</div>
                            </div>
                        @endif
                    </section>

                    <form method="POST" action="{{ route('onboarding.completar') }}">
                        @csrf
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary auth-card__submit">Empezar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection