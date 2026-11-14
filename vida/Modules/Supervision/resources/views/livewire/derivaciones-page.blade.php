{{-- Bandeja de derivaciones del centro de destino (fase 3, B4.2) --}}
<div class="op-page">
    <section class="p-3">

        @if($aviso)
            <div class="alert alert-success d-flex align-items-start gap-2" role="status">
                <x-heroicon-o-check-circle class="icon-20 flex-shrink-0" aria-hidden="true"/>
                <span>{{ $aviso }}</span>
            </div>
        @endif

        @if($this->centro === null)
            <x-op.empty icono="building-office">No supervisas ningún centro: la bandeja de derivaciones es de la dirección y la supervisión de cada centro.</x-op.empty>

        @elseif($this->pendientes->isEmpty())
            <x-op.empty icono="arrow-right-circle">No hay derivaciones pendientes para {{ $this->centro->nombre }}.</x-op.empty>

        @else
            @unless($this->resuelve)
                <p class="text-body-secondary small">Las resuelven la dirección y la supervisión adscritas a {{ $this->centro->nombre }}.</p>
            @endunless

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="visually-hidden">Derivaciones pendientes de {{ $this->centro->nombre }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Persona</th>
                                <th scope="col">Centro de origen</th>
                                <th scope="col">Deriva</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Motivo</th>
                                <th scope="col">Antigüedad</th>
                                @if($this->resuelve)<th scope="col"><span class="visually-hidden">Acciones</span></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->pendientes as $fila)
                                @php $id = $fila['derivacion']->id; @endphp
                                <tr wire:key="derivacion-pendiente-{{ $id }}">
                                    <td>{{ $fila['persona'] }}</td>
                                    <td class="text-body-secondary">{{ $fila['origen'] }}</td>
                                    <td class="text-body-secondary">{{ $fila['tsr'] }}</td>
                                    <td>{{ $fila['tipo'] }}</td>
                                    <td class="small">{{ $fila['motivo'] }}</td>
                                    <td class="text-body-secondary text-nowrap">{{ $fila['antiguedad'] }}</td>
                                    @if($this->resuelve)
                                        <td class="text-end text-nowrap">
                                            <button type="button" wire:click="prepararAceptacion({{ $id }})" class="btn btn-primary btn-sm">Aceptar</button>
                                            <button type="button" wire:click="prepararRechazo({{ $id }})" class="btn btn-outline-danger btn-sm">Rechazar</button>
                                        </td>
                                    @endif
                                </tr>

                                @if($aceptarId === $id)
                                    <tr wire:key="aceptar-{{ $id }}">
                                        <td colspan="7" class="bg-body-tertiary">
                                            <label for="responsable-{{ $id }}" class="form-label small fw-semibold mb-1">Profesional responsable del plan</label>
                                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                                <div class="flex-grow-1">
                                                    <select id="responsable-{{ $id }}" wire:model="responsableId" class="form-select form-select-sm @error('responsableId') is-invalid @enderror">
                                                        <option value="">— Elige el profesional —</option>
                                                        @foreach($this->profesionales as $profesional)
                                                            <option value="{{ $profesional->id }}">{{ $profesional->nombreVisible() }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('responsableId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <button type="button" wire:click="aceptar" class="btn btn-primary btn-sm">Aceptar y abrir el plan</button>
                                                <button type="button" wire:click="cancelar" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                @if($rechazarId === $id)
                                    <tr wire:key="rechazar-{{ $id }}">
                                        <td colspan="7" class="bg-body-tertiary">
                                            <label for="motivo-rechazo-{{ $id }}" class="form-label small fw-semibold mb-1">Motivo del rechazo</label>
                                            <textarea id="motivo-rechazo-{{ $id }}" wire:model="motivoRechazo" rows="2"
                                                      class="form-control form-control-sm @error('motivoRechazo') is-invalid @enderror"></textarea>
                                            @error('motivoRechazo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            <div class="d-flex gap-2 mt-2">
                                                <button type="button" wire:click="rechazar" class="btn btn-danger btn-sm">Rechazar derivación</button>
                                                <button type="button" wire:click="cancelar" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
</div>
