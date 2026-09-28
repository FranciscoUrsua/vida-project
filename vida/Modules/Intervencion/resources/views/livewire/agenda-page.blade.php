@php
    use Carbon\Carbon;

    $ancla = Carbon::parse($fechaAncla)->locale('es');
    $hoy = today()->toDateString();

    // Tipos de cita: etiqueta y color de tema Bootstrap
    $estiloCita = [
        'entrevista' => ['label' => 'Entrevista', 'color' => 'primary'],
        'seguimiento' => ['label' => 'Seguimiento', 'color' => 'success'],
        'urgencia' => ['label' => 'Urgencia', 'color' => 'danger'],
        'evento' => ['label' => 'Evento', 'color' => 'secondary'],
    ];

    // Clases completas de una entrada de agenda según su tipo (sin concatenar nombres de clase)
    $claseEntrada = [
        'entrevista' => 'bg-primary-subtle border-primary',
        'seguimiento' => 'bg-success-subtle border-success',
        'urgencia' => 'bg-danger-subtle border-danger',
        'evento' => 'bg-secondary-subtle border-secondary',
    ];
    $clasePastilla = [
        'entrevista' => 'bg-primary-subtle text-primary-emphasis',
        'seguimiento' => 'bg-success-subtle text-success-emphasis',
        'urgencia' => 'bg-danger-subtle text-danger-emphasis',
        'evento' => 'bg-secondary-subtle text-secondary-emphasis',
    ];
    $claseMuestra = [
        'entrevista' => 'bg-primary',
        'seguimiento' => 'bg-success',
        'urgencia' => 'bg-danger',
        'evento' => 'bg-secondary',
    ];

    $horas = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

    // URL de destino de una cita: la historia social para intervención; si no, la ficha
    $urlCita = function (array $cita): ?string {
        if ($cita['historia_id'] && auth()->user()->hasRole('intervencion')) {
            return route('intervencion.ciudadano.show', $cita['historia_id']);
        }

        return isset($cita['ciudadano_id']) ? route('ciudadania.ciudadano.ficha', $cita['ciudadano_id']) : null;
    };
@endphp

