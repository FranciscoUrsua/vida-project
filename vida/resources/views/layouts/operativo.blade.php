<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Intervención</title>
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">
    <style>
        :root {
            --op-sidebar-width: 196px;
            --op-primary: #534AB7;
            --op-bg-primary: #F8F7FF;
            --op-bg-secondary: #F1F0F9;
            --op-ink: #1D160E;
            --op-ink-muted: #6B7280;
            --op-coral: #F0997B;
            --op-border: #E5E3F5;
        }

        body {
            margin: 0;
            font-family: 'Source Sans 3', system-ui, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: var(--op-ink);
            background: #F9F8FF;
        }

        .op-layout {
            display: flex;
            min-height: 100vh;
        }

        /* ------------------------------------------------------------------ */
        /* Sidebar                                                              */
        /* ------------------------------------------------------------------ */

        .op-sidebar {
            width: var(--op-sidebar-width);
            flex-shrink: 0;
            background: var(--op-bg-primary);
            border-right: 1px solid var(--op-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .op-sidebar-logo {
            padding: 1.25rem 1rem 1rem;
            border-bottom: 1px solid var(--op-border);
        }

        .op-sidebar-logo .op-logo-icon {
            color: var(--op-primary);
            font-size: 1.4rem;
        }

        .op-sidebar-logo .op-logo-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--op-ink);
            letter-spacing: -0.01em;
        }

        .op-nav {
            flex: 1;
            padding: 0.75rem 0;
        }

        .op-nav-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.55rem 1rem;
            color: var(--op-ink-muted);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0;
            transition: background 0.1s, color 0.1s;
            position: relative;
        }

        .op-nav-item:hover {
            background: var(--op-bg-secondary);
            color: var(--op-ink);
        }

        .op-nav-item.activo {
            background: var(--op-bg-secondary);
            color: var(--op-primary);
            font-weight: 600;
        }

        .op-nav-item.activo::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--op-primary);
            border-radius: 0 2px 2px 0;
        }

        .op-nav-icon {
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .op-nav-badge {
            margin-left: auto;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.1rem 0.45rem;
            border-radius: 99px;
            background: var(--op-primary);
            color: #fff;
            line-height: 1.5;
        }

        .op-nav-badge.alerta {
            background: var(--op-coral);
            color: #fff;
        }

        .op-sidebar-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--op-border);
        }

        .op-user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--op-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .op-user-role {
            font-size: 0.72rem;
            color: var(--op-ink-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ------------------------------------------------------------------ */
        /* Área principal                                                       */
        /* ------------------------------------------------------------------ */

        .op-main {
            flex: 1;
            margin-left: var(--op-sidebar-width);
            min-width: 0;
            min-height: 100vh;
        }
    </style>
</head>
<body>
<div class="op-layout">

    {{-- Sidebar Livewire (se refresca cada 5 min) --}}
    <livewire:intervencion.sidebar />

    {{-- Área de contenido principal --}}
    <main class="op-main">
        {{ $slot }}
    </main>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@livewireScripts
</body>
</html>
