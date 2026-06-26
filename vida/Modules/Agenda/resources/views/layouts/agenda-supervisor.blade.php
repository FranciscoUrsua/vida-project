@php
    $seccion = match(true) {
        request()->routeIs('agenda.supervisor.cuadrante')   => 'Cuadrante mensual',
        request()->routeIs('agenda.supervisor.ausencias')   => 'Ausencias del día',
        request()->routeIs('agenda.supervisor.excepciones') => 'Excepciones',
        request()->routeIs('agenda.supervisor.eventos')     => 'Eventos internos',
        default                                             => '',
    };
@endphp

<x-layouts.operativo-shell
    :title="config('app.name') . ' — Agenda'"
    area="Agenda"
    :section="$seccion"
    sidebar="agenda.supervisor.sidebar"
>
    {{ $slot }}
</x-layouts.operativo-shell>