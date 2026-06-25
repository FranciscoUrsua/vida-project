{{-- Sidebar operativo de Supervisión --}}
{{-- Se refresca cada 5 minutos (wire:poll.300s) --}}
<aside class="op-sidebar" wire:poll.300s>

    {{-- Navegación principal --}}
    <nav class="op-nav" aria-label="Navegación principal">

        <a href="{{ route('supervision.inicio') }}"
           class="op-nav-item {{ request()->routeIs('supervision.inicio') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.inicio') ? 'page' : 'false' }}">
            <x-heroicon-o-home class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Inicio</span>
        </a>

        <a href="{{ route('supervision.cuadrante') }}"
           class="op-nav-item {{ request()->routeIs('supervision.cuadrante') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.cuadrante') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar-days class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Cuadrante del centro</span>
        </a>

        <a href="{{ route('supervision.actividades') }}"
           class="op-nav-item {{ request()->routeIs('supervision.actividades*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.actividades*') ? 'page' : 'false' }}">
            <x-heroicon-o-user-group class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Actividades grupales</span>
        </a>

        @if($this->tienePlazas)
        <a href="{{ route('supervision.plazas') }}"
           class="op-nav-item {{ request()->routeIs('supervision.plazas') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.plazas') ? 'page' : 'false' }}">
            <x-heroicon-o-building-office class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Plazas</span>
        </a>
        @endif

        <a href="{{ route('supervision.equipo') }}"
           class="op-nav-item {{ request()->routeIs('supervision.equipo*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.equipo*') ? 'page' : 'false' }}">
            <x-heroicon-o-users class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Mi equipo</span>
        </a>

        <a href="{{ route('supervision.auditoria') }}"
           class="op-nav-item {{ request()->routeIs('supervision.auditoria') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.auditoria') ? 'page' : 'false' }}">
            <x-heroicon-o-shield-check class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Accesos</span>
        </a>

        <a href="{{ route('supervision.aprobaciones') }}"
           class="op-nav-item {{ request()->routeIs('supervision.aprobaciones') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.aprobaciones') ? 'page' : 'false' }}">
            <x-heroicon-o-check-badge class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Aprobaciones</span>
            @if($this->aprobacionesPendientes > 0)
                <span class="badge bg-danger rounded-pill">{{ $this->aprobacionesPendientes }}</span>
            @endif
        </a>

        <a href="{{ route('supervision.configuracion') }}"
           class="op-nav-item {{ request()->routeIs('supervision.configuracion') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.configuracion') ? 'page' : 'false' }}">
            <x-heroicon-o-cog-6-tooth class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Configuración</span>
        </a>

    </nav>

</aside>
