<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Queries\AccesosExpedienteQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Ciudadania\Enums\ImplicacionFuncional;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\Valoracion;

/**
 * Pantalla principal de trabajo con el ciudadano.
 *
 * Concentra el timeline de la Historia Social y las siete herramientas de registro.
 * La ruta aplica `can:view,historia` usando HistoriaSocialPolicy.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega3.md
 *
 * Propiedades computadas expuestas como propiedades mágicas por Livewire 4 #[Computed]:
 *
 * @property-read Collection<int, Audit> $accesosRecientes
 * @property-read bool $puedeVerTodosLosAccesos
 */
#[Layout('layouts.operativo')]
class CiudadanoPage extends Component
{
    /** @var HistoriaSocial Inyectada por route model binding */
    public HistoriaSocial $historia;

    /** @var string Filtro del timeline: 'todos' | 'plan' | 'entrevista' */
    public string $filtroHS = 'todos';

    /** @var bool Unidad de convivencia expandida */
    public bool $ucExpandida = false;

    /** @var array<int, bool> IDs de apuntes expandidos en el timeline */
    public array $apuntesExpandidos = [];

    /** @var string|null Herramienta activa */
    public ?string $herramientaActiva = null;

    /** Tipo de apunte sugerido visualmente según la herramienta activa. No filtra solo. */
    public ?string $filtroSugerido = null;

    /** ID del apunte cuyo detalle se muestra en el modal. */
    public ?int $modalApunteId = null;

    /** Valor del enum TipoApunte del apunte en el modal. */
    public ?string $modalApunteTipo = null;

    /** Datos del apunte para el modal (array serializable para Livewire). */
    public array $modalApunteDatos = [];

    /** Controla si el modal de detalle de apunte está abierto. */
    public bool $modalApunteAbierto = false;

    // --- Modal UC ---

    /** @var bool Modal de gestión de UC abierto */
    public bool $modalUcAbierto = false;

    /** @var string Texto de búsqueda para añadir miembro a la UC */
    public string $ucBusqueda = '';

    /** @var int|null ID de UnidadConvivenciaMiembro en proceso de confirmación de baja */
    public ?int $ucMiembroParaBaja = null;

    /** @var int|null ciudadano_id seleccionado para confirmar su adición a la UC */
    public ?int $ucCiudadanoSeleccionado = null;

    /** @var string Mensaje de feedback de operación exitosa o error */
    public string $ucMensaje = '';

    // --- Modal relaciones ---

    /** @var bool Modal de todas las relaciones abierto */
    public bool $modalRelacionesAbierto = false;

    /** @var bool Modal de datos de contacto del representante abierto */
    public bool $modalRepresentanteAbierto = false;

    // -------------------------------------------------------------------------
    // Formularios de herramientas inline
    // -------------------------------------------------------------------------

    /** @var array<string, mixed> */
    public array $formEntrevista = [
        'tipo' => 'seguimiento',
        'modalidad' => 'presencial',
        'notas' => '',
        'generar_valoracion' => false,
        'programar_seguimiento' => false,
        'fecha_siguiente_seguimiento' => '',
    ];

    /** @var array<string, mixed> */
    public array $formAnotacion = [
        'contenido' => '',
        'visibilidad' => 'profesionales',
    ];

    /** @var array<string, mixed> */
    public array $formDerivacion = [
        'servicio_receptor_id' => '',
        'urgencia' => 'ordinaria',
        'motivo' => '',
    ];

    /** @var array<string, mixed> */
    public array $formGestion = [
        'tipo_gestion' => '',
        'recurso_interlocutor' => '',
        'descripcion' => '',
    ];

    /** @var array<string, mixed> */
    public array $formValoracion = [
        'tipo_ficha_id' => '',
    ];

    /** @var array<string, mixed> */
    public array $formEscala = [
        'tipo_escala_id' => '',
    ];

    // -------------------------------------------------------------------------
    // Propiedades computadas
    // -------------------------------------------------------------------------