<div class="op-page d-flex flex-column gap-3 p-3">
    <section class="d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div>
            <p class="small text-uppercase fw-semibold text-body-secondary mb-0">Planificacion diaria</p>
            <span class="fs-5 fw-bold">{{ $this->tituloFecha }}</span>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Navegacion temporal">
                <button wire:click="navegarAnterior" type="button" class="btn btn-outline-secondary" aria-label="Periodo anterior">
                    <x-heroicon-o-chevron-left class="icon-16" aria-hidden="true"/>
                </button>
                <button wire:click="navegarSiguiente" type="button" class="btn btn-outline-secondary" aria-label="Periodo siguiente">
                    <x-heroicon-o-chevron-right class="icon-16" aria-hidden="true"/>
                </button>
                <button wire:click="irAHoy" type="button" class="btn btn-outline-primary">Hoy</button>
            </div>

            <div class="btn-group btn-group-sm" role="group" aria-label="Vista de agenda">
                <button wire:click="setVista('dia')" type="button" class="btn {{ $vista === 'dia' ? 'btn-primary' : 'btn-outline-primary' }}">Dia</button>
                <button wire:click="setVista('semana')" type="button" class="btn {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}">Semana</button>
                <button wire:click="setVista('mes')" type="button" class="btn {{ $vista === 'mes' ? 'btn-primary' : 'btn-outline-primary' }}">Mes</button>
            </div>
        </div>
    </section>

    <section class="row row-cols-2 row-cols-lg-4 g-2" aria-label="Resumen de actividad">
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div @class(['fs-4 fw-bold', 'text-danger' => $this->kpis['alertas_sin_reconocer'] > 0])>{{ $this->kpis['alertas_sin_reconocer'] }}</div>
                <div class="small text-body-secondary">Alertas sin reconocer</div>
            </article>
        </div>
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div @class(['fs-4 fw-bold', 'text-warning' => $this->kpis['seguimientos_vencidos'] > 0])>{{ $this->kpis['seguimientos_vencidos'] }}</div>
                <div class="small text-body-secondary">Seguimientos vencidos</div>
            </article>
        </div>
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div class="fs-4 fw-bold">{{ $this->kpis['citas'] }}</div>
                <div class="small text-body-secondary">
                    @if($vista === 'dia')
                        Citas hoy
                    @elseif($vista === 'semana')
                        Citas esta semana
                    @else
                        Citas este mes
                    @endif
                </div>
            </article>
        </div>
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div class="fs-4 fw-bold">{{ $this->kpis['mensajes_sin_leer'] }}</div>
                <div class="small text-body-secondary">Mensajes sin leer</div>
            </article>
        </div>
    </section>

    <section class="card flex-grow-1">
        <div class="card-body">
            @if($vista === 'dia')
                <div class="row g-3">
                    @foreach($this->citasDia as $fecha => $citas)
                        @php
                            $col = Carbon::parse($fecha)->locale('es');
                            $esHoy = $fecha === $hoy;
                            $esPasado = $fecha < $hoy;
                        @endphp
                        <section @class(['col', 'opacity-75' => $esPasado])>
                            <header @class(['text-center rounded py-1 mb-2', 'bg-primary-subtle text-primary-emphasis' => $esHoy, 'bg-body-tertiary' => ! $esHoy])>
                                <div class="small text-uppercase">{{ $col->isoFormat('ddd') }}</div>
                                <div class="fs-5 fw-bold">{{ $col->day }}</div>
                            </header>

                            <div class="d-flex flex-column gap-1">
                                @forelse($citas as $cita)
                                    @php
                                        $tipo = $cita['tipo'] ?? 'evento';
                                        $url = $urlCita($cita);
                                        $clases = 'd-block rounded border-start border-3 px-2 py-1 small text-body text-decoration-none '.($claseEntrada[$tipo] ?? $claseEntrada['evento']);
                                    @endphp

                                    @if($url)
                                        <a href="{{ $url }}" wire:navigate class="{{ $clases }}">
                                    @else
                                        <div class="{{ $clases }}" title="{{ $cita['ciudadano'] ?? 'Evento interno' }}">
                                    @endif
                                            @if($tipo === 'urgencia')
                                                <span class="badge text-bg-danger">Urgencia</span>
                                            @endif
                                            <div class="fw-semibold">{{ $cita['hora'] }}</div>
                                            <div class="text-truncate">{{ $cita['ciudadano'] ?? 'Evento interno' }}</div>
                                    @if($url)
                                        </a>
                                    @else
                                        </div>
                                    @endif
                                @empty
                                    <p class="small text-body-secondary text-center mb-0">Sin citas programadas.</p>
                                @endforelse

                                @if(! $esPasado)
                                    @php $horasCitas = collect($citas)->pluck('hora')->toArray(); @endphp
                                    @foreach($horas as $hora)
                                        @if(! in_array($hora, $horasCitas))
                                            <div class="small text-body-tertiary border-bottom py-1">{{ $hora }} <span class="ms-1">Disponible</span></div>
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
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-top mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><span class="visually-hidden">Hora</span></th>
                                @foreach($diasSemana as $fecha)
                                    @php
                                        $dia = Carbon::parse($fecha)->locale('es');
                                        $esHoy = $fecha === $hoy;
                                    @endphp
                                    <th scope="col" @class(['text-center', 'table-primary' => $esHoy])>
                                        <div class="small text-uppercase fw-normal">{{ $dia->isoFormat('ddd') }}</div>
                                        <div>{{ $dia->day }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($horas as $hora)
                                <tr>
                                    <th scope="row" class="small fw-normal text-body-secondary text-nowrap">{{ $hora }}</th>
                                    @foreach($diasSemana as $fecha)
                                        @php
                                            $citasHora = collect($citasSemana[$fecha] ?? [])->filter(fn($c) => $c['hora'] === $hora)->values();
                                            $esHoy = $fecha === $hoy;
                                        @endphp
                                        <td @class(['table-primary' => $esHoy])>
                                            @foreach($citasHora as $cita)
                                                @php
                                                    $tipo = $cita['tipo'] ?? 'evento';
                                                    $url = $urlCita($cita);
                                                    $clases = 'd-block rounded border-start border-3 px-1 mb-1 small text-body text-decoration-none text-truncate '.($claseEntrada[$tipo] ?? $claseEntrada['evento']);
                                                @endphp
                                                @if($url)
                                                    <a href="{{ $url }}" wire:navigate class="{{ $clases }}">{{ $cita['ciudadano'] }}</a>
                                                @else
                                                    <div class="{{ $clases }}" title="{{ $cita['ciudadano'] ?? 'Evento interno' }}">{{ $cita['ciudadano'] ?? 'Evento interno' }}</div>
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
                    $prioridad = ['urgencia' => 0, 'entrevista' => 1, 'seguimiento' => 2, 'evento' => 3];
                @endphp

                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                @foreach(['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $nombreDia)
                                    <th scope="col" class="small text-center text-body-secondary">{{ $nombreDia }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_chunk($diasCalendario, 7) as $semana)
                                <tr>
                                    @foreach($semana as $dia)
                                        @php
                                            $fechaDia = $dia->toDateString();
                                            $esMesActual = $dia->month === $inicioMes->month;
                                            $esHoy = $fechaDia === $hoy;
                                            $numeroDia = (int) $dia->day;
                                            $datosDia = $esMesActual ? ($datosMes[$numeroDia] ?? null) : null;
                                            $tiposDia = $datosDia ? $datosDia['tipos'] : [];
                                            uksort($tiposDia, fn($a, $b) => ($prioridad[$a] ?? 9) <=> ($prioridad[$b] ?? 9));
                                            $visibles = array_slice($tiposDia, 0, 3, true);
                                        @endphp
                                        @if($esMesActual)
                                            <td @class(['p-0', 'table-primary' => $esHoy, 'bg-body-tertiary' => ! $esHoy && $dia->isWeekend()])>
                                                <button type="button" wire:click="irADia('{{ $fechaDia }}')" class="btn w-100 h-100 text-start rounded-0 p-2">
                                                    <div @class(['small', 'fw-bold text-primary' => $esHoy])>{{ $dia->day }}</div>
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @foreach($visibles as $tipo => $conteo)
                                                            <span class="badge rounded-pill {{ $clasePastilla[$tipo] ?? $clasePastilla['evento'] }}">{{ $conteo }}</span>
                                                        @endforeach
                                                    </div>
                                                </button>
                                            </td>
                                        @else
                                            <td class="small text-body-tertiary bg-body-tertiary p-2">{{ $dia->day }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <footer class="card-footer d-flex flex-wrap align-items-center gap-3 small" aria-label="Leyenda de tipos de cita">
            <span class="fw-semibold text-body-secondary">Leyenda</span>
            @foreach($estiloCita as $tipo => $estilos)
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="d-inline-block rounded p-1 {{ $claseMuestra[$tipo] }}" aria-hidden="true"></span>
                    {{ $estilos['label'] }}
                </span>
            @endforeach
        </footer>
    </section>
</div>
