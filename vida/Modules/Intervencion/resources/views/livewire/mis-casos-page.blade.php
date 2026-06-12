@php
    use Carbon\Carbon;

    $hoy = today();

    /**
     * Calcula el estado del semáforo de seguimiento.
     * @param string|null $fecha
     */
    $estadoSeguimiento = function (?string $fecha) use ($hoy): string {
        if ($fecha === null) return 'sin';
        $f = Carbon::parse($fecha);
        if ($f->lt($hoy)) return 'vencido';
        if ($f->between($hoy, $hoy->copy()->addDays(7))) return 'proximo';
        return 'programado';
    };

    $semaforo = [
        'vencido'    => ['bg' => 'var(--color-danger-soft)',  'color' => 'var(--color-danger)',  'icon' => 'clock'],
        'proximo'    => ['bg' => 'var(--color-warning-soft)', 'color' => 'var(--color-warning)', 'icon' => ''],
        'programado' => ['bg' => 'var(--color-success-soft)', 'color' => 'var(--color-success)', 'icon' => ''],
        'sin'        => ['bg' => 'transparent',               'color' => 'var(--color-ink-400)', 'icon' => ''],
    ];

    $nombrePlan = $this->nombrePlanAsp();
@endphp

<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">

    {{-- Barra superior --}}
    <div style="background: #fff; border-bottom: 1px solid var(--color-ink-200); padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 1rem; flex-shrink: 0;">
        <h1 style="font-size: 1rem; font-weight: 700; margin: 0; color: var(--color-ink-900);">Mis casos</h1>

        {{-- Filtro seguimiento --}}
        <select wire:model.live="filtroSeguimiento" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem;">
            <option value="">Todos los seguimientos</option>
            <option value="vencido">Vencidos</option>
            <option value="proximo">Próximos (7 días)</option>
            <option value="programado">Programados</option>
            <option value="sin">Sin programar</option>
        </select>

        {{-- Filtro PISO --}}
        <select wire:model.live="filtroPiso" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem;">
            <option value="">Todos los {{ $nombrePlan }}</option>
            <option value="activo">{{ $nombrePlan }} activo</option>
            <option value="revision">{{ $nombrePlan }} en revisión</option>
            <option value="sin">Sin {{ $nombrePlan }}</option>
        </select>

        {{-- Filtro especializados --}}
        <select wire:model.live="filtroEsp" class="form-select form-select-sm" style="width: auto; font-size: 0.8rem;">
            <option value="">Con/sin especializados</option>
            <option value="con">Con derivación</option>
            <option value="sin">Sin derivación</option>
        </select>

        {{-- Orden --}}
        <select wire:model.live="ordenarPor" class="form-select form-select-sm ms-auto" style="width: auto; font-size: 0.8rem;">
            <option value="seg">Ordenar por seguimiento</option>
            <option value="nombre">Ordenar por nombre</option>
        </select>
    </div>

    {{-- Tabla de casos --}}
    <div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem;">

        @if($this->casos->isEmpty())
            <div style="text-align: center; padding: 3rem; color: var(--color-ink-600); font-size: 0.875rem;">
                No hay casos que coincidan con los filtros seleccionados.
            </div>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--color-ink-200); text-align: left;">
                        <th style="padding: 0.5rem 0.75rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600); font-weight: 600;">Ciudadano/a</th>
                        <th style="padding: 0.5rem 0.75rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600); font-weight: 600;">Historia Social</th>
                        <th style="padding: 0.5rem 0.75rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600); font-weight: 600;">Próximo seguimiento</th>
                        <th style="padding: 0.5rem 0.75rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600); font-weight: 600;">{{ $nombrePlan }}</th>
                        <th style="padding: 0.5rem 0.75rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600); font-weight: 600;">Especializados</th>
                        <th style="padding: 0.5rem 0.75rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600); font-weight: 600;">Inicio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->casos as $caso)
                        @php
                            $estado = $estadoSeguimiento($caso->fecha_siguiente_seguimiento);
                            $sem = $semaforo[$estado];
                        @endphp
                        {{-- El @click de la fila solo dispara si no se pulsó dentro de un <a> --}}
                        <tr style="border-bottom: 1px solid var(--color-ink-100); transition: background 0.1s; cursor: pointer;"
                            @click="$event.target.closest('a') || (window.location.href='{{ route('intervencion.ciudadano.show', $caso->historia_id) }}')"
                            onmouseover="this.style.background='var(--color-paper)'"
                            onmouseout="this.style.background=''">

                            {{-- Ciudadano/a: enlace a ficha del ciudadano --}}
                            <td style="padding: 0.6rem 0.75rem; font-weight: 600; color: var(--color-ink-900);">
                                <a href="{{ route('ciudadania.ciudadano.ficha', $caso->ciudadano_id) }}"
                                   style="color: var(--color-ink-900); text-decoration: none;">
                                    {{ $this->ciudadanosDelPage->get($caso->ciudadano_id)?->nombre_completo ?? 'Ciudadano #'.$caso->ciudadano_id }}
                                </a>
                            </td>

                            {{-- Historia Social: enlace a pantalla de intervención --}}
                            <td style="padding: 0.6rem 0.75rem; font-family: monospace; font-size: 0.8rem; color: var(--color-ink-600);">
                                <a href="{{ route('intervencion.ciudadano.show', $caso->historia_id) }}"
                                   style="color: var(--color-ink-600); text-decoration: none;">
                                    HS-{{ str_pad($caso->historia_id, 6, '0', STR_PAD_LEFT) }}
                                </a>
                            </td>

                            {{-- Semáforo seguimiento --}}
                            <td style="padding: 0.6rem 0.75rem;">
                                <span style="display: inline-flex; align-items: center; gap: 0.3rem; background: {{ $sem['bg'] }}; color: {{ $sem['color'] }}; padding: 0.2rem 0.55rem; border-radius: 99px; font-size: 0.78rem; font-weight: 600;">
                                    @if($sem['icon'])
                                        <i data-lucide="{{ $sem['icon'] }}" style="width:13px;height:13px;" aria-hidden="true"></i>
                                    @endif
                                    @if($caso->fecha_siguiente_seguimiento)
                                        {{ Carbon::parse($caso->fecha_siguiente_seguimiento)->format('d/m/Y') }}
                                    @else
                                        Sin programar
                                    @endif
                                </span>
                            </td>

                            {{-- Estado PISO --}}
                            <td style="padding: 0.6rem 0.75rem;">
                                <span style="display: inline-flex; align-items: center; gap: 0.25rem; background: var(--color-success-soft); color: var(--color-success); padding: 0.15rem 0.5rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600;">
                                    Activo
                                </span>
                            </td>

                            {{-- Planes especializados --}}
                            <td style="padding: 0.6rem 0.75rem; text-align: center;">
                                @if($caso->planes_esp_count > 0)
                                    <span style="background: var(--color-primary-soft); color: var(--color-primary); padding: 0.15rem 0.5rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600;">
                                        {{ $caso->planes_esp_count }}
                                    </span>
                                @else
                                    <span style="color: var(--color-ink-400); font-size: 0.78rem;">—</span>
                                @endif
                            </td>

                            {{-- Fecha inicio --}}
                            <td style="padding: 0.6rem 0.75rem; color: var(--color-ink-600); font-size: 0.8rem;">
                                {{ Carbon::parse($caso->fecha_inicio)->format('d/m/Y') }}
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Paginación --}}
            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 1rem; font-size: 0.8rem; color: var(--color-ink-600);">
                <span>{{ $this->casos->firstItem() }}–{{ $this->casos->lastItem() }} de {{ $this->casos->total() }} casos</span>
                <div style="display: flex; gap: 0.25rem;">
                    @if($this->casos->onFirstPage())
                        <span style="padding: 0.25rem 0.6rem; border: 1px solid var(--color-ink-200); border-radius: 4px; color: var(--color-ink-400);">‹</span>
                    @else
                        <button wire:click="previousPage" style="padding: 0.25rem 0.6rem; border: 1px solid var(--color-ink-200); border-radius: 4px; background: #fff; cursor: pointer; color: var(--color-primary);">‹</button>
                    @endif

                    @foreach(range(1, $this->casos->lastPage()) as $p)
                        <button wire:click="gotoPage({{ $p }})"
                                style="padding: 0.25rem 0.6rem; border: 1px solid {{ $this->casos->currentPage() === $p ? 'var(--color-primary)' : 'var(--color-ink-200)' }}; border-radius: 4px; background: {{ $this->casos->currentPage() === $p ? 'var(--color-primary)' : '#fff' }}; color: {{ $this->casos->currentPage() === $p ? '#fff' : 'var(--color-ink-700)' }}; cursor: pointer; font-size: 0.78rem;">
                            {{ $p }}
                        </button>
                    @endforeach

                    @if($this->casos->hasMorePages())
                        <button wire:click="nextPage" style="padding: 0.25rem 0.6rem; border: 1px solid var(--color-ink-200); border-radius: 4px; background: #fff; cursor: pointer; color: var(--color-primary);">›</button>
                    @else
                        <span style="padding: 0.25rem 0.6rem; border: 1px solid var(--color-ink-200); border-radius: 4px; color: var(--color-ink-400);">›</span>
                    @endif
                </div>
            </div>
        @endif

    </div>

</div>
