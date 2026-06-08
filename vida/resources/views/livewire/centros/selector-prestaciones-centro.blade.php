<div class="flex flex-col gap-4">

    {{-- Filtros --}}
    <div class="flex flex-col gap-3">

        {{-- Búsqueda por texto --}}
        <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por código o nombre…"
                class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm
                       text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none
                       focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800
                       dark:text-white dark:placeholder-gray-500"
            >
        </div>

        {{-- Filtros por segmento de población --}}
        @if (count($this->segmentosFiltro) > 1)
            <div class="flex flex-wrap gap-2">
                @foreach ($this->segmentosFiltro as $id => $nombre)
                    <button
                        type="button"
                        wire:click="setSegmento('{{ $id }}')"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors
                               {{ $segmentoActivo == $id
                                   ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950 dark:text-primary-300'
                                   : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' }}"
                    >
                        {{ $nombre }}
                    </button>
                @endforeach
            </div>
        @endif

    </div>

    {{-- Cuerpo: catálogo + seleccionadas --}}
    <div class="grid grid-cols-3 gap-4">

        {{-- Catálogo --}}
        <div class="col-span-2 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
             style="max-height: 360px;">
            @forelse ($this->prestacionesFiltradas as $grupo => $prestaciones)
                <div class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">

                    {{-- Cabecera de grupo --}}
                    <div class="sticky top-0 bg-gray-50 px-4 py-2 dark:bg-gray-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ $grupo }}
                        </p>
                    </div>

                    {{-- Prestaciones del grupo --}}
                    @foreach ($prestaciones as $prestacion)
                        <div class="flex items-start gap-3 border-b border-gray-50 px-4 py-3
                                    last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50">

                            {{-- Checkbox --}}
                            <div class="pt-0.5">
                                <input
                                    type="checkbox"
                                    wire:click="togglePrestacion({{ $prestacion->id }})"
                                    @checked(in_array($prestacion->id, $seleccionadas))
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600
                                           focus:ring-primary-500 dark:border-gray-600"
                                >
                            </div>

                            {{-- Datos de la prestación --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start gap-2">
                                    <button
                                        type="button"
                                        wire:click="togglePrestacion({{ $prestacion->id }})"
                                        class="text-left text-sm text-gray-900 hover:text-primary-600
                                               dark:text-white dark:hover:text-primary-400"
                                    >
                                        {{ $prestacion->nombre }}
                                    </button>
                                    {{-- Botón de detalle --}}
                                    <button
                                        type="button"
                                        wire:click="verDetalle({{ $prestacion->id }})"
                                        class="flex-shrink-0 text-gray-300 hover:text-gray-500
                                               dark:text-gray-600 dark:hover:text-gray-400"
                                        title="Ver detalle"
                                    >
                                        <x-heroicon-o-information-circle class="h-4 w-4" />
                                    </button>
                                </div>
                                <p class="mt-0.5 font-mono text-xs text-gray-400">
                                    {{ $prestacion->codigo }}
                                </p>
                            </div>

                        </div>
                    @endforeach

                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    No hay prestaciones que coincidan con los filtros aplicados.
                </div>
            @endforelse
        </div>

        {{-- Panel de seleccionadas --}}
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Seleccionadas
                </p>
                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium
                             text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                    {{ count($seleccionadas) }}
                </span>
            </div>

            <div class="overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
                 style="max-height: 360px;">
                @if (empty($seleccionadas))
                    <p class="px-3 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                        Ninguna prestación seleccionada
                    </p>
                @else
                    @php
                        $modelosSeleccionadas = \Modules\Prestaciones\Models\Prestacion::whereIn('id', $seleccionadas)
                            ->orderBy('nombre')
                            ->get();
                    @endphp
                    @foreach ($modelosSeleccionadas as $pres)
                        <div class="flex items-start gap-2 border-b border-gray-50 px-3 py-2.5
                                    last:border-b-0 dark:border-gray-800">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-900 dark:text-white">
                                    {{ $pres->nombre }}
                                </p>
                                <p class="font-mono text-xs text-gray-400">
                                    {{ $pres->codigo }}
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="deseleccionar({{ $pres->id }})"
                                class="flex-shrink-0 text-gray-300 hover:text-danger-500
                                       dark:text-gray-600"
                                title="Quitar"
                            >
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

    </div>

    {{-- Footer: botón guardar siempre visible --}}
    <div class="flex items-center justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ count($seleccionadas) }}
            {{ count($seleccionadas) === 1 ? 'prestación seleccionada' : 'prestaciones seleccionadas' }}
        </p>
        <button
            type="button"
            wire:click="guardar"
            wire:loading.attr="disabled"
            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white
                   hover:bg-primary-700 disabled:opacity-50 dark:bg-primary-500
                   dark:hover:bg-primary-600"
        >
            <span wire:loading.remove wire:target="guardar">Guardar selección</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </button>
    </div>

    {{-- Modal de detalle de prestación --}}
    @if ($prestacionDetalle !== null)
        @php $detalle = \Modules\Prestaciones\Models\Prestacion::find($prestacionDetalle) @endphp
        @if ($detalle)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6
                            shadow-xl dark:border-gray-700 dark:bg-gray-900">

                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-gray-400">{{ $detalle->codigo }}</p>
                            <h3 class="mt-1 text-base font-medium text-gray-900 dark:text-white">
                                {{ $detalle->nombre }}
                            </h3>
                        </div>
                        <button
                            type="button"
                            wire:click="cerrarDetalle"
                            class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <dl class="space-y-2 text-sm">
                        @if ($detalle->descripcion)
                            <div class="flex gap-3">
                                <dt class="w-32 flex-shrink-0 text-gray-400">Descripción</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ $detalle->descripcion }}</dd>
                            </div>
                        @endif
                        @if ($detalle->tipo_prestacion)
                            <div class="flex gap-3">
                                <dt class="w-32 flex-shrink-0 text-gray-400">Tipo</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ $detalle->tipo_prestacion }}</dd>
                            </div>
                        @endif
                        @if ($detalle->nivel_garantia)
                            <div class="flex gap-3">
                                <dt class="w-32 flex-shrink-0 text-gray-400">Nivel de garantía</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ $detalle->nivel_garantia }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-5 text-right">
                        <button
                            type="button"
                            wire:click="cerrarDetalle"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600
                                   hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400
                                   dark:hover:bg-gray-800"
                        >
                            Cerrar
                        </button>
                    </div>

                </div>
            </div>
        @endif
    @endif

</div>
