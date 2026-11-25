{{-- Tabla de encargos de «Mis derivaciones». Una persona protegida que ya no se puede ver
     sale enmascarada y sin enlace (EP-7); en el histórico, con «Solicitar acceso» (RN-18).
     Variables: $filas, $titulo, $cerrados (y $solicitarDe del componente). --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <caption class="visually-hidden">{{ $titulo }}</caption>
            <thead>
                <tr>
                    <th scope="col">Persona</th>
                    <th scope="col">Centro</th>
                    <th scope="col">Área</th>
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
                                @if($cerrados)
                                    @if($fila['solicitado'])
                                        <span class="d-block small text-body-secondary">Acceso solicitado</span>
                                    @else
                                        <button type="button" wire:click="prepararSolicitud({{ $encargo->id }})" class="btn btn-outline-protected btn-sm d-block mt-1 text-nowrap">Solicitar acceso</button>
                                    @endif
                                @endif
                            @endif
                        </td>
                        <td class="text-body-secondary">{{ $encargo->centroDestino?->nombre }}</td>
                        <td class="text-body-secondary">{{ $encargo->area?->nombre ?? '—' }}</td>
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
                    @if($cerrados && $solicitarDe === $encargo->id)
                        <tr wire:key="solicitud-{{ $encargo->id }}">
                            <td colspan="7" class="bg-body-tertiary">
                                <label for="justificacion-{{ $encargo->id }}" class="form-label small fw-semibold mb-1">
                                    Justificación <span class="fw-normal text-body-secondary">(mínimo 20 caracteres)</span>
                                </label>
                                <textarea id="justificacion-{{ $encargo->id }}" wire:model="justificacion" rows="3"
                                          class="form-control form-control-sm @error('justificacion') is-invalid @enderror"
                                          placeholder="Motivo profesional por el que necesitas volver a ver a esta persona"></textarea>
                                @error('justificacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <p class="form-text mb-2">La resuelve la dirección del centro de la persona.</p>
                                <div class="d-flex gap-2">
                                    <button type="button" wire:click="solicitarAcceso" class="btn btn-primary btn-sm">Enviar solicitud</button>
                                    <button type="button" wire:click="cancelarSolicitud" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
