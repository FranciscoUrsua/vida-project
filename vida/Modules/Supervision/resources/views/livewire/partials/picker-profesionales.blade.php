{{--
    Partial reutilizable: picker de profesionales con lista + búsqueda.

    Variables esperadas:
      $prefijo        string   Prefijo único para los ids HTML (ej: 'act', 'ses')
      $asignados      Collection<Profesional>  Profesionales ya asignados
      $paraSelector   Collection<Profesional>  Profesionales disponibles para añadir
      $buscarEnTodo   bool     Si true, muestra input de búsqueda en lugar de lista del centro
      $busqueda       string   Texto de búsqueda actual
      $agregarAction  string   Nombre del método Livewire para añadir (recibe int $id)
      $quitarAction   string   Nombre del método Livewire para quitar (recibe int $id)
      $toggleId       string   Id del checkbox de "toda la organización"
      $toggleModel    string   Propiedad Livewire enlazada al checkbox
      $busquedaModel  string   Propiedad Livewire enlazada al input de búsqueda
      $errorKey       string|null  Clave de error de validación (null si no es obligatorio)
      $requerido      bool     Si true, muestra * y mensaje de validación
--}}
<div class="border-top pt-3">
    <p class="fw-semibold mb-2">
        Profesionales
        @if($requerido) <span class="text-danger">*</span> @endif
        @if(! $requerido) <span class="fw-normal text-body-secondary small">(opcional — se puede dejar vacío)</span> @endif
    </p>

    @if($errorKey)
        @error($errorKey)
            <div class="alert alert-warning py-2 px-3 small mb-2">{{ $message }}</div>
        @enderror
    @endif

    {{-- Lista asignados --}}
    @if($asignados->isNotEmpty())
    <ul class="list-group list-group-flush mb-3">
        @foreach($asignados as $prof)
        <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-1">
            <span class="small">
                <x-heroicon-s-user-circle class="icon-16 text-secondary me-1" aria-hidden="true"/>
                {{ $prof->nombre_completo }}
                @if($prof->cargo)
                    <span class="text-body-secondary">({{ $prof->cargo->nombre }})</span>
                @endif
            </span>
            <button type="button"
                    class="btn btn-link btn-sm p-0 text-danger"
                    wire:click="{{ $quitarAction }}({{ $prof->id }})"
                    aria-label="Quitar a {{ $prof->nombre_completo }}">
                <x-heroicon-o-x-mark class="icon-16" aria-hidden="true"/>
            </button>
        </li>
        @endforeach
    </ul>
    @else
    <p class="text-body-secondary small mb-3">Sin profesionales asignados todavía.</p>
    @endif

    {{-- Picker modo centro --}}
    @if(! $buscarEnTodo)
        @if($paraSelector->isNotEmpty())
        <div class="border rounded overflow-hidden mb-2" style="max-height: 160px; overflow-y: auto;">
            @foreach($paraSelector as $prof)
            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                <span class="small">
                    {{ $prof->nombre_completo }}
                    @if($prof->cargo)
                        <span class="text-body-secondary">({{ $prof->cargo->nombre }})</span>
                    @endif
                </span>
                <button type="button"
                        class="btn btn-outline-primary btn-sm py-0 px-2 flex-shrink-0"
                        wire:click="{{ $agregarAction }}({{ $prof->id }})">
                    <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                </button>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-body-secondary small mb-2">Todos los profesionales del centro ya están asignados.</p>
        @endif
    @endif

    {{-- Picker modo organización --}}
    @if($buscarEnTodo)
    <div class="mb-2">
        <input type="text"
               class="form-control form-control-sm"
               placeholder="Buscar por nombre o apellido…"
               wire:model.live.debounce.300ms="{{ $busquedaModel }}"
               autocomplete="off">
    </div>
    @if(mb_strlen(trim($busqueda)) < 2)
        <p class="text-body-secondary small mb-2">Escribe al menos 2 caracteres para buscar.</p>
    @elseif($paraSelector->isEmpty())
        <p class="text-body-secondary small mb-2">Sin resultados para «{{ $busqueda }}».</p>
    @else
        <div class="border rounded overflow-hidden mb-2">
            @foreach($paraSelector as $prof)
            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                <span class="small">
                    {{ $prof->nombre_completo }}
                    @if($prof->cargo)
                        <span class="text-body-secondary">({{ $prof->cargo->nombre }})</span>
                    @endif
                </span>
                <button type="button"
                        class="btn btn-outline-primary btn-sm py-0 px-2 flex-shrink-0"
                        wire:click="{{ $agregarAction }}({{ $prof->id }})">
                    <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                </button>
            </div>
            @endforeach
        </div>
        @if($paraSelector->count() === 15)
        <p class="text-body-secondary small mb-2">Mostrando los primeros 15 resultados. Ajusta la búsqueda.</p>
        @endif
    @endif
    @endif

    {{-- Toggle modo búsqueda --}}
    <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" id="{{ $toggleId }}"
               wire:model.live="{{ $toggleModel }}">
        <label class="form-check-label small text-body-secondary" for="{{ $toggleId }}">
            Buscar en toda la organización
        </label>
    </div>
</div>
