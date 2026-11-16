<aside class="op-sidebar" wire:poll.60s>
    <nav class="op-nav" aria-label="Navegación principal">
        <a href="{{ route('prestaciones.bandeja') }}"
           class="op-nav-item {{ request()->routeIs('prestaciones.bandeja') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('prestaciones.bandeja') ? 'page' : 'false' }}">
            <x-heroicon-o-inbox-stack class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Bandeja de tramitación</span>
            @if($this->pendientes > 0)
                <span class="op-nav-badge">{{ $this->pendientes }}<span class="visually-hidden"> por tomar</span></span>
            @endif
        </a>

        {{-- Citación (fase 4, paso 5): tramitación da, reprograma y cancela citas en su UO --}}
        @can('citas.gestionar')
        <a href="{{ route('agenda.citas.bandeja') }}"
           class="op-nav-item {{ request()->routeIs('agenda.citas*') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.citas*') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar-days class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Citación</span>
        </a>
        @endcan

        <a href="{{ route('ciudadania.buscar') }}"
           class="op-nav-item {{ request()->routeIs('ciudadania.buscar') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('ciudadania.buscar') ? 'page' : 'false' }}">
            <x-heroicon-o-magnifying-glass class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Buscar ciudadano/a</span>
        </a>
    </nav>
</aside>
