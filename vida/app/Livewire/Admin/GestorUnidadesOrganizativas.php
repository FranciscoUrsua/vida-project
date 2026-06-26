<?php

namespace App\Livewire\Admin;

use App\Models\UnidadOrganizativa;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Componente Livewire para la gestión de Unidades Organizativas en el backoffice.
 *
 * Ofrece:
 *   - Listado del árbol jerárquico de UO (usando descendants del paquete
 *     staudenmeir/laravel-adjacency-list).
 *   - Formulario de creación y edición de UO.
 *   - Acción de desactivación (soft-disable, no elimina).
 *
 * Accesible únicamente para usuarios con el permiso `configuracion.acceder`
 * (docs/modulo-usuarios-permisos.md § 4.5).
 *
 * @see UnidadOrganizativa
 * @see resources/views/livewire/admin/gestor-unidades-organizativas.blade.php
 */
class GestorUnidadesOrganizativas extends Component
{
    // -------------------------------------------------------------------------
    // Propiedades del formulario
    // -------------------------------------------------------------------------

    /** @var int|null ID de la UO en edición (null = creación) */
    public ?int $editandoId = null;

    /** @var string Nombre de la UO en el formulario */
    public string $nombre = '';

    /** @var string Tipo de UO (código de tipos_unidad_organizativa) */
    public string $tipo = '';

    /** @var int|null ID de la UO padre seleccionada */
    public ?int $parentId = null;

    /** @var bool Controla visibilidad del modal de formulario */
    public bool $mostrarFormulario = false;

    /** @var string Búsqueda para filtrar el árbol */
    public string $busqueda = '';

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Valida que el usuario autenticado tiene el permiso requerido.
     * Livewire llama a este método al montar el componente.
     *
     * @throws AuthorizationException
     */
    public function mount(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->can('configuracion.acceder'),
            403,
            'No tienes permiso para acceder a la configuración del sistema.'
        );
    }

    // -------------------------------------------------------------------------
    // Propiedades computadas
    // -------------------------------------------------------------------------

    /**
     * Devuelve el árbol de UO desde los nodos raíz.
     * Si hay búsqueda activa, filtra por nombre en toda la jerarquía.
     *
     * @return Collection<int, UnidadOrganizativa>
     */
    #[Computed]
    public function arbolUo(): Collection
    {
        if (! empty($this->busqueda)) {
            return UnidadOrganizativa::where('nombre', 'ilike', '%'.$this->busqueda.'%')
                ->with('padre')
                ->orderBy('nombre')
                ->get();
        }

        // Raíces con todos sus descendientes cargados
        return UnidadOrganizativa::raiz()
            ->with(['descendantsAndSelf' => fn ($q) => $q->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Catálogo de tipos de UO disponibles para el selector del formulario.
     *
     * @return Collection<int, stdClass>
     */
    #[Computed]
    public function tiposDisponibles(): Collection
    {
        return DB::table('tipos_unidad_organizativa')->orderBy('nombre')->get();
    }

    /**
     * Lista de UO activas disponibles como padre (excluye la que se está editando).
     *
     * @return Collection<int, UnidadOrganizativa>
     */
    #[Computed]
    public function uosDisponiblesComoPadre(): Collection
    {
        return UnidadOrganizativa::activas()
            ->when($this->editandoId, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->orderBy('nombre')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Abre el formulario para crear una nueva UO.
     */
    public function abrirFormularioCreacion(): void
    {
        $this->resetFormulario();
        $this->mostrarFormulario = true;
    }

    /**
     * Abre el formulario cargando los datos de una UO existente para editarla.
     *
     * @param int $id ID de la UO a editar
     */
    public function abrirFormularioEdicion(int $id): void
    {
        $uo = UnidadOrganizativa::findOrFail($id);

        $this->editandoId = $uo->id;
        $this->nombre = $uo->nombre;
        $this->tipo = $uo->tipo;
        $this->parentId = $uo->parent_id;

        $this->mostrarFormulario = true;
    }

    /**
     * Guarda la UO (crea o actualiza según si hay editandoId).
     */
    public function guardar(): void
    {
        $datos = $this->validar();

        if ($this->editandoId) {
            $uo = UnidadOrganizativa::findOrFail($this->editandoId);
            $uo->update($datos);
        } else {
            UnidadOrganizativa::create($datos);
        }

        $this->mostrarFormulario = false;
        $this->resetFormulario();

        $this->dispatch('uo-actualizada');
        session()->flash('exito', 'Unidad Organizativa guardada correctamente.');
    }

    /**
     * Desactiva una UO (la marca como inactiva; no la elimina).
     * El historial de adscripciones y la estructura quedan preservados.
     *
     * @param int $id ID de la UO a desactivar
     */
    public function desactivar(int $id): void
    {
        $uo = UnidadOrganizativa::findOrFail($id);
        $uo->update(['activa' => false]);

        session()->flash('exito', "La UO '{$uo->nombre}' ha sido desactivada.");
    }

    /**
     * Reactiva una UO previamente desactivada.
     *
     * @param int $id ID de la UO a reactivar
     */
    public function reactivar(int $id): void
    {
        $uo = UnidadOrganizativa::findOrFail($id);
        $uo->update(['activa' => true]);

        session()->flash('exito', "La UO '{$uo->nombre}' ha sido reactivada.");
    }

    /**
     * Cierra el formulario sin guardar.
     */
    public function cancelar(): void
    {
        $this->mostrarFormulario = false;
        $this->resetFormulario();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * Renderiza el componente.
     */
    public function render(): View
    {
        return view('livewire.admin.gestor-unidades-organizativas');
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Valida el formulario y devuelve los datos validados.
     *
     * @return array<string, mixed>
     */
    private function validar(): array
    {
        return $this->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'tipo' => [
                'required',
                'string',
                Rule::exists('tipos_unidad_organizativa', 'codigo'),
            ],
            'parentId' => ['nullable', 'integer', 'exists:unidades_organizativas,id'],
        ], [
            'nombre.required' => 'El nombre de la UO es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 200 caracteres.',
            'tipo.required' => 'Debe seleccionar un tipo de UO.',
            'tipo.exists' => 'El tipo seleccionado no es válido.',
            'parentId.exists' => 'La UO padre seleccionada no existe.',
        ]);
    }

    /**
     * Resetea los campos del formulario a sus valores iniciales.
     */
    private function resetFormulario(): void
    {
        $this->editandoId = null;
        $this->nombre = '';
        $this->tipo = '';
        $this->parentId = null;
    }
}
