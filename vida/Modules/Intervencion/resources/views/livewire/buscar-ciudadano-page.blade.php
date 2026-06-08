<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">

    {{-- Barra superior --}}
    <div style="background: #fff; border-bottom: 1px solid var(--color-ink-200); padding: 0.75rem 1.25rem; flex-shrink: 0;">
        <h1 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--color-ink-900);">Buscar ciudadano/a</h1>

        <form wire:submit.prevent="buscar" style="display: flex; gap: 0.5rem; align-items: flex-end;">
            {{-- Campo de búsqueda --}}
            <select wire:model="campoBusqueda" class="form-select form-select-sm" style="width: 140px; font-size: 0.8rem;">
                <option value="nombre">Nombre</option>
                <option value="alias">Alias / apodo</option>
                <option value="doc">DNI / NIE / Pasaporte</option>
                <option value="hsu">NI-HSU-CM</option>
            </select>

            <input wire:model="query" type="text" class="form-control form-control-sm"
                   placeholder="Introduce el término de búsqueda..."
                   style="flex: 1; font-size: 0.85rem;"
                   autocomplete="off" />

            <button type="submit" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">
                <i data-lucide="search" style="width:14px;height:14px;" aria-hidden="true"></i> Buscar
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
    <div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem;">

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

                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: var(--color-ink-900); font-size: 0.9rem;">
                            {{ $resultado['nombre'] }}
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
                        {{-- TODO: Entrega 3 — route('intervencion.ciudadano.show', $resultado['ciudadano_id']) --}}
                        <a href="#" style="font-size: 0.8rem; color: var(--color-primary); font-weight: 600; text-decoration: none; white-space: nowrap;">
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
            <button disabled title="Pendiente de implementación"
                    style="margin-left: 0.75rem; font-size: 0.8rem; background: var(--color-ink-100); border: 1px solid var(--color-ink-200); color: var(--color-ink-400); padding: 0.3rem 0.85rem; border-radius: 6px; cursor: not-allowed;">
                Dar de alta nuevo ciudadano/a
            </button>
        </div>
    </div>

    {{-- Modal solicitud de acceso (nivel 3) --}}
    @if($modalSolicitud)
        <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000;">
            <div style="background: #fff; border-radius: 12px; padding: 1.5rem; max-width: 480px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,0.15);">
                <h2 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-ink-900);">Solicitar acceso — ciudadano/a protegido/a</h2>
                <p style="font-size: 0.85rem; color: var(--color-ink-600); margin: 0 0 1rem;">
                    Para acceder a la Historia Social de una persona de colectivo especialmente protegido
                    es necesaria la aprobación de un supervisor/a. Tu solicitud será revisada.
                </p>

                @error('justificacion')
                    <div style="background: var(--color-danger-soft); color: var(--color-danger-ink); padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.8rem; margin-bottom: 0.75rem;">
                        {{ $message }}
                    </div>
                @enderror

                <label style="font-size: 0.8rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.3rem;">
                    Justificación <span style="color: var(--color-ink-400); font-weight: 400;">(mínimo 20 caracteres)</span>
                </label>
                <textarea wire:model="justificacion" rows="4"
                          style="width: 100%; border: 1px solid var(--color-ink-200); border-radius: 6px; padding: 0.5rem; font-size: 0.85rem; resize: vertical; box-sizing: border-box;"
                          placeholder="Describe el motivo asistencial que justifica el acceso..."></textarea>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                    <button wire:click="cerrarModalSolicitud"
                            style="font-size: 0.8rem; background: #fff; border: 1px solid var(--color-ink-200); color: var(--color-ink-700); padding: 0.4rem 1rem; border-radius: 6px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button wire:click="solicitarAcceso({{ $ciudadanoSolicitudId }}, '{{ addslashes($justificacion) }}')"
                            style="font-size: 0.8rem; background: var(--color-primary); border: none; color: #fff; padding: 0.4rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                        Enviar solicitud
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
