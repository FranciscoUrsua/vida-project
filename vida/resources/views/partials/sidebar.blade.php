{{-- Sidebar: Menú colapsable vertical --}}
<div class="offcanvas offcanvas-start bg-white shadow" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="sidebarLabel">Menú Principal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0">
        <ul class="nav flex-column">
            {{-- Dashboard --}}
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-house-door me-2"></i> Dashboard
                </a>
            </li>
            
            {{-- Gestión (visible por rol) --}}
            @can('access-gestion')
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-folder me-2"></i> Historias Sociales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link " href="#">
                    <i class="bi bi-clipboard-check me-2"></i> Valoraciones
                </a>
            </li>
            @endcan
            
            {{-- Administración (visible por rol) --}}
            @can('access-admin')
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-building me-2"></i> Centros
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link " href="#">
                    <i class="bi bi-people me-2"></i> Usuarios
                </a>
            </li>
            @endcan
            
            {{-- Reportes y Config --}}
            <li class="nav-item mt-auto">
                <a class="nav-link" href="#">
                    <i class="bi bi-bar-chart me-2"></i> Reportes
                </a>
            </li>
        </ul>
    </div>
</div>
