<div class="space-y-2 text-sm">
    @if(is_array($datos) && count($datos) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4 font-semibold text-gray-600 dark:text-gray-400">Campo</th>
                        <th class="py-2 font-semibold text-gray-600 dark:text-gray-400">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($datos as $campo => $valor)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-1 pr-4 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $campo }}
                            </td>
                            <td class="py-1 text-gray-800 dark:text-gray-200">
                                @if(is_array($valor))
                                    <span class="font-mono text-xs">{{ json_encode($valor, JSON_UNESCAPED_UNICODE) }}</span>
                                @elseif(is_null($valor))
                                    <span class="text-gray-400 italic">null</span>
                                @elseif(is_bool($valor))
                                    <span class="{{ $valor ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $valor ? 'true' : 'false' }}
                                    </span>
                                @else
                                    {{ $valor }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500 italic">No hay datos en este snapshot.</p>
    @endif
</div>
