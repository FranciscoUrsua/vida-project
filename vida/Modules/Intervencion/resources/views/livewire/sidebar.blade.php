{{-- Sidebar operativo de Intervención --}}
{{-- Se refresca cada 5 minutos (wire:poll.300s) --}}
<aside class="op-sidebar" wire:poll.300s>

    {{-- Navegación principal --}}
    <nav class="op-nav" aria-label="Navegación principal">

        <a href="{{ route('intervencion.agenda.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.agenda*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.agenda*') ? 'page' : 'false' }}">
            <x-icon name="calendar" class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Agenda</span>
        </a>

        <a href="{{ route('intervencion.casos.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.casos*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.casos*') ? 'page' : 'false' }}">
            <x-icon name="users" class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Mis casos</span>
            @if($this->datos['casos'] > 0)
                <span class="op-nav-badge">{{ $this->datos['casos'] }}</span>
            @endif
        </a>

        <a href="{{ route('intervencion.mensajes.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.alertas*') || request()->routeIs('intervencion.mensajes*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.alertas*') || request()->routeIs('intervencion.mensajes*') ? 'page' : 'false' }}">
            <x-icon name="bell" class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Alertas y mensajes</span>
            @if($this->datos['notificaciones'] > 0)
                <span class="op-nav-badge alerta">{{ $this->datos['notificaciones'] }}</span>
            @endif
        </a>

        <a href="{{ route('intervencion.buscar.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.buscar*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.buscar*') ? 'page' : 'false' }}">
            <x-icon name="search" class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Buscar ciudadano/a</span>
        </a>

        <a href="{{ route('ciudadania.alta') }}"
           class="op-nav-item {{ request()->routeIs('ciudadania.alta') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('ciudadania.alta') ? 'page' : 'false' }}">
            <x-icon name="user-plus" class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Alta de ciudadano/a</span>
        </a>

    </nav>

</aside>
