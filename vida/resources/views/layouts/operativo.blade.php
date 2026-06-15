<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Intervención</title>
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/css/app-operativo.css')
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => lucide.createIcons({ 'stroke-width': 1.75 }));
        document.addEventListener('livewire:navigated', () => lucide.createIcons({ 'stroke-width': 1.75 }));
        document.addEventListener('livewire:updated', () => lucide.createIcons({ 'stroke-width': 1.75 }));
    </script>
</head>
<body>
<div class="op-layout">

    {{-- Sidebar Livewire (se refresca cada 5 min) --}}
    <livewire:intervencion.sidebar />

    {{-- Topbar con menu de usuario --}}
    <header class="op-topbar">
        {{-- Lado izquierdo: reservado para breadcrumb futuro --}}
        <div></div>

        {{-- Lado derecho: menu de usuario con dropdown Alpine --}}
        <div class="topbar__user" x-data="{ abierto: false }">
            <button @click="abierto = !abierto"
                    @click.outside="abierto = false"
                    class="topbar__user-btn"
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
                   style="width:16px;height:16px;"
                   :style="abierto ? 'transform:rotate(180deg)' : ''"
                   aria-hidden="true"></i>
            </button>

            {{-- Menu desplegable --}}
            <div x-show="abierto"
                 x-transition
                 class="topbar__user-menu"
                 style="display:none;">

                {{-- Info del usuario --}}
                <div class="topbar__user-info">
                    <div style="font-size:14px; font-weight:600; color:var(--color-ink-900);">
                        {{ Auth::user()->profesional?->nombre_completo ?? Auth::user()->email }}
                    </div>
                    <div style="font-size:12px; color:var(--color-ink-500);">
                        {{ Auth::user()->roles->first()?->name ?? '—' }}
                    </div>
                </div>

                <div class="topbar__user-divider"></div>

                {{-- Cerrar sesion --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="topbar__user-menu-item topbar__user-menu-item--danger">
                        <i data-lucide="log-out" style="width:16px;height:16px;" aria-hidden="true"></i>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@livewireScripts
</body>
</html>
