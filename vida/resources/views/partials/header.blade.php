{{-- Header VIDA --}}
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top border-bottom">
    <div class="container-fluid">
        {{-- Logo a la izquierda --}}
        <a class="navbar-brand d-flex align-items-center text-primary fw-bold" href="{{ route('dashboard') }}">
            <i class="bi bi-heart-pulse me-2 text-primary"></i>
            VIDA
        </a>

        {{-- Título del módulo (centro, responsive) --}}
        <span class="navbar-text mx-auto d-none d-lg-block">
            @if (isset($modulo) && !empty($modulo))
                {{ $modulo }}
            @else
                VIDA: Gestión de Servicios Sociales
            @endif
            <small class="d-block text-muted mt-1">
                {{ now()->locale('es')->format('d \d\e F \d\e Y') }} - {{ now()->format('H:i') }}
            </small>
        </span>

        {{-- Icons de notificaciones (derecha, antes del usuario) --}}
        <div class="d-flex align-items-center me-3">
            {{-- Mensajes --}}
            <div class="position-relative me-2">
                <i class="bi bi-envelope fs-5 text-primary" style="cursor: pointer;" title="Mensajes"></i>
                @if (($unreadMessages ?? 0) > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        {{ $unreadMessages }}
                        <span class="visually-hidden">No leídos</span>
                    </span>
                @endif
            </div>

            {{-- Información --}}
            <div class="position-relative me-2">
                <i class="bi bi-info-circle fs-5 text-primary" style="cursor: pointer;" title="Información"></i>
                @if (($unreadInfo ?? 0) > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        {{ $unreadInfo }}
                        <span class="visually-hidden">No leídos</span>
                    </span>
                @endif
            </div>

            {{-- Alertas --}}
            <div class="position-relative me-2">
                <i class="bi bi-bell fs-5 text-primary" style="cursor: pointer;" title="Alertas"></i>
                @if (($unreadAlerts ?? 0) > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        {{ $unreadAlerts }}
                        <span class="visually-hidden">No leídos</span>
                    </span>
                @endif
            </div>
        </div>

        {{-- Usuario con dropdown --}}
        <div class="dropdown">
            <a class="btn btn-outline-primary dropdown-toggle d-flex align-items-center px-0" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-2"></i>
                {{ auth()->user()->name ?? 'Usuario Invitado' }}
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                <li><a class="dropdown-item" href="/datos-personales">Datos personales</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    {{-- Form para logout con Sanctum --}}
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item text-start border-0 bg-transparent w-100">Salir</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

{{-- Espaciado para contenido principal (debajo del header fijo) --}}
<div class="pt-4"></div>
