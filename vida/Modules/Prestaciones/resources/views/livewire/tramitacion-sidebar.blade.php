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
    </nav>
</aside>
