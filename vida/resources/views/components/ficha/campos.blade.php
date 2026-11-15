{{-- Campos de una ficha a partir de su esquema (valoraciones y formularios de solicitud, fase 3 bis).
     Props: campos (lista del esquema), prefijo (ruta wire:model de los datos), deshabilitado. --}}
@props(['campos' => [], 'prefijo' => 'datos', 'deshabilitado' => false])

<fieldset @disabled($deshabilitado)>
@foreach($campos as $campo)
                @php
                    $tipo = $campo['tipo'] ?? 'texto';
                    $idCampo = 'campo-'.$campo['id'];
                    $conError = $errors->has("{$prefijo}.{$campo['id']}");
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
                        <textarea id="{{ $idCampo }}" wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}" rows="2"
                                  @class(['form-control form-control-sm', 'is-invalid' => $conError])></textarea>

                    @elseif($tipo === 'numero')
                        <div class="d-flex align-items-center gap-2">
                            <input id="{{ $idCampo }}" type="number" wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}"
                                   @class(['form-control form-control-sm w-auto', 'is-invalid' => $conError])>
                            @if($campo['unidad'] ?? null)
                                <span class="small text-body-secondary">{{ $campo['unidad'] }}</span>
                            @endif
                        </div>

                    @elseif($tipo === 'select')
                        <select id="{{ $idCampo }}" wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}"
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
                                       wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}" value="1">
                                <label class="form-check-label" for="{{ $idCampo }}-si">Sí</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="{{ $idCampo }}-no"
                                       wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}" value="0">
                                <label class="form-check-label" for="{{ $idCampo }}-no">No</label>
                            </div>
                        </div>

                    @elseif($tipo === 'fecha')
                        <input id="{{ $idCampo }}" type="date" wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}"
                               @class(['form-control form-control-sm w-auto', 'is-invalid' => $conError])>

                    @elseif($tipo === 'escala')
                        {{-- Solo puntuación total; el pase completo se hace en módulo Escalas --}}
                        <div class="d-flex align-items-center gap-2">
                            <input id="{{ $idCampo }}" type="number" min="0" wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}"
                                   @class(['form-control form-control-sm w-auto', 'is-invalid' => $conError])>
                            <span class="small text-body-secondary">puntuación total</span>
                        </div>
                    @endif

                    @error("{$prefijo}.{$campo['id']}")
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
@endforeach
</fieldset>
