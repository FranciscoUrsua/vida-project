<x-filament-panels::page>

    {{-- Aviso de entorno: solo se muestra, no bloquea --}}
    <x-filament::section>
        <x-slot name="heading">Entornos de demostración</x-slot>
        <x-slot name="description">
            Selecciona un mundo para reconstruir el entorno de demo desde cero.
            Esta operación
            <strong class="font-semibold text-danger-600 dark:text-danger-400">
                destruirá todos los ciudadanos, historias sociales, planes y entrevistas actuales.
            </strong>
        </x-slot>

        <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">
            Solo disponible en entornos no productivos
        </x-filament::badge>
    </x-filament::section>

    @if (empty($worlds))

        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No hay mundos YAML disponibles en
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">
                    database/seeders/worlds/
                </code>.
            </p>
        </x-filament::section>

    @else

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($worlds as $world)
                <x-filament::section>

                    {{-- Cabecera de la tarjeta --}}
                    <x-slot name="heading">
                        {{ $world['nombre'] }}
                    </x-slot>
                    <x-slot name="description">
                        {{ $world['descripcion'] }}
                    </x-slot>

                    {{-- Estadísticas del mundo --}}
                    <dl class="mb-4 grid grid-cols-3 gap-3 text-center">
                        <div>
                            <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                Centros
                            </dt>
                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ $world['centros'] }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                Profesionales
                            </dt>
                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ $world['profesionales'] }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                Ciudadanos
                            </dt>
                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                                ~{{ $world['ciudadanos'] }}
                            </dd>
                        </div>
                    </dl>

                    {{-- Reset --}}
                    @if ($world['ciudadanos'] > 0)
                        <x-filament::button
                            wire:click="mountAction('reset_{{ $world['id'] }}')"
                            color="gray"
                            icon="heroicon-o-arrow-path"
                            class="w-full"
                        >
                            Cargar mundo
                        </x-filament::button>
                    @else
                        {{-- YAML con errores: mostrar aviso en lugar de botón --}}
                        <x-filament::badge color="danger" icon="heroicon-m-exclamation-circle" class="w-full justify-center">
                            YAML con errores — no disponible
                        </x-filament::badge>
                    @endif

                </x-filament::section>
            @endforeach
        </div>

    @endif

    {{-- Contenedor de modales de Filament: obligatorio para que funcionen las confirmaciones --}}
    <x-filament-actions::modals />

</x-filament-panels::page>
