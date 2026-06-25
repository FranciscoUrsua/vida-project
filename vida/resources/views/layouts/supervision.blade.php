<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Supervisión</title>
    @livewireStyles
    @vite(['resources/scss/app-operativo.scss', 'resources/js/app.js'])
</head>
<body>
<div class="op-layout">

    {{-- Sidebar Livewire (se refresca cada 5 min) --}}
    <livewire:supervision.sidebar />

    {{-- Topbar: ocupa todo el ancho — logo | título de sección | menú usuario --}}
    <header class="op-topbar">

        {{-- Zona logo (izquierda, 196px = ancho del sidebar) --}}
        <div class="topbar__logo">
            @php
                $logoUrl   = \Modules\Organizacion\Models\Configuracion::logoUrl();
                $nombreApp = \Modules\Organizacion\Models\Configuracion::nombreAplicacion();
            @endphp
            @if($logoUrl)
                <img src="{{ $logoUrl }}"
                     alt="{{ $nombreApp ?? 'VIDA360' }}"
                     class="topbar__logo-img">
            @elseif($nombreApp)
                <span class="topbar__logo-text">{{ $nombreApp }}</span>
            @else
                <x-heroicon-o-heart class="icon-20" aria-hidden="true"/>
                <span class="topbar__logo-text">VIDA360</span>
            @endif
        </div>

        {{-- Título de sección (centro) --}}
        @php
            $seccion = match(true) {
                request()->routeIs('supervision.inicio')        => 'Inicio',
                request()->routeIs('supervision.cuadrante')     => 'Cuadrante del centro',
                request()->routeIs('supervision.actividades*')  => 'Actividades grupales',
                request()->routeIs('supervision.plazas')        => 'Plazas',
                request()->routeIs('supervision.equipo*')       => 'Mi equipo',
                request()->routeIs('supervision.auditoria')     => 'Accesos',
                request()->routeIs('supervision.aprobaciones')  => 'Aprobaciones',
                request()->routeIs('supervision.configuracion') => 'Configuración',
                default                                         => '',
            };
        @endphp
        <div class="topbar__section" aria-label="Sección actual">
            <h1 class="topbar__title h5 mb-0 fw-semibold text-body">
                <span>Supervisión</span>
                @if($seccion)
                    <span class="topbar__title-sep text-body-secondary" aria-hidden="true">-</span>
                    <span>{{ $seccion }}</span>
                @endif
            </h1>
        </div>

        {{-- Menú de usuario (derecha) --}}
        <div class="topbar__user dropdown">
            <button type="button"
                    class="btn btn-sm btn-light d-flex align-items-center gap-2 px-2 py-1 border-0 shadow-none"
                    data-bs-toggle="dropdown"
                    data-bs-offset="[0,8]"
                    aria-expanded="false">

                {{-- Avatar con iniciales --}}
                <div class="avatar avatar--sm">
                    {{ mb_strtoupper(
                        mb_substr(Auth::user()->profesional?->nombre ?? Auth::user()->email, 0, 1)
                        . mb_substr(Auth::user()->profesional?->apellido1 ?? '', 0, 1)
                    ) }}
                </div>

                {{-- Nombre completo --}}
                <span class="topbar__user-nombre">
                    {{ Auth::user()->profesional?->nombre_completo ?? Auth::user()->email }}
                </span>

                <x-heroicon-o-chevron-down class="icon-16 op-toggle-icon" aria-hidden="true"/>
            </button>

            {{-- Menú desplegable --}}
            <div class="topbar__user-menu dropdown-menu dropdown-menu-end">

                {{-- Info del usuario --}}
                <div class="topbar__user-info">
                    <div class="topbar__user-detail-name">
                        {{ Auth::user()->profesional?->nombre_completo ?? Auth::user()->email }}
                    </div>
                    <div class="topbar__user-detail-role">
                        {{ Auth::user()->roles->first()?->name ?? '—' }}
                    </div>
                </div>

                <div class="topbar__user-divider"></div>

                {{-- Cerrar sesión --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-link text-danger text-decoration-none d-flex align-items-center gap-2 w-100 justify-content-start px-4 py-2 rounded-0">
                        <x-heroicon-o-arrow-right-on-rectangle class="icon-16" aria-hidden="true"/>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Área de contenido principal --}}
    <main class="op-main">
        {{ $slot }}
    </main>

</div>
@livewireScripts
</body>
</html>
