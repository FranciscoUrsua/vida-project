@props([
    'title' => config('app.name', 'VIDA'),
    'area' => '',
    'section' => '',
    'sidebar' => null,
])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @livewireStyles
    @vite(['resources/scss/app-operativo.scss', 'resources/js/app.js'])
</head>
<body>
<div class="op-layout">
    @if($sidebar)
        @livewire($sidebar)
    @endif

    <header class="op-topbar">
        <div class="topbar__logo">
            @php
                $logoUrl = \Modules\Organizacion\Models\Configuracion::logoUrl();
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

        <div class="topbar__section" aria-label="Sección actual">
            <h1 class="topbar__title h5 mb-0 fw-semibold text-body">
                <span>{{ $area }}</span>
                @if($section)
                    <span class="topbar__title-sep text-body-secondary" aria-hidden="true">-</span>
                    <span>{{ $section }}</span>
                @endif
            </h1>
        </div>

        <div class="topbar__user dropdown">
            <button type="button"
                    class="btn btn-sm btn-light d-flex align-items-center gap-2 px-2 py-1 border-0 shadow-none"
                    data-bs-toggle="dropdown"
                    data-bs-offset="[0,8]"
                    aria-expanded="false">
                <div class="avatar avatar--sm">
                    {{ mb_strtoupper(
                        mb_substr(Auth::user()->profesional?->nombre ?? Auth::user()->email, 0, 1)
                        . mb_substr(Auth::user()->profesional?->apellido1 ?? '', 0, 1)
                    ) }}
                </div>

                <span class="topbar__user-nombre">
                    {{ Auth::user()->profesional?->nombre_completo ?? Auth::user()->email }}
                </span>

                <x-heroicon-o-chevron-down class="icon-16 op-toggle-icon" aria-hidden="true"/>
            </button>

            <div class="topbar__user-menu dropdown-menu dropdown-menu-end">
                <div class="topbar__user-info">
                    <div class="topbar__user-detail-name">
                        {{ Auth::user()->profesional?->nombre_completo ?? Auth::user()->email }}
                    </div>
                    <div class="topbar__user-detail-role">
                        {{ Auth::user()->roles->first()?->name ?? '—' }}
                    </div>
                </div>

                <div class="topbar__user-divider"></div>

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

    <main class="op-main">
        {{ $slot }}
    </main>
</div>
@livewireScripts
</body>
</html>