<x-filament-panels::page>
    <div class="space-y-6">
        @if (empty($worlds))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No hay mundos YAML disponibles en <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">database/seeders/worlds/</code>.
                </p>
            </div>
        @else
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Selecciona un mundo para resetear el entorno de demo. Esta operación
                    <strong class="text-danger-600 dark:text-danger-400">destruirá todos los datos de ciudadanos, historias, planes y entrevistas</strong>
                    y reconstruirá el mundo desde el YAML seleccionado.
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500">
                    Solo disponible en entornos no productivos. Las citas no se generan (pendiente integración con Agenda).
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($worlds as $world)
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5 flex flex-col gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $world['nombre'] ?? $world['id'] }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {{ $world['descripcion'] ?? '' }}
                            </p>
                        </div>

                        <dl class="grid grid-cols-3 gap-2 text-xs text-gray-600 dark:text-gray-400">
                            <div class="text-center">
                                <dt class="font-medium text-gray-400 dark:text-gray-500">Centros</dt>
                                <dd class="font-bold text-gray-900 dark:text-white">{{ $world['centros'] }}</dd>
                            </div>
                            <div class="text-center">
                                <dt class="font-medium text-gray-400 dark:text-gray-500">Profesionales</dt>
                                <dd class="font-bold text-gray-900 dark:text-white">{{ $world['profesionales'] }}</dd>
                            </div>
                            <div class="text-center">
                                <dt class="font-medium text-gray-400 dark:text-gray-500">Ciudadanos</dt>
                                <dd class="font-bold text-gray-900 dark:text-white">~{{ $world['ciudadanos'] }}</dd>
                            </div>
                        </dl>

                        <div class="text-xs text-gray-400 dark:text-gray-500">
                            Reset: {{ $world['reset_cada'] ?? 'por demanda' }}
                        </div>

                        <div class="mt-auto pt-2">
                            {{ ($this->getResetAction($world['id']))->toHtmlString() }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
