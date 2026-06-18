@php
    use Carbon\Carbon;
    use Modules\Intervencion\Enums\TipoApunte;

    $ciudadano = $this->ciudadano;
    $piso      = $this->pisoActivo;

    $badgeEstado = [
        'abierta'        => ['bg' => 'var(--color-primary-soft)', 'color' => 'var(--color-primary-ink)', 'label' => 'Abierta'],
        'en_seguimiento' => ['bg' => 'var(--color-success-soft)', 'color' => 'var(--color-success-ink)', 'label' => 'En seguimiento'],
        'cerrada'        => ['bg' => 'var(--color-ink-100)',      'color' => 'var(--color-ink-500)',      'label' => 'Cerrada'],
    ];
    $badge = $badgeEstado[$historia->estado] ?? $badgeEstado['abierta'];

    $coloresTipo = [
        'plan_intervencion'    => 'var(--color-warning)',
        'entrevista'           => 'var(--color-primary)',
        'valoracion'           => 'var(--color-success)',
        'escala'               => 'var(--color-primary)',
        'derivacion'           => 'var(--color-success)',
        'anotacion'            => 'var(--color-ink-500)',
        'gestion_coordinacion' => 'var(--color-ink-500)',
        'seguimiento'          => 'var(--color-primary)',
        'documento'            => 'var(--color-ink-500)',
    ];

    $herramientas = [
        ['id' => 'entrevista', 'label' => 'Entrevista',  'icon' => 'message-square',    'fullpage' => false],
        ['id' => 'anotacion',  'label' => 'Anotación',   'icon' => 'pencil',            'fullpage' => false],
        ['id' => 'derivacion', 'label' => 'Derivación',  'icon' => 'circle-arrow-right','fullpage' => false],
        ['id' => 'gestion',    'label' => 'Gestión',     'icon' => 'network',           'fullpage' => false],
        ['id' => 'valoracion', 'label' => 'Valoración',  'icon' => 'clipboard-check',   'fullpage' => true],
        ['id' => 'escala',     'label' => 'Escala',      'icon' => 'bar-chart-2',       'fullpage' => true],
        ['id' => 'informes',   'label' => 'Informes',    'icon' => 'file-text',         'fullpage' => true],
    ];
@endphp

