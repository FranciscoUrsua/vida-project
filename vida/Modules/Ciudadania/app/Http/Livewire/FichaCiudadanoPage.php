<?php

namespace Modules\Ciudadania\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Models\CiudadanoPrestacionResumen;
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
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * @param int $ciudadano ID del ciudadano (parámetro de ruta {ciudadano})
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
     */
    #[Computed]
    public function ciudadano(): Ciudadano
    {
        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($this->ciudadanoId);
    }

    /**
     * El rol supervision tiene acceso de solo lectura. Todos los demás con acceso pueden editar.
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
     * Últimas 5 entradas de auditoría del ciudadano.
     * Stub — pendiente implementar tabla ciudadanos_auditoria.
     *
     * NOTA: no se usa try/catch con una query al interior porque en PostgreSQL
     * una query fallida (tabla no existe) aborta la transacción completa aunque
     * se capture la excepción PHP, impidiendo cualquier query posterior.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function actividadReciente(): Collection
    {
        // TODO: implementar cuando exista tabla ciudadanos_auditoria
        return collect();
    }

    // -------------------------------------------------------------------------
    // Edición de Capa 1
    // -------------------------------------------------------------------------

    /**
     * Activa el modo edición simultáneo de todos los campos de Capa 1.
     * Solo si puedeEditar — supervision no puede modificar datos.
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
    // Documentos de identidad
    // -------------------------------------------------------------------------

    /**
     * Abre el modal de añadir documento. Solo si puedeEditar.
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
