{{-- Buscador de persona de las pantallas de citación (trait BuscaPersonas). Parámetros: $accion (método Livewire que recibe el id), $etiqueta (texto del botón). --}}
<form wire:submit="buscarPersona" class="d-flex gap-2 mb-2" role="search">
    <label for="buscar-persona-{{ $accion }}" class="visually-hidden">Documento o nombre</label>
    <input id="buscar-persona-{{ $accion }}" type="search" wire:model="busquedaPersona" class="form-control form-control-sm" placeholder="Documento o nombre" autocomplete="off">
    <button type="submit" class="btn btn-outline-primary btn-sm">Buscar</button>
</form>

@if($personas !== null)
    @if($personas === [])
        <p class="small text-body-secondary mb-2">
            No se ha encontrado a nadie.
            <a href="{{ route('ciudadania.alta') }}" wire:navigate>Dar de alta a la persona</a>
        </p>
    @else
        <div class="list-group mb-2">
            @foreach($personas as $persona)
                <div class="list-group-item d-flex flex-wrap align-items-center gap-2 small" wire:key="persona-{{ $accion }}-{{ $persona['id'] }}">
                    <span class="fw-semibold">{{ $persona['nombre'] }}</span>
                    @if($persona['documento'])
                        <span class="font-monospace text-body-secondary">{{ $persona['documento'] }}</span>
                    @endif
                    @if($persona['telefono'])
                        <span class="text-body-secondary">{{ $persona['telefono'] }}</span>
                    @endif
                    <button type="button" wire:click="{{ $accion }}({{ $persona['id'] }})" class="btn btn-sm btn-outline-primary ms-auto">{{ $etiqueta }}</button>
                </div>
            @endforeach
        </div>
    @endif
@endif
