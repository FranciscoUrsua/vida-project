@php
    use Carbon\Carbon;

    $ancla = Carbon::parse($fechaAncla)->locale('es');
    $hoy = today()->toDateString();

    // Estilos por tipo de cita
    $estiloCita = [
        'entrevista'  => ['bg' => 'var(--color-primary-soft)', 'border' => 'var(--color-primary)',  'label' => 'Entrevista'],
        'seguimiento' => ['bg' => 'var(--color-success-soft)', 'border' => 'var(--color-success)',  'label' => 'Seguimiento'],
        'urgencia'    => ['bg' => 'var(--color-danger-soft)',  'border' => 'var(--color-danger)',   'label' => 'Urgencia'],
        'evento'      => ['bg' => 'var(--color-sand)',         'border' => 'var(--color-ink-300)',  'label' => 'Evento'],
    ];

    // Colores para chips de vista mes (base y soft)
    $coloresTipo = [
        'urgencia'    => 'var(--color-danger)',
        'entrevista'  => 'var(--color-primary)',
        'seguimiento' => 'var(--color-success)',
        'evento'      => 'var(--color-ink-500)',
    ];
    $coloresTipoSoft = [
        'urgencia'    => 'var(--color-danger-soft)',
        'entrevista'  => 'var(--color-primary-soft)',
        'seguimiento' => 'var(--color-success-soft)',
        'evento'      => 'var(--color-ink-100)',
    ];

    // Horas de la jornada (vista semana y slots libres)
    $horas = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00'];
@endphp

