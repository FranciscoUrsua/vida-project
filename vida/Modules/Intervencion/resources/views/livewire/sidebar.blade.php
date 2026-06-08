{{-- Sidebar operativo de Intervención --}}
{{-- Se refresca cada 5 minutos (wire:poll.300s) --}}
<aside class="op-sidebar" wire:poll.300s>

    {{-- Logo --}}
    <div class="op-sidebar-logo">
        <i data-lucide="hand-heart" class="op-logo-icon" style="width:22px;height:22px;" aria-hidden="true"></i>
        <span class="op-logo-name">{{ config('app.name') }}</span>
    </div>

    {{-- Navegación principal --}}
    <nav class="op-nav" aria-label="Navegación principal">

        <a href="{{ route('intervencion.agenda.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.agenda*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.agenda*') ? 'page' : 'false' }}">
            <i data-lucide="calendar" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>
            <span>Agenda</span>
        </a>

        <a href="{{ route('intervencion.casos.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.casos*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.casos*') ? 'page' : 'false' }}">
            <i data-lucide="users" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>
            <span>Mis casos</span>
            @if($this->datos['casos'] > 0)
                <span class="op-nav-badge">{{ $this->datos['casos'] }}</span>
            @endif
        </a>

        <a href="{{ route('intervencion.mensajes.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.alertas*') || request()->routeIs('intervencion.mensajes*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.alertas*') || request()->routeIs('intervencion.mensajes*') ? 'page' : 'false' }}">
            <i data-lucide="bell" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>
            <span>Alertas y mensajes</span>
            @if($this->datos['notificaciones'] > 0)
                <span class="op-nav-badge alerta">{{ $this->datos['notificaciones'] }}</span>
            @endif
        </a>

        <a href="{{ route('intervencion.buscar.index') }}"
           class="op-nav-item {{ request()->routeIs('intervencion.buscar*') ? 'activo' : '' }}"
           aria-current="{{ request()->routeIs('intervencion.buscar*') ? 'page' : 'false' }}">
            <i data-lucide="search" class="op-nav-icon" style="width:18px;height:18px;" aria-hidden="true"></i>
            <span>Buscar ciudadano/a</span>
        </a>

    </nav>

    {{-- Pie: avatar, datos del profesional y menú de sesión --}}
    <div class="op-sidebar-footer">
        <div class="dropdown dropup w-100">
            <button class="d-flex align-items-center gap-2 w-100 bg-transparent border-0 p-0 text-start"
                    style="cursor: pointer;"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                <x-avatar :usuario="auth()->user()" />
                <div class="min-w-0 flex-1">
                    <div class="op-user-name">{{ auth()->user()->name }}</div>
                    <div class="op-user-role">
                        Intervención
                        {{-- TODO: · CSS cuando Profesional::centroActivo() esté implementado --}}
                    </div>
                </div>
                <i data-lucide="more-vertical" style="width:14px;height:14px;color:var(--color-ink-400);flex-shrink:0;" aria-hidden="true"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-start shadow-sm" style="min-width: 180px; font-size: 0.82rem;">
                <li>
                    <span class="dropdown-item-text text-muted" style="font-size: 0.72rem;">
                        {{ auth()->user()->email }}
                    </span>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i data-lucide="log-out" style="width:14px;height:14px;" aria-hidden="true" class="me-2"></i>Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

</aside>
