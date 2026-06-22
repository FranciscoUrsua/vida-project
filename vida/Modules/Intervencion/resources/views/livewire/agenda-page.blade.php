@php
    use Carbon\Carbon;

    $ancla = Carbon::parse($fechaAncla)->locale('es');
    $hoy = today()->toDateString();

    $estiloCita = [
        'entrevista' => ['label' => 'Entrevista'],
        'seguimiento' => ['label' => 'Seguimiento'],
        'urgencia' => ['label' => 'Urgencia'],
        'evento' => ['label' => 'Evento'],
    ];

    $horas = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
@endphp

<div class="agenda-screen">
    <section class="agenda-screen__toolbar">
        <div class="agenda-screen__heading">
            <p class="agenda-screen__eyebrow">Planificacion diaria</p>
            <span class="agenda-screen__range">{{ $this->tituloFecha }}</span>
        </div>

        <div class="agenda-screen__toolbar-actions">
            <div class="btn-group btn-group-sm agenda-screen__nav-group" aria-label="Navegacion temporal">
                <button wire:click="navegarAnterior" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Periodo anterior">
                    <x-icon name="chevron-left" class="icon-16" aria-hidden="true"/>
                </button>
                <button wire:click="navegarSiguiente" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Periodo siguiente">
                    <x-icon name="chevron-right" class="icon-16" aria-hidden="true"/>
                </button>
                <button wire:click="irAHoy" type="button" class="btn btn-sm btn-outline-primary">Hoy</button>
            </div>

            <div class="btn-group btn-group-sm agenda-screen__view-toggle" role="group" aria-label="Vista de agenda">
                <button wire:click="setVista('dia')" type="button" class="btn {{ $vista === 'dia' ? 'btn-primary' : 'btn-outline-primary' }}">Dia</button>
                <button wire:click="setVista('semana')" type="button" class="btn {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}">Semana</button>
                <button wire:click="setVista('mes')" type="button" class="btn {{ $vista === 'mes' ? 'btn-primary' : 'btn-outline-primary' }}">Mes</button>
            </div>
        </div>
    </section>

    <section class="agenda-screen__kpis" aria-label="Resumen de actividad">
        <article class="agenda-screen__kpi {{ $this->kpis['alertas_sin_reconocer'] > 0 ? 'agenda-screen__kpi--accent' : '' }}">
            <div class="agenda-screen__kpi-value">{{ $this->kpis['alertas_sin_reconocer'] }}</div>
            <div class="agenda-screen__kpi-label">Alertas sin reconocer</div>
        </article>
        <article class="agenda-screen__kpi {{ $this->kpis['seguimientos_vencidos'] > 0 ? 'agenda-screen__kpi--warning' : '' }}">
            <div class="agenda-screen__kpi-value">{{ $this->kpis['seguimientos_vencidos'] }}</div>
            <div class="agenda-screen__kpi-label">Seguimientos vencidos</div>
        </article>
        <article class="agenda-screen__kpi">
            <div class="agenda-screen__kpi-value">{{ $this->kpis['citas'] }}</div>
            <div class="agenda-screen__kpi-label">
                @if($vista === 'dia')
                    Citas hoy
                @elseif($vista === 'semana')
                    Citas esta semana
                @else
                    Citas este mes
                @endif
            </div>
        </article>
        <article class="agenda-screen__kpi">
            <div class="agenda-screen__kpi-value">{{ $this->kpis['mensajes_sin_leer'] }}</div>
            <div class="agenda-screen__kpi-label">Mensajes sin leer</div>
        </article>
    </section>

    <section class="agenda-screen__surface">
        <div class="agenda-screen__content">
            @if($vista === 'dia')
                <div class="agenda-screen__day-grid">
                    @foreach($this->citasDia as $fecha => $citas)
                        @php
                            $col = Carbon::parse($fecha)->locale('es');
                            $esHoy = $fecha === $hoy;
                            $esPasado = $fecha < $hoy;
                        @endphp
                        <section class="agenda-screen__day-column {{ $esHoy ? 'agenda-screen__day-column--today' : '' }} {{ $esPasado ? 'agenda-screen__day-column--past' : '' }}">
                            <header class="agenda-screen__day-header">
                                <div class="agenda-screen__day-weekday">{{ $col->isoFormat('ddd') }}</div>
                                <div class="agenda-screen__day-number">{{ $col->day }}</div>
                            </header>

                            <div class="agenda-screen__day-items">
                                @forelse($citas as $cita)
                                    @php
                                        $tipo = $cita['tipo'] ?? 'evento';
                                        $url = null;
                                        if ($cita['historia_id'] && auth()->user()->hasRole('intervencion')) {
                                            $url = route('intervencion.ciudadano.show', $cita['historia_id']);
                                        } elseif (isset($cita['ciudadano_id'])) {
                                            $url = route('ciudadania.ciudadano.ficha', $cita['ciudadano_id']);
                                        }
                                    @endphp

                                    @if($url)
                                        <a href="{{ $url }}" wire:navigate class="agenda-screen__entry agenda-screen__entry--{{ $tipo }}">
                                            @if($tipo === 'urgencia')
                                                <span class="agenda-screen__entry-badge">Urgencia</span>
                                            @endif
                                            <div class="agenda-screen__entry-time">{{ $cita['hora'] }}</div>
                                            <div class="agenda-screen__entry-title">{{ $cita['ciudadano'] }}</div>
                                        </a>
                                    @else
                                        <div class="agenda-screen__entry agenda-screen__entry--{{ $tipo }}" title="{{ $cita['ciudadano'] ?? 'Evento interno' }}">
                                            @if($tipo === 'urgencia')
                                                <span class="agenda-screen__entry-badge">Urgencia</span>
                                            @endif
                                            <div class="agenda-screen__entry-time">{{ $cita['hora'] }}</div>
                                            <div class="agenda-screen__entry-title">{{ $cita['ciudadano'] ?? 'Evento interno' }}</div>
                                        </div>
                                    @endif
                                @empty
                                    <div class="agenda-screen__empty">Sin citas programadas.</div>
                                @endforelse

                                @if(! $esPasado)
                                    @php $horasCitas = collect($citas)->pluck('hora')->toArray(); @endphp
                                    @foreach($horas as $hora)
                                        @if(! in_array($hora, $horasCitas))
                                            <div class="agenda-screen__slot">{{ $hora }} <span>Disponible</span></div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            @elseif($vista === 'semana')
                @php
                    $diasSemana = array_keys($this->citasSemana);
                    $citasSemana = $this->citasSemana;
                @endphp
                <div class="agenda-screen__table-wrap">
                    <table class="agenda-screen__table">
                        <thead>
                            <tr>
                                <th class="agenda-screen__table-hour-head"></th>
                                @foreach($diasSemana as $fecha)
                                    @php
                                        $dia = Carbon::parse($fecha)->locale('es');
                                        $esHoy = $fecha === $hoy;
                                    @endphp
                                    <th class="agenda-screen__table-day-head {{ $esHoy ? 'agenda-screen__table-day-head--today' : '' }}">
                                        <div class="agenda-screen__table-weekday">{{ $dia->isoFormat('ddd') }}</div>
                                        <div class="agenda-screen__table-daynum">{{ $dia->day }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($horas as $hora)
                                <tr>
                                    <td class="agenda-screen__table-hour">{{ $hora }}</td>
                                    @foreach($diasSemana as $fecha)
                                        @php
                                            $citasHora = collect($citasSemana[$fecha] ?? [])->filter(fn($c) => $c['hora'] === $hora)->values();
                                            $esHoy = $fecha === $hoy;
                                        @endphp
                                        <td class="agenda-screen__table-cell {{ $esHoy ? 'agenda-screen__table-cell--today' : '' }}">
                                            @foreach($citasHora as $cita)
                                                @php
                                                    $tipo = $cita['tipo'] ?? 'evento';
                                                    $url = null;
                                                    if ($cita['historia_id'] && auth()->user()->hasRole('intervencion')) {
                                                        $url = route('intervencion.ciudadano.show', $cita['historia_id']);
                                                    } elseif (isset($cita['ciudadano_id'])) {
                                                        $url = route('ciudadania.ciudadano.ficha', $cita['ciudadano_id']);
                                                    }
                                                @endphp
                                                @if($url)
                                                    <a href="{{ $url }}" wire:navigate class="agenda-screen__entry agenda-screen__entry--compact agenda-screen__entry--{{ $tipo }}">
                                                        <div class="agenda-screen__entry-title">{{ $cita['ciudadano'] }}</div>
                                                    </a>
                                                @else
                                                    <div class="agenda-screen__entry agenda-screen__entry--compact agenda-screen__entry--{{ $tipo }}" title="{{ $cita['ciudadano'] ?? 'Evento interno' }}">
                                                        <div class="agenda-screen__entry-title">{{ $cita['ciudadano'] ?? 'Evento interno' }}</div>
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
                @php
                    $inicioMes = Carbon::parse($fechaAncla)->startOfMonth();
                    $finMes = Carbon::parse($fechaAncla)->endOfMonth();
                    $primerLunes = $inicioMes->copy()->startOfWeek();
                    $ultimoDomingo = $finMes->copy()->endOfWeek();
                    $diasCalendario = [];
                    $cur = $primerLunes->copy();
                    while ($cur->lte($ultimoDomingo)) {
                        $diasCalendario[] = $cur->copy();
                        $cur->addDay();
                    }
                    $datosMes = $this->datosMes;
                @endphp

                <div class="agenda-screen__month-grid">
                    @foreach(['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $nombreDia)
                        <div class="agenda-screen__month-head">{{ $nombreDia }}</div>
                    @endforeach

                    @foreach($diasCalendario as $dia)
                        @php
                            $fechaDia = $dia->toDateString();
                            $esMesActual = $dia->month === $inicioMes->month;
                            $esHoy = $fechaDia === $hoy;
                            $esFinDeSemana = $dia->isWeekend();
                            $numeroDia = (int) $dia->day;
                            $datosDia = $datosMes[$numeroDia] ?? null;
                            $tiposDia = $datosDia ? $datosDia['tipos'] : [];
                            $prioridad = ['urgencia' => 0, 'entrevista' => 1, 'seguimiento' => 2, 'evento' => 3];
                            uksort($tiposDia, fn($a, $b) => ($prioridad[$a] ?? 9) <=> ($prioridad[$b] ?? 9));
                            $visibles = array_slice($tiposDia, 0, 3, true);
                        @endphp

                        @if($esMesActual)
                            <button type="button" wire:click="irADia('{{ $fechaDia }}')" class="agenda-screen__month-cell {{ $esHoy ? 'agenda-screen__month-cell--today' : '' }} {{ $esFinDeSemana ? 'agenda-screen__month-cell--weekend' : '' }}">
                                <div class="agenda-screen__month-day">{{ $dia->day }}</div>
                                <div class="agenda-screen__month-pills">
                                    @foreach($visibles as $tipo => $conteo)
                                        <span class="agenda-screen__month-pill agenda-screen__month-pill--{{ $tipo }}">{{ $conteo }}</span>
                                    @endforeach
                                </div>
                            </button>
                        @else
                            <div class="agenda-screen__month-cell agenda-screen__month-cell--muted {{ $esFinDeSemana ? 'agenda-screen__month-cell--weekend' : '' }}">
                                <div class="agenda-screen__month-day">{{ $dia->day }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <footer class="agenda-screen__legend" aria-label="Leyenda de tipos de cita">
            <span class="agenda-screen__legend-title">Leyenda</span>
            @foreach($estiloCita as $tipo => $estilos)
                <span class="agenda-screen__legend-item">
                    <span class="agenda-screen__legend-swatch agenda-screen__legend-swatch--{{ $tipo }}"></span>
                    {{ $estilos['label'] }}
                </span>
            @endforeach
        </footer>
    </section>
</div>
