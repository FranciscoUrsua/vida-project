@extends('layouts.public', ['title' => config('app.name', 'VIDA'), 'bodyClass' => 'min-vh-100'])

@section('content')
<header class="container pt-3">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 py-3">
        <div class="d-flex align-items-center gap-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-bg-primary fw-bold small p-3">VIDA</span>
            <div>
                <p class="fw-bold mb-0">VIDA 360</p>
                <p class="small text-body-secondary mb-0">Gestion operativa para intervencion social</p>
            </div>
        </div>

        @if (Route::has('login'))
            <nav class="d-flex align-items-center gap-2" aria-label="Acceso">
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
</header>

<main class="container pt-2 pb-5">
    <div class="row g-4 align-items-stretch">
        <div class="col-12 col-xl-7">
            <section class="card card-body h-100 shadow-sm p-4 p-lg-5">
                <div>
                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis text-uppercase mb-3">Plataforma unificada</span>
                </div>
                <h1 class="display-6 fw-bold lh-sm mb-3">
                    Agenda, expedientes y seguimiento en una sola capa de trabajo.
                </h1>
                <p class="lead fs-6 text-body-secondary mb-0">
                    VIDA centraliza la operativa diaria del equipo tecnico: citas, historia social,
                    prestaciones, incidencias y trazabilidad del ciudadano.
                </p>

                <div class="d-flex flex-wrap gap-2 mt-4">
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

                <dl class="row row-cols-1 row-cols-md-3 g-3 mt-4 mb-0">
                    <div class="col">
                        <div class="bg-body-tertiary rounded p-3 h-100">
                            <dt class="small text-uppercase text-body-secondary mb-1">Operacion</dt>
                            <dd class="fw-semibold mb-0">Casos, agenda y actuaciones</dd>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-body-tertiary rounded p-3 h-100">
                            <dt class="small text-uppercase text-body-secondary mb-1">Seguimiento</dt>
                            <dd class="fw-semibold mb-0">Estados, trazas y responsables</dd>
                        </div>
                    </div>
                    <div class="col">
                        <div class="bg-body-tertiary rounded p-3 h-100">
                            <dt class="small text-uppercase text-body-secondary mb-1">Documentacion</dt>
                            <dd class="fw-semibold mb-0">Informes y datos consolidados</dd>
                        </div>
                    </div>
                </dl>
            </section>
        </div>

        <div class="col-12 col-xl-5">
            <aside class="card card-body h-100 shadow-sm d-flex flex-column gap-4">
                <div>
                    <p class="small fw-bold mb-2">Modulos principales</p>
                    <ul class="mb-0">
                        <li>Historia social y expedientes</li>
                        <li>Agenda y gestion de citas</li>
                        <li>Prestaciones y planes</li>
                        <li>Seguimiento de intervenciones</li>
                        <li>Informes y documentos</li>
                    </ul>
                </div>

                <div>
                    <p class="small fw-bold mb-2">Enfoque</p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge rounded-pill bg-body-tertiary text-body border fw-semibold">Trabajo diario</span>
                        <span class="badge rounded-pill bg-body-tertiary text-body border fw-semibold">Consistencia visual</span>
                        <span class="badge rounded-pill bg-body-tertiary text-body border fw-semibold">Datos estructurados</span>
                        <span class="badge rounded-pill bg-body-tertiary text-body border fw-semibold">Acceso por roles</span>
                    </div>
                </div>

                <p class="small text-body-secondary border-top pt-3 mt-auto mb-0">
                    La portada publica queda reducida a contexto y acceso. La operativa real empieza
                    dentro del panel.
                </p>
            </aside>
        </div>
    </div>
</main>
@endsection
