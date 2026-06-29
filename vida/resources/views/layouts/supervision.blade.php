@php
    $seccion = match(true) {
        request()->routeIs('supervision.inicio')             => 'Inicio',
        request()->routeIs('supervision.cuadrante')         => 'Cuadrante del centro',
        request()->routeIs('agenda.cuadrante')               => 'Cuadrante del centro',
        request()->routeIs('agenda.supervisor.ausencias')    => 'Ausencias del día',
        request()->routeIs('agenda.supervisor.excepciones')  => 'Excepciones',
        request()->routeIs('agenda.supervisor.eventos')      => 'Eventos internos',
        request()->routeIs('supervision.actividades*')       => 'Actividades grupales',
        request()->routeIs('supervision.plazas')             => 'Plazas',
        request()->routeIs('supervision.equipo*')            => 'Mi equipo',
        request()->routeIs('supervision.auditoria')          => 'Accesos',
        request()->routeIs('supervision.aprobaciones')       => 'Aprobaciones',
        request()->routeIs('supervision.configuracion')      => 'Configuración',
        default                                              => '',
    };
@endphp

<x-layouts.operativo-shell
    :title="config('app.name') . ' — Supervisión'"
    area="Supervisión"
    :section="$seccion"
    sidebar="supervision.sidebar"
>
    {{ $slot }}
</x-layouts.operativo-shell>