<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Barra superior                                                       --}}
    {{-- ------------------------------------------------------------------ --}}
    <div style="background: #fff; border-bottom: 1px solid var(--color-ink-200); padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0;">

        <h1 style="font-size: 1rem; font-weight: 700; margin: 0; color: var(--color-ink-900);">Agenda</h1>

        <span style="font-size: 0.875rem; color: var(--color-primary); font-weight: 500; margin-right: 0.25rem;">
            {{ $this->tituloFecha }}
        </span>

        {{-- Navegación anterior / siguiente / hoy --}}
        <button wire:click="navegarAnterior" class="btn btn-sm btn-outline-secondary" aria-label="Período anterior" style="padding: 0.2rem 0.55rem;">
            <i data-lucide="chevron-left" style="width:16px;height:16px;" aria-hidden="true"></i>
        </button>
        <button wire:click="navegarSiguiente" class="btn btn-sm btn-outline-secondary" aria-label="Período siguiente" style="padding: 0.2rem 0.55rem;">
            <i data-lucide="chevron-right" style="width:16px;height:16px;" aria-hidden="true"></i>
        </button>
        <button wire:click="irAHoy" class="btn btn-sm btn-outline-primary" style="font-size: 0.8rem;">Hoy</button>

        {{-- Selector de vista --}}
        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Vista de agenda">
            <button wire:click="setVista('dia')"
                    class="btn {{ $vista === 'dia' ? 'btn-primary' : 'btn-outline-primary' }}"
                    style="font-size: 0.8rem;">
                Día
            </button>
            <button wire:click="setVista('semana')"
                    class="btn {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}"
                    style="font-size: 0.8rem;">
                Semana
            </button>
            <button wire:click="setVista('mes')"
                    class="btn {{ $vista === 'mes' ? 'btn-primary' : 'btn-outline-primary' }}"
                    style="font-size: 0.8rem;">
                Mes
            </button>
        </div>

    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Franja de KPIs                                                       --}}
    {{-- ------------------------------------------------------------------ --}}
    <div style="background: var(--color-paper); border-bottom: 1px solid var(--color-ink-200); padding: 0.65rem 1.25rem; display: flex; gap: 1rem; flex-shrink: 0;">

        {{-- KPI: Alertas sin reconocer --}}
        <div style="flex: 1; background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: {{ $this->kpis['alertas_sin_reconocer'] > 0 ? 'var(--color-accent)' : 'var(--color-ink-900)' }};">
                {{ $this->kpis['alertas_sin_reconocer'] }}
            </div>
            <div style="font-size: 0.72rem; color: var(--color-ink-600); text-transform: uppercase; letter-spacing: 0.04em;">Alertas sin reconocer</div>
        </div>

        {{-- KPI: Seguimientos vencidos --}}
        <div style="flex: 1; background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: {{ $this->kpis['seguimientos_vencidos'] > 0 ? 'var(--color-warning)' : 'var(--color-ink-900)' }};">
                {{ $this->kpis['seguimientos_vencidos'] }}
            </div>
            <div style="font-size: 0.72rem; color: var(--color-ink-600); text-transform: uppercase; letter-spacing: 0.04em;">Seguimientos vencidos</div>
        </div>

        {{-- KPI: Citas (label dinámico según vista) --}}
        <div style="flex: 1; background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: var(--color-ink-900);">
                {{ $this->kpis['citas'] }}
            </div>
            <div style="font-size: 0.72rem; color: var(--color-ink-600); text-transform: uppercase; letter-spacing: 0.04em;">
                @if($vista === 'dia')
                    Citas hoy
                @elseif($vista === 'semana')
                    Citas esta semana
                @else
                    Citas este mes
                @endif
            </div>
        </div>

        {{-- KPI: Mensajes sin leer --}}
        <div style="flex: 1; background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: var(--color-ink-900);">
                {{ $this->kpis['mensajes_sin_leer'] }}
            </div>
            <div style="font-size: 0.72rem; color: var(--color-ink-600); text-transform: uppercase; letter-spacing: 0.04em;">Mensajes sin leer</div>
        </div>

    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Área de contenido                                                    --}}
    {{-- ------------------------------------------------------------------ --}}
    <div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem;">

        @if($vista === 'dia')
            {{-- ============================================================ --}}
            {{-- Vista día: 4 columnas                                         --}}
            {{-- ============================================================ --}}
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; align-items: start;">
                @foreach($this->citasDia as $fecha => $citas)
                    @php
                        $col = Carbon::parse($fecha)->locale('es');
                        $esHoy = $fecha === $hoy;
                        $esPasado = $fecha < $hoy;
                    @endphp
                    <div style="{{ $esPasado ? 'opacity: 0.5;' : '' }}">

                        {{-- Cabecera de columna --}}
                        <div style="text-align: center; margin-bottom: 0.5rem; padding: 0.3rem; border-radius: 6px; {{ $esHoy ? 'background: var(--color-primary-soft);' : '' }}">
                            <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600);">
                                {{ $col->isoFormat('ddd') }}
                            </div>
                            <div style="font-size: 1.4rem; font-weight: 700; color: {{ $esHoy ? 'var(--color-primary)' : 'var(--color-ink-900)' }}; line-height: 1.2;">
                                {{ $col->day }}
                            </div>
                        </div>

                        {{-- Citas --}}
                        @forelse($citas as $cita)
                            @php $estilo = $estiloCita[$cita['tipo']] ?? $estiloCita['evento']; @endphp
                            @if($cita['historia_id'] && auth()->user()->hasRole('intervencion'))
                                {{-- TSR + historia: va a pantalla de intervención --}}
                                <a href="{{ route('intervencion.ciudadano.show', $cita['historia_id']) }}"
                                   wire:navigate
                                   style="display: block; background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 6px 6px 0; padding: 0.4rem 0.6rem; margin-bottom: 0.4rem; font-size: 0.8rem; text-decoration: none;">
                                    @if($cita['tipo'] === 'urgencia')
                                        <span style="display: inline-block; background: var(--color-danger-soft); color: var(--color-danger-ink); font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 99px; margin-bottom: 0.2rem;">Urgencia</span>
                                    @endif
                                    <div style="font-weight: 600; color: var(--color-ink-900);">{{ $cita['hora'] }}</div>
                                    <div style="color: var(--color-ink-700);">{{ $cita['ciudadano'] }}</div>
                                </a>
                            @elseif(isset($cita['ciudadano_id']))
                                {{-- Otros roles o intervencion sin historia: va a ficha del ciudadano --}}
                                <a href="{{ route('ciudadania.ciudadano.ficha', $cita['ciudadano_id']) }}"
                                   wire:navigate
                                   style="display: block; background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 6px 6px 0; padding: 0.4rem 0.6rem; margin-bottom: 0.4rem; font-size: 0.8rem; text-decoration: none;">
                                    @if($cita['tipo'] === 'urgencia')
                                        <span style="display: inline-block; background: var(--color-danger-soft); color: var(--color-danger-ink); font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 99px; margin-bottom: 0.2rem;">Urgencia</span>
                                    @endif
                                    <div style="font-weight: 600; color: var(--color-ink-900);">{{ $cita['hora'] }}</div>
                                    <div style="color: var(--color-ink-700);">{{ $cita['ciudadano'] }}</div>
                                </a>
                            @else
                                {{-- Sin ciudadano_id (evento o cita sin datos aún): no clicable --}}
                                <div style="background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 6px 6px 0; padding: 0.4rem 0.6rem; margin-bottom: 0.4rem; font-size: 0.8rem;"
                                     title="{{ $cita['ciudadano'] ?? 'Evento interno' }}">
                                    @if($cita['tipo'] === 'urgencia')
                                        <span style="display: inline-block; background: var(--color-danger-soft); color: var(--color-danger-ink); font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 99px; margin-bottom: 0.2rem;">Urgencia</span>
                                    @endif
                                    <div style="font-weight: 600; color: var(--color-ink-900);">{{ $cita['hora'] }}</div>
                                    <div style="color: var(--color-ink-700);">{{ $cita['ciudadano'] ?? 'Evento interno' }}</div>
                                </div>
                            @endif
                        @empty
                        @endforelse

                        {{-- Slots libres (solo hoy y futuro) --}}
                        @if(! $esPasado)
                            @php
                                $horasCitas = collect($citas)->pluck('hora')->toArray();
                            @endphp
                            @foreach($horas as $hora)
                                @if(! in_array($hora, $horasCitas))
                                    <div style="border: 1.5px dashed var(--color-ink-300); border-radius: 6px; padding: 0.35rem 0.6rem; margin-bottom: 0.4rem; font-size: 0.75rem; color: var(--color-ink-400);">
                                        {{ $hora }} — Disponible
                                    </div>
                                @endif
                            @endforeach
                        @endif

                    </div>
                @endforeach
            </div>

        @elseif($vista === 'semana')
            {{-- ============================================================ --}}
            {{-- Vista semana: cuadrícula hora × día (L–V)                    --}}
            {{-- ============================================================ --}}
            @php
                $diasSemana = array_keys($this->citasSemana);
                $citasSemana = $this->citasSemana;
            @endphp
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                    <thead>
                        <tr>
                            <th style="width: 52px; padding: 0.4rem; font-size: 0.7rem; color: var(--color-ink-600); font-weight: 500; text-align: right; border-bottom: 1px solid var(--color-ink-200);"></th>
                            @foreach($diasSemana as $fecha)
                                @php
                                    $dia = Carbon::parse($fecha)->locale('es');
                                    $esHoy = $fecha === $hoy;
                                @endphp
                                <th style="padding: 0.4rem 0.5rem; text-align: center; border-bottom: 1px solid var(--color-ink-200); {{ $esHoy ? 'background: var(--color-primary-soft);' : '' }}">
                                    <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600);">{{ $dia->isoFormat('ddd') }}</div>
                                    <div style="font-size: 1rem; font-weight: 700; color: {{ $esHoy ? 'var(--color-primary)' : 'var(--color-ink-900)' }};">{{ $dia->day }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($horas as $hora)
                            <tr>
                                <td style="padding: 0.3rem 0.4rem 0.3rem 0; font-size: 0.72rem; color: var(--color-ink-400); text-align: right; vertical-align: top; border-top: 1px solid var(--color-ink-100);">{{ $hora }}</td>
                                @foreach($diasSemana as $fecha)
                                    @php
                                        $citasHora = collect($citasSemana[$fecha] ?? [])->filter(fn($c) => $c['hora'] === $hora)->values();
                                        $esHoy = $fecha === $hoy;
                                    @endphp
                                    <td style="padding: 0.2rem 0.3rem; vertical-align: top; border-top: 1px solid var(--color-ink-100); border-left: 1px solid var(--color-ink-100); min-height: 36px; {{ $esHoy ? 'background: var(--color-paper);' : '' }}">
                                        @foreach($citasHora as $cita)
                                            @php $estilo = $estiloCita[$cita['tipo']] ?? $estiloCita['evento']; @endphp
                                            @if($cita['historia_id'] && auth()->user()->hasRole('intervencion'))
                                                {{-- TSR + historia: va a pantalla de intervención --}}
                                                <a href="{{ route('intervencion.ciudadano.show', $cita['historia_id']) }}"
                                                   wire:navigate
                                                   style="display: block; background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 4px 4px 0; padding: 0.2rem 0.4rem; font-size: 0.75rem; margin-bottom: 0.15rem; text-decoration: none;">
                                                    <div style="font-weight: 600; color: var(--color-ink-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $cita['ciudadano'] }}</div>
                                                </a>
                                            @elseif(isset($cita['ciudadano_id']))
                                                {{-- Otros roles o intervencion sin historia: va a ficha del ciudadano --}}
                                                <a href="{{ route('ciudadania.ciudadano.ficha', $cita['ciudadano_id']) }}"
                                                   wire:navigate
                                                   style="display: block; background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 4px 4px 0; padding: 0.2rem 0.4rem; font-size: 0.75rem; margin-bottom: 0.15rem; text-decoration: none;">
                                                    <div style="font-weight: 600; color: var(--color-ink-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $cita['ciudadano'] }}</div>
                                                </a>
                                            @else
                                                {{-- Sin ciudadano_id (evento o cita sin datos aún): no clicable --}}
                                                <div style="background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 4px 4px 0; padding: 0.2rem 0.4rem; font-size: 0.75rem; margin-bottom: 0.15rem;"
                                                     title="{{ $cita['ciudadano'] ?? 'Evento interno' }}">
                                                    <div style="font-weight: 600; color: var(--color-ink-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $cita['ciudadano'] ?? 'Evento interno' }}</div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @else
            {{-- ============================================================ --}}
            {{-- Vista mes: calendario mensual clásico                         --}}
            {{-- ============================================================ --}}
            @php
                $inicioMes = Carbon::parse($fechaAncla)->startOfMonth();
                $finMes = Carbon::parse($fechaAncla)->endOfMonth();
                // Primer lunes visible (puede ser del mes anterior)
                $primerLunes = $inicioMes->copy()->startOfWeek();
                // Último domingo visible
                $ultimoDomingo = $finMes->copy()->endOfWeek();
                $diasCalendario = [];
                $cur = $primerLunes->copy();
                while ($cur->lte($ultimoDomingo)) {
                    $diasCalendario[] = $cur->copy();
                    $cur->addDay();
                }
                $datosMes = $this->datosMes;
            @endphp

            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--color-ink-200); border: 1px solid var(--color-ink-200); border-radius: 8px; overflow: hidden;">

                {{-- Cabecera días de la semana --}}
                @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $nombreDia)
                    <div style="background: var(--color-sand); padding: 0.4rem; text-align: center; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-600);">
                        {{ $nombreDia }}
                    </div>
                @endforeach

                {{-- Celdas de días --}}
                @foreach($diasCalendario as $dia)
                    @php
                        $fechaDia = $dia->toDateString();
                        $esMesActual = $dia->month === $inicioMes->month;
                        $esHoy = $fechaDia === $hoy;
                        $esFinDeSemana = $dia->isWeekend();
                        $numeroDia = (int) $dia->day;
                        $datosDia = $datosMes[$numeroDia] ?? null;
                        $tiposDia = $datosDia ? $datosDia['tipos'] : [];
                        // Ordenar por prioridad: urgencia, entrevista, seguimiento, evento
                        $prioridad = ['urgencia' => 0, 'entrevista' => 1, 'seguimiento' => 2, 'evento' => 3];
                        uksort($tiposDia, fn($a, $b) => ($prioridad[$a] ?? 9) <=> ($prioridad[$b] ?? 9));
                    @endphp
                    <div wire:click="{{ $esMesActual ? 'irADia(\'' . $fechaDia . '\')' : '' }}"
                         style="background: {{ $esFinDeSemana ? 'var(--color-sand)' : '#fff' }}; padding: 0.4rem; min-height: 72px; cursor: {{ $esMesActual ? 'pointer' : 'default' }}; opacity: {{ $esMesActual ? '1' : '0.4' }}; border: 1.5px solid {{ $esHoy ? 'var(--color-primary)' : 'transparent' }}; box-sizing: border-box; transition: background 0.1s;"
                         onmouseover="{{ $esMesActual ? "this.style.background='var(--color-primary-soft)'" : '' }}"
                         onmouseout="{{ $esMesActual ? "this.style.background='" . ($esFinDeSemana ? 'var(--color-sand)' : '#fff') . "'" : '' }}">

                        <div style="font-size: 0.82rem; font-weight: {{ $esHoy ? '700' : '400' }}; color: {{ $esHoy ? 'var(--color-primary)' : 'var(--color-ink-700)' }}; margin-bottom: 0.2rem;">
                            {{ $dia->day }}
                        </div>

                        @if($esMesActual)
                            @php $visibles = array_slice($tiposDia, 0, 3, true); @endphp
                            @foreach($visibles as $tipo => $conteo)
                                @php
                                    $color = $coloresTipo[$tipo] ?? 'var(--color-ink-500)';
                                    $colorSoft = $coloresTipoSoft[$tipo] ?? 'var(--color-ink-100)';
                                @endphp
                                <div style="font-size: 0.65rem; background: {{ $colorSoft }}; color: {{ $color }}; border-radius: 99px; padding: 0 0.3rem; margin-bottom: 0.15rem; display: inline-flex; align-items: center; gap: 0.2rem; font-weight: 600;">
                                    <span style="width: 5px; height: 5px; border-radius: 50%; background: {{ $color }}; display: inline-block; flex-shrink: 0;"></span>
                                    {{ $conteo }}
                                </div>
                            @endforeach
                        @endif

                    </div>
                @endforeach

            </div>

        @endif

    </div>

</div>
