<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">

    {{-- Barra superior con pestañas --}}
    <div style="background: #fff; border-bottom: 1px solid #E5E3F5; padding: 0 1.25rem; display: flex; align-items: stretch; flex-shrink: 0;">
        <h1 style="font-size: 1rem; font-weight: 700; margin: 0; color: #1D160E; align-self: center; padding: 0.75rem 1.5rem 0.75rem 0; border-right: 1px solid #E5E3F5; margin-right: 1rem;">Buzón</h1>

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
        <button wire:click="$set('modalNuevoMensaje', true)"
                style="margin-left: auto; align-self: center; background: #534AB7; border: none; color: #fff; padding: 0.35rem 0.9rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
            <i class="bi bi-pencil-square me-1"></i> Nuevo mensaje
        </button>
    </div>

    {{-- Cuerpo de dos columnas: lista + detalle --}}
    <div style="flex: 1; display: flex; overflow: hidden;">

        {{-- Lista --}}
        <div style="width: 320px; flex-shrink: 0; border-right: 1px solid #E5E3F5; overflow-y: auto; background: #FAFAFA;">

            @if($pestana === 'alertas')
                @forelse($this->alertas as $alerta)
                    <div wire:click="seleccionar({{ $alerta->id }})"
                         style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #F3F4F6; background: {{ $itemSeleccionado === $alerta->id ? '#EEEDFE' : 'transparent' }}; transition: background 0.1s;"
                         onmouseover="if({{ $itemSeleccionado !== $alerta->id ? 'true' : 'false' }}) this.style.background='#F8F7FF'"
                         onmouseout="if({{ $itemSeleccionado !== $alerta->id ? 'true' : 'false' }}) this.style.background='transparent'">
                        <div style="font-size: 0.85rem; font-weight: 600; color: #1D160E;">{{ $alerta->titulo }}</div>
                        @if($alerta->expira_en)
                            <div style="font-size: 0.72rem; color: #993C1D; margin-top: 0.15rem;">
                                Vence {{ $alerta->expira_en->diffForHumans() }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.82rem;">Sin alertas pendientes</div>
                @endforelse

            @elseif($pestana === 'avisos')
                @forelse($this->avisos as $aviso)
                    <div wire:click="seleccionar({{ $aviso->id }})"
                         style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #F3F4F6; background: {{ $itemSeleccionado === $aviso->id ? '#EEEDFE' : 'transparent' }}; transition: background 0.1s;">
                        <div style="font-size: 0.85rem; font-weight: 600; color: #1D160E;">{{ $aviso->titulo }}</div>
                        <div style="font-size: 0.72rem; color: #6B7280; margin-top: 0.15rem;">{{ $aviso->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.82rem;">Sin avisos</div>
                @endforelse

            @else
                @forelse($this->hilos as $participacion)
                    @php
                        $hilo = $participacion->hilo;
                        $noLeidos = $participacion->mensajesNoLeidos();
                    @endphp
                    <div wire:click="seleccionar({{ $participacion->id }})"
                         style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #F3F4F6; background: {{ $itemSeleccionado === $participacion->id ? '#EEEDFE' : 'transparent' }}; display: flex; align-items: flex-start; gap: 0.5rem; transition: background 0.1s;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 0.85rem; font-weight: {{ $noLeidos > 0 ? '700' : '500' }}; color: #1D160E; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ $hilo->ultimoMensaje?->usuario->name ?? 'Hilo' }}
                            </div>
                            <div style="font-size: 0.75rem; color: #6B7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.1rem;">
                                {{ Str::limit($hilo->ultimoMensaje?->cuerpo ?? '', 60) }}
                            </div>
                        </div>
                        @if($noLeidos > 0)
                            <span style="background: #534AB7; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 99px; flex-shrink: 0;">{{ $noLeidos }}</span>
                        @endif
                    </div>
                @empty
                    <div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.82rem;">Sin mensajes</div>
                @endforelse
            @endif

        </div>

        {{-- Detalle --}}
        <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">

            @if($itemSeleccionado === null)
                <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #9CA3AF; font-size: 0.875rem;">
                    Selecciona un elemento de la lista para ver el detalle.
                </div>

            @elseif($pestana !== 'mensajes' && $this->alertaSeleccionada)
                @php $a = $this->alertaSeleccionada; @endphp
                <div style="padding: 1.25rem; overflow-y: auto; flex: 1;">

                    {{-- Banner de urgencia (solo alertas con fecha de vencimiento) --}}
                    @if($pestana === 'alertas' && $a->expira_en)
                        <div style="background: #FAECE7; border: 1px solid #F0997B; border-radius: 8px; padding: 0.6rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ti ti-clock" style="color: #993C1D; font-size: 1.1rem;"></i>
                            <span style="font-size: 0.82rem; color: #712B13; font-weight: 600;">
                                Vence en {{ $a->expira_en->diffForHumans(now(), true) }}
                            </span>
                        </div>
                    @endif

                    <h2 style="font-size: 1rem; font-weight: 700; color: #1D160E; margin: 0 0 0.5rem;">{{ $a->titulo }}</h2>
                    <p style="font-size: 0.875rem; color: #374151; line-height: 1.6; margin: 0 0 1.25rem;">{{ $a->cuerpo }}</p>

                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        @if($pestana === 'alertas')
                            <button wire:click="reconocerAlerta({{ $a->id }})"
                                    style="background: #534AB7; border: none; color: #fff; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                <i class="bi bi-check-circle me-1"></i> Reconocer alerta
                            </button>
                        @else
                            <button wire:click="reconocerAlerta({{ $a->id }})"
                                    style="background: #fff; border: 1px solid #E5E3F5; color: #374151; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.8rem; cursor: pointer;">
                                Marcar como leído
                            </button>
                        @endif
                    </div>
                </div>

            @elseif($pestana === 'mensajes' && $this->hiloSeleccionado)
                @php $p = $this->hiloSeleccionado; $hilo = $p->hilo; @endphp

                {{-- Burbujeas de mensajes --}}
                <div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.6rem;">
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
                <div style="border-top: 1px solid #E5E3F5; padding: 0.75rem 1.25rem; background: #fff;">
                    <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                        <textarea wire:model="respuesta" rows="2"
                                  style="flex: 1; border: 1px solid #E5E3F5; border-radius: 6px; padding: 0.5rem; font-size: 0.85rem; resize: none; box-sizing: border-box;"
                                  placeholder="Escribe tu respuesta..."></textarea>
                        <button wire:click="enviarRespuesta({{ $hilo->id }})"
                                style="background: #534AB7; border: none; color: #fff; padding: 0.55rem 1rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; flex-shrink: 0; align-self: flex-end;">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>
