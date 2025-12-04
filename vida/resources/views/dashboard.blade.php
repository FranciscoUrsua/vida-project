{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard Personal')

@section('styles')
    {{-- Estilos extras para dashboard, ej. DataTables --}}
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endsection

@section('breadcrumbs')
    <nav aria-label="breadcrumb" class="bg-white shadow-sm mt-5 pt-4">
        <ol class="breadcrumb mb-0 p-3">
            <li class="breadcrumb-item active"><i class="bi bi-house-door"></i> Dashboard</li>
        </ol>
    </nav>
@endsection

@section('content')
    {{-- Hero: Saludo y KPIs --}}
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 fw-bold text-primary mb-2">Bienvenido de vuelta, {{ Auth::user()->name ?? 'Usuario' }}!</h1>
            <p class="text-muted">Tu rol: {{ Auth::user()->rol ?? 'Trabajador Social' }} | Centro: {{ Auth::user()->centro ?? 'Centro de Servicios Sociales' }}</p>
        </div>
    </div>
    
    <div class="row mb-4">
        {{-- KPI Cards --}}
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary"><i class="bi bi-folder"></i> Casos Abiertos</h5>
                    <h2 class="fw-bold">{{ $kpis['casos_abiertos'] ?? 12 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title text-success"><i class="bi bi-calendar-check"></i> Citas Hoy</h5>
                    <h2 class="fw-bold">{{ $kpis['citas_hoy'] ?? 3 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title text-warning"><i class="bi bi-exclamation-triangle"></i> Tareas Pendientes</h5>
                    <h2 class="fw-bold">{{ $kpis['tareas_pendientes'] ?? 5 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title text-info"><i class="bi bi-people"></i> Beneficiarios Activos</h5>
                    <h2 class="fw-bold">{{ $kpis['beneficiarios'] ?? 45 }}</h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        {{-- Card 1: Últimos Casos --}}
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Últimos Casos</h5>
                </div>
                <div class="card-body">
                    {{-- Placeholder tabla --}}
                    <table class="table table-sm">
                        <thead><tr><th>Beneficiario</th><th>Estado</th><th>Última Acción</th></tr></thead>
                        <tbody>
                            <tr><td>Ana López</td><td><span class="badge bg-primary">Abierto</span></td><td>Hace 2h</td></tr>
                            <tr><td>Carlos Ruiz</td><td><span class="badge bg-warning">En Seguimiento</span></td><td>Hace 1d</td></tr>
                            {{-- Más rows... --}}
                        </tbody>
                    </table>
                    <a href="#" class="btn btn-outline-primary btn-sm">Ver Todos</a>
                </div>
            </div>
        </div>
        
        {{-- Card 2: Citas y Tareas Hoy --}}
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i> Citas y Tareas Hoy</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Valoración con J. García <span class="badge bg-info">14:00</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Revisar Plan Intervención <span class="badge bg-warning">Pendiente</span>
                        </li>
                        {{-- Más items... --}}
                    </ul>
                    <a href="#" class="btn btn-outline-primary btn-sm mt-2">Ver Calendario</a>
                </div>
            </div>
        </div>
        
        {{-- Card 3: Alertas --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-exclamation-circle me-2"></i> Alertas y Recomendaciones</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <strong>Caso #123:</strong> Necesita valoración urgente (Prioridad Alta).
                    </div>
                    <div class="alert alert-info" role="alert">
                        <strong>Prestación Disponible:</strong> Ayuda económica según Guía 2024.
                    </div>
                    {{-- Integrar Livewire para alertas dinámicas --}}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    {{-- JS extras para dashboard, ej. DataTables --}}
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Inicializar DataTables en tablas del dashboard
        $(document).ready(function() {
            $('table').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                pageLength: 5
            });
        });
    </script>
@endsection
