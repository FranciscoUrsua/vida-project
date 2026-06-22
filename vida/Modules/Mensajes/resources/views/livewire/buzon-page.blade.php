<div class="mensajes-buzon">

    {{-- Barra superior con pestañas --}}
    <div class="mensajes-buzon__tabs-bar">
        <h1 class="mensajes-buzon__title">Buzón</h1>

        {{-- Pestañas --}}
        @foreach([
            ['alertas', 'Alertas', count($this->alertas)],
            ['avisos', 'Avisos', count($this->avisos)],
            ['mensajes', 'Mensajes', 0],
        ] as [$tab, $label, $badge])
            <button wire:click="cambiarPestana('{{ $tab }}')"
                    style="background: none; border: none; padding: 0.75rem 1rem; font-size: 0.85rem; font-weight: {{ $pestana === $tab ? '700' : '500' }}; color: {{ $pestana === $tab ? '#534AB7' : '#6B7280' }}; border-bottom: 2.5px solid {{ $pestana === $tab ? '#534AB7' : 'transparent' }}; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; transition: color 0.1s;">
                {{ $label }}
                @if($badge > 0)
                    <span style="background: #F0997B; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 0.05rem 0.4rem; border-radius: 99px;">{{ $badge }}</span>
                @endif
            </button>
        @endforeach

        {{-- Nuevo mensaje --}}
        <button wire:click="abrirModalNuevoMensaje"
                style="margin-left: auto; align-self: center; background: var(--color-primary); border: none; color: #fff; padding: 0.35rem 0.9rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.35rem;">
            <x-heroicon-o-pencil class="icon-14" aria-hidden="true"/>
            Nuevo mensaje
        </button>
    </div>

    {{-- Cuerpo de dos columnas: lista + detalle --}}
    <div class="mensajes-buzon__content">

        {{-- Lista --}}
        <div class="mensajes-buzon__list">

            @if($pestana === 'alertas')
                @forelse($this->alertas as $alerta)
                    <div wire:click="seleccionar({{ $alerta->id }})"
                         style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #F3F4F6; background: {{ $itemSeleccionado === $alerta->id ? '#EEEDFE' : 'transparent' }}; transition: background 0.1s;"
                         onmouseover="if({{ $itemSeleccionado !== $alerta->id ? 'true' : 'false' }}) this.style.background='#F8F7FF'"
                         onmouseout="if({{ $itemSeleccionado !== $alerta->id ? 'true' : 'false' }}) this.style.background='transparent'">
                        <div style="font-size: 0.85rem; font-weight: 600; color: #1D160E;">{{ $alerta->titulo }}</div>
                        @if($alerta->expira_en)
                            <div class="mensajes-buzon__meta-alert">
                                Vence {{ $alerta->expira_en->diffForHumans() }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="mensajes-buzon__empty">Sin alertas pendientes</div>
                @endforelse

            @elseif($pestana === 'avisos')
                @forelse($this->avisos as $aviso)
                    <div wire:click="seleccionar({{ $aviso->id }})"
                         style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #F3F4F6; background: {{ $itemSeleccionado === $aviso->id ? '#EEEDFE' : 'transparent' }}; transition: background 0.1s;">
                        <div style="font-size: 0.85rem; font-weight: 600; color: #1D160E;">{{ $aviso->titulo }}</div>
                        <div style="font-size: 0.72rem; color: #6B7280; margin-top: 0.15rem;">{{ $aviso->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="mensajes-buzon__empty">Sin avisos</div>
                @endforelse

            @else
                @forelse($this->hilos as $participacion)
                    @php
                        $hilo = $participacion->hilo;
                        $noLeidos = $participacion->mensajesNoLeidos();
                    @endphp
                    <div wire:click="seleccionar({{ $participacion->id }})"
                         style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #F3F4F6; background: {{ $itemSeleccionado === $participacion->id ? '#EEEDFE' : 'transparent' }}; display: flex; align-items: flex-start; gap: 0.5rem; transition: background 0.1s;">
                        <div class="mensajes-buzon__thread-main">
                            <div style="font-size: 0.85rem; font-weight: {{ $noLeidos > 0 ? '700' : '500' }}; color: #1D160E; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ $hilo->ultimoMensaje?->usuario->name ?? 'Hilo' }}
                            </div>
                            <div class="mensajes-buzon__thread-preview">
                                {{ Str::limit($hilo->ultimoMensaje?->cuerpo ?? '', 60) }}
                            </div>
                        </div>
                        @if($noLeidos > 0)
                            <span style="background: #534AB7; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 99px; flex-shrink: 0;">{{ $noLeidos }}</span>
                        @endif
                    </div>
                @empty
                    <div class="mensajes-buzon__empty">Sin mensajes</div>
                @endforelse
            @endif

        </div>

        {{-- Detalle --}}
        <div class="mensajes-buzon__detail">

            @if($itemSeleccionado === null)
                <div class="mensajes-buzon__empty-detail">
                    Selecciona un elemento de la lista para ver el detalle.
                </div>

            @elseif($pestana !== 'mensajes' && $this->alertaSeleccionada)
                @php $a = $this->alertaSeleccionada; @endphp
                <div class="mensajes-buzon__detail-body">

                    {{-- Banner de urgencia (solo alertas con fecha de vencimiento) --}}
                    @if($pestana === 'alertas' && $a->expira_en)
                        <div class="mensajes-buzon__urgency">
                            <x-heroicon-o-clock class="mensajes-buzon__urgency-icon icon-14"/>
                            <span class="mensajes-buzon__urgency-text">
                                Vence en {{ $a->expira_en->diffForHumans(now(), true) }}
                            </span>
                        </div>
                    @endif

                    <h2 style="font-size: 1rem; font-weight: 700; color: #1D160E; margin: 0 0 0.5rem;">{{ $a->titulo }}</h2>
                    <p style="font-size: 0.875rem; color: #374151; line-height: 1.6; margin: 0 0 1.25rem;">{{ $a->cuerpo }}</p>

                    <div class="mensajes-buzon__actions">
                        @if($pestana === 'alertas')
                            <button wire:click="reconocerAlerta({{ $a->id }})"
                                    class="btn btn-primary btn-sm">
                                <x-heroicon-o-check-circle class="me-1 icon-14"/> Reconocer alerta
                            </button>
                        @else
                            <button wire:click="reconocerAlerta({{ $a->id }})"
                                    class="btn btn-outline-secondary btn-sm">
                                Marcar como leído
                            </button>
                        @endif
                    </div>
                </div>

            @elseif($pestana === 'mensajes' && $this->hiloSeleccionado)
                @php $p = $this->hiloSeleccionado; $hilo = $p->hilo; @endphp

                {{-- Burbujeas de mensajes --}}
                <div class="mensajes-buzon__thread-panel">
                    @foreach($hilo->mensajes as $mensaje)
                        @php $esMio = $mensaje->remitente_id === auth()->id(); @endphp
                        <div style="display: flex; flex-direction: {{ $esMio ? 'row-reverse' : 'row' }}; gap: 0.5rem; align-items: flex-end;">
                            <div style="max-width: 65%; background: {{ $esMio ? '#EEEDFE' : '#F3F4F6' }}; border-radius: {{ $esMio ? '12px 12px 2px 12px' : '12px 12px 12px 2px' }}; padding: 0.5rem 0.85rem; font-size: 0.85rem; color: #1D160E; line-height: 1.5;">
                                @if(! $esMio)
                                    <div style="font-size: 0.7rem; font-weight: 600; color: #534AB7; margin-bottom: 0.15rem;">{{ $mensaje->remitente->name }}</div>
                                @endif
                                {{ $mensaje->cuerpo }}
                                <div style="font-size: 0.65rem; color: #9CA3AF; margin-top: 0.25rem; text-align: {{ $esMio ? 'right' : 'left' }};">{{ $mensaje->created_at->format('d/m H:i') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Área de respuesta --}}
                <div class="mensajes-buzon__composer">
                    <div class="mensajes-buzon__composer-row">
                        <textarea wire:model="respuesta" rows="2"
                                  class="mensajes-buzon__composer-input"
                                  placeholder="Escribe tu respuesta..."></textarea>
                        <button wire:click="enviarRespuesta({{ $hilo->id }})"
                                class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center">
                            <x-heroicon-o-paper-airplane class="icon-14"/>
                        </button>
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- Modal de nuevo mensaje --}}
    @if($modalNuevoMensaje)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6">Nuevo mensaje</h2>
                        <button wire:click="$set('modalNuevoMensaje', false)" type="button" class="btn-close" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body d-flex flex-column gap-3">
                        {{-- Destinatario con autocompletado --}}
                        <div class="position-relative">
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                Para
                            </label>
                            <input wire:model.live.debounce.300ms="destinatarioBusqueda"
                                   wire:updated="buscarDestinatario"
                                   type="text"
                                   autocomplete="off"
                                   placeholder="Buscar profesional por nombre..."
                                   class="form-control form-control-sm" />

                            @if(count($resultadosDestinatario) > 0)
                                <div class="position-absolute top-100 start-0 end-0 bg-white border rounded shadow-sm z-3 overflow-auto" style="max-height: 200px;">
                                    <div class="list-group list-group-flush">
                                        @foreach($resultadosDestinatario as $res)
                                            <button wire:click="seleccionarDestinatario({{ $res['id'] }}, '{{ addslashes($res['nombre']) }}')"
                                                    type="button"
                                                    class="list-group-item list-group-item-action">
                                                <span class="d-block small fw-semibold text-body">{{ $res['nombre'] }}</span>
                                                <span class="d-block small text-secondary">{{ $res['rol'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($destinatarioNombre)
                                <div class="mt-2 small text-success">
                                    <x-heroicon-o-check-circle class="icon-12" aria-hidden="true"/>
                                    Seleccionado: {{ $destinatarioNombre }}
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                Asunto
                            </label>
                            <input wire:model="asunto"
                                   type="text"
                                   maxlength="200"
                                   placeholder="Asunto del mensaje"
                                   class="form-control form-control-sm" />
                        </div>

                        <div>
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                Mensaje
                            </label>
                            <textarea wire:model="cuerpo"
                                      rows="5"
                                      placeholder="Escribe tu mensaje..."
                                      class="form-control form-control-sm"></textarea>
                        </div>

                        @if($errors->any())
                            <div class="alert alert-danger py-2 px-3 mb-0 small">
                                @foreach($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button wire:click="$set('modalNuevoMensaje', false)" type="button" class="btn btn-outline-secondary btn-sm">
                            Cancelar
                        </button>
                        <button wire:click="enviarMensaje" type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                            <x-heroicon-o-paper-airplane class="icon-14" aria-hidden="true"/>
                            Enviar mensaje
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

</div>
