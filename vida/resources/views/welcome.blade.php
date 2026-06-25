@extends('layouts.public', ['title' => config('app.name', 'VIDA'), 'bodyClass' => 'auth-page welcome-page'])

@section('content')
<div class="container-fluid px-0">
    <header class="welcome-page__header">
        <div class="container">
            <div class="welcome-page__nav">
                <div class="welcome-page__brand">
                    <span class="welcome-page__brand-mark">VIDA</span>
                    <div>
                        <p class="welcome-page__brand-title">VIDA 360</p>
                        <p class="welcome-page__brand-copy">Gestion operativa para intervencion social</p>
                    </div>
                </div>

                @if (Route::has('login'))
                    <nav class="welcome-page__actions" aria-label="Acceso">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn btn-primary">
                                Ir al panel
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-outline-primary">
                                Acceder
                            </a>
                        @endauth
                    </nav>
                @endif
            </div>
        </div>
    </header>

    <main class="welcome-page__main">
        <div class="container">
            <section class="welcome-page__hero">
                <div class="row g-4 align-items-stretch">
                    <div class="col-12 col-xl-7">
                        <div class="welcome-page__hero-panel h-100">
                            <span class="welcome-page__eyebrow">Plataforma unificada</span>
                            <h1 class="welcome-page__title">
                                Agenda, expedientes y seguimiento en una sola capa de trabajo.
                            </h1>
                            <p class="welcome-page__lead">
                                VIDA centraliza la operativa diaria del equipo tecnico: citas, historia social,
                                prestaciones, incidencias y trazabilidad del ciudadano.
                            </p>

                            <div class="welcome-page__cta">
                                @auth
                                    <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg">
                                        Abrir dashboard
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                                        Iniciar sesion
                                    </a>
                                @endauth
                            </div>

                            <dl class="welcome-page__stats">
                                <div class="welcome-page__stat">
                                    <dt>Operacion</dt>
                                    <dd>Casos, agenda y actuaciones</dd>
                                </div>
                                <div class="welcome-page__stat">
                                    <dt>Seguimiento</dt>
                                    <dd>Estados, trazas y responsables</dd>
                                </div>
                                <div class="welcome-page__stat">
                                    <dt>Documentacion</dt>
                                    <dd>Informes y datos consolidados</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="col-12 col-xl-5">
                        <div class="welcome-page__summary h-100">
                            <div class="welcome-page__summary-section">
                                <p class="welcome-page__section-title">Modulos principales</p>
                                <ul class="welcome-page__module-list">
                                    <li>Historia social y expedientes</li>
                                    <li>Agenda y gestion de citas</li>
                                    <li>Prestaciones y planes</li>
                                    <li>Seguimiento de intervenciones</li>
                                    <li>Informes y documentos</li>
                                </ul>
                            </div>

                            <div class="welcome-page__summary-section">
                                <p class="welcome-page__section-title">Enfoque</p>
                                <div class="welcome-page__pill-list">
                                    <span class="welcome-page__pill">Trabajo diario</span>
                                    <span class="welcome-page__pill">Consistencia visual</span>
                                    <span class="welcome-page__pill">Datos estructurados</span>
                                    <span class="welcome-page__pill">Acceso por roles</span>
                                </div>
                            </div>

                            <div class="welcome-page__summary-note">
                                La portada publica queda reducida a contexto y acceso. La operativa real empieza
                                dentro del panel.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>
@endsection