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

            {{-- Unidad de convivencia --}}
            <div style="margin-top: 0.75rem; border: 1px solid var(--color-ink-200); border-radius: 8px; overflow: hidden;">
                <button wire:click="toggleUC"
                        style="width: 100%; background: #fff; border: none; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                    <span>Unidad de convivencia</span>
                    <i data-lucide="{{ $ucExpandida ? 'chevron-up' : 'chevron-down' }}" style="width:12px;height:12px;" aria-hidden="true"></i>
                </button>
                @if($ucExpandida)
                    <div style="padding: 0.5rem 0.75rem; font-size: 0.75rem; color: var(--color-ink-400); border-top: 1px solid var(--color-ink-100);">
                        {{-- TODO: implementar cuando exista la tabla unidades_convivencia --}}
                        Sin datos de unidad de convivencia disponibles.
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
                @foreach([['todos', 'Todos'], ['plan', $this->planNombreCorto], ['entrevista', 'Entrevistas']] as [$key, $label])
                    <button wire:click="setFiltroHS('{{ $key }}')"
                            style="font-size: 0.72rem; padding: 0.2rem 0.6rem; border-radius: 99px; border: 1px solid {{ $filtroHS === $key ? 'var(--color-primary)' : 'var(--color-ink-200)' }}; background: {{ $filtroHS === $key ? 'var(--color-primary-soft)' : '#fff' }}; color: {{ $filtroHS === $key ? 'var(--color-primary-ink)' : 'var(--color-ink-600)' }}; cursor: pointer; font-weight: {{ $filtroHS === $key ? '600' : '400' }};">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Timeline de apuntes --}}
            @forelse($this->apuntesHS as $apunte)
                @php
                    $colorPunto = $coloresTipo[$apunte->tipo->value] ?? 'var(--color-ink-500)';
                    $expandido  = $apuntesExpandidos[$apunte->id] ?? false;
                @endphp
                <div wire:click="toggleApunte({{ $apunte->id }})"
                     style="display: flex; gap: 0.5rem; align-items: flex-start; margin-bottom: 0.6rem; cursor: pointer; padding: 0.4rem 0.5rem; border-radius: 6px; background: {{ $expandido ? 'var(--color-paper)' : 'transparent' }}; transition: background 0.1s;"
                     onmouseover="this.style.background='var(--color-paper)'"
                     onmouseout="this.style.background='{{ $expandido ? 'var(--color-paper)' : 'transparent' }}'">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $colorPunto }}; flex-shrink: 0; margin-top: 5px;"></span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $apunte->tipo->label() }}
                        </div>
                        <div style="font-size: 0.7rem; color: var(--color-ink-400);">
                            {{ $apunte->fecha->format('d/m/Y') }} · {{ $apunte->autor->name ?? '' }}
                        </div>
                        @if($expandido && $apunte->contenido)
                            <div style="font-size: 0.78rem; color: var(--color-ink-700); margin-top: 0.3rem; white-space: pre-wrap; line-height: 1.5;">{{ $apunte->contenido }}</div>
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
                            <select wire:model="formValoracion.tipo_ficha_id" class="form-select form-select-sm">
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
                            <select wire:model="formEscala.tipo_escala_id" class="form-select form-select-sm">
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

</div>
