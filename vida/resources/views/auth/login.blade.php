<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso — VIDA 360</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { min-height: 100vh; background: #f5f6fa; }
        .login-left { background: #1a3a5c; color: #fff; min-height: 100vh; padding: 3rem 2.5rem; display: flex; flex-direction: column; justify-content: center; }
        .login-right { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .pill { display: inline-block; background: rgba(255,255,255,0.15); border-radius: 20px; padding: 0.25rem 0.85rem; font-size: 0.8rem; margin: 0.2rem; }
        .env-badge { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
        .login-card { max-width: 400px; width: 100%; }
        .access-note { font-size: 0.8rem; opacity: 0.7; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 1rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">

        {{-- Columna izquierda: identidad visual --}}
        <div class="col-lg-5 login-left d-none d-lg-flex">
            <div>
                <div class="mb-4">
                    <h1 class="fw-bold fs-3 mb-1">VIDA 360</h1>
                    <p class="mb-0 opacity-75">Plataforma integrada de servicios sociales</p>
                </div>
                <div class="mb-4">
                    <span class="pill">Historia Social</span>
                    <span class="pill">Agenda</span>
                    <span class="pill">Prestaciones</span>
                    <span class="pill">Intervención</span>
                    <span class="pill">Informes</span>
                    <span class="pill">Centros</span>
                </div>
                <p class="access-note">
                    🔒 Acceso restringido a personal autorizado del Área de Servicios Sociales.
                    Si no dispones de credenciales, contacta con tu responsable de unidad.
                </p>
            </div>
        </div>

        {{-- Columna derecha: formulario --}}
        <div class="col-12 col-lg-7 login-right">
            <div class="login-card">

                {{-- Badge de entorno --}}
                <div class="text-end mb-3">
                    <span class="badge bg-secondary env-badge">{{ config('app.env_label') }}</span>
                </div>

                <h2 class="fw-semibold mb-1 fs-5">Bienvenido</h2>
                <p class="text-muted small mb-4">Introduce tus credenciales para acceder</p>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 small" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.post') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
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
                        <label for="password" class="form-label">Contraseña</label>
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
                        <button type="submit" class="btn btn-primary">Entrar</button>
                    </div>
                </form>

                <hr class="my-3">

                <div class="text-center small">
                    <a href="#" class="text-muted">¿Olvidaste tu contraseña?</a>
                </div>

                <div class="text-center small mt-3 text-muted">
                    ¿Necesitas ayuda? <a href="mailto:soporte@vida360.es">Contacta con soporte</a>
                </div>
            </div>
        </div>

    </div>
</div>
</body>
</html>
