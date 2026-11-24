{{--
    Cabecera de columna ordenable de una tabla Livewire con el trait OrdenaPorColumnas.
    Uso: <x-op.th-orden columna="fecha" :actual="$ordenarPor" :direccion="$direccion">Fecha</x-op.th-orden>
    Los atributos extra (class="text-end"…) van al <th>. Una segunda tabla del mismo
    componente puede usar otro método con accion="ordenarOtros".
--}}
@props(['columna', 'actual', 'direccion', 'accion' => 'ordenar'])
@php $activa = $actual === $columna; @endphp
<th scope="col" {{ $attributes }} @if($activa) aria-sort="{{ $direccion === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <button type="button" wire:click="{{ $accion }}('{{ $columna }}')"
            @class(['btn btn-sm btn-link text-decoration-none p-0', 'fw-bold' => $activa, 'link-secondary' => ! $activa])>
        {{ $slot }}
        @if($activa)
            <span aria-hidden="true">{{ $direccion === 'asc' ? '↑' : '↓' }}</span>
        @endif
    </button>
</th>
