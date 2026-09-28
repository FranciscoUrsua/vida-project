@extends('layouts.public', ['title' => 'Acceso — VIDA 360'])

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
        <aside class="col-lg-5 d-none d-lg-flex align-items-center text-bg-primary">
            <div class="px-5 py-5 w-100">
                <div class="mb-4">
                    <h1 class="display-6 fw-bold mb-1">VIDA 360</h1>
                    <p class="text-white-50 mb-0">Plataforma integrada de servicios sociales</p>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-4" aria-label="Áreas funcionales">
                    <span class="badge rounded-pill bg-white bg-opacity-10 fw-normal fs-6">Historia social</span>
                    <span class="badge rounded-pill bg-white bg-opacity-10 fw-normal fs-6">Agenda</span>
                    <span class="badge rounded-pill bg-white bg-opacity-10 fw-normal fs-6">Prestaciones</span>
                    <span class="badge rounded-pill bg-white bg-opacity-10 fw-normal fs-6">Intervención</span>
                    <span class="badge rounded-pill bg-white bg-opacity-10 fw-normal fs-6">Informes</span>
                    <span class="badge rounded-pill bg-white bg-opacity-10 fw-normal fs-6">Centros</span>
                </div>

                <p class="small text-white-50 border-top border-white border-opacity-25 pt-3 mb-0">
                    Acceso restringido a personal autorizado.<br>
                    Si no dispones de credenciales, contacta con tu responsable de unidad.
                </p>
            </div>
        </aside>

        <main class="col-12 col-lg-7 d-flex align-items-center p-3 p-lg-5">
            <div class="row justify-content-center w-100 mx-0">
                <div class="col-12 col-sm-10 col-md-8 col-xxl-6 px-0">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4 p-md-5">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                                <div class="d-lg-none">
                                    <div class="small fw-bold text-uppercase">VIDA 360</div>
                                    <div class="small text-body-secondary">Servicios sociales</div>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis text-uppercase ms-lg-auto">{{ config('app.env_label') }}</span>
                            </div>

                            <header class="mb-4">
                                <h2 class="h4 fw-bold mb-1">{{ saludo() }}</h2>
                                <p class="text-body-secondary mb-0">Introduce tus credenciales para acceder</p>
                            </header>

                            @if ($errors->any())
                                <div class="alert alert-danger small py-2" role="alert">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login.post') }}">
                                @csrf

                                <div class="mb-3">
                                    <label for="email" class="form-label small fw-semibold">Correo electrónico</label>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        class="form-control @error('email') is-invalid @enderror"
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
                                    <label for="password" class="form-label small fw-semibold">Contraseña</label>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        autocomplete="current-password"
                                        required
                                        @if ($errors->has('password')) aria-describedby="password-error" @endif
                                    >
                                    @error('password')
                                        <div id="password-error" class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary fw-semibold">Entrar</button>
                                </div>
                            </form>

                            <hr>

                            <div class="text-center small text-body-secondary">
                                <a href="#" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
                            </div>

                            <div class="text-center small text-body-secondary mt-3">
                                ¿Necesitas ayuda? <a href="mailto:soporte@vida360.es" class="text-decoration-none">Contacta con soporte</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection
