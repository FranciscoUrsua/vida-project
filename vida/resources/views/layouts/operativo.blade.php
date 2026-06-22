<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Intervención</title>
    @livewireStyles
    @vite(['resources/scss/app-operativo.scss', 'resources/js/app.js'])
    <script>
        // 'morphed' dispara cuando un elemento existente es actualizado in-place.
        // 'morph.added' dispara cuando Livewire inserta un elemento nuevo (ej: bloques @@if
        // que pasan de false a true). Sin este segundo hook, los iconos dentro de botones
        // que aparecen condicionalmente nunca se procesan por Lucide.
        document.addEventListener('livewire:initialized', () => {
            const scheduleIcons = () => queueMicrotask(() => window.renderLucideIcons?.());
            Livewire.hook('morphed', scheduleIcons);
            Livewire.hook('morph.added', scheduleIcons);
        });
    </script>
</head>
<body>
<div class="op-layout">

    {{-- Sidebar Livewire (se refresca cada 5 min) --}}
    <livewire:intervencion.sidebar />

    {{-- Topbar: ocupa todo el ancho — logo | título de sección | menú usuario --}}
    <header class="op-topbar">

        {{-- Zona logo (izquierda, 196px = ancho del sidebar) --}}
        <div class="topbar__logo">
            @php
                $logoUrl  = \Modules\Organizacion\Models\Configuracion::logoUrl();
                $nombreApp = \Modules\Organizacion\Models\Configuracion::nombreAplicacion();
            @endphp
            @if($logoUrl)
                <img src="{{ $logoUrl }}"
                     alt="{{ $nombreApp ?? 'VIDA360' }}"
                     class="topbar__logo-img">
            @elseif($nombreApp)
                <span class="topbar__logo-text">{{ $nombreApp }}</span>
            @else
                <i data-lucide="hand-heart"
                   class="icon-20"
                   aria-hidden="true"></i>
                <span class="topbar__logo-text">VIDA360</span>
            @endif
        </div>

        {{-- Título de sección (centro) --}}
        @php
            $seccion = match(true) {
                request()->routeIs('intervencion.agenda*')     => 'Agenda',
                request()->routeIs('intervencion.casos*')      => 'Mis casos',
                request()->routeIs('intervencion.mensajes*')   => 'Alertas y mensajes',
                request()->routeIs('intervencion.buscar*')     => 'Buscar ciudadano/a',
                request()->routeIs('intervencion.valoracion*') => 'Valoración',
                request()->routeIs('intervencion.escala*')     => 'Escala',
                request()->routeIs('intervencion.ciudadano*')  => 'Expediente',
                request()->routeIs('ciudadania.alta*')         => 'Alta de ciudadano/a',
                request()->routeIs('ciudadania.ciudadano*')    => 'Ficha del ciudadano',
                default                                        => '',
            };
        @endphp
        <div class="topbar__section" aria-label="Sección actual">
            <h1 class="topbar__title h5 mb-0 fw-semibold text-body">
                <span>Intervención</span>
                @if($seccion)
                    <span class="topbar__title-sep text-body-secondary" aria-hidden="true">-</span>
                    <span>{{ $seccion }}</span>
                @endif
            </h1>
        </div>

        {{-- Menú de usuario (derecha) --}}
        <div class="topbar__user" x-data="{ abierto: false }">
            <button @click="abierto = !abierto"
                    @click.outside="abierto = false"
                    type="button" class="btn btn-sm btn-light d-flex align-items-center gap-2 px-2 py-1 border-0 shadow-none"
                    aria-haspopup="true"
                    :aria-expanded="abierto">

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

                <i data-lucide="chevron-down"
                   class="icon-16"
                   :class="{ 'icon-rotate-180': abierto }"
                   aria-hidden="true"></i>
            </button>

            {{-- Menu desplegable --}}
            <div x-show="abierto"
                 x-transition
                 x-cloak
                 class="topbar__user-menu">

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

                {{-- Cerrar sesion --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-link text-danger text-decoration-none d-flex align-items-center gap-2 w-100 justify-content-start px-4 py-2 rounded-0">
                        <i data-lucide="log-out" class="icon-16" aria-hidden="true"></i>
                        Cerrar sesion
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Area de contenido principal --}}
    <main class="op-main">
        {{ $slot }}
    </main>

</div>
@livewireScripts
</body>
</html>
