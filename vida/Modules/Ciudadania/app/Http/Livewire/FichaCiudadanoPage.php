<?php

namespace Modules\Ciudadania\Http\Livewire;

use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Queries\AccesosExpedienteQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\CiudadanoPrestacionResumen;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Services\NormalizadorCiudadano;

/**
 * Ficha del ciudadano: vista y edición de Capa 1 (datos identificativos y de contacto).
 *
 * Distinta de intervencion/ciudadano/{historia}: pivota sobre Ciudadano,
 * no sobre HistoriaSocial. Accesible aunque el ciudadano no tenga historia social.
 *
 * Se accede sin AmbitoUoScope porque un ciudadano puede no tener historia social
 * en ninguna UO y aun así tener ficha (e.g., recién creado vía alta ciudadano).
 *
 * @see docs/instrucciones-cli/instrucciones-cli-ficha-ciudadano.md
 * @see docs/front/ui-ficha-ciudadano.md
 *
 * Propiedades computadas expuestas como propiedades mágicas por Livewire 4 #[Computed]:
 *
 * @property-read bool $puedeEditar
 * @property-read Ciudadano $ciudadano
 * @property-read HistoriaSocial|null $historiaSocial
 * @property-read bool $puedeVerHistoria
 * @property-read Collection<int, CiudadanoIdentificador> $documentos
 * @property-read object|null $ucVigente
 * @property-read Collection<int, CiudadanoPrestacionResumen> $prestaciones
 * @property-read Collection<int, Audit> $actividadReciente
 * @property-read bool $puedeVerAccesos
 * @property-read bool $puedeVerTodosLosAccesos
 */
#[Layout('layouts.operativo')]
class FichaCiudadanoPage extends Component
{
    // -------------------------------------------------------------------------
    // Control de estado
    // -------------------------------------------------------------------------

    /** ID del ciudadano. Se almacena como int para evitar problemas de hidratación con AmbitoUoScope. */
    public int $ciudadanoId;

    /** Activa la edición simultánea de todos los campos de Capa 1. */
    public bool $modoEdicion = false;

    // -------------------------------------------------------------------------
    // Campos editables de Capa 1
    // -------------------------------------------------------------------------

    public string $nombre = '';

    public string $apellido1 = '';

    public string $apellido2 = '';

    public string $fechaNacimiento = '';

    public string $sexo = '';

    public string $alias = '';

    public string $direccionTexto = '';

    public string $telefono = '';

    public string $email = '';

    // -------------------------------------------------------------------------
    // Modal de nuevo documento
    // -------------------------------------------------------------------------

    public bool $modalDocumento = false;

    public string $nuevoTipoDocumento = 'nif';

    public string $nuevoValorDocumento = '';


    // -------------------------------------------------------------------------
    // Gestión de relaciones
    // -------------------------------------------------------------------------

    public bool $mostrarHistorialRelaciones = false;

    public string $relacionBusqueda = '';

    public ?int $relacionCiudadanoSeleccionado = null;

    public string $relacionTipo = '';

    public string $relacionObservaciones = '';

    public string $relacionMensaje = '';

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * @param int $ciudadano ID del ciudadano (parámetro de ruta {ciudadano})
     *
     * @return void
     */
    public function mount(int $ciudadano): void
    {
        /** @var User $user */
        $user = auth()->user();
        if (! $user->hasAnyRole(['intervencion', 'tramitacion', 'consulta_basica', 'supervision'])) {
            abort(403);
        }

        $c = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadano);

