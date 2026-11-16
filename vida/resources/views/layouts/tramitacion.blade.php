@php
    $seccion = match(true) {
        request()->routeIs('prestaciones.bandeja')   => 'Bandeja de tramitación',
        request()->routeIs('prestaciones.solicitud') => 'Solicitud de prestación',
        request()->routeIs('agenda.citas*')          => 'Citación',
        default                                      => '',
    };
@endphp

{{-- Superficie operativa del rol tramitacion (fase 3 bis) --}}
<x-layouts.operativo-shell
    :title="config('app.name') . ' — Tramitación'"
    area="Tramitación"
    :section="$seccion"
    sidebar="prestaciones.tramitacion-sidebar"
>
    {{ $slot }}
</x-layouts.operativo-shell>
