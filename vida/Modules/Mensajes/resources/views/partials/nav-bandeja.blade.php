{{--
    Tres entradas de menú de la bandeja (Alertas, Avisos, Mensajes) que abren
    la misma pantalla con la pestaña correspondiente. Lo incluyen los sidebars
    de Intervención y de Supervisión.

    @param string $ruta        Nombre de la ruta de la bandeja en ese interfaz.
    @param array  $contadores  ['alertas' => int, 'avisos' => int, 'mensajes' => int]
--}}
@php
    $enBandeja = request()->routeIs($ruta);
    $pestanaActual = $enBandeja ? (request()->route('pestana') ?? 'alertas') : null;
    $entradas = [
        ['alertas', 'Alertas', 'exclamation-triangle'],
        ['avisos', 'Avisos', 'bell'],
        ['mensajes', 'Mensajes', 'chat-bubble-left-right'],
    ];
@endphp

@foreach($entradas as [$pestana, $etiqueta, $icono])
    <a href="{{ route($ruta, $pestana) }}"
       class="op-nav-item {{ $pestanaActual === $pestana ? 'activo' : '' }}"
       aria-current="{{ $pestanaActual === $pestana ? 'page' : 'false' }}">
        <x-dynamic-component :component="'heroicon-o-' . $icono" class="op-nav-icon icon-18" aria-hidden="true"/>
        <span>{{ $etiqueta }}</span>
        @if($contadores[$pestana] > 0)
            {{-- Solo las alertas llevan el tinte de urgencia --}}
            <span class="op-nav-badge {{ $pestana === 'alertas' ? 'alerta' : '' }}">
                {{ $contadores[$pestana] }}
                <span class="visually-hidden">{{ $pestana === 'mensajes' ? 'sin leer' : 'pendientes' }}</span>
            </span>
        @endif
    </a>
@endforeach
