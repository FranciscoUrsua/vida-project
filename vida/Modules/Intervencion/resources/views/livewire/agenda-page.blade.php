@php
    use Carbon\Carbon;

    $ancla = Carbon::parse($fechaAncla)->locale('es');
    $hoy = today()->toDateString();

    // Estilos por tipo de cita
    $estiloCita = [
        'entrevista' => ['bg' => '#EEEDFE', 'border' => '#534AB7', 'label' => 'Entrevista'],
        'seguimiento' => ['bg' => '#E1F5EE', 'border' => '#0F6E56', 'label' => 'Seguimiento'],
        'urgencia'   => ['bg' => '#FAECE7', 'border' => '#993C1D', 'label' => 'Urgencia'],
        'evento'     => ['bg' => '#F3F4F6', 'border' => '#9CA3AF', 'label' => 'Evento'],
    ];

    // Horas de la jornada (vista semana y slots libres)
    $horas = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00'];
@endphp

<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Barra superior                                                       --}}
    {{-- ------------------------------------------------------------------ --}}
    <div style="background: #fff; border-bottom: 1px solid #E5E3F5; padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0;">

        <h1 style="font-size: 1rem; font-weight: 700; margin: 0; color: #1D160E;">Agenda</h1>

        <span style="font-size: 0.875rem; color: #534AB7; font-weight: 500; margin-right: 0.25rem;">
            {{ $this->tituloFecha }}
        </span>

        {{-- Navegación anterior / siguiente / hoy --}}
        <button wire:click="navegarAnterior" class="btn btn-sm btn-outline-secondary" aria-label="Período anterior" style="padding: 0.2rem 0.55rem;">
            <i class="bi bi-chevron-left"></i>
        </button>
        <button wire:click="navegarSiguiente" class="btn btn-sm btn-outline-secondary" aria-label="Período siguiente" style="padding: 0.2rem 0.55rem;">
            <i class="bi bi-chevron-right"></i>
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
    <div style="background: #F8F7FF; border-bottom: 1px solid #E5E3F5; padding: 0.65rem 1.25rem; display: flex; gap: 1rem; flex-shrink: 0;">

        {{-- KPI: Alertas sin reconocer --}}
        <div style="flex: 1; background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: {{ $this->kpis['alertas_sin_reconocer'] > 0 ? '#F0997B' : '#1D160E' }};">
                {{ $this->kpis['alertas_sin_reconocer'] }}
            </div>
            <div style="font-size: 0.72rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.04em;">Alertas sin reconocer</div>
        </div>

        {{-- KPI: Seguimientos vencidos --}}
        <div style="flex: 1; background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: {{ $this->kpis['seguimientos_vencidos'] > 0 ? '#D97706' : '#1D160E' }};">
                {{ $this->kpis['seguimientos_vencidos'] }}
            </div>
            <div style="font-size: 0.72rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.04em;">Seguimientos vencidos</div>
        </div>

        {{-- KPI: Citas (label dinámico según vista) --}}
        <div style="flex: 1; background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: #1D160E;">
                {{ $this->kpis['citas'] }}
            </div>
            <div style="font-size: 0.72rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.04em;">
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
        <div style="flex: 1; background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 0.6rem 0.9rem;">
            <div style="font-size: 1.3rem; font-weight: 700; color: #1D160E;">
                {{ $this->kpis['mensajes_sin_leer'] }}
            </div>
            <div style="font-size: 0.72rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.04em;">Mensajes sin leer</div>
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
                        <div style="text-align: center; margin-bottom: 0.5rem; padding: 0.3rem; border-radius: 6px; {{ $esHoy ? 'background: #EEEDFE;' : '' }}">
                            <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6B7280;">
                                {{ $col->isoFormat('ddd') }}
                            </div>
                            <div style="font-size: 1.4rem; font-weight: 700; color: {{ $esHoy ? '#534AB7' : '#1D160E' }}; line-height: 1.2;">
                                {{ $col->day }}
                            </div>
                        </div>

                        {{-- Citas --}}
                        @forelse($citas as $cita)
                            @php $estilo = $estiloCita[$cita['tipo']] ?? $estiloCita['evento']; @endphp
                            <div style="background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 6px 6px 0; padding: 0.4rem 0.6rem; margin-bottom: 0.4rem; font-size: 0.8rem;">
                                @if($cita['tipo'] === 'urgencia')
                                    <span style="display: inline-block; background: #F5C4B3; color: #4A1B0C; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 99px; margin-bottom: 0.2rem;">Urgencia</span>
                                @endif
                                <div style="font-weight: 600; color: #1D160E;">{{ $cita['hora'] }}</div>
                                <div style="color: #374151;">{{ $cita['ciudadano'] }}</div>
                            </div>
                        @empty
                        @endforelse

                        {{-- Slots libres (solo hoy y futuro) --}}
                        @if(! $esPasado)
                            @php
                                $horasCitas = collect($citas)->pluck('hora')->toArray();
                            @endphp
                            @foreach($horas as $hora)
                                @if(! in_array($hora, $horasCitas))
                                    <div style="border: 1.5px dashed #D1D5DB; border-radius: 6px; padding: 0.35rem 0.6rem; margin-bottom: 0.4rem; font-size: 0.75rem; color: #9CA3AF;">
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
                            <th style="width: 52px; padding: 0.4rem; font-size: 0.7rem; color: #6B7280; font-weight: 500; text-align: right; border-bottom: 1px solid #E5E3F5;"></th>
                            @foreach($diasSemana as $fecha)
                                @php
                                    $dia = Carbon::parse($fecha)->locale('es');
                                    $esHoy = $fecha === $hoy;
                                @endphp
                                <th style="padding: 0.4rem 0.5rem; text-align: center; border-bottom: 1px solid #E5E3F5; {{ $esHoy ? 'background: #EEEDFE;' : '' }}">
                                    <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6B7280;">{{ $dia->isoFormat('ddd') }}</div>
                                    <div style="font-size: 1rem; font-weight: 700; color: {{ $esHoy ? '#534AB7' : '#1D160E' }};">{{ $dia->day }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($horas as $hora)
                            <tr>
                                <td style="padding: 0.3rem 0.4rem 0.3rem 0; font-size: 0.72rem; color: #9CA3AF; text-align: right; vertical-align: top; border-top: 1px solid #F3F4F6;">{{ $hora }}</td>
                                @foreach($diasSemana as $fecha)
                                    @php
                                        $citasHora = collect($citasSemana[$fecha] ?? [])->filter(fn($c) => $c['hora'] === $hora)->values();
                                        $esHoy = $fecha === $hoy;
                                    @endphp
                                    <td style="padding: 0.2rem 0.3rem; vertical-align: top; border-top: 1px solid #F3F4F6; border-left: 1px solid #F3F4F6; min-height: 36px; {{ $esHoy ? 'background: #FBFAFF;' : '' }}">
                                        @foreach($citasHora as $cita)
                                            @php $estilo = $estiloCita[$cita['tipo']] ?? $estiloCita['evento']; @endphp
                                            <div style="background: {{ $estilo['bg'] }}; border-left: 3px solid {{ $estilo['border'] }}; border-radius: 0 4px 4px 0; padding: 0.2rem 0.4rem; font-size: 0.75rem; margin-bottom: 0.15rem;">
                                                <div style="font-weight: 600; color: #1D160E; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $cita['ciudadano'] }}</div>
                                            </div>
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
                $coloresTipo = [
                    'urgencia'   => '#993C1D',
                    'entrevista' => '#534AB7',
                    'seguimiento'=> '#0F6E56',
                    'evento'     => '#6B7280',
                ];
            @endphp

            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #E5E3F5; border: 1px solid #E5E3F5; border-radius: 8px; overflow: hidden;">

                {{-- Cabecera días de la semana --}}
                @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $nombreDia)
                    <div style="background: #F8F7FF; padding: 0.4rem; text-align: center; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6B7280;">
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
                         style="background: {{ $esFinDeSemana ? '#F5F4FC' : '#fff' }}; padding: 0.4rem; min-height: 72px; cursor: {{ $esMesActual ? 'pointer' : 'default' }}; opacity: {{ $esMesActual ? '1' : '0.4' }}; border: 1.5px solid {{ $esHoy ? '#534AB7' : 'transparent' }}; box-sizing: border-box; transition: background 0.1s;"
                         onmouseover="{{ $esMesActual ? "this.style.background='#EEEDFE'" : '' }}"
                         onmouseout="{{ $esMesActual ? "this.style.background='" . ($esFinDeSemana ? '#F5F4FC' : '#fff') . "'" : '' }}">

                        <div style="font-size: 0.82rem; font-weight: {{ $esHoy ? '700' : '400' }}; color: {{ $esHoy ? '#534AB7' : '#374151' }}; margin-bottom: 0.2rem;">
                            {{ $dia->day }}
                        </div>

                        @if($esMesActual)
                            @php $visibles = array_slice($tiposDia, 0, 3, true); @endphp
                            @foreach($visibles as $tipo => $conteo)
                                @php $color = $coloresTipo[$tipo] ?? '#6B7280'; @endphp
                                <div style="font-size: 0.65rem; background: {{ $color }}22; color: {{ $color }}; border-radius: 99px; padding: 0 0.3rem; margin-bottom: 0.15rem; display: inline-flex; align-items: center; gap: 0.2rem; font-weight: 600;">
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
