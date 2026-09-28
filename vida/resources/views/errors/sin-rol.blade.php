@extends('layouts.public', ['title' => 'Sin perfil de acceso — ' . config('app.name'), 'bodyClass' => 'd-flex align-items-center min-vh-100 py-4'])

@section('content')
@php
    $usuario = Auth::user();
    $nombreProfesional = $usuario->profesional
        ? trim(($usuario->profesional->nombre ?? '') . ' ' . ($usuario->profesional->apellido1 ?? ''))
        : null;
@endphp

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-center text-primary mb-3" aria-hidden="true">
                        <x-heroicon-o-lock-closed class="icon-40"/>
                    </div>

                    <header class="text-center mb-4">
                        <div class="small fw-bold text-uppercase mb-2">VIDA 360</div>
                        <h1 class="h4 fw-bold mb-2">Sin perfil de acceso</h1>
                        <p class="text-body-secondary mb-0">
                            Tu cuenta no tiene un perfil de acceso asignado. Para acceder a la aplicación,
                            contacta con tu responsable de unidad y solicita la asignación de perfil.
                        </p>
                    </header>

                    @if($nombreProfesional || $usuario->email)
                        <dl class="border-top border-bottom mb-4" aria-label="Datos de la cuenta">
                            @if($nombreProfesional)
                                <div class="d-flex justify-content-between gap-3 py-2">
                                    <dt class="small text-body-secondary">Nombre</dt>
                                    <dd class="fw-semibold text-end text-break mb-0">{{ $nombreProfesional }}</dd>
                                </div>
                            @endif

                            @if($usuario->email)
                                <div @class(['d-flex justify-content-between gap-3 py-2', 'border-top' => $nombreProfesional])>
                                    <dt class="small text-body-secondary">Correo electrónico</dt>
                                    <dd class="fw-semibold text-end text-break mb-0">{{ $usuario->email }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="d-grid">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
