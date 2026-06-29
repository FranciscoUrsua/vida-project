@php
    $seccion = match(true) {
        request()->routeIs('agenda.cuadrante')               => 'Cuadrante del centro',
        request()->routeIs('agenda.supervisor.ausencias')    => 'Ausencias del día',
        request()->routeIs('agenda.supervisor.excepciones')  => 'Excepciones',
        request()->routeIs('agenda.supervisor.eventos')      => 'Eventos internos',
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
