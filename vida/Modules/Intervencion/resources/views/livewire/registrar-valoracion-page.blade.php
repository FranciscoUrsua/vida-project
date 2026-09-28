<div class="op-page">
<div class="row justify-content-center g-0 p-3 p-lg-4">
<div class="col-12 col-lg-9 col-xl-7">

    {{-- Navegación --}}
    <a href="{{ route('intervencion.ciudadano.show', $historiaId) }}"
       class="link-secondary small text-decoration-none d-inline-flex align-items-center gap-1 mb-3">
        <x-heroicon-o-arrow-left class="icon-14" aria-hidden="true"/>
        Volver a la Historia Social
    </a>

    <h1 class="h5 fw-bold mb-3">Registrar valoración</h1>

    {{-- Selector de ficha --}}
    <div class="mb-4">
        <label for="valoracion-tipo-ficha" class="form-label small fw-semibold">Tipo de ficha</label>
        <select id="valoracion-tipo-ficha" wire:change="seleccionarFicha($event.target.value)"
                class="form-select form-select-sm w-auto mw-100 @error('tipoFichaId') is-invalid @enderror">
            <option value="">Selecciona una ficha…</option>
            @foreach($this->fichasDisponibles as $id => $nombre)
                <option value="{{ $id }}" @selected($id == $tipoFichaId)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('tipoFichaId')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Formulario de la ficha --}}
    @if($tipoFichaId && $this->tipoFicha)

        <div class="card card-body">
            <h2 class="h6 fw-bold text-primary mb-1">
                {{ $this->tipoFicha->nombre }}
            </h2>

            @if($this->tipoFicha->descripcion)
                <p class="small text-body-secondary mb-3">
                    {{ $this->tipoFicha->descripcion }}
                </p>
            @endif

            @foreach($this->tipoFicha->schema['campos'] ?? [] as $campo)
                @php
                    $tipo = $campo['tipo'] ?? 'texto';
                    $idCampo = 'campo-'.$campo['id'];
                    $conError = $errors->has("datos.{$campo['id']}");
                @endphp
                <div class="mb-3">
                    <label for="{{ $idCampo }}" class="form-label small fw-semibold mb-1">
                        {{ $campo['etiqueta'] ?? $campo['id'] }}
                        @if($campo['obligatorio'] ?? false)
                            <span class="text-danger" aria-hidden="true"> *</span>
                        @endif
                    </label>

                    @if($campo['descripcion'] ?? null)
                        <p class="form-text mt-0 mb-1">{{ $campo['descripcion'] }}</p>
                    @endif

                    @if($tipo === 'texto')
                        <textarea id="{{ $idCampo }}" wire:model.live="datos.{{ $campo['id'] }}" rows="2"
                                  @class(['form-control form-control-sm', 'is-invalid' => $conError])></textarea>

                    @elseif($tipo === 'numero')
                        <div class="d-flex align-items-center gap-2">
                            <input id="{{ $idCampo }}" type="number" wire:model.live="datos.{{ $campo['id'] }}"
                                   @class(['form-control form-control-sm w-auto', 'is-invalid' => $conError])>
                            @if($campo['unidad'] ?? null)
                                <span class="small text-body-secondary">{{ $campo['unidad'] }}</span>
                            @endif
                        </div>

                    @elseif($tipo === 'select')
                        <select id="{{ $idCampo }}" wire:model.live="datos.{{ $campo['id'] }}"
                                @class(['form-select form-select-sm w-auto mw-100', 'is-invalid' => $conError])>
                            <option value="">Selecciona…</option>
                            @foreach($campo['opciones'] ?? [] as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>

                    @elseif($tipo === 'booleano')
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="{{ $idCampo }}-si"
                                       wire:model.live="datos.{{ $campo['id'] }}" value="1">
                                <label class="form-check-label" for="{{ $idCampo }}-si">Sí</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="{{ $idCampo }}-no"
                                       wire:model.live="datos.{{ $campo['id'] }}" value="0">
                                <label class="form-check-label" for="{{ $idCampo }}-no">No</label>
                            </div>
                        </div>

                    @elseif($tipo === 'fecha')
                        <input id="{{ $idCampo }}" type="date" wire:model.live="datos.{{ $campo['id'] }}"
                               @class(['form-control form-control-sm w-auto', 'is-invalid' => $conError])>

                    @elseif($tipo === 'escala')
                        {{-- Solo puntuación total; el pase completo se hace en módulo Escalas --}}
                        <div class="d-flex align-items-center gap-2">
                            <input id="{{ $idCampo }}" type="number" min="0" wire:model.live="datos.{{ $campo['id'] }}"
                                   @class(['form-control form-control-sm w-auto', 'is-invalid' => $conError])>
                            <span class="small text-body-secondary">puntuación total</span>
                        </div>
                    @endif

                    @error("datos.{$campo['id']}")
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach

            {{-- Notas --}}
            <div class="border-top pt-3 mt-2">
                <label for="valoracion-notas" class="form-label small fw-semibold mb-1">Notas libres</label>
                <p class="form-text mt-0 mb-1">Observaciones no estructuradas de la entrevista.</p>
                <textarea id="valoracion-notas" wire:model.live="notas" rows="3" class="form-control form-control-sm"></textarea>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="d-flex gap-2 mt-4">
            <button type="button" wire:click="guardarDefinitivo" class="btn btn-primary">
                Guardar
            </button>
            <a href="{{ route('intervencion.ciudadano.show', $historiaId) }}" class="btn btn-outline-secondary">
                Cancelar
            </a>
        </div>

    @elseif(! $tipoFichaId)
        <p class="text-body-secondary">
            Selecciona un tipo de ficha para comenzar.
        </p>
    @endif

</div>
</div>
</div>