    /**
     * Ciudadano titular de la Historia Social.
     *
     * @return Ciudadano|null
     */
    #[Computed]
    public function ciudadano(): ?Ciudadano
    {
        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->find($this->historia->ciudadano_id);
    }

    /**
     * Apuntes visibles de la Historia Social, aplicando el filtro activo.
     *
     * @return Collection<int, Apunte>
     */
    #[Computed]
    public function apuntesHS(): Collection
    {
        $query = Apunte::withoutGlobalScopes()
            ->whereHas('plan', fn ($q) => $q->withoutGlobalScopes()
                ->where('historia_id', $this->historia->id))
            ->visiblesParaUsuario(Auth::id())
            ->with('autor')
            ->latest();

        $mapaFiltro = [
            'plan' => TipoApunte::PlanIntervencion,
            'entrevista' => TipoApunte::Entrevista,
            'anotacion' => TipoApunte::Anotacion,
            'derivacion' => TipoApunte::Derivacion,
            'gestion' => TipoApunte::GestionCoordinacion,
            'valoracion' => TipoApunte::Valoracion,
            'escala' => TipoApunte::Escala,
        ];

        if (isset($mapaFiltro[$this->filtroHS])) {
            $query->where('tipo', $mapaFiltro[$this->filtroHS]);
        }

        return $query->get();
    }

