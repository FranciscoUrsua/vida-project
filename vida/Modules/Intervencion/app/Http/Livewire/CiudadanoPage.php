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
use Modules\Intervencion\Models\TipoFicha;
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
     */
    #[Computed]
    public function puedeVerTodosLosAccesos(): bool
    {
        return app(AccesosExpedienteQuery::class)->puedeVerTodos(Auth::user(), $this->historia);
    }

    /**
     * Nombre corto (o completo) de la UO responsable de la Historia Social.
     * Se muestra en la cabecera del ciudadano sustituyendo al ID numérico de UO.
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
     */
    #[Computed]
    public function planNombreCorto(): string
    {
        return $this->historia->unidadOrganizativa?->plan_nombre_corto ?? 'Plan';
    }

    /**
     * Nombre completo del Plan de Intervención según la UO de la Historia Social.
     * Fallback: «Plan de intervención».
     */
    #[Computed]
    public function planNombreCompleto(): string
    {
        return $this->historia->unidadOrganizativa?->plan_nombre_completo ?? 'Plan de intervención';
    }

    /**
     * Documento de identidad del ciudadano (cifrado, desencriptado por el cast).
     */
    #[Computed]
    public function ciudadanoDocumento(): ?string
    {
        return $this->ciudadano?->documento_identidad ?? null;
    }

    /**
     * Teléfono de contacto del ciudadano.
     */
    #[Computed]
    public function ciudadanoTelefono(): ?string
    {
        return $this->ciudadano?->telefono ?? null;
    }

    /**
     * Correo electrónico de contacto del ciudadano.
     */
    #[Computed]
    public function ciudadanoEmail(): ?string
    {
        return $this->ciudadano?->email ?? null;
    }

    /**
     * Total de apuntes visibles en la Historia Social (todos los filtros).
     * Usa la colección ya cargada para evitar consulta adicional.
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
     * @todo Integrar con módulo Prestaciones cuando esté disponible.
     */
    #[Computed]
    public function statPrestaciones(): ?string
    {
        return null;
    }

    /**
     * Fecha del último registro en la Historia Social formateada.
     */
    #[Computed]
    public function statUltimoContacto(): ?string
    {
        return $this->apuntesHS->first()?->created_at?->translatedFormat('j M Y');
    }

    // -------------------------------------------------------------------------
    // Métodos de UI
    // -------------------------------------------------------------------------

    public function toggleUC(): void
    {
        $this->ucExpandida = ! $this->ucExpandida;
    }

    public function toggleApunte(int $apunteId): void
    {
        $this->apuntesExpandidos[$apunteId] = ! ($this->apuntesExpandidos[$apunteId] ?? false);
    }

    /**
     * @param string $filtro 'todos' | 'plan' | 'entrevista'
     */
    public function setFiltroHS(string $filtro): void
    {
        $this->filtroHS = $filtro;
        unset($this->apuntesHS);
    }

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

    public function cancelarHerramienta(): void
    {
        $this->herramientaActiva = null;
    }

    /**
     * Abre el modal de detalle de un apunte en modo solo lectura.
     * El pasado es inmutable: este modal nunca ofrece edición.
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

        $this->modalApunteAbierto = true;
    }

    /** Cierra el modal de detalle de apunte. */
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
     * @param array<string, mixed> $datos
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
     * @param array<string, mixed> $respuestas [item_id => valor]
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

    public function render(): View
    {
        return view('intervencion::livewire.ciudadano-page');
    }
}
