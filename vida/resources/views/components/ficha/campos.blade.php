{{-- Campos de una ficha a partir de su esquema (valoraciones, formularios de solicitud y fichas de la persona).
     Props: campos (lista del esquema), prefijo (ruta wire:model de los datos), deshabilitado,
     noContesta (null: sin la opción; array campo_id => bool: casilla «No desea contestar» por campo,
     enlazada a prefijoNoContesta; marcada, el campo se deshabilita, SD-9 de la ficha sociodemográfica). --}}
@props(['campos' => [], 'prefijo' => 'datos', 'deshabilitado' => false, 'noContesta' => null, 'prefijoNoContesta' => 'noContesta'])

<fieldset @disabled($deshabilitado)>
@foreach($campos as $campo)
                @php
                    $tipo = $campo['tipo'] ?? 'texto';
                    $idCampo = 'campo-'.$campo['id'];
                    $conError = $errors->has("{$prefijo}.{$campo['id']}");
                    $noContestaMarcado = is_array($noContesta) && ! empty($noContesta[$campo['id']]);
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

                    {{-- Un fieldset por campo: «No desea contestar» deshabilita también el selector de catálogo anidado --}}
                    <fieldset @disabled($noContestaMarcado)>
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

                    @elseif($tipo === 'catalogo')
                        {{-- Guarda la clave del catálogo; el servicio la vuelve a validar (SD-4) --}}
                        <livewire:fichas.selector-catalogo
                            wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}"
                            :grupo="$campo['catalogo'] ?? ''"
                            :id-campo="$idCampo"
                            :deshabilitado="(bool) $deshabilitado"
                            :wire:key="'catalogo-'.$prefijo.'-'.$campo['id']" />

                    @elseif($tipo === 'escala')
                        {{-- Solo puntuación total; el pase completo se hace en módulo Escalas --}}
                        <div class="d-flex align-items-center gap-2">
                            <input id="{{ $idCampo }}" type="number" min="0" wire:model.live="{{ $prefijo }}.{{ $campo['id'] }}"
                                   @class(['form-control form-control-sm w-auto', 'is-invalid' => $conError])>
                            <span class="small text-body-secondary">puntuación total</span>
                        </div>
                    @endif
                    </fieldset>

                    @if(is_array($noContesta))
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="{{ $idCampo }}-no-contesta"
                                   wire:model.live="{{ $prefijoNoContesta }}.{{ $campo['id'] }}">
                            <label class="form-check-label small text-body-secondary" for="{{ $idCampo }}-no-contesta">No desea contestar</label>
                        </div>
                    @endif

                    @error("{$prefijo}.{$campo['id']}")
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
@endforeach
</fieldset>
