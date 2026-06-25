@php
    $seccion = match(true) {
        request()->routeIs('intervencion.agenda*')     => 'Agenda',
        request()->routeIs('intervencion.casos*')      => 'Mis casos',
        request()->routeIs('intervencion.mensajes*')   => 'Alertas y mensajes',
        request()->routeIs('intervencion.buscar*')     => 'Buscar ciudadano/a',
        request()->routeIs('intervencion.valoracion*') => 'Valoración',
        request()->routeIs('intervencion.escala*')     => 'Escala',
        request()->routeIs('intervencion.plan*')       => 'Plan de Intervención',
        request()->routeIs('intervencion.ciudadano*')  => 'Expediente',
        request()->routeIs('ciudadania.alta*')         => 'Alta de ciudadano/a',
        request()->routeIs('ciudadania.ciudadano*')    => 'Ficha del ciudadano',
        default                                        => '',
    };
@endphp

<x-layouts.operativo-shell
    :title="config('app.name') . ' — Intervención'"
    area="Intervención"
    :section="$seccion"
    sidebar="intervencion.sidebar"
>
    {{ $slot }}
</x-layouts.operativo-shell>