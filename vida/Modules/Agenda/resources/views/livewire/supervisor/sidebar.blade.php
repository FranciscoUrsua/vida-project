{{-- Sidebar operativo de Agenda — supervisor de centro --}}
{{-- Se refresca cada 30 segundos para actualizar el badge de ausencias --}}
<aside class="op-sidebar" wire:poll.30s>

    <nav class="op-nav" aria-label="Navegación de agenda">

        <a href="{{ route('agenda.supervisor.cuadrante') }}"
           class="op-nav-item {{ request()->routeIs('agenda.supervisor.cuadrante') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.supervisor.cuadrante') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar-days class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Cuadrante mensual</span>
        </a>

        <a href="{{ route('agenda.supervisor.ausencias') }}"
           class="op-nav-item {{ request()->routeIs('agenda.supervisor.ausencias') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.supervisor.ausencias') ? 'page' : 'false' }}">
            <x-heroicon-o-exclamation-triangle class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Ausencias</span>
            @if($this->citasPendientesBadge > 0)
                <span class="badge bg-danger rounded-pill ms-auto">{{ $this->citasPendientesBadge }}</span>
            @endif
        </a>

        <a href="{{ route('agenda.supervisor.excepciones') }}"
           class="op-nav-item {{ request()->routeIs('agenda.supervisor.excepciones') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.supervisor.excepciones') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Excepciones</span>
        </a>

        <a href="{{ route('agenda.supervisor.eventos') }}"
           class="op-nav-item {{ request()->routeIs('agenda.supervisor.eventos') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.supervisor.eventos') ? 'page' : 'false' }}">
            <x-heroicon-o-users class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Eventos internos</span>
        </a>

    </nav>

    {{-- Footer: enlace a Filament para configuración estable --}}
    <div class="op-nav-footer border-top pt-2 mt-auto">
        <a href="{{ url('/admin/horarios-centro') }}"
           class="op-nav-item text-body-secondary"
           target="_blank"
           rel="noopener"
           aria-label="Configuración del centro — abre en nueva pestaña">
            <x-heroicon-o-cog-6-tooth class="op-nav-icon icon-18" aria-hidden="true"/>
            <span class="small">Configuración del centro ↗</span>
        </a>
    </div>

</aside>
