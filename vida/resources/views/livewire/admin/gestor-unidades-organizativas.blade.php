{{--
    Vista del componente GestorUnidadesOrganizativas.

    Muestra el árbol jerárquico de Unidades Organizativas con acciones
    de creación, edición y desactivación. Solo accesible para usuarios
    con el permiso configuracion.acceder.

    @see \App\Livewire\Admin\GestorUnidadesOrganizativas
--}}
<div class="p-6">

    {{-- Cabecera --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Unidades Organizativas</h1>
            <p class="mt-1 text-sm text-gray-500">
                Gestión de la estructura jerárquica de la organización.
            </p>
        </div>
        <button
            wire:click="abrirFormularioCreacion"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
        >
            + Nueva UO
        </button>
    </div>

    {{-- Mensajes de éxito --}}
    @if (session()->has('exito'))
        <div class="p-4 mb-4 text-green-800 bg-green-100 rounded-md">
            {{ session('exito') }}
        </div>
    @endif

    {{-- Buscador --}}
    <div class="mb-4">
        <input
            wire:model.live.debounce.300ms="busqueda"
            type="text"
            placeholder="Buscar unidad organizativa..."
            class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
    </div>

    {{-- Árbol de UO --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-lg">
        @forelse ($this->arbolUo as $raiz)
            @include('livewire.admin.partials.uo-nodo', ['uo' => $raiz, 'nivel' => 0])
        @empty
            <div class="p-8 text-center text-gray-500">
                No hay unidades organizativas registradas.
            </div>
        @endforelse
    </div>

    {{-- Modal de formulario --}}
    @if ($mostrarFormulario)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div class="w-full max-w-lg p-6 bg-white rounded-lg shadow-xl">

                <h2 class="mb-4 text-lg font-semibold text-gray-900">
                    {{ $editandoId ? 'Editar Unidad Organizativa' : 'Nueva Unidad Organizativa' }}
                </h2>

                <form wire:submit="guardar" class="space-y-4">

                    {{-- Nombre --}}
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input
                            wire:model="nombre"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nombre') border-red-500 @enderror"
                            placeholder="Ej: Centro de Servicios Sociales Arganzuela"
                        />
                        @error('nombre')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tipo --}}
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">
                            Tipo <span class="text-red-500">*</span>
                        </label>
                        <select
                            wire:model="tipo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('tipo') border-red-500 @enderror"
                        >
                            <option value="">— Selecciona un tipo —</option>
                            @foreach ($this->tiposDisponibles as $tipoUo)
                                <option value="{{ $tipoUo->codigo }}">{{ $tipoUo->nombre }}</option>
                            @endforeach
                        </select>
                        @error('tipo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- UO padre --}}
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">
                            UO padre <span class="text-gray-400 font-normal">(opcional — vacío = nodo raíz)</span>
                        </label>
                        <select
                            wire:model="parentId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('parentId') border-red-500 @enderror"
                        >
                            <option value="">— Sin padre (nodo raíz) —</option>
                            @foreach ($this->uosDisponiblesComoPadre as $uoPadre)
                                <option value="{{ $uoPadre->id }}">{{ $uoPadre->nombre }}</option>
                            @endforeach
                        </select>
                        @error('parentId')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Botones --}}
                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="cancelar"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                        >
                            {{ $editandoId ? 'Guardar cambios' : 'Crear UO' }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif

</div>
