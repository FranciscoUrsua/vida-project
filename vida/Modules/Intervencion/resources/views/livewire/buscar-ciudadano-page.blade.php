<div class="buscar-ciudadano-page">

    {{-- Barra superior --}}
    <div style="background: #fff; border-bottom: 1px solid var(--color-ink-200); padding: 0.75rem 1.25rem; flex-shrink: 0;">
        <form wire:submit.prevent="buscar" class="buscar-ciudadano-page__form">
            {{-- Campo de búsqueda --}}
            <select wire:model="campoBusqueda" class="form-select form-select-sm buscar-ciudadano-page__select">
                <option value="nombre">Nombre</option>
                <option value="alias">Alias / apodo</option>
                <option value="doc">DNI / NIE / Pasaporte</option>
                <option value="hsu">NI-HSU-CM</option>
            </select>

            <input wire:model="query" type="text"
                   class="form-control form-control-sm buscar-ciudadano-page__input"
                   placeholder="Introduce el término de búsqueda..."
                   autocomplete="off" />

            <button type="submit" class="btn btn-primary btn-sm">
                <x-icon name="search" class="icon-14" aria-hidden="true"/> Buscar
            </button>
        </form>

        @if($campoBusqueda === 'nombre')
            <p style="font-size: 0.72rem; color: var(--color-ink-400); margin: 0.3rem 0 0;">
                La búsqueda por nombre opera sobre datos cifrados y puede ser lenta.
            </p>
        @endif
        @if(in_array($campoBusqueda, ['doc', 'hsu']))
            <p style="font-size: 0.72rem; color: var(--color-warning); margin: 0.3rem 0 0;">
                <!-- TODO: implementar cuando exista la tabla ciudadano_identificadores -->
                La búsqueda por documento no está disponible todavía.
            </p>
        @endif
    </div>

    {{-- Resultados --}}
    <div class="buscar-ciudadano-page__results">

        @if(! $buscado)
            <div style="text-align: center; padding: 3rem; color: var(--color-ink-400); font-size: 0.875rem;">
                Introduce un término y pulsa Buscar.
            </div>
        @elseif(count($resultados) === 0)
            <div style="text-align: center; padding: 2rem; color: var(--color-ink-600); font-size: 0.875rem;">
                No se han encontrado ciudadanos/as con los criterios indicados.
            </div>
        @else
            <div style="margin-bottom: 0.5rem; font-size: 0.78rem; color: var(--color-ink-600);">
                {{ count($resultados) }} resultado{{ count($resultados) !== 1 ? 's' : '' }}
            </div>

            @foreach($resultados as $resultado)
                <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem;">

                    {{-- Indicador de nivel --}}
                    @if($resultado['nivel'] === 3)
                        <span title="Colectivo especialmente protegido"
                              style="width: 10px; height: 10px; border-radius: 50%; background: var(--color-protected); flex-shrink: 0;"></span>
                    @elseif($resultado['nivel'] === 2)
                        <span title="Historia Social en otra UO"
                              style="width: 10px; height: 10px; border-radius: 50%; background: var(--color-warning); flex-shrink: 0;"></span>
                    @else
                        <span title="Historia Social en tu UO"
                              style="width: 10px; height: 10px; border-radius: 50%; background: var(--color-success); flex-shrink: 0;"></span>
                    @endif

                    <div class="buscar-ciudadano-page__result-main">
                        <div class="buscar-ciudadano-page__result-name">
                            @if($resultado['nivel'] === 1 && $resultado['historia_id'])
                                {{-- Nivel 1 (propia UO): nombre clicable --}}
                                <a href="{{ route('intervencion.ciudadano.show', $resultado['historia_id']) }}"
                                   wire:navigate
                                   style="font-weight: 600; color: var(--color-primary); text-decoration: none;">
                                    {{ $resultado['nombre'] }}
                                </a>
                            @elseif($resultado['nivel'] === 2 && $resultado['historia_id'])
                                {{-- Nivel 2 (otra UO): nombre no es enlace; el boton "Ver Historia Social" registra el acceso --}}
                                <span style="font-weight: 600; color: var(--color-ink-900);">{{ $resultado['nombre'] }}</span>
                            @else
                                {{-- Nivel 3 (protegido) o sin HS: nombre no es enlace --}}
                                <span style="font-weight: 600; color: var(--color-ink-900);">{{ $resultado['nombre'] }}</span>
                            @endif
                            @if($resultado['alias'])
                                <span style="font-weight: 400; color: var(--color-ink-600); font-size: 0.78rem;">({{ $resultado['alias'] }})</span>
                            @endif
                        </div>
                        @if($resultado['nivel'] === 2)
                            <div style="font-size: 0.75rem; color: var(--color-warning);">Historia Social en otra unidad organizativa. El acceso queda registrado.</div>
                        @elseif($resultado['nivel'] === 3)
                            <div style="font-size: 0.75rem; color: var(--color-protected);">Ciudadano/a de colectivo especialmente protegido. Requiere solicitud de acceso.</div>
                        @endif
                    </div>

                    {{-- Acciones según nivel --}}
                    @if($resultado['nivel'] === 1 && $resultado['historia_id'])
                        <a href="{{ route('intervencion.ciudadano.show', $resultado['historia_id']) }}"
                           wire:navigate
                           style="font-size: 0.8rem; color: var(--color-primary); font-weight: 600; text-decoration: none; white-space: nowrap;">
                            Ir a Historia Social
                        </a>
                    @elseif($resultado['nivel'] === 2 && $resultado['historia_id'])
                        <button wire:click="registrarAccesoNivel2({{ $resultado['historia_id'] }})"
                                style="font-size: 0.8rem; background: none; border: 1px solid var(--color-warning); color: var(--color-warning); padding: 0.25rem 0.75rem; border-radius: 6px; cursor: pointer; white-space: nowrap; font-weight: 600;">
                            Ver Historia Social
                        </button>
                    @elseif($resultado['nivel'] === 3)
                        <button wire:click="abrirModalSolicitud({{ $resultado['ciudadano_id'] }})"
                                style="font-size: 0.8rem; background: none; border: 1px solid var(--color-protected); color: var(--color-protected); padding: 0.25rem 0.75rem; border-radius: 6px; cursor: pointer; white-space: nowrap; font-weight: 600;">
                            Solicitar acceso
                        </button>
                    @else
                        <span style="font-size: 0.78rem; color: var(--color-ink-400); white-space: nowrap;">Sin Historia Social</span>
                    @endif
                </div>
            @endforeach
        @endif

        {{-- Pie: dar de alta --}}
        <div style="margin-top: 2rem; padding: 1rem; border: 1px dashed var(--color-ink-200); border-radius: 8px; text-align: center; color: var(--color-ink-600); font-size: 0.85rem;">
            ¿No está la persona que buscas?
            <a href="{{ route('ciudadania.alta') }}" wire:navigate
               style="margin-left: 0.75rem; font-size: 0.8rem; background: var(--color-primary); border: none; color: #fff; padding: 0.3rem 0.85rem; border-radius: 6px; text-decoration: none; display: inline-block;">
                Dar de alta nuevo ciudadano/a
            </a>
        </div>
    </div>

    {{-- Modal solicitud de acceso (nivel 3) --}}
    @if($modalSolicitud)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6">Solicitar acceso - ciudadano/a protegido/a</h2>
                        <button wire:click="cerrarModalSolicitud" type="button" class="btn-close" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        <p class="small text-secondary mb-0">
                            Para acceder a la Historia Social de una persona de colectivo especialmente protegido
                            es necesaria la aprobación de un supervisor/a. Tu solicitud será revisada.
                        </p>

                        @error('justificacion')
                            <div class="alert alert-danger py-2 px-3 mb-0 small">
                                {{ $message }}
                            </div>
                        @enderror

                        <div>
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                Justificación <span class="fw-normal text-secondary">(mínimo 20 caracteres)</span>
                            </label>
                            <textarea wire:model="justificacion" rows="4"
                                      class="form-control form-control-sm"
                                      placeholder="Describe el motivo asistencial que justifica el acceso..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button wire:click="cerrarModalSolicitud" type="button" class="btn btn-outline-secondary btn-sm">
                            Cancelar
                        </button>
                        <button wire:click="solicitarAcceso({{ $ciudadanoSolicitudId }}, '{{ addslashes($justificacion) }}')" type="button" class="btn btn-primary btn-sm">
                            Enviar solicitud
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

</div>
