@extends('layouts.public', ['title' => 'Acceso — VIDA 360', 'bodyClass' => 'auth-page'])

@section('content')
<div class="container-fluid p-0 auth-page__shell">
    <div class="row g-0 min-vh-100">
        <aside class="col-lg-5 d-none d-lg-flex auth-page__aside">
            <div class="auth-page__aside-inner">
                <div class="auth-page__brand">
                    <h1 class="auth-page__brand-title">VIDA 360</h1>
                    <p class="auth-page__brand-copy">Plataforma integrada de servicios sociales</p>
                </div>

                <div class="auth-page__chip-list" aria-label="Áreas funcionales">
                    <span class="auth-page__chip">Historia social</span>
                    <span class="auth-page__chip">Agenda</span>
                    <span class="auth-page__chip">Prestaciones</span>
                    <span class="auth-page__chip">Intervención</span>
                    <span class="auth-page__chip">Informes</span>
                    <span class="auth-page__chip">Centros</span>
                </div>

                <p class="auth-page__aside-note">
                    Acceso restringido a personal autorizado.<br>
                    Si no dispones de credenciales, contacta con tu responsable de unidad.
                </p>
            </div>
        </aside>

        <main class="col-12 col-lg-7 auth-page__main">
            <div class="auth-card card border-0 shadow-sm">
                <div class="auth-card__body card-body p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div class="d-lg-none">
                            <div class="auth-card__mobile-brand">VIDA 360</div>
                            <div class="auth-card__mobile-copy">Servicios sociales</div>
                        </div>
                        <span class="badge auth-card__env ms-lg-auto">{{ config('app.env_label') }}</span>
                    </div>

                    <header class="mb-4">
                        <h2 class="auth-card__title mb-1">{{ saludo() }}</h2>
                        <p class="auth-card__subtitle mb-0">Introduce tus credenciales para acceder</p>
                    </header>

                    @if ($errors->any())
                        <div class="alert alert-danger auth-card__alert" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.post') }}" class="auth-card__form">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label auth-card__label">Correo electrónico</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                class="form-control auth-card__input @error('email') is-invalid @enderror"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                required
                                autofocus
                                @if ($errors->has('email')) aria-describedby="email-error" @endif
                            >
                            @error('email')
                                <div id="email-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label auth-card__label">Contraseña</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control auth-card__input @error('password') is-invalid @enderror"
                                autocomplete="current-password"
                                required
                                @if ($errors->has('password')) aria-describedby="password-error" @endif
                            >
                            @error('password')
                                <div id="password-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary auth-card__submit">Entrar</button>
                        </div>
                    </form>

                    <hr class="auth-card__divider">

                    <div class="text-center auth-card__meta">
                        <a href="#" class="auth-card__link">¿Olvidaste tu contraseña?</a>
                    </div>

                    <div class="text-center auth-card__meta mt-3">
                        ¿Necesitas ayuda? <a href="mailto:soporte@vida360.es" class="auth-card__link">Contacta con soporte</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection