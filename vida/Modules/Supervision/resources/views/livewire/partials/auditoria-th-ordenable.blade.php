{{-- Cabecera de columna ordenable de la auditoría de accesos. Recibe $columna y $etiqueta. --}}
@php $activa = $this->ordenarPor === $columna; @endphp
<th scope="col" @if($activa) aria-sort="{{ $this->direccion === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <button type="button" wire:click="ordenar('{{ $columna }}')"
            @class(['btn btn-sm btn-link text-decoration-none p-0', 'fw-bold' => $activa, 'link-secondary' => ! $activa])>
        {{ $etiqueta }}
        @if($activa)
            <span aria-hidden="true">{{ $this->direccion === 'asc' ? '↑' : '↓' }}</span>
        @endif
    </button>
</th>