<div style="display: flex; flex-direction: column; height: calc(100vh - 56px); overflow: hidden;">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Banda del Plan de Intervención — ancho completo                    --}}
    {{-- ------------------------------------------------------------------ --}}
    @if($piso)
        <div style="background: var(--color-primary-soft); border-bottom: 1px solid var(--color-ink-200); padding: 0.5rem 1.25rem; display: flex; align-items: center; gap: 1rem; flex-shrink: 0; font-size: 0.8rem;">
            <span style="font-weight: 600; color: var(--color-primary-ink);">{{ $this->planNombreCorto }} activo</span>
            <span style="color: var(--color-ink-600);">v{{ $piso->version }} · desde {{ Carbon::parse($piso->fecha_inicio)->format('d/m/Y') }}</span>
            {{-- TODO: Entrega 4 — route('intervencion.piso.show', $piso->id) --}}
            <a href="#" style="margin-left: auto; font-size: 0.78rem; color: var(--color-primary); text-decoration: none; font-weight: 600;">Ver {{ $this->planNombreCorto }} →</a>
        </div>
    @else
        <div style="background: var(--color-paper); border-bottom: 1px solid var(--color-ink-200); padding: 0.45rem 1.25rem; font-size: 0.78rem; color: var(--color-ink-400); flex-shrink: 0;">
            Sin {{ $this->planNombreCorto }} activo
        </div>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Layout 4 cuadrantes                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="ciudadano-layout" style="flex: 1; min-height: 0;">

        {{-- ============================================================== --}}
        {{-- ZONA SUPERIOR IZQUIERDA — datos del ciudadano + UC colapsable  --}}
        {{-- ============================================================== --}}
        <div class="ciudadano-header-left" style="padding: 0.75rem;">

            {{-- Fila superior: retorno + acciones --}}
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;">
                <a href="{{ route('intervencion.casos.index') }}"
                   style="font-size: 0.75rem; color: var(--color-primary); text-decoration: none; display: flex; align-items: center; gap: 0.2rem; flex-shrink: 0;">
                    <i data-lucide="arrow-left" style="width:12px;height:12px;" aria-hidden="true"></i> Mis casos
                </a>
                <div style="margin-left: auto; display: flex; align-items: center; gap: 0.3rem;">
                    @if($ciudadano)
                        <a href="{{ route('ciudadania.ciudadano.ficha', $ciudadano->id) }}"
                           wire:navigate
                           style="font-size: 0.72rem; color: var(--color-primary); border: 1px solid var(--color-primary); border-radius: 4px; padding: 0.1rem 0.4rem; text-decoration: none; white-space: nowrap;">
                            Ficha completa
                        </a>
                    @endif
                    {{-- TODO: menú ⋯ con acciones adicionales del expediente --}}
                </div>
            </div>

            {{-- Nombre completo --}}
            <div style="font-size: 1rem; font-weight: 700; color: var(--color-ink-900); line-height: 1.3; margin-bottom: 0.45rem;">
                {{ $ciudadano ? ($ciudadano->nombre . ' ' . $ciudadano->apellido1 . ($ciudadano->apellido2 ? ' ' . $ciudadano->apellido2 : '')) : 'Ciudadano #' . $historia->ciudadano_id }}
            </div>

            {{-- HS + UO + Estado HS --}}
            <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.3rem;">
                <span style="font-size: 0.72rem; color: var(--color-ink-500);">HS #{{ $historia->id }}</span>
                <span style="font-size: 0.72rem; color: var(--color-ink-300);">·</span>
                @if($this->uoNombre)
                    <span style="font-size: 0.72rem; color: var(--color-ink-500);">{{ $this->uoNombre }}</span>
                @else
                    <span style="font-size: 0.72rem; color: var(--color-ink-500);">UO #{{ $historia->unidad_organizativa_id }}</span>
                @endif
                <span style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; padding: 0.1rem 0.4rem; border-radius: 99px; font-size: 0.65rem; font-weight: 600; white-space: nowrap;">
                    Estado HS: {{ $badge['label'] }}
                </span>
            </div>

            {{-- Fecha de nacimiento · edad --}}
            @if($ciudadano?->fecha_nacimiento)
                <div style="font-size: 0.72rem; color: var(--color-ink-600); margin-bottom: 0.2rem;">
                    {{ Carbon::parse($ciudadano->fecha_nacimiento)->format('d/m/Y') }} · {{ Carbon::parse($ciudadano->fecha_nacimiento)->age }} años
                </div>
            @endif

            {{-- Domicilio --}}
            @if($ciudadano?->direccion_texto)
                <div style="font-size: 0.72rem; color: var(--color-ink-600); line-height: 1.4; margin-bottom: 0.2rem;">
                    {{ $ciudadano->direccion_texto }}
                </div>
            @endif

            {{-- Documento · Teléfono · Email --}}
            @if($this->ciudadanoDocumento || $this->ciudadanoTelefono || $this->ciudadanoEmail)
                <p class="hs-ciudadano-contacto">
                    @if($this->ciudadanoDocumento)
                        <span>{{ $this->ciudadanoDocumento }}</span>
                    @endif
                    @if($this->ciudadanoTelefono)
                        <span>{{ $this->ciudadanoTelefono }}</span>
                    @endif
                    @if($this->ciudadanoEmail)
                        <span>{{ $this->ciudadanoEmail }}</span>
                    @endif
                </p>
            @endif

            {{-- Representante (solo si existe relación activa) --}}
            @if($this->representante)
            <div class="hs-representante">
                <span class="hs-representante__label">Representante</span>
                <button
                    wire:click="abrirModalRepresentante"
                    class="hs-representante__nombre"
                    title="Ver datos de contacto del representante"
                >
                    {{ $this->representante->nombre }}
                    {{ $this->representante->apellido1 }}
                    {{ $this->representante->apellido2 }}
                    <i data-lucide="chevron-right" style="width:12px;height:12px;"></i>
                </button>
            </div>
            @endif

            {{-- Unidad de convivencia --}}
            <div style="margin-top: 0.75rem; border: 1px solid var(--color-ink-200); border-radius: 8px; overflow: hidden;">
                <button wire:click="toggleUC"
                        style="width: 100%; background: #fff; border: none; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        Unidad de convivencia
                        @if($this->ucVigente)
                            <span style="font-size: 0.7rem; font-weight: 400; color: var(--color-ink-500); margin-left: 0.3rem;">
                                {{ $this->ucMiembrosActivos->count() }} miembro{{ $this->ucMiembrosActivos->count() !== 1 ? 's' : '' }}
                            </span>
                        @endif
                    </span>
                    <i data-lucide="{{ $ucExpandida ? 'chevron-up' : 'chevron-down' }}" style="width:12px;height:12px;" aria-hidden="true"></i>
                </button>
                @if($ucExpandida)
                    <div style="padding: 0.5rem 0.75rem; border-top: 1px solid var(--color-ink-100);">
                        @if($this->ucVigente)
                            <ul style="list-style: none; margin: 0 0 0.5rem; padding: 0; display: flex; flex-direction: column; gap: 0.2rem;">
                                @foreach($this->ucMiembrosActivos as $ucm)
                                    <li style="font-size: 0.73rem; color: var(--color-ink-700); display: flex; align-items: center; gap: 0.3rem;">
                                        @if($ucm->verificado)
                                            <i data-lucide="shield-check" style="width:10px;height:10px; color: var(--color-success, #166534); flex-shrink:0;" aria-hidden="true"></i>
                                        @else
                                            <i data-lucide="shield-alert" style="width:10px;height:10px; color: var(--color-warning, #92400e); flex-shrink:0;" aria-hidden="true"></i>
                                        @endif
                                        @if($ucm->ciudadano)
                                            @php $tipoRelUc = $this->relacionesMiembrosUc->get($ucm->ciudadano_id); @endphp
                                            <a href="{{ route('ciudadania.ciudadano.ficha', $ucm->ciudadano) }}"
                                               style="color: inherit; text-decoration: none;"
                                               onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                                <span class="uc-widget-miembro__nombre">{{ $ucm->ciudadano->nombre }} {{ $ucm->ciudadano->apellido1 }}</span>
                                            </a>
                                            @if($tipoRelUc)
                                                <span class="uc-widget-miembro__relacion">{{ $tipoRelUc }}</span>
                                            @endif
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p style="font-size: 0.73rem; color: var(--color-ink-400); margin: 0 0 0.5rem;">Sin unidad de convivencia registrada.</p>
                        @endif
                        {{-- Botón gestionar UC --}}
                        <button wire:click="abrirModalUc" class="uc-widget__gestionar" title="Gestionar unidad de convivencia">
                            <i data-lucide="users" style="width:14px;height:14px;" aria-hidden="true"></i>
                            Gestionar UC
                        </button>
                        {{-- Botón para ver todas las relaciones del ciudadano --}}
                        <button
                            wire:click="abrirModalRelaciones"
                            class="uc-widget__ver-relaciones"
                            title="Ver todas las personas relacionadas"
                        >
                            <i data-lucide="network" style="width:13px;height:13px;"></i>
                            Ver todas las relaciones
                        </button>
                    </div>
                @endif
            </div>

        </div>

        {{-- ============================================================== --}}
        {{-- ZONA SUPERIOR DERECHA — toolbox de herramientas                --}}
        {{-- ============================================================== --}}
        <div class="ciudadano-header-right" style="padding: 1rem 1.25rem;">

            <div wire:key="toolbox-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                @foreach($herramientas as $h)
                    <button wire:key="tool-{{ $h['id'] }}"
                            wire:click="seleccionarHerramienta('{{ $h['id'] }}')"
                            style="background: {{ $herramientaActiva === $h['id'] ? 'var(--color-primary-soft)' : '#fff' }}; border: 1px solid {{ $herramientaActiva === $h['id'] ? 'var(--color-primary)' : 'var(--color-ink-200)' }}; border-radius: 8px; padding: 0.75rem 0.5rem; cursor: pointer; text-align: center; transition: all 0.1s;">
                        <i data-lucide="{{ $h['icon'] }}" style="font-size: inherit; width: 20px; height: 20px; color: {{ $herramientaActiva === $h['id'] ? 'var(--color-primary)' : 'var(--color-ink-600)' }}; display: block; margin-bottom: 0.3rem;" aria-hidden="true"></i>
                        <span style="font-size: 0.7rem; color: {{ $herramientaActiva === $h['id'] ? 'var(--color-primary-ink)' : 'var(--color-ink-700)' }}; font-weight: {{ $herramientaActiva === $h['id'] ? '600' : '400' }}; display: block;">
                            {{ $h['label'] }}
                            @if($h['fullpage'])
                                <span style="display: block; font-size: 0.62rem; color: var(--color-ink-400);">↗ pantalla completa</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>

        </div>

        {{-- ============================================================== --}}
        {{-- ZONA INFERIOR IZQUIERDA — filtros + timeline + últimos accesos --}}
        {{-- ============================================================== --}}
        <div class="ciudadano-body-left" style="padding: 0.75rem;">

            {{-- Filtros del timeline --}}
            <div style="display: flex; gap: 0.3rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                @foreach([
                    ['todos',      'Todos'],
                    ['plan',       $this->planNombreCorto],
                    ['entrevista', 'Entrevista'],
                    ['anotacion',  'Anotación'],
                    ['derivacion', 'Derivación'],
                    ['gestion',    'Gestión'],
                    ['valoracion', 'Valoración'],
                    ['escala',     'Escala'],
                ] as [$filtroKey, $filtroLabel])
                    @php
                        $esActivo   = $filtroHS === $filtroKey;
                        $esSugerido = ! $esActivo && $filtroSugerido === $filtroKey;
                    @endphp
                    <button wire:click="setFiltroHS('{{ $filtroKey }}')"
                            class="hs-timeline-filter{{ $esActivo ? ' hs-timeline-filter--activo' : ($esSugerido ? ' hs-timeline-filter--sugerido' : '') }}">
                        {{ $filtroLabel }}@if($esSugerido)<span class="hs-timeline-filter__hint" title="Filtrar por este tipo">↑</span>@endif
                    </button>
                @endforeach
            </div>

            {{-- Timeline de apuntes --}}
            @forelse($this->apuntesHS as $apunte)
                @php $colorPunto = $coloresTipo[$apunte->tipo->value] ?? 'var(--color-ink-500)'; @endphp
                <div wire:click="verApunte({{ $apunte->id }})"
                     role="button"
                     style="display: flex; gap: 0.5rem; align-items: flex-start; margin-bottom: 0.6rem; cursor: pointer; padding: 0.4rem 0.5rem; border-radius: 6px; background: transparent; transition: background 0.1s;"
                     onmouseover="this.style.background='var(--color-paper)'"
                     onmouseout="this.style.background='transparent'">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $colorPunto }}; flex-shrink: 0; margin-top: 5px;"></span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $apunte->tipo->label() }}
                        </div>
                        <div style="font-size: 0.7rem; color: var(--color-ink-400);">
                            {{ $apunte->fecha->format('d/m/Y') }} · {{ $apunte->autor?->name ?? '' }}
                        </div>
                        @if($apunte->contenido)
                            <div style="font-size: 0.72rem; color: var(--color-ink-600); margin-top: 0.15rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $apunte->contenido }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div style="font-size: 0.78rem; color: var(--color-ink-400); text-align: center; padding: 1rem 0;">
                    Sin registros en la historia social.
                </div>
            @endforelse

            {{-- ── Últimos accesos al expediente ──────────────────────── --}}
            <div class="accesos-panel">
                <div class="accesos-panel__header">
                    <span class="accesos-panel__titulo">Últimos accesos</span>
                    @if($this->puedeVerTodosLosAccesos)
                        {{-- TODO: modal historial completo --}}
                        <a href="#" class="accesos-panel__ver-todo">Ver todo</a>
                    @endif
                </div>

                @forelse($this->accesosRecientes as $acceso)
                    @php
                        $esPropio     = $acceso->user_id === Auth::id();
                        $uoAcceso     = $acceso->contexto['unidad_organizativa_id'] ?? null;
                        $uoAcceso     = $uoAcceso ?? $acceso->user?->profesional?->unidad_organizativa_id;
                        $esOtraUo     = $uoAcceso !== null && $uoAcceso !== $historia->unidad_organizativa_id;
                        $esCambio     = in_array($acceso->accion?->value, ['crear', 'editar', 'eliminar']);
                        $esAnomalos   = $esOtraUo && $esCambio;
                        $esSospechoso = $esOtraUo && ! $esCambio;
                    @endphp

                    <div class="acceso-fila
                        {{ $esPropio     ? 'acceso-fila--propio'      : '' }}
                        {{ $esAnomalos   ? 'acceso-fila--anomalo'     : '' }}
                        {{ $esSospechoso ? 'acceso-fila--sospechoso'  : '' }}">

                        <div class="acceso-fila__quien">
                            <span class="acceso-fila__nombre">
                                {{ $acceso->user?->profesional?->nombre_completo ?? $acceso->user?->name ?? '—' }}
                            </span>
                            @if($esOtraUo)
                                <span class="acceso-fila__badge-uo" title="Profesional de otra UO">Otra UO</span>
                            @endif
                        </div>

                        <div class="acceso-fila__detalle">
                            <span class="acceso-fila__accion acceso-fila__accion--{{ $acceso->accion?->value }}">
                                {{ $acceso->accion?->etiqueta() ?? '—' }}
                            </span>
                            @if($esAnomalos)
                                <span class="acceso-fila__alerta" title="Modificación desde otra UO — revisar">
                                    <i data-lucide="alert-triangle" style="width:14px;height:14px;" aria-hidden="true"></i>
                                </span>
                            @endif
                            <span class="acceso-fila__fecha">{{ $acceso->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <p class="accesos-panel__vacio">Sin accesos registrados.</p>
                @endforelse
            </div>

        </div>

        {{-- ============================================================== --}}
        {{-- ZONA INFERIOR DERECHA — área de trabajo activa + estadísticas  --}}
        {{-- ============================================================== --}}
        <div class="ciudadano-body-right">

            {{-- Área de trabajo de la herramienta activa --}}
            <div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem; min-height: 0;">

                @if($herramientaActiva === 'entrevista')
                    <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--color-ink-900);">Registrar entrevista</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Tipo</label>
                                <select wire:model="formEntrevista.tipo" class="form-select form-select-sm">
                                    <option value="seguimiento">Seguimiento</option>
                                    <option value="inicial">Inicial</option>
                                    <option value="urgencia">Urgencia</option>
                                    <option value="informativa">Informativa</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Modalidad</label>
                                <select wire:model="formEntrevista.modalidad" class="form-select form-select-sm">
                                    <option value="presencial">Presencial</option>
                                    <option value="telefonica">Telefónica</option>
                                    <option value="videollamada">Videollamada</option>
                                    <option value="domicilio">Domicilio</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Notas generales</label>
                            <textarea wire:model="formEntrevista.notas" rows="3" class="form-control form-control-sm" placeholder="Observaciones de la entrevista..."></textarea>
                        </div>
                        <div style="display: flex; gap: 1rem; margin-bottom: 0.75rem; font-size: 0.8rem;">
                            <label style="display: flex; align-items: center; gap: 0.3rem; cursor: pointer;">
                                <input type="checkbox" wire:model="formEntrevista.programar_seguimiento"> Programar siguiente seguimiento
                            </label>
                        </div>
                        @if($formEntrevista['programar_seguimiento'])
                            <div style="margin-bottom: 0.75rem;">
                                <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Fecha siguiente seguimiento</label>
                                <input type="date" wire:model="formEntrevista.fecha_siguiente_seguimiento" class="form-control form-control-sm">
                            </div>
                        @endif
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="guardarEntrevista" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar entrevista</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'anotacion')
                    <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--color-ink-900);">Guardar anotación</h3>
                        <div style="margin-bottom: 0.75rem;">
                            <textarea wire:model="formAnotacion.contenido" rows="4" class="form-control form-control-sm" placeholder="Escribe la anotación..."></textarea>
                        </div>
                        <div style="margin-bottom: 0.75rem; font-size: 0.8rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem; cursor: pointer;">
                                <input type="radio" wire:model="formAnotacion.visibilidad" value="profesionales"> Para profesionales
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" wire:model="formAnotacion.visibilidad" value="privada"> Privada (solo yo)
                            </label>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="guardarAnotacion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar anotación</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'derivacion')
                    <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--color-ink-900);">Crear derivación</h3>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Urgencia</label>
                            <select wire:model="formDerivacion.urgencia" class="form-select form-select-sm">
                                <option value="ordinaria">Ordinaria</option>
                                <option value="preferente">Preferente</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Motivo</label>
                            <textarea wire:model="formDerivacion.motivo" rows="3" class="form-control form-control-sm" placeholder="Motivo de la derivación..."></textarea>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="crearDerivacion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Crear derivación</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'gestion')
                    <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--color-ink-900);">Guardar gestión</h3>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Tipo de gestión</label>
                            <select wire:model="formGestion.tipo_gestion" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                <option value="coordinacion">Coordinación con otro servicio</option>
                                <option value="tramite">Trámite administrativo</option>
                                <option value="mesa_trabajo">Mesa de trabajo</option>
                                <option value="contacto_familia">Contacto con familia</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Recurso / interlocutor</label>
                            <input type="text" wire:model="formGestion.recurso_interlocutor" class="form-control form-control-sm" placeholder="Nombre del recurso o persona...">
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Descripción</label>
                            <textarea wire:model="formGestion.descripcion" rows="3" class="form-control form-control-sm" placeholder="Describe la gestión realizada..."></textarea>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="guardarGestion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar gestión</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'valoracion')
                    <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-ink-900);">Valoración</h3>
                        <p style="font-size: 0.78rem; color: var(--color-ink-600); margin: 0 0 0.75rem;">La ficha se abrirá en pantalla completa.</p>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Tipo de ficha</label>
                            <select wire:model.live="formValoracion.tipo_ficha_id" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                @foreach($this->tiposFicha as $tf)
                                    <option value="{{ $tf->id }}">{{ $tf->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            @if($formValoracion['tipo_ficha_id'])
                                <a href="{{ route('intervencion.valoracion.nueva', ['historia' => $historia->id, 'tipo_ficha' => $formValoracion['tipo_ficha_id']]) }}"
                                   class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla completa</a>
                            @endif
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'escala')
                    <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-ink-900);">Escala</h3>
                        <p style="font-size: 0.78rem; color: var(--color-ink-600); margin: 0 0 0.75rem;">La escala se abrirá en pantalla completa.</p>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">Instrumento</label>
                            <select wire:model.live="formEscala.tipo_escala_id" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                @foreach($this->tiposEscala as $te)
                                    <option value="{{ $te->id }}">{{ $te->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            @if($formEscala['tipo_escala_id'])
                                <a href="{{ route('intervencion.escala.nueva', ['historia' => $historia->id, 'tipo_escala' => $formEscala['tipo_escala_id']]) }}"
                                   class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla completa</a>
                            @endif
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'informes')
                    <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-ink-900);">Informes</h3>
                        {{-- TODO: conectar con módulo Documentos cuando implemente la vista de edición --}}
                        <p style="font-size: 0.85rem; color: var(--color-ink-600);">Módulo de informes en construcción.</p>
                        <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem; margin-top: 0.5rem;">Cerrar</button>
                    </div>

                @endif

            </div>

            {{-- Barra de estadísticas de contexto --}}
            <div class="hs-stats-bar">
                <div class="hs-stat">
                    <span class="hs-stat__val">{{ $this->statApuntes }}</span>
                    <span class="hs-stat__label">Apuntes</span>
                </div>
                <div class="hs-stat">
                    <span class="hs-stat__val">{{ $this->statPrestaciones ?? '—' }}</span>
                    <span class="hs-stat__label">Prestaciones activas</span>
                </div>
                <div class="hs-stat">
                    <span class="hs-stat__val">{{ $this->statUltimoContacto ?? '—' }}</span>
                    <span class="hs-stat__label">Último contacto</span>
                </div>
            </div>

        </div>

    </div>

    {{-- ================================================================== --}}
    {{-- Modal de detalle de apunte — genérico (entrevista, anotación, etc.) --}}
    {{-- ================================================================== --}}
    @if($modalApunteAbierto && ! in_array($modalApunteTipo, ['escala', 'valoracion']))
    <div class="hs-modal-overlay"
         wire:click.self="cerrarModalApunte"
         x-data x-on:keydown.escape.window="$wire.cerrarModalApunte()"
         role="dialog" aria-modal="true" aria-label="Detalle del apunte">
        <div class="hs-modal">
            <div class="hs-modal__header">
                <span class="hs-modal__tipo">{{ $modalApunteDatos['tipo_label'] ?? '' }}</span>
                <span class="hs-modal__fecha">{{ $modalApunteDatos['fecha'] ?? '' }}</span>
                <button wire:click="cerrarModalApunte" class="hs-modal__cerrar" aria-label="Cerrar">&times;</button>
            </div>
            <div class="hs-modal__body">
                <p class="hs-modal__autor"><strong>Profesional:</strong> {{ $modalApunteDatos['autor'] ?? '—' }}</p>
                @if($modalApunteDatos['contenido'] ?? null)
                    <div class="hs-modal__contenido">{!! nl2br(e($modalApunteDatos['contenido'])) !!}</div>
                @endif
            </div>
            <div class="hs-modal__footer">
                <span class="hs-modal__inmutable">Solo lectura · El pasado es inmutable</span>
                <button wire:click="cerrarModalApunte" class="btn btn-outline-secondary btn-sm">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================== --}}
    {{-- SlideOver de detalle — escala y valoración (ancho amplio)           --}}
    {{-- ================================================================== --}}
    @if($modalApunteAbierto && in_array($modalApunteTipo, ['escala', 'valoracion']))
    <div class="hs-slideover-overlay"
         wire:click.self="cerrarModalApunte"
         x-data x-on:keydown.escape.window="$wire.cerrarModalApunte()"
         role="dialog" aria-modal="true">
        <div class="hs-slideover">
            <div class="hs-slideover__header">
                <span class="hs-modal__tipo">{{ $modalApunteDatos['tipo_label'] ?? '' }}</span>
                <span class="hs-modal__fecha">{{ $modalApunteDatos['fecha'] ?? '' }}</span>
                <button wire:click="cerrarModalApunte" class="hs-modal__cerrar" aria-label="Cerrar">&times;</button>
            </div>
            <div class="hs-slideover__body">
                <p class="hs-modal__autor"><strong>Profesional:</strong> {{ $modalApunteDatos['autor'] ?? '—' }}</p>

                @if($modalApunteTipo === 'escala')
                    @if($modalApunteDatos['escala_nombre'] ?? null)
                        <h3 class="hs-slideover__subtitulo">{{ $modalApunteDatos['escala_nombre'] }}</h3>
                    @endif
                    @if(isset($modalApunteDatos['escala_score']))
                        <div class="hs-escala-score">
                            <span class="hs-escala-score__val">{{ $modalApunteDatos['escala_score'] }}</span>
                            @if($modalApunteDatos['escala_interpretacion'] ?? null)
                                <span class="hs-escala-score__interp">{{ $modalApunteDatos['escala_interpretacion'] }}</span>
                            @endif
                        </div>
                    @endif
                    @if(! empty($modalApunteDatos['escala_secciones']))
                        <div class="hs-escala-secciones">
                            @foreach($modalApunteDatos['escala_secciones'] as $sec => $score)
                                <div class="hs-escala-seccion">
                                    <span>{{ $sec }}</span>
                                    <span>{{ $score }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                @if($modalApunteTipo === 'valoracion' && ! empty($modalApunteDatos['ficha_campos']))
                    <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.5rem;">
                        @foreach($modalApunteDatos['ficha_campos'] as $campo)
                            <div style="padding: 0.55rem 0.75rem; background: var(--color-ink-50, #f8fafc); border-radius: 6px; border: 1px solid var(--color-ink-100);">
                                <p style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-ink-400); margin: 0 0 0.2rem;">{{ $campo['etiqueta'] }}</p>
                                @if($campo['valor'] !== null && $campo['valor'] !== '')
                                    <p style="font-size: 0.85rem; color: var(--color-ink-900); margin: 0;">
                                        @if($campo['tipo'] === 'booleano')
                                            {{ $campo['valor'] ? 'Sí' : 'No' }}
                                        @elseif($campo['tipo'] === 'fecha')
                                            {{ \Carbon\Carbon::parse($campo['valor'])->translatedFormat('j M Y') }}
                                        @else
                                            {{ $campo['valor'] }}{{ $campo['unidad'] ? ' '.$campo['unidad'] : '' }}
                                        @endif
                                    </p>
                                @else
                                    <p style="font-size: 0.82rem; color: var(--color-ink-300); margin: 0; font-style: italic;">Sin respuesta</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($modalApunteDatos['ficha_notas'] ?? null)
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--color-ink-100);">
                            <p style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-ink-400); margin: 0 0 0.25rem;">Notas</p>
                            <p style="font-size: 0.82rem; color: var(--color-ink-700); margin: 0; white-space: pre-wrap;">{{ $modalApunteDatos['ficha_notas'] }}</p>
                        </div>
                    @endif
                @endif

                @if($modalApunteDatos['contenido'] ?? null)
                    <div class="hs-modal__contenido" style="margin-top: 1rem;">{!! nl2br(e($modalApunteDatos['contenido'])) !!}</div>
                @endif
            </div>
            <div class="hs-slideover__footer">
                <span class="hs-modal__inmutable">Solo lectura · El pasado es inmutable</span>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    @if(($modalApunteDatos['ficha_url'] ?? null))
                        <a href="{{ $modalApunteDatos['ficha_url'] }}" wire:navigate
                           style="font-size: 0.8rem; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                            <i data-lucide="external-link" style="width:13px;height:13px;" aria-hidden="true"></i>
                            Ver ficha completa
                        </a>
                    @endif
                    <button wire:click="cerrarModalApunte" class="btn btn-outline-secondary btn-sm">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================== --}}
    {{-- MODAL: GESTIÓN DE UNIDAD DE CONVIVENCIA                            --}}
    {{-- ================================================================== --}}
    @if($this->modalUcAbierto)
    <div
        class="uc-modal-backdrop"
        x-data
        x-on:keydown.escape.window="$wire.cerrarModalUc()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="uc-modal-titulo"
    >
        <div class="uc-modal">

            {{-- Cabecera --}}
            <div class="uc-modal__header">
                <h2 id="uc-modal-titulo" class="uc-modal__titulo">Unidad de convivencia</h2>
                <button wire:click="cerrarModalUc" class="uc-modal__cerrar" aria-label="Cerrar">
                    <i data-lucide="x" style="width:18px;height:18px;" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Feedback --}}
            @if($ucMensaje)
            <div class="uc-modal__mensaje" wire:key="uc-mensaje">
                <i data-lucide="check-circle" style="width:14px;height:14px;" aria-hidden="true"></i>
                {{ $ucMensaje }}
            </div>
            @endif

            {{-- Cuerpo --}}
            <div class="uc-modal__cuerpo">

                @if(! $this->ucVigente)
                    {{-- Sin UC: opción de crear --}}
                    <div class="uc-modal__vacio">
                        <p>Este ciudadano no tiene unidad de convivencia registrada.</p>
                        <button wire:click="crearUc" class="uc-modal__btn-crear">
                            <i data-lucide="plus" style="width:14px;height:14px;" aria-hidden="true"></i>
                            Crear unidad de convivencia
                        </button>
                    </div>

                @else
                    {{-- Lista de miembros activos --}}
                    <div class="uc-modal__seccion">
                        <h3 class="uc-modal__seccion-titulo">
                            Miembros activos
                            <span class="uc-modal__badge">{{ $this->ucMiembrosActivos->count() }}</span>
                        </h3>

                        <ul class="uc-modal__lista">
                            @forelse($this->ucMiembrosActivos as $miembro)
                            <li class="uc-modal__miembro" wire:key="miembro-{{ $miembro->id }}">

                                <div class="uc-modal__miembro-info">
                                    @if($miembro->ciudadano)
                                    <a href="{{ route('ciudadania.ciudadano.ficha', $miembro->ciudadano) }}"
                                       class="uc-modal__miembro-nombre">
                                        {{ $miembro->ciudadano->nombre }}
                                        {{ $miembro->ciudadano->apellido1 }}
                                        {{ $miembro->ciudadano->apellido2 }}
                                    </a>
                                    @else
                                    <span class="uc-modal__miembro-nombre">—</span>
                                    @endif
                                    <span class="uc-modal__miembro-meta">
                                        Desde {{ $miembro->fecha_inicio?->format('d/m/Y') }}
                                    </span>
                                </div>

                                <div class="uc-modal__miembro-acciones">
                                    @if($miembro->verificado)
                                        <span class="uc-badge uc-badge--verificado" title="Residencia verificada">
                                            <i data-lucide="shield-check" style="width:12px;height:12px;" aria-hidden="true"></i>
                                            Verificado
                                        </span>
                                    @else
                                        <button
                                            wire:click="verificarMiembro({{ $miembro->id }})"
                                            class="uc-badge uc-badge--sin-verificar"
                                            title="Verificar residencia manualmente"
                                        >
                                            <i data-lucide="shield-alert" style="width:12px;height:12px;" aria-hidden="true"></i>
                                            Sin verificar
                                        </button>
                                    @endif

                                    @if($ucMiembroParaBaja === $miembro->id)
                                        <span class="uc-modal__confirmar-baja">
                                            ¿Confirmar baja?
                                            <button wire:click="confirmarBajaMiembro" class="uc-btn uc-btn--danger-sm">Sí</button>
                                            <button wire:click="cancelarBajaMiembro" class="uc-btn uc-btn--ghost-sm">No</button>
                                        </span>
                                    @else
                                        <button
                                            wire:click="iniciarBajaMiembro({{ $miembro->id }})"
                                            class="uc-btn uc-btn--ghost-sm"
                                            title="Dar de baja como miembro"
                                        >
                                            <i data-lucide="user-minus" style="width:13px;height:13px;" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>

                            </li>
                            @empty
                            <li class="uc-modal__vacio-lista">No hay miembros activos.</li>
                            @endforelse
                        </ul>
                    </div>

                    {{-- Añadir miembro --}}
                    <div class="uc-modal__seccion">
                        <h3 class="uc-modal__seccion-titulo">Añadir miembro</h3>

                        @if($ucCiudadanoSeleccionado)
                            @php $cSeleccionado = \App\Models\Ciudadano::find($ucCiudadanoSeleccionado); @endphp
                            <div class="uc-modal__confirmar-adicion">
                                <span>
                                    ¿Añadir a <strong>{{ $cSeleccionado?->nombre }} {{ $cSeleccionado?->apellido1 }}</strong> como miembro de esta unidad?
                                </span>
                                <div class="uc-modal__confirmar-acciones">
                                    <button wire:click="confirmarAnadirMiembro" class="uc-btn uc-btn--primary-sm">Confirmar</button>
                                    <button wire:click="cancelarSeleccionUc" class="uc-btn uc-btn--ghost-sm">Cancelar</button>
                                </div>
                            </div>

                        @else
                            <div class="uc-modal__busqueda">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="ucBusqueda"
                                    placeholder="Buscar por nombre…"
                                    class="uc-modal__input"
                                    autocomplete="off"
                                />
                                <i data-lucide="search" class="uc-modal__busqueda-icon" style="width:14px;height:14px;" aria-hidden="true"></i>
                            </div>

                            @if($this->ucResultadosBusqueda->isNotEmpty())
                            <ul class="uc-modal__resultados">
                                @foreach($this->ucResultadosBusqueda as $resultado)
                                <li
                                    wire:click="seleccionarCiudadanoUc({{ $resultado->id }})"
                                    class="uc-modal__resultado"
                                    wire:key="resultado-{{ $resultado->id }}"
                                >
                                    <span class="uc-modal__resultado-nombre">
                                        {{ $resultado->nombre }} {{ $resultado->apellido1 }} {{ $resultado->apellido2 }}
                                    </span>
                                    @if(! $resultado->tieneResidenciaVerificada())
                                        <span class="uc-badge uc-badge--sin-verificar uc-badge--sm">Sin verificar</span>
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                            @elseif(strlen(trim($ucBusqueda)) >= 2)
                            <div class="uc-modal__sin-resultados">
                                No se encontró ningún ciudadano con ese nombre.
                                {{-- TODO: pasar contexto UC a AltaCiudadano para prerellenar domicilio --}}
                                <a href="{{ route('ciudadania.alta') }}" class="uc-modal__alta-link">
                                    Dar de alta ciudadano nuevo
                                </a>
                            </div>
                            @endif
                        @endif
                    </div>
                @endif

            </div>{{-- fin cuerpo --}}

            {{-- Pie --}}
            <div class="uc-modal__pie">
                <button wire:click="cerrarModalUc" class="uc-btn uc-btn--ghost">Cerrar</button>
            </div>

        </div>{{-- fin .uc-modal --}}
    </div>{{-- fin backdrop --}}
    @endif

    {{-- ================================================================== --}}
    {{-- MODAL: DATOS DE CONTACTO DEL REPRESENTANTE                         --}}
    {{-- ================================================================== --}}
    @if($this->modalRepresentanteAbierto && $this->representante)
    <div
        class="uc-modal-backdrop"
        x-data
        x-on:keydown.escape.window="$wire.cerrarModalRepresentante()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-representante-titulo"
    >
        <div class="uc-modal uc-modal--sm">

            <div class="uc-modal__header">
                <h2 id="modal-representante-titulo" class="uc-modal__titulo">
                    Representante
                </h2>
                <button wire:click="cerrarModalRepresentante"
                        class="uc-modal__cerrar" aria-label="Cerrar">
                    <i data-lucide="x" style="width:18px;height:18px;"></i>
                </button>
            </div>

            <div class="uc-modal__cuerpo">
                <div class="rel-modal__persona">
                    <span class="rel-modal__nombre">
                        {{ $this->representante->nombre }}
                        {{ $this->representante->apellido1 }}
                        {{ $this->representante->apellido2 }}
                    </span>

                    @if($this->representante->telefono)
                    <a href="tel:{{ $this->representante->telefono }}"
                       class="rel-modal__dato">
                        <i data-lucide="phone" style="width:13px;height:13px;"></i>
                        {{ $this->representante->telefono }}
                    </a>
                    @endif

                    @if($this->representante->email)
                    <a href="mailto:{{ $this->representante->email }}"
                       class="rel-modal__dato">
                        <i data-lucide="mail" style="width:13px;height:13px;"></i>
                        {{ $this->representante->email }}
                    </a>
                    @endif

                    @if(! $this->representante->telefono && ! $this->representante->email)
                    <span class="rel-modal__sin-contacto">
                        Sin datos de contacto registrados.
                    </span>
                    @endif
                </div>

                <div class="rel-modal__pie-accion">
                    <a
                        href="{{ route('ciudadania.ciudadano.ficha', $this->representante->id) }}"
                        class="rel-modal__link-ficha"
                        wire:navigate
                    >
                        <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                        Ver ficha completa
                    </a>
                </div>
            </div>

            <div class="uc-modal__pie">
                <button wire:click="cerrarModalRepresentante" class="uc-btn uc-btn--ghost">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
    @endif

    {{-- ================================================================== --}}
    {{-- MODAL: TODAS LAS RELACIONES DEL CIUDADANO                          --}}
    {{-- ================================================================== --}}
    @if($this->modalRelacionesAbierto)
    <div
        class="uc-modal-backdrop"
        x-data
        x-on:keydown.escape.window="$wire.cerrarModalRelaciones()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-relaciones-titulo"
    >
        <div class="uc-modal">

            <div class="uc-modal__header">
                <h2 id="modal-relaciones-titulo" class="uc-modal__titulo">
                    Personas relacionadas
                </h2>
                <button wire:click="cerrarModalRelaciones"
                        class="uc-modal__cerrar" aria-label="Cerrar">
                    <i data-lucide="x" style="width:18px;height:18px;"></i>
                </button>
            </div>

            <div class="uc-modal__cuerpo">

                @forelse($this->relacionesAgrupadas as $slug => $grupo)
                <div class="uc-modal__seccion" wire:key="grupo-{{ $slug }}">
                    <h3 class="uc-modal__seccion-titulo">
                        {{ $grupo['etiqueta'] }}
                        <span class="uc-modal__badge">
                            {{ $grupo['miembros']->count() }}
                        </span>
                    </h3>

                    <ul class="uc-modal__lista">
                        @foreach($grupo['miembros'] as $persona)
                        <li class="uc-modal__miembro" wire:key="rel-{{ $slug }}-{{ $persona->id }}">
                            <div class="uc-modal__miembro-info">
                                <span class="uc-modal__miembro-nombre">
                                    {{ $persona->nombre }}
                                    {{ $persona->apellido1 }}
                                    {{ $persona->apellido2 }}
                                </span>
                                @if($persona->telefono)
                                <span class="uc-modal__miembro-meta">
                                    {{ $persona->telefono }}
                                </span>
                                @endif
                            </div>
                            <div class="uc-modal__miembro-acciones">
                                <a
                                    href="{{ route('ciudadania.ciudadano.ficha', $persona->id) }}"
                                    class="uc-btn uc-btn--ghost-sm"
                                    wire:navigate
                                    title="Ver ficha"
                                >
                                    <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                                </a>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @empty
                <div class="uc-modal__vacio">
                    <p>No hay personas relacionadas registradas.</p>
                    <a
                        href="{{ route('ciudadania.ciudadano.ficha', $this->ciudadano->id) }}"
                        class="uc-modal__alta-link"
                        wire:navigate
                    >
                        Gestionar relaciones en la ficha del ciudadano
                    </a>
                </div>
                @endforelse

            </div>

            <div class="uc-modal__pie">
                <a
                    href="{{ route('ciudadania.ciudadano.ficha', $this->ciudadano->id) }}"
                    class="rel-modal__link-ficha"
                    wire:navigate
                >
                    <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                    Gestionar relaciones en la ficha
                </a>
                <button wire:click="cerrarModalRelaciones" class="uc-btn uc-btn--ghost">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
    @endif

</div>
