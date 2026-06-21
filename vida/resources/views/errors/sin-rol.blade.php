<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin perfil de acceso — {{ config('app.name') }}</title>
    @vite(['resources/scss/app-public.scss', 'resources/js/app.js'])
</head>
<body class="auth-page auth-page--centered">
@php
    $usuario = Auth::user();
    $nombreProfesional = $usuario->profesional
        ? trim(($usuario->profesional->nombre ?? '') . ' ' . ($usuario->profesional->apellido1 ?? ''))
        : null;
@endphp

<div class="container auth-page__center-wrap">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5">
            <div class="auth-card auth-card--blocked border-0 shadow-sm">
                <div class="auth-card__body card-body p-4 p-md-5">
                    <div class="auth-card__status-icon" aria-hidden="true">
                        <i data-lucide="lock" class="icon-40"></i>
                    </div>

                    <header class="text-center mb-4">
                        <div class="auth-card__mobile-brand mb-2">VIDA 360</div>
                        <h1 class="auth-card__title mb-2">Sin perfil de acceso</h1>
                        <p class="auth-card__subtitle auth-card__subtitle--center mb-0">
                            Tu cuenta no tiene un perfil de acceso asignado. Para acceder a la aplicación,
                            contacta con tu responsable de unidad y solicita la asignación de perfil.
                        </p>
                    </header>

                    @if($nombreProfesional || $usuario->email)
                        <section class="blocked-card__details mb-4" aria-label="Datos de la cuenta">
                            @if($nombreProfesional)
                                <div class="blocked-card__row">
                                    <div class="blocked-card__label">Nombre</div>
                                    <div class="blocked-card__value">{{ $nombreProfesional }}</div>
                                </div>
                            @endif

                            @if($usuario->email)
                                <div class="blocked-card__row">
                                    <div class="blocked-card__label">Correo electrónico</div>
                                    <div class="blocked-card__value">{{ $usuario->email }}</div>
                                </div>
                            @endif
                        </section>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="d-grid">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary auth-card__submit">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
