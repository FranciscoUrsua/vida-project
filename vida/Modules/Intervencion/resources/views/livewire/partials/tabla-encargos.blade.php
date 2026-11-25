{{-- Tabla de encargos de «Mis derivaciones». Una persona protegida que ya no se puede ver
     sale enmascarada y sin enlace (EP-7). Variables: $filas, $titulo, $cerrados. --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <caption class="visually-hidden">{{ $titulo }}</caption>
            <thead>
                <tr>
                    <th scope="col">Persona</th>
                    <th scope="col">Centro</th>
                    <th scope="col">Encarga</th>
                    <th scope="col">Motivo</th>
                    <th scope="col">Estado</th>
                    <th scope="col">{{ $cerrados ? 'Cerrado' : 'Antigüedad' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filas as $fila)
                    @php($encargo = $fila['encargo'])
                    <tr wire:key="encargo-{{ $encargo->id }}">
                        <td>
                            @if($fila['persona']['visible'])
                                <a href="{{ route('intervencion.encargos.show', $encargo->id) }}" wire:navigate>{{ $fila['persona']['nombre'] }}</a>
                            @else
                                <span class="text-body-secondary fst-italic">{{ $fila['persona']['nombre'] }}</span>
                            @endif
                        </td>
                        <td class="text-body-secondary">{{ $encargo->centroDestino?->nombre }}</td>
                        <td class="text-body-secondary">{{ $encargo->derivadaPor?->nombreVisible() }}</td>
                        <td class="small">
                            {{-- El motivo de una persona protegida enmascarada tampoco se muestra --}}
                            @if($fila['persona']['visible'])
                                {{ \Illuminate\Support\Str::limit($encargo->motivo, 120) }}
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="badge {{ $encargo->estado->tono()->clasesSuave() }}">{{ $encargo->estado->label() }}</span></td>
                        <td class="text-body-secondary text-nowrap">
                            {{ $cerrados ? $encargo->cerrada_en?->format('d/m/Y') : ($encargo->resuelta_en ?? $encargo->created_at)->diffForHumans() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
