{{--
    Partial recursivo que renderiza un nodo del árbol de UO.

    Variables:
      $uo    — instancia de UnidadOrganizativa
      $nivel — profundidad en el árbol (0 = raíz)
--}}
<div class="{{ $nivel > 0 ? 'border-t border-gray-100' : '' }}">

    <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50"
         style="padding-left: {{ 16 + $nivel * 24 }}px">

        <div class="flex items-center gap-3">
            {{-- Icono de sangría --}}
            @if ($nivel > 0)
                <span class="text-gray-300">└</span>
            @endif

            <div>
                <span class="text-sm font-medium {{ $uo->activa ? 'text-gray-900' : 'text-gray-400 line-through' }}">
                    {{ $uo->nombre }}
                </span>
                <span class="ml-2 px-1.5 py-0.5 text-xs rounded bg-gray-100 text-gray-500">
                    {{ $uo->tipo }}
                </span>
                @unless ($uo->activa)
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-yellow-100 text-yellow-700">
                        Inactiva
                    </span>
                @endunless
            </div>
        </div>

        {{-- Acciones --}}
        <div class="flex items-center gap-2">
            <button
                wire:click="abrirFormularioEdicion({{ $uo->id }})"
                class="px-3 py-1 text-xs text-blue-700 bg-blue-50 rounded hover:bg-blue-100"
                title="Editar esta UO"
            >
                Editar
            </button>

            @if ($uo->activa)
                <button
                    wire:click="desactivar({{ $uo->id }})"
                    wire:confirm="¿Desactivar la UO '{{ $uo->nombre }}'? Los usuarios adscritos mantendrán su historial."
                    class="px-3 py-1 text-xs text-red-700 bg-red-50 rounded hover:bg-red-100"
                    title="Desactivar esta UO"
                >
                    Desactivar
                </button>
            @else
                <button
                    wire:click="reactivar({{ $uo->id }})"
                    class="px-3 py-1 text-xs text-green-700 bg-green-50 rounded hover:bg-green-100"
                    title="Reactivar esta UO"
                >
                    Reactivar
                </button>
            @endif
        </div>

    </div>

    {{-- Hijos (recursión) --}}
    @if (! empty($this->busqueda))
        {{-- En modo búsqueda no renderizamos subárbol —ya se muestra la lista plana --}}
    @else
        @foreach ($uo->children ?? [] as $hija)
            @include('livewire.admin.partials.uo-nodo', ['uo' => $hija, 'nivel' => $nivel + 1])
        @endforeach
    @endif

</div>