    /**
     * Plan general ASP activo más reciente de la Historia Social.
     *
     * @return PlanDeIntervencion|null
     */
    #[Computed]
    public function pisoActivo(): ?PlanDeIntervencion
    {
        return PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $this->historia->id)
            ->where('tipo', TipoPlan::GeneralAsp)
            ->where('estado', EstadoPlan::Activo)
            ->latest()
            ->first();
    }

    /**
     * Tipos de ficha disponibles para la herramienta de Valoración.
     *
     * @return Collection<int, TipoFicha>
     */
    #[Computed]
    public function tiposFicha(): Collection
    {
        return TipoFicha::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Tipos de escala disponibles para la herramienta de Escala.
     *
     * @return Collection<int, TipoEscala>
     */
    #[Computed]
    public function tiposEscala(): Collection
    {
        return TipoEscala::where('activa', true)->orderBy('nombre')->get();
    }

    /**
     * Últimos 5 accesos al expediente filtrados según la visibilidad del usuario.
     *
     * @return Collection<int, Audit>
     */
    #[Computed]
    public function accesosRecientes(): Collection
    {
        $ciudadano = $this->ciudadano;
        if (! $ciudadano) {
            return collect();
        }

        return app(AccesosExpedienteQuery::class)
            ->paraUsuario(Auth::user(), $ciudadano, $this->historia)
            ->limit(5)
            ->get();
    }

    /**
     * Indica si el usuario puede ver todos los accesos o únicamente los propios.
     * Controla la visibilidad del enlace "Ver todo" en el widget.
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerTodosLosAccesos(): bool
    {
        return app(AccesosExpedienteQuery::class)->puedeVerTodos(Auth::user(), $this->historia);
    }

    /**
     * Nombre corto (o completo) de la UO responsable de la Historia Social.
     * Se muestra en la cabecera del ciudadano sustituyendo al ID numérico de UO.
     *
     * @return string|null
     */
    #[Computed]
    public function uoNombre(): ?string
    {
        return $this->historia->unidadOrganizativa?->nombre_corto
            ?? $this->historia->unidadOrganizativa?->nombre
            ?? null;
    }

    /**
     * Nombre corto del Plan de Intervención según la UO de la Historia Social.
     * Fallback: «Plan».
     *
     * @return string
     */
    #[Computed]
    public function planNombreCorto(): string
    {
        return $this->historia->unidadOrganizativa?->plan_nombre_corto ?? 'Plan';
    }

    /**
     * Nombre completo del Plan de Intervención según la UO de la Historia Social.
     * Fallback: «Plan de intervención».
     *
     * @return string
     */
    #[Computed]
    public function planNombreCompleto(): string
    {
        return $this->historia->unidadOrganizativa?->plan_nombre_completo ?? 'Plan de intervención';
    }

    /**
     * Documento de identidad del ciudadano (cifrado, desencriptado por el cast).
     *
     * @return string|null
     */
    #[Computed]
    public function ciudadanoDocumento(): ?string
    {
        return $this->ciudadano?->documento_identidad ?? null;
    }

    /**
     * Teléfono de contacto del ciudadano.
     *
     * @return string|null
     */
    #[Computed]
    public function ciudadanoTelefono(): ?string
    {
        return $this->ciudadano?->telefono ?? null;
    }

    /**
     * Correo electrónico de contacto del ciudadano.
     *
     * @return string|null
     */
    #[Computed]
    public function ciudadanoEmail(): ?string
    {
        return $this->ciudadano?->email ?? null;
    }

    /**
     * Total de apuntes visibles en la Historia Social (todos los filtros).
     * Usa la colección ya cargada para evitar consulta adicional.
     *
     * @return int
     */
    #[Computed]
    public function statApuntes(): int
    {
        return $this->apuntesHS->count();
    }

    /**
     * Prestaciones activas del ciudadano.
     * Pendiente de integración real con el módulo Prestaciones.
     *
     * @return string|null
     *
     * @todo Integrar con módulo Prestaciones cuando esté disponible.
     */
    #[Computed]
    public function statPrestaciones(): ?string
    {
        return null;
    }

    /**
     * Fecha del último registro en la Historia Social formateada.
     *
     * @return string|null
     */
    #[Computed]
    public function statUltimoContacto(): ?string
    {
        return $this->apuntesHS->first()?->created_at?->translatedFormat('j M Y');
    }

    /**
     * UC vigente del ciudadano (primera activa), con miembros y ciudadanos cargados.
     *
     * @return UnidadConvivencia|null
     */
    #[Computed]
    public function ucVigente(): ?UnidadConvivencia
    {
        if (! $this->ciudadano) {
            return null;
        }

        return $this->ciudadano
            ->unidadesConvivenciaActivas()
            ->with(['miembrosActivos.ciudadano'])
            ->first();
    }

    /**
     * Miembros activos de la UC vigente con datos de ciudadano cargados.
     *
     * @return \Illuminate\Support\Collection<int, UnidadConvivenciaMiembro>
     */
    #[Computed]
    public function ucMiembrosActivos(): \Illuminate\Support\Collection
    {
        return $this->ucVigente
            ? $this->ucVigente->miembrosActivos()->with('ciudadano')->get()
            : collect();
    }

    /**
     * Ciudadanos que coinciden con la búsqueda, excluyendo miembros actuales y titular.
     * Carga en PHP porque los campos de nombre están cifrados (mismo patrón que BuscarCiudadanoPage).
     *
     * @return \Illuminate\Support\Collection<int, Ciudadano>
     */
    #[Computed]
    public function ucResultadosBusqueda(): \Illuminate\Support\Collection
    {
        if (strlen(trim($this->ucBusqueda)) < 2 || ! $this->ciudadano) {
            return collect();
        }

        $excluidos = $this->ucMiembrosActivos
            ->pluck('ciudadano_id')
            ->push($this->ciudadano->id)
            ->all();

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->whereNotIn('id', $excluidos)
            ->limit(500)
            ->get()
            ->filter(fn ($c) => str_contains(
                mb_strtolower($c->nombre.' '.$c->apellido1.' '.($c->apellido2 ?? '')),
                mb_strtolower(trim($this->ucBusqueda))
            ))
            ->take(8)
            ->values();
    }

    /**
     * Representante legal/designado del ciudadano, si existe relación activa.
     * Busca por implicacion_funcional = 'representante', nunca por slug directamente.
     *
     * @return Ciudadano|null
     */
    #[Computed]
    public function representante(): ?Ciudadano
    {
        $slugsRepresentante = TipoRelacion::where(
            'implicacion_funcional',
            ImplicacionFuncional::Representante->value
        )->pluck('slug')->toArray();

        if (empty($slugsRepresentante) || ! $this->ciudadano) {
            return null;
        }

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)
            ->whereIn('tipo_relacion', $slugsRepresentante)
            ->whereNull('fecha_fin')
            ->first();

        // Se carga sin AmbitoUoScope porque el representante puede pertenecer
        // a una UO diferente a la del titular del expediente.
        return $relacion
            ? Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->find($relacion->ciudadano_relacionado_id)
            : null;
    }

    /**
     * Relaciones activas del ciudadano agrupadas por tipo (para el modal completo).
     * Excluye tipos no presentes en el catálogo activo.
     *
     * @return \Illuminate\Support\Collection<string, array{etiqueta: string, miembros: \Illuminate\Support\Collection}>
     */
    #[Computed]
    public function relacionesAgrupadas(): \Illuminate\Support\Collection
    {
        if (! $this->ciudadano) {
            return collect();
        }

        $slugsActivos = TipoRelacion::activos()->pluck('etiqueta', 'slug');

        return CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)
            ->whereNull('fecha_fin')
            ->with('ciudadanoRelacionado')
            ->get()
            ->filter(fn ($r) => $slugsActivos->has($r->tipo_relacion))
            ->groupBy('tipo_relacion')
            ->map(fn ($grupo, $slug) => [
                'etiqueta' => $slugsActivos[$slug],
                'miembros' => $grupo->map(fn ($r) => $r->ciudadanoRelacionado),
            ]);
    }

    /**
     * Tipo de relación (etiqueta) de cada miembro de la UC respecto al titular,
     * indexado por ciudadano_id. Enriquece el widget UC.
     *
     * @return \Illuminate\Support\Collection<int, string|null>
     */
    #[Computed]
    public function relacionesMiembrosUc(): \Illuminate\Support\Collection
    {
        if (! $this->ucVigente || ! $this->ciudadano) {
            return collect();
        }

        $idsMiembros = $this->ucMiembrosActivos->pluck('ciudadano_id')->toArray();

        if (empty($idsMiembros)) {
            return collect();
        }

        $slugsEtiquetas = TipoRelacion::activos()->pluck('etiqueta', 'slug');

        return CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)
            ->whereIn('ciudadano_relacionado_id', $idsMiembros)
            ->whereNull('fecha_fin')
            ->get()
            ->mapWithKeys(fn ($r) => [
                $r->ciudadano_relacionado_id => $slugsEtiquetas[$r->tipo_relacion] ?? null,
            ]);
    }

    // -------------------------------------------------------------------------
    // Métodos de UI
    // -------------------------------------------------------------------------

    /**
     * Expande o contrae la sección de unidad de convivencia.
     *
     * @return void
     */
    public function toggleUC(): void
    {
        $this->ucExpandida = ! $this->ucExpandida;
    }

    /**
     * Abre el modal con todas las relaciones del ciudadano.
     *
     * @return void
     */
    public function abrirModalRelaciones(): void
    {
        $this->modalRelacionesAbierto = true;
    }

    /**
     * Cierra el modal con todas las relaciones del ciudadano.
     *
     * @return void
     */
    public function cerrarModalRelaciones(): void
    {
        $this->modalRelacionesAbierto = false;
    }

    /**
     * Abre el modal de datos del representante.
     *
     * @return void
     */
    public function abrirModalRepresentante(): void
    {
        $this->modalRepresentanteAbierto = true;
    }

    /**
     * Cierra el modal de datos del representante.
     *
     * @return void
     */
    public function cerrarModalRepresentante(): void
    {
        $this->modalRepresentanteAbierto = false;
    }

    /**
     * Abre el modal de gestión de UC y reinicia su estado interno.
     *
     * @return void
     */
    public function abrirModalUc(): void
    {
        $this->modalUcAbierto = true;
        $this->ucBusqueda = '';
        $this->ucMiembroParaBaja = null;
        $this->ucCiudadanoSeleccionado = null;
        $this->ucMensaje = '';
    }

    /**
     * Cierra el modal de gestión de UC.
     *
     * @return void
     */
    public function cerrarModalUc(): void
    {
        $this->modalUcAbierto = false;
    }

    /**
     * Selecciona un ciudadano de los resultados de búsqueda para confirmar su adición.
     *
     * @param int $ciudadanoId ID del ciudadano seleccionado.
     *
     * @return void
     */
    public function seleccionarCiudadanoUc(int $ciudadanoId): void
    {
        $this->ucCiudadanoSeleccionado = $ciudadanoId;
        $this->ucBusqueda = '';
    }

    /**
     * Confirma la adición del ciudadano seleccionado a la UC vigente.
     *
     * @return void
     */
    public function confirmarAnadirMiembro(): void
    {
        if (! $this->ucCiudadanoSeleccionado || ! $this->ucVigente) {
            return;
        }

        try {
            $this->ucVigente->agregarMiembro($this->ucCiudadanoSeleccionado);
            $this->ucCiudadanoSeleccionado = null;
            $this->ucMensaje = 'Miembro añadido correctamente.';
            unset($this->ucMiembrosActivos);
            unset($this->ucVigente);
        } catch (\LogicException $e) {
            $this->ucMensaje = $e->getMessage();
        }
    }

    /**
     * Cancela la selección de ciudadano para añadir a la UC.
     *
     * @return void
     */
    public function cancelarSeleccionUc(): void
    {
        $this->ucCiudadanoSeleccionado = null;
    }

    /**
     * Inicia el flujo de confirmación de baja de un miembro.
     *
     * @param int $miembroId ID de UnidadConvivenciaMiembro.
     *
     * @return void
     */
    public function iniciarBajaMiembro(int $miembroId): void
    {
        $this->ucMiembroParaBaja = $miembroId;
    }

    /**
     * Confirma la baja del miembro seleccionado, estableciendo su fecha_fin.
     *
     * @return void
     */
    public function confirmarBajaMiembro(): void
    {
        if (! $this->ucMiembroParaBaja || ! $this->ucVigente) {
            return;
        }

        $miembro = UnidadConvivenciaMiembro::find($this->ucMiembroParaBaja);

        if (! $miembro || $miembro->unidad_convivencia_id !== $this->ucVigente->id) {
            $this->ucMiembroParaBaja = null;

            return;
        }

        $this->ucVigente->darDeBajaMiembro($miembro->ciudadano_id);
        $this->ucMiembroParaBaja = null;
        $this->ucMensaje = 'Miembro dado de baja correctamente.';
        unset($this->ucMiembrosActivos);
        unset($this->ucVigente);
    }

    /**
     * Cancela el flujo de confirmación de baja.
     *
     * @return void
     */
    public function cancelarBajaMiembro(): void
    {
        $this->ucMiembroParaBaja = null;
    }

    /**
     * Verifica manualmente la residencia de un miembro en la UC vigente.
     *
     * @param int $miembroId ID de UnidadConvivenciaMiembro.
     *
     * @return void
     */
    public function verificarMiembro(int $miembroId): void
    {
        $miembro = UnidadConvivenciaMiembro::find($miembroId);

        if (! $miembro || $miembro->unidad_convivencia_id !== $this->ucVigente?->id) {
            return;
        }

        $miembro->verificar(auth()->user());
        $this->ucMensaje = 'Residencia verificada.';
        unset($this->ucMiembrosActivos);
    }

    /**
     * Crea la UC tomando el domicilio del ciudadano titular y lo añade como primer miembro.
     * Solo actúa si el ciudadano no tiene UC vigente.
     *
     * @return void
     */
    public function crearUc(): void
    {
        if ($this->ucVigente || ! $this->ciudadano) {
            return;
        }

        $uc = UnidadConvivencia::create([
            'domicilio'          => $this->ciudadano->direccion_texto,
            'latitud'            => $this->ciudadano->coordenadas_lat,
            'longitud'           => $this->ciudadano->coordenadas_lng,
            'fecha_constitucion' => now()->toDateString(),
        ]);

        $uc->agregarMiembro($this->ciudadano->id, fuente: 'manual');

        unset($this->ucVigente);
        unset($this->ucMiembrosActivos);
        $this->ucMensaje = 'Unidad de convivencia creada.';
    }

    /**
     * Expande o contrae un apunte del timeline.
     *
     * @param int $apunteId ID del apunte.
     *
     * @return void
     */
    public function toggleApunte(int $apunteId): void
    {
        $this->apuntesExpandidos[$apunteId] = ! ($this->apuntesExpandidos[$apunteId] ?? false);
    }

    /**
     * @param string $filtro 'todos' | 'plan' | 'entrevista'
     *
     * @return void
     */
    public function setFiltroHS(string $filtro): void
    {
        $this->filtroHS = $filtro;
        unset($this->apuntesHS);
    }

    /**
     * Activa una herramienta del panel lateral.
     *
     * @param string $herramienta Identificador de la herramienta.
     *
     * @return void
     */
    public function seleccionarHerramienta(string $herramienta): void
    {
        $this->herramientaActiva = $herramienta;

        $mapa = [
            'entrevista' => 'entrevista',
            'anotacion' => 'anotacion',
            'derivacion' => 'derivacion',
            'gestion' => 'gestion',
            'valoracion' => 'valoracion',
            'escala' => 'escala',
            'informes' => null,
        ];
        $this->filtroSugerido = $mapa[$herramienta] ?? null;
    }

    /**
     * Cancela la herramienta activa y limpia su estado visual.
     *
     * @return void
     */
    public function cancelarHerramienta(): void
    {
        $this->herramientaActiva = null;
    }

    /**
     * Abre el modal de detalle de un apunte en modo solo lectura.
     * El pasado es inmutable: este modal nunca ofrece edición.
     *
     * @param int $apunteId ID del apunte que se muestra.
     *
     * @return void
     */
    public function verApunte(int $apunteId): void
    {
        $apunte = $this->apuntesHS->firstWhere('id', $apunteId);

        if (! $apunte) {
            return;
        }

        $this->modalApunteId = $apunte->id;
        $this->modalApunteTipo = $apunte->tipo->value;

        $this->modalApunteDatos = [
            'fecha' => $apunte->fecha->format('d/m/Y').' '.$apunte->created_at->format('H:i'),
            'autor' => $apunte->autor?->name ?? '—',
            'contenido' => $apunte->contenido,
            'tipo_label' => $apunte->tipo->label(),
        ];

        if ($apunte->tipo === TipoApunte::Escala && $apunte->apuntable_id) {
            $pase = PaseEscala::with('tipoEscala')->find($apunte->apuntable_id);
            if ($pase) {
                $this->modalApunteDatos['escala_nombre'] = $pase->tipoEscala?->nombre;
                $this->modalApunteDatos['escala_score'] = $pase->score_total;
                $this->modalApunteDatos['escala_interpretacion'] = $pase->interpretacion_codigo;
                $this->modalApunteDatos['escala_secciones'] = $pase->scores_seccion ?? [];
            }
        }

        if ($apunte->tipo === TipoApunte::Valoracion && $apunte->apuntable_id) {
            $ficha = Ficha::find($apunte->apuntable_id);
            if ($ficha) {
                $this->modalApunteDatos['ficha_url'] = route(
                    'intervencion.ficha.show',
                    ['historia' => $this->historia->id, 'ficha' => $ficha->id]
                );
                $this->modalApunteDatos['ficha_campos'] = collect($ficha->schema_snapshot['campos'] ?? [])
                    ->map(fn (array $c) => [
                        'etiqueta' => $c['etiqueta'] ?? $c['id'],
                        'tipo'     => $c['tipo'] ?? 'texto',
                        'valor'    => ($ficha->datos ?? [])[$c['id']] ?? null,
                        'unidad'   => $c['unidad'] ?? null,
                    ])
                    ->all();
                $this->modalApunteDatos['ficha_notas'] = $ficha->notas;
            }
        }

        $this->modalApunteAbierto = true;
    }

    /**
     * Cierra el modal de detalle de apunte.
     *
     * @return void
     */
    public function cerrarModalApunte(): void
    {
        $this->modalApunteAbierto = false;
        $this->modalApunteId = null;
        $this->modalApunteDatos = [];
    }

    // -------------------------------------------------------------------------
    // Herramientas inline
    // -------------------------------------------------------------------------

    /**
     * Guarda una entrevista y su apunte asociado.
     *
     * @return void
     */
    public function guardarEntrevista(): void
    {
        $plan = $this->pisoActivo;

        $entrevista = Entrevista::create([
            'historia_id' => $this->historia->id,
            'profesional_id' => Auth::id(),
            'cita_id' => null,
            'plan_intervencion_id' => $plan?->id,
            'fecha_hora' => now()->toDateTimeString(),
            'modalidad' => $this->formEntrevista['modalidad'],
            'tipo' => TipoEntrevista::from($this->formEntrevista['tipo']),
            'notas_generales' => $this->formEntrevista['notas'] ?: null,
            'estado' => 'realizada',
        ]);

        if ($plan) {
            Apunte::create([
                'plan_id' => $plan->id,
                'autor_id' => Auth::id(),
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::Entrevista,
                'apuntable_type' => Entrevista::class,
                'apuntable_id' => $entrevista->id,
                'contenido' => $this->formEntrevista['notas'] ?: null,
                'visibilidad' => VisibilidadApunte::Profesionales,
            ]);

            // Programar siguiente seguimiento si se indicó
            if ($this->formEntrevista['programar_seguimiento'] && $this->formEntrevista['fecha_siguiente_seguimiento']) {
                SeguimientoPlan::create([
                    'plan_id' => $plan->id,
                    'entrevista_id' => $entrevista->id,
                    'profesional_id' => Auth::id(),
                    'fecha' => today()->toDateString(),
                    'fecha_siguiente_seguimiento' => $this->formEntrevista['fecha_siguiente_seguimiento'],
                ]);
            }
        }

        $this->formEntrevista = ['tipo' => 'seguimiento', 'modalidad' => 'presencial', 'notas' => '', 'generar_valoracion' => false, 'programar_seguimiento' => false, 'fecha_siguiente_seguimiento' => ''];
        $this->herramientaActiva = null;
        unset($this->apuntesHS);
    }

    /**
     * Guarda una anotación en la Historia Social.
     *
     * @return void
     */
    public function guardarAnotacion(): void
    {
        $plan = $this->pisoActivo;

        if ($plan) {
            Apunte::create([
                'plan_id' => $plan->id,
                'autor_id' => Auth::id(),
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::Anotacion,
                'contenido' => $this->formAnotacion['contenido'],
                'visibilidad' => VisibilidadApunte::from($this->formAnotacion['visibilidad']),
            ]);
        }

        $this->formAnotacion = ['contenido' => '', 'visibilidad' => 'profesionales'];
        $this->herramientaActiva = null;
        unset($this->apuntesHS);
    }

    /**
     * Crea una derivación (apunte de tipo derivacion).
     * La tabla derivaciones no existe todavía — se registra como apunte.
     * TODO: crear modelo Derivacion y tabla derivaciones cuando esté disponible.
     *
     * @return void
     */
    public function crearDerivacion(): void
    {
        $plan = $this->pisoActivo;

        if ($plan) {
            Apunte::create([
                'plan_id' => $plan->id,
                'autor_id' => Auth::id(),
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::Derivacion,
                'contenido' => trim(
                    'Urgencia: '.($this->formDerivacion['urgencia'] ?? '')."\n".
                    'Motivo: '.($this->formDerivacion['motivo'] ?? '')
                ),
                'visibilidad' => VisibilidadApunte::Profesionales,
            ]);
        }

        $this->formDerivacion = ['servicio_receptor_id' => '', 'urgencia' => 'ordinaria', 'motivo' => ''];
        $this->herramientaActiva = null;
        unset($this->apuntesHS);
    }

    /**
     * Guarda una gestión / coordinación como apunte.
     *
     * @return void
     */
    public function guardarGestion(): void
    {
        $plan = $this->pisoActivo;

        if ($plan) {
            Apunte::create([
                'plan_id' => $plan->id,
                'autor_id' => Auth::id(),
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::GestionCoordinacion,
                'contenido' => trim(
                    'Tipo: '.($this->formGestion['tipo_gestion'] ?? '')."\n".
                    'Interlocutor: '.($this->formGestion['recurso_interlocutor'] ?? '')."\n".
                    $this->formGestion['descripcion']
                ),
                'visibilidad' => VisibilidadApunte::Profesionales,
            ]);
        }

        $this->formGestion = ['tipo_gestion' => '', 'recurso_interlocutor' => '', 'descripcion' => ''];
        $this->herramientaActiva = null;
        unset($this->apuntesHS);
    }

    /**
     * Guarda una valoración y su apunte asociado (desde RegistrarValoracionPage).
     *
     * @param int $tipoFichaId ID del tipo de ficha.
     * @param array<string, mixed> $datos
     * @param int|null $entrevistaId ID de la entrevista vinculada, si existe.
     *
     * @return void
     */
    public function guardarValoracion(int $tipoFichaId, array $datos, ?int $entrevistaId = null): void
    {
        $tipoVal = TipoValoracion::first();
        $plan = $this->pisoActivo;

        $valoracion = Valoracion::create([
            'historia_id' => $this->historia->id,
            'entrevista_id' => $entrevistaId,
            'profesional_id' => Auth::id(),
            'tipo_valoracion_id' => $tipoVal?->id ?? 1,
            'fecha' => today()->toDateString(),
            'estado' => 'completada',
            'resumen' => implode(' | ', array_map(
                fn ($k, $v) => "$k: $v",
                array_keys($datos),
                $datos
            )),
        ]);

        if ($plan) {
            Apunte::create([
                'plan_id' => $plan->id,
                'autor_id' => Auth::id(),
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::Valoracion,
                'apuntable_type' => Valoracion::class,
                'apuntable_id' => $valoracion->id,
                'contenido' => 'Valoración registrada.',
                'visibilidad' => VisibilidadApunte::Profesionales,
            ]);
        }

        $this->herramientaActiva = null;
        unset($this->apuntesHS);
    }

    /**
     * Guarda un pase de escala y su apunte asociado (desde RegistrarEscalaPage).
     *
     * @param int $tipoEscalaId ID del tipo de escala.
     * @param array<string, mixed> $respuestas [item_id => valor]
     *
     * @return void
     */
    public function guardarEscala(int $tipoEscalaId, array $respuestas): void
    {
        $tipoEscala = TipoEscala::findOrFail($tipoEscalaId);
        $plan = $this->pisoActivo;

        $scoreTotal = $this->calcularScoreEscala($tipoEscala->schema, $respuestas);

        $pase = PaseEscala::create([
            'tipo_escala_id' => $tipoEscalaId,
            'historia_id' => $this->historia->id,
            'profesional_id' => Auth::id(),
            'fecha' => today()->toDateString(),
            'respuestas' => $respuestas,
            'score_total' => $scoreTotal,
            'estado' => EstadoPase::Completado,
        ]);

        if ($plan) {
            Apunte::create([
                'plan_id' => $plan->id,
                'autor_id' => Auth::id(),
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::Escala,
                'apuntable_type' => PaseEscala::class,
                'apuntable_id' => $pase->id,
                'contenido' => "{$tipoEscala->nombre}: puntuación {$scoreTotal}",
                'visibilidad' => VisibilidadApunte::Profesionales,
            ]);
        }

        $this->herramientaActiva = null;
        unset($this->apuntesHS);
    }

    /**
     * Calcula la puntuación total de un pase de escala.
     * Suma valor × peso de cada ítem respondido.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $respuestas
     *
     * @return int
     */
    public function calcularScoreEscala(array $schema, array $respuestas): int
    {
        $total = 0;
        foreach ($schema['secciones'] ?? [] as $seccion) {
            foreach ($seccion['items'] ?? [] as $item) {
                $valor = (int) ($respuestas[$item['id']] ?? 0);
                $peso = (int) ($item['peso'] ?? 1);
                $total += $valor * $peso;
            }
        }

        return $total;
    }

    /**
     * Renderiza la vista principal del expediente del ciudadano.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.ciudadano-page');
    }
}