        $this->ciudadanoId = $c->id;
        $this->nombre = $c->nombre ?? '';
        $this->apellido1 = $c->apellido1 ?? '';
        $this->apellido2 = $c->apellido2 ?? '';
        $this->fechaNacimiento = $c->fecha_nacimiento ?? '';
        $this->sexo = $c->sexo ?? '';
        $this->alias = $c->alias ?? '';
        $this->direccionTexto = $c->direccion_texto ?? '';
        $this->telefono = $c->telefono ?? '';
        $this->email = $c->email ?? '';
    }

    // -------------------------------------------------------------------------
    // Propiedades computadas
    // -------------------------------------------------------------------------

    /**
     * Ciudadano sin AmbitoUoScope — accesible aunque no tenga historia social en la UO.
     *
     * @return Ciudadano
     */
    #[Computed]
    public function ciudadano(): Ciudadano
    {
        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($this->ciudadanoId);
    }

    /**
     * El rol supervision tiene acceso de solo lectura. Todos los demás con acceso pueden editar.
     *
     * @return bool
     */
    #[Computed]
    public function puedeEditar(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasAnyRole(['intervencion', 'tramitacion', 'consulta_basica']);
    }

    /**
     * Historia social sin AmbitoUoScope ni SoftDeletes — solo comprueba existencia.
     * La historia es única y permanente: nunca se cierra.
     *
     * @return HistoriaSocial|null
     */
    #[Computed]
    public function historiaSocial(): ?HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()
            ->where('ciudadano_id', $this->ciudadanoId)
            ->first();
    }

    /**
     * Solo el rol intervencion puede navegar a la historia social.
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerHistoria(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasRole('intervencion');
    }

    /**
     * Historial completo de documentos de identidad, descendente por fecha de inicio.
     *
     * @return Collection<int, CiudadanoIdentificador>
     */
    #[Computed]
    public function documentos(): Collection
    {
        return CiudadanoIdentificador::where('ciudadano_id', $this->ciudadanoId)
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Unidad de convivencia vigente.
     * Stub — pendiente implementar módulo UnidadConvivencia.
     *
     * @return object|null
     */
    #[Computed]
    public function ucVigente(): ?object
    {
        // TODO: implementar cuando exista Modules/UnidadConvivencia
        return null;
    }

    /**
     * Últimas 4 prestaciones ordenadas por estado (activas primero) y fecha.
     * Se leen desde la tabla de agregación — nunca de los módulos origen directamente.
     *
     * @return Collection<int, CiudadanoPrestacionResumen>
     */
    #[Computed]
    public function prestaciones(): Collection
    {
        return CiudadanoPrestacionResumen::where('ciudadano_id', $this->ciudadanoId)
            ->recientes(4)
            ->get();
    }

    /**
     * El panel de accesos es visible solo para roles con competencia de intervención o supervisión.
     * Revelar metadatos de acceso a roles sin competencia es una fuga de información sobre el caso.
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerAccesos(): bool
    {
        return auth()->user()->hasAnyRole(['adm_sistema', 'supervision', 'intervencion']);
    }

    /**
     * Indica si el usuario ve todos los accesos (TSR/supervisor/adm) o solo los propios.
     * Usado en la vista para mostrar u ocultar el enlace "Ver todo".
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerTodosLosAccesos(): bool
    {
        $historia = $this->historiaSocial;
        if (! $historia) {
            return false;
        }

        return app(AccesosExpedienteQuery::class)->puedeVerTodos(auth()->user(), $historia);
    }

    /**
     * Últimos 10 accesos al expediente de este ciudadano.
     *
     * Visibilidad por rol (spec §5):
     *   - adm_sistema → todos los accesos.
     *   - TSR responsable del plan activo → todos los accesos.
     *   - Supervisor con la UO de la historia en su árbol → todos los accesos.
     *   - Cualquier otro profesional → solo sus propios accesos.
     *
     * La restricción es deliberada: revelar quién accedió al expediente
     * es en sí mismo una fuga de metadatos sobre el estado del caso.
     *
     * @return Collection<int, Audit>
     */
    #[Computed]

    public function actividadReciente(): Collection
    {
        if (! $this->puedeVerAccesos) {
            return collect();
        }

        $historia = $this->historiaSocial;
        if (! $historia) {
            return collect();
        }

        return app(AccesosExpedienteQuery::class)
            ->paraUsuario(auth()->user(), $this->ciudadano, $historia)
            ->limit(10)
            ->get();
    }

    /**
     * Tipos de relación activos disponibles para crear nuevas relaciones.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tiposRelacion(): array
    {
        return TipoRelacion::opcionesParaSelect();
    }

    /**
     * Relaciones activas salientes desde este ciudadano, con persona y tipo cargados.
     *
     * @return Collection<int, CiudadanoRelacion>
     */
    #[Computed]
    public function relacionesActivas(): Collection
    {
        return CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->whereNull('fecha_fin')
            ->with(['ciudadanoRelacionado', 'tipoRelacion'])
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Historial completo de relaciones del ciudadano.
     *
     * @return Collection<int, CiudadanoRelacion>
     */
    #[Computed]
    public function relacionesHistoricas(): Collection
    {
        return CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->with(['ciudadanoRelacionado', 'tipoRelacion'])
            ->orderByRaw('fecha_fin is null desc')
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Resultados de búsqueda para añadir una relación. Los nombres están cifrados,
     * por eso se filtra en memoria siguiendo el patrón del buscador de ciudadanos.
     *
     * @return Collection<int, Ciudadano>
     */
    #[Computed]
    public function relacionResultadosBusqueda(): Collection
    {
        if (strlen(trim($this->relacionBusqueda)) < 2) {
            return collect();
        }

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->where('id', '!=', $this->ciudadanoId)
            ->limit(500)
            ->get()
            ->filter(fn (Ciudadano $ciudadano) => str_contains(
                mb_strtolower($ciudadano->nombre_completo),
                mb_strtolower(trim($this->relacionBusqueda))
            ))
            ->take(8)
            ->values();
    }

    #[Computed]
    public function ciudadanoSeleccionadoRelacion(): ?Ciudadano
    {
        if (! $this->relacionCiudadanoSeleccionado) {
            return null;
        }

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->find($this->relacionCiudadanoSeleccionado);
    }

    // -------------------------------------------------------------------------
    // Edición de Capa 1
    // -------------------------------------------------------------------------

    /**
     * Activa el modo edición simultáneo de todos los campos de Capa 1.
     * Solo si puedeEditar — supervision no puede modificar datos.
     *
     * @return void
     */
    public function activarEdicion(): void
    {
        if (! $this->puedeEditar) {
            return;
        }
        $this->modoEdicion = true;
    }

    /**
     * Cancela la edición y recarga los datos desde BD.
     *
     * @return void
     */
    public function cancelarEdicion(): void
    {
        $c = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($this->ciudadanoId);

        $this->nombre = $c->nombre ?? '';
        $this->apellido1 = $c->apellido1 ?? '';
        $this->apellido2 = $c->apellido2 ?? '';
        $this->fechaNacimiento = $c->fecha_nacimiento ?? '';
        $this->sexo = $c->sexo ?? '';
        $this->alias = $c->alias ?? '';
        $this->direccionTexto = $c->direccion_texto ?? '';
        $this->telefono = $c->telefono ?? '';
        $this->email = $c->email ?? '';
        $this->modoEdicion = false;
    }

    /**
     * Valida, normaliza y persiste los campos de Capa 1.
     * Solo si puedeEditar. DireccionObserver procesará geocodificación si cambia direccion_texto.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function guardar(): void
    {
        if (! $this->puedeEditar) {
            return;
        }

        $this->validate([
            'nombre' => 'required|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'fechaNacimiento' => 'nullable|date|before:today',
            'sexo' => 'required|string',
            'alias' => 'nullable|string|max:200',
            'direccionTexto' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $norm = NormalizadorCiudadano::normalizar([
            'nombre' => $this->nombre,
            'apellido1' => $this->apellido1,
            'apellido2' => $this->apellido2,
            'telefono' => $this->telefono,
            'email' => $this->email,
        ]);

        Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->findOrFail($this->ciudadanoId)
            ->update([
                'nombre' => $norm['nombre'] ?? $this->nombre,
                'apellido1' => $norm['apellido1'] ?? $this->apellido1,
                'apellido2' => $norm['apellido2'] ?? ($this->apellido2 ?: null),
                'fecha_nacimiento' => $this->fechaNacimiento ?: null,
                'sexo' => $this->sexo,
                'alias' => $this->alias ?: null,
                'direccion_texto' => $this->direccionTexto ?: null,
                'telefono' => $norm['telefono'] ?? ($this->telefono ?: null),
                'email' => $norm['email'] ?? ($this->email ?: null),
            ]);

        $this->modoEdicion = false;
        $this->dispatch('ciudadano-actualizado');
    }


    // -------------------------------------------------------------------------
    // Gestión de relaciones
    // -------------------------------------------------------------------------

    public function toggleHistorialRelaciones(): void
    {
        $this->mostrarHistorialRelaciones = ! $this->mostrarHistorialRelaciones;
    }

    public function seleccionarCiudadanoRelacion(int $ciudadanoId): void
    {
        if (! $this->puedeEditar || $ciudadanoId === $this->ciudadanoId) {
            return;
        }

        $this->relacionCiudadanoSeleccionado = $ciudadanoId;
        $this->relacionBusqueda = '';
    }

    public function cancelarNuevaRelacion(): void
    {
        $this->relacionCiudadanoSeleccionado = null;
        $this->relacionBusqueda = '';
        $this->relacionTipo = '';
        $this->relacionObservaciones = '';
    }

    /**
     * @return void
     *
     * @throws ValidationException
     */
    public function guardarRelacion(): void
    {
        if (! $this->puedeEditar) {
            return;
        }

        $this->validate([
            'relacionCiudadanoSeleccionado' => 'required|integer|exists:ciudadanos,id|different:ciudadanoId',
            'relacionTipo' => 'required|string|exists:tipos_relacion,slug',
            'relacionObservaciones' => 'nullable|string|max:1000',
        ]);

        $tipoActivo = TipoRelacion::activos()->where('slug', $this->relacionTipo)->exists();
        if (! $tipoActivo) {
            throw ValidationException::withMessages([
                'relacionTipo' => 'El tipo de relación seleccionado no está activo.',
            ]);
        }

        $duplicada = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->where('ciudadano_relacionado_id', $this->relacionCiudadanoSeleccionado)
            ->where('tipo_relacion', $this->relacionTipo)
            ->whereNull('fecha_fin')
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'relacionTipo' => 'Ya existe una relación activa de ese tipo con esta persona.',
            ]);
        }

        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadanoId,
            'ciudadano_relacionado_id' => $this->relacionCiudadanoSeleccionado,
            'tipo_relacion' => $this->relacionTipo,
            'fecha_inicio' => today()->toDateString(),
            'observaciones' => $this->relacionObservaciones ?: null,
        ]);

        $this->cancelarNuevaRelacion();
        $this->relacionMensaje = 'Relación añadida correctamente.';
        unset($this->relacionesActivas, $this->relacionesHistoricas);
    }

    public function cerrarRelacion(int $relacionId): void
    {
        if (! $this->puedeEditar) {
            return;
        }

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->where('id', $relacionId)
            ->whereNull('fecha_fin')
            ->first();

        if (! $relacion) {
            return;
        }

        $relacion->update(['fecha_fin' => today()->toDateString()]);
        $this->relacionMensaje = 'Relación cerrada correctamente.';
        unset($this->relacionesActivas, $this->relacionesHistoricas);
    }

    // -------------------------------------------------------------------------
    // Documentos de identidad
    // -------------------------------------------------------------------------

    /**
     * Abre el modal de añadir documento. Solo si puedeEditar.
     *
     * @return void
     */
    public function abrirModalDocumento(): void
    {
        if (! $this->puedeEditar) {
            return;
        }
        $this->modalDocumento = true;
    }

    /**
     * Cierra el modal y limpia el formulario.
     *
     * @return void
     */
    public function cerrarModalDocumento(): void
    {
        $this->nuevoTipoDocumento = 'nif';
        $this->nuevoValorDocumento = '';
        $this->modalDocumento = false;
    }

    /**
     * Cierra el documento activo anterior y crea el nuevo.
     * El historial se mantiene íntegro (principio 4.2 — el pasado es inmutable):
     * los documentos anteriores reciben fecha_fin pero no se eliminan.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function guardarDocumento(): void
    {
        if (! $this->puedeEditar) {
            return;
        }

        $this->validate([
            'nuevoTipoDocumento' => 'required|in:nif,nie,pasaporte',
            'nuevoValorDocumento' => 'required|string|max:20',
        ]);

        DB::transaction(function (): void {
            CiudadanoIdentificador::where('ciudadano_id', $this->ciudadanoId)
                ->whereNull('fecha_fin')
                ->update(['fecha_fin' => today()]);

            CiudadanoIdentificador::create([
                'ciudadano_id' => $this->ciudadanoId,
                'tipo' => $this->nuevoTipoDocumento,
                'valor' => $this->nuevoValorDocumento,
                'fecha_inicio' => today()->toDateString(),
                'verificado' => false,
                'fuente' => 'manual',
            ]);
        });

        $this->cerrarModalDocumento();
    }

    public function render(): View
    {
        return view('ciudadania::livewire.ficha-ciudadano-page');
    }
}
