{{-- Header: Navbar fijo con logo, búsqueda, notificaciones y perfil --}}
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top" style="z-index: 1030;">
    <div class="container-fluid">
        {{-- Logo y Toggle Sidebar --}}
        <button class="navbar-toggler d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand fw-bold text-primary" href="#">
            <i class="bi bi-heart-pulse me-2"></i> VIDA
        </a>
        
        {{-- Búsqueda Global --}}
        <form class="d-flex me-auto" wire:submit="search">
            <div class="input-group input-group-sm">
                <input type="search" class="form-control" wire:model.live="searchQuery" placeholder="Buscar por DNI, caso o beneficiario...">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
        
        {{-- Notificaciones (Badge dinámico) --}}
        <ul class="navbar-nav me-3">
            <li class="nav-item dropdown">
                <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" wire:model="unreadNotificationsCount">
                        {{ $unreadNotificationsCount ?? 0 }}
                        <span class="visually-hidden">Nuevas notificaciones</span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                    {{-- Placeholder para lista de notificaciones --}}
                        <li><span class="dropdown-item text-muted">No hay notificaciones</span></li>
                </ul>
            </li>
        </ul>
        
        {{-- Perfil Usuario --}}
        <ul class="navbar-nav">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                    <img src="https://via.placeholder.com/32" alt="Avatar" class="rounded-circle me-2" width="32" height="32">
                    <span class="d-none d-sm-inline">{{ Auth::user()->name ?? 'Usuario' }}</span>
                    <small class="d-block d-sm-none text-muted">{{ Auth::user()->rol ?? 'Rol' }}</small>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Mi Perfil</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Configuración</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="#">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
