{{-- Menú lateral único de la superficie operativa (R4.2): App\Support\Menu\RegistroMenu --}}
{{-- Se refresca cada 60 segundos para mantener los contadores al día --}}
<aside class="op-sidebar" wire:poll.60s>
    <nav class="op-nav" aria-label="Navegación principal">
        @foreach($this->secciones as $seccion => $entradas)
            <div role="group" aria-labelledby="menu-seccion-{{ $seccion }}">
                <div id="menu-seccion-{{ $seccion }}" class="small fw-semibold text-uppercase text-body-secondary px-3 pt-3 pb-1">
                    {{ \App\Support\Menu\RegistroMenu::SECCIONES[$seccion] }}
                </div>

                {{-- Centro supervisado (componente aparte: el sondeo no lo vuelve a pintar) --}}
                @if($seccion === 'supervision')
                    <livewire:supervision.selector-centro />
                @endif

                @foreach($entradas as $entrada)
                    @php $activa = $entrada->activa(); @endphp
                    <a href="{{ $entrada->url($usuario) }}"
                       @class(['op-nav-item', 'op-nav-item--activo' => $activa])
                       aria-current="{{ $activa ? 'page' : 'false' }}"
                       data-menu="{{ $entrada->clave }}">
                        <x-dynamic-component :component="'heroicon-o-' . $entrada->icono" class="op-nav-icon icon-18" aria-hidden="true"/>
                        <span>{{ $entrada->etiqueta }}</span>
                        @if($entrada->contador !== null && ($total = $this->contador($entrada->contador)) > 0)
                            <span @class(['op-nav-badge', 'op-nav-badge--alerta' => $entrada->contadorAlerta])>
                                {{ $total }}<span class="visually-hidden"> pendientes</span>
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>
