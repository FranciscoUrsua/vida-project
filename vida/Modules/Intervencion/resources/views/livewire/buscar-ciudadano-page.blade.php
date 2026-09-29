<div class="op-page">

    {{-- Barra de búsqueda --}}
    <div class="bg-white border-bottom px-3 px-lg-4 py-3">
        <form wire:submit.prevent="buscar" class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="buscar-campo" class="visually-hidden">Buscar por</label>
                <select id="buscar-campo" wire:model="campoBusqueda" class="form-select form-select-sm">
                    <option value="nombre">Nombre</option>
                    <option value="alias">Alias / apodo</option>
                    <option value="doc">DNI / NIE / Pasaporte</option>
                    <option value="hsu">NI-HSU-CM</option>
                </select>
            </div>
            <div class="col col-lg-4">
                <label for="buscar-termino" class="visually-hidden">Término de búsqueda</label>
                <input id="buscar-termino" wire:model="query" type="text"
                       class="form-control form-control-sm"
                       placeholder="Introduce el término de búsqueda..."
                       autocomplete="off" />
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <x-heroicon-o-magnifying-glass class="icon-14" aria-hidden="true"/> Buscar
                </button>
            </div>
        </form>

        @if($campoBusqueda === 'nombre')
            <p class="small text-body-secondary mt-2 mb-0">
                La búsqueda por nombre opera sobre datos cifrados y puede ser lenta.
            </p>
        @endif
        @if(in_array($campoBusqueda, ['doc', 'hsu']))
            <p class="small text-warning-emphasis mt-2 mb-0">
                {{-- TODO: implementar cuando exista la tabla ciudadano_identificadores --}}
                La búsqueda por documento no está disponible todavía.
            </p>
        @endif
    </div>

    {{-- Resultados --}}
    <div class="px-3 px-lg-4 py-3">

        @if(! $buscado)
            <p class="text-center text-body-secondary py-5 mb-0">
                Introduce un término y pulsa Buscar.
            </p>
        @elseif(count($resultados) === 0)
            <p class="text-center text-body-secondary py-4 mb-0">
                No se han encontrado ciudadanos/as con los criterios indicados.
            </p>
        @else
            <p class="small text-body-secondary mb-2">
                {{ count($resultados) }} resultado{{ count($resultados) !== 1 ? 's' : '' }}
            </p>

            @foreach($resultados as $resultado)
                @php
                    $puntoTexto = match($resultado['nivel']) {
                        3 => 'Colectivo especialmente protegido',
                        2 => 'Historia Social en otra UO',
                        default => 'Historia Social en tu UO',
                    };
                @endphp
                <div class="card mb-2" wire:key="resultado-{{ $resultado['ciudadano_id'] }}">
                    <div class="card-body py-2 d-flex align-items-center gap-3">

                        {{-- Indicador de nivel --}}
                        <span class="d-inline-block rounded-circle p-1 flex-shrink-0 {{ \Modules\Intervencion\Support\Ui\Tonos::nivelBusqueda($resultado['nivel'])->clasesPunto() }}" title="{{ $puntoTexto }}">
                            <span class="visually-hidden">{{ $puntoTexto }}</span>
                        </span>

                        <div class="flex-grow-1 text-truncate">
                            <div>
                                @if($resultado['nivel'] === 1 && $resultado['historia_id'])
                                    {{-- Nivel 1 (propia UO): nombre clicable --}}
                                    <a href="{{ route('intervencion.ciudadano.show', $resultado['historia_id']) }}"
                                       wire:navigate class="fw-semibold text-decoration-none">
                                        {{ $resultado['nombre'] }}
                                    </a>
                                @else
                                    {{-- Nivel 2 (otra UO), nivel 3 (protegido) o sin HS: nombre no es enlace;
                                         en nivel 2 el botón «Ver Historia Social» registra el acceso --}}
                                    <span class="fw-semibold">{{ $resultado['nombre'] }}</span>
                                @endif
                                @if($resultado['alias'])
                                    <span class="small text-body-secondary">({{ $resultado['alias'] }})</span>
                                @endif
                            </div>
                            @if($resultado['nivel'] === 2)
                                <div class="small text-warning-emphasis">Historia Social en otra unidad organizativa. El acceso queda registrado.</div>
                            @elseif($resultado['nivel'] === 3)
                                <div class="small text-protected-emphasis">Ciudadano/a de colectivo especialmente protegido. Requiere solicitud de acceso.</div>
                            @endif
                        </div>

                        {{-- Acciones según nivel --}}
                        @if($resultado['nivel'] === 1 && $resultado['historia_id'])
                            <a href="{{ route('intervencion.ciudadano.show', $resultado['historia_id']) }}"
                               wire:navigate class="btn btn-link btn-sm fw-semibold text-decoration-none text-nowrap">
                                Ir a Historia Social
                            </a>
                        @elseif($resultado['nivel'] === 2 && $resultado['historia_id'])
                            <button type="button" wire:click="registrarAccesoNivel2({{ $resultado['historia_id'] }})"
                                    class="btn btn-outline-warning btn-sm text-nowrap">
                                Ver Historia Social
                            </button>
                        @elseif($resultado['nivel'] === 3)
                            <button type="button" wire:click="abrirModalSolicitud({{ $resultado['ciudadano_id'] }})"
                                    class="btn btn-outline-protected btn-sm text-nowrap">
                                Solicitar acceso
                            </button>
                        @else
                            <span class="small text-body-secondary text-nowrap">Sin Historia Social</span>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Pie: dar de alta --}}
        <div class="card bg-body-tertiary mt-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-center gap-2 text-body-secondary">
                ¿No está la persona que buscas?
                <a href="{{ route('ciudadania.alta') }}" wire:navigate class="btn btn-primary btn-sm">
                    Dar de alta nuevo ciudadano/a
                </a>
            </div>
        </div>
    </div>

    {{-- Modal solicitud de acceso (nivel 3) --}}
    @if($modalSolicitud)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-solicitud-titulo">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 id="modal-solicitud-titulo" class="modal-title fs-6">Solicitar acceso - ciudadano/a protegido/a</h2>
                        <button wire:click="cerrarModalSolicitud" type="button" class="btn-close" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        <p class="small text-body-secondary mb-0">
                            Para acceder a la Historia Social de una persona de colectivo especialmente protegido
                            es necesaria la aprobación de un supervisor/a. Tu solicitud será revisada.
                        </p>

                        @error('justificacion')
                            <div class="alert alert-danger py-2 px-3 mb-0 small">
                                {{ $message }}
                            </div>
                        @enderror

                        <div>
                            <label for="solicitud-justificacion" class="form-label small fw-semibold mb-1">
                                Justificación <span class="fw-normal text-body-secondary">(mínimo 20 caracteres)</span>
                            </label>
                            <textarea id="solicitud-justificacion" wire:model="justificacion" rows="4"
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
