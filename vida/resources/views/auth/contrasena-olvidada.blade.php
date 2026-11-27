@extends('layouts.public', ['title' => 'Contraseña olvidada — VIDA 360'])

{{-- «¿Olvidaste tu contraseña?» sin correo: avisa a la supervisión del centro (ContrasenaOlvidadaController) --}}
@section('content')
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h4 fw-bold mb-2">¿Olvidaste tu contraseña?</h1>

                    @if (session('contrasena-olvidada-enviada'))
                        <div class="alert alert-success small" role="status">
                            Si el correo corresponde a una cuenta de VIDA, hemos avisado a la supervisión de tu centro y a la administración de usuarios.
                        </div>
                        <p class="small text-body-secondary">
                            Habla con tu supervisor/a: comprobará que eres tú y te asignarán una contraseña nueva. Cuando entres, cámbiala desde tu menú («Cambiar contraseña»).
                        </p>
                    @else
                        <p class="small text-body-secondary mb-4">
                            Escribe el correo con el que entras en VIDA. Avisaremos a la supervisión de tu centro para que te ayude a recuperar el acceso.
                        </p>

                        <form method="POST" action="{{ route('contrasena.olvidada.enviar') }}">
                            @csrf
                            <div class="mb-4">
                                <label for="email" class="form-label small fw-semibold">Correo electrónico</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus
                                       class="form-control @error('email') is-invalid @enderror"
                                       @if ($errors->has('email')) aria-describedby="email-error" @endif>
                                @error('email')
                                    <div id="email-error" class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary fw-semibold">Avisar a mi supervisor/a</button>
                            </div>
                        </form>
                    @endif

                    <hr>
                    <div class="text-center small">
                        <a href="{{ route('login') }}" class="text-decoration-none">Volver al acceso</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
