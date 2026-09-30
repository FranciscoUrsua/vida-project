{{-- Sidebar unificado del interfaz operativo de Supervisión y Agenda --}}
{{-- Se refresca cada 60 segundos para mantener los badges actualizados --}}
<aside class="op-sidebar" wire:poll.60s>

    {{-- Navegación principal --}}
    <nav class="op-nav" aria-label="Navegación principal">

        <a href="{{ route('supervision.inicio') }}"
           class="op-nav-item {{ request()->routeIs('supervision.inicio') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.inicio') ? 'page' : 'false' }}">
            <x-heroicon-o-home class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Inicio</span>
        </a>

        @include('mensajes::partials.nav-bandeja', [
            'ruta' => 'supervision.bandeja',
            'contadores' => $this->contadoresBandeja,
        ])

        <a href="{{ route('supervision.control-alertas') }}"
           class="op-nav-item {{ request()->routeIs('supervision.control-alertas') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.control-alertas') ? 'page' : 'false' }}">
            <x-heroicon-o-shield-exclamation class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Control de alertas</span>
            @if($this->escaladasAbiertas > 0)
                <span class="op-nav-badge op-nav-badge--alerta">
                    {{ $this->escaladasAbiertas }}<span class="visually-hidden"> escaladas sin cerrar</span>
                </span>
            @endif
        </a>

        <a href="{{ route('supervision.cuadrante') }}"
           class="op-nav-item {{ request()->routeIs('supervision.cuadrante', 'agenda.cuadrante') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.cuadrante', 'agenda.cuadrante') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar-days class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Cuadrante del centro</span>
        </a>

        <a href="{{ route('agenda.supervisor.ausencias') }}"
           class="op-nav-item {{ request()->routeIs('agenda.supervisor.ausencias') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.supervisor.ausencias') ? 'page' : 'false' }}">
            <x-heroicon-o-exclamation-triangle class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Ausencias</span>
            @if($this->citasPendientesBadge > 0)
                <span class="badge bg-danger rounded-pill ms-auto">{{ $this->citasPendientesBadge }}</span>
            @endif
        </a>

        <a href="{{ route('agenda.supervisor.excepciones') }}"
           class="op-nav-item {{ request()->routeIs('agenda.supervisor.excepciones') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.supervisor.excepciones') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Excepciones</span>
        </a>

        <a href="{{ route('agenda.supervisor.eventos') }}"
           class="op-nav-item {{ request()->routeIs('agenda.supervisor.eventos') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.supervisor.eventos') ? 'page' : 'false' }}">
            <x-heroicon-o-users class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Eventos internos</span>
        </a>

        <a href="{{ route('supervision.actividades') }}"
           class="op-nav-item {{ request()->routeIs('supervision.actividades*') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.actividades*') ? 'page' : 'false' }}">
            <x-heroicon-o-user-group class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Actividades grupales</span>
        </a>

        @if($this->tienePlazas)
        <a href="{{ route('supervision.plazas') }}"
           class="op-nav-item {{ request()->routeIs('supervision.plazas') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.plazas') ? 'page' : 'false' }}">
            <x-heroicon-o-building-office class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Plazas</span>
        </a>
        @endif

        <a href="{{ route('supervision.equipo') }}"
           class="op-nav-item {{ request()->routeIs('supervision.equipo*') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.equipo*') ? 'page' : 'false' }}">
            <x-heroicon-o-users class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Mi equipo</span>
        </a>

        @if(auth()->user()->can('citas.gestionar') || auth()->user()->can('citas.supervisar'))
        <a href="{{ route('agenda.citas.bandeja') }}"
           class="op-nav-item {{ request()->routeIs('agenda.citas*') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('agenda.citas*') ? 'page' : 'false' }}">
            <x-heroicon-o-calendar-days class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Citación</span>
        </a>
        @endif

        <a href="{{ route('supervision.asignaciones') }}"
           class="op-nav-item {{ request()->routeIs('supervision.asignaciones*') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.asignaciones*') ? 'page' : 'false' }}">
            <x-heroicon-o-arrows-right-left class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Asignaciones</span>
            @if($this->asignacionesPendientes > 0)
                <span class="op-nav-badge">
                    {{ $this->asignacionesPendientes }}<span class="visually-hidden"> por decidir</span>
                </span>
            @endif
        </a>

        <a href="{{ route('supervision.auditoria') }}"
           class="op-nav-item {{ request()->routeIs('supervision.auditoria') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.auditoria') ? 'page' : 'false' }}">
            <x-heroicon-o-shield-check class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Accesos</span>
        </a>

<a href="{{ route('supervision.configuracion') }}"
           class="op-nav-item {{ request()->routeIs('supervision.configuracion') ? 'op-nav-item--activo' : '' }}"
           aria-current="{{ request()->routeIs('supervision.configuracion') ? 'page' : 'false' }}">
            <x-heroicon-o-cog-6-tooth class="op-nav-icon icon-18" aria-hidden="true"/>
            <span>Configuración</span>
        </a>

    </nav>

</aside>
