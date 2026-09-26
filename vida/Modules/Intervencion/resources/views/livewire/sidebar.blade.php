{{-- Sidebar operativo de Intervención --}}
{{-- Se refresca cada 60 segundos para mantener los contadores de la bandeja al día --}}
<aside class="op-sidebar" wire:poll.60s>

    {{-- Navegación principal --}}
    <nav class="op-nav" aria-label="Navegación principal">

        <a href="{{ route('intervencion.agenda.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.agenda*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.agenda*') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Agenda</span>
        </a>

        <a href="{{ route('intervencion.casos.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.casos*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.casos*') ? 'page' : 'false' }}">
            <x-heroicon-o-users class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Mis casos</span>
            @if($this->datos['casos'] > 0)
                <span class="op-nav-badge">{{ $this->datos['casos'] }}</span>
            @endif
        </a>

        @include('mensajes::partials.nav-bandeja', [
            'ruta' => 'intervencion.mensajes.index',
            'contadores' => $this->datos,
        ])

        @if($this->tienePlazas)
        <a href="{{ route('intervencion.recursos.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.recursos*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.recursos*') ? 'page' : 'false' }}">
            <x-heroicon-o-building-office-2 class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Recursos</span>
        </a>
        @endif

        <a href="{{ route('intervencion.buscar.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.buscar*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.buscar*') ? 'page' : 'false' }}">
            <x-heroicon-o-magnifying-glass class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Buscar ciudadano/a</span>
        </a>

        <a href="{{ route('ciudadania.alta') }}"
           class="op-nav-item {{ request()->routeIs('ciudadania.alta') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('ciudadania.alta') ? 'page' : 'false' }}">
            <x-heroicon-o-user-plus class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Alta de ciudadano/a</span>
        </a>

    </nav>

</aside>
