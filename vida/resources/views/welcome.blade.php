@extends('layouts.app')

@section('title', 'VIDA - Bienvenido')

@section('content')
    <!-- Hero Section -->
    <section class="hero text-center py-5">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">¡Bienvenido a VIDA!</h1>
            <p class="lead mb-0">Tu plataforma para una gestión proactiva de servicios sociales.</p>
        </div>
    </section>

    <!-- Contenido Principal -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-5">
                            <h2 class="card-title text-center mb-4 text-primary">¿Qué es VIDA?</h2>
                            <p class="card-text text-muted lead fs-5 text-center">
                                VIDA es una plataforma open-source diseñada para empoderar a trabajadores sociales, administradores y entidades públicas en la gestión proactiva de servicios sociales del Ayuntamiento de Madrid. Integra datos de solicitudes, evaluaciones y prestaciones en un flujo unificado, priorizando la prevención, la equidad y la coordinación territorial –inspirada en el Plan Estratégico de Servicios Sociales 2023-2027 y la Guía de Prestaciones 2024. Con herramientas para valoraciones personalizadas, seguimiento de intervenciones y alertas en tiempo real, VIDA reduce la burocracia y fortalece el apoyo a familias en riesgo, promoviendo una ciudad más inclusiva y solidaria.
                            </p>
                            <div class="text-center mt-4">
                                <a href="/dashboard" class="btn btn-primary btn-lg">Explorar Plataforma</a>
                                <a href="https://github.com/tu-usuario/vida-project" class="btn btn-outline-secondary btn-lg ms-2">Ver en GitHub</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
