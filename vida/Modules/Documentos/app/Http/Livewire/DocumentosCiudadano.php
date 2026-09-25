<?php

namespace Modules\Documentos\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Documentos\Services\LecturaDocumentoService;

/**
 * Tarjeta «Documentos» de la ficha del ciudadano: lista, alta, nueva versión y desvinculación.
 *
 * Ver y descargar sigue la regla de DocumentoPolicy (quien puede ver a alguna persona
 * vinculada). Subir, subir versiones y desvincular exige poder editar al ciudadano
 * (CiudadanoPolicy::update: ciudadano.editar y ámbito de UO; supervisión nunca).
 * Todo alta pasa por CicloVidaDocumentoService; los rechazos de la ingesta se muestran
 * con su mensaje en castellano llano.
 *
 * @property-read Ciudadano $ciudadano
 * @property-read bool $puedeEditar
 * @property-read Collection<int, Documento> $documentos
 * @property-read Collection<int, TipoDocumental> $tipos
 * @property-read Collection<int, UnidadConvivenciaMiembro> $otrosMiembros
 * @property-read TipoDocumental|null $tipoSeleccionado
 */
class DocumentosCiudadano extends Component
{
    use WithFileUploads;

    /** Ciudadano de la ficha. */
    #[Locked]
    public int $ciudadanoId;

    /** Controla la visibilidad del modal de subida. */
    public bool $modalAbierto = false;

    /** Documento que recibe una nueva versión; null = alta de documento nuevo. */
    #[Locked]
    public ?int $documentoVersionId = null;

    /** Tipo documental elegido en el alta. */
    public ?int $tipoId = null;

    /** Fichero subido (temporal de Livewire). */
    public ?TemporaryUploadedFile $fichero = null;

    /** Descripción libre breve. */
    public string $titulo = '';

    /** Fecha de emisión (Y-m-d). */
    public string $fechaEmision = '';

    /** Órgano emisor. */
    public string $organoEmisor = '';

    /** @var array<string, string> Metadatos adicionales que exige el tipo, por clave. */
    public array $metadatos = [];

    /** @var list<int> Otros miembros de la unidad de convivencia a los que se vincula también. */
    public array $otrosVinculados = [];

    /** Canal de entrada: presencial o escaneo. */
    public string $canal = 'presencial';

    /** Mensaje del último rechazo de la ingesta, en castellano llano. */
    public ?string $errorIngesta = null;

    /** @var list<int> Documentos con el historial de versiones desplegado. */
    public array $historialAbierto = [];

    /**
     * Recibe el ciudadano de la ficha.
     *
     * @param int $ciudadanoId Ciudadano.
     *
     * @return void
     */
    public function mount(int $ciudadanoId): void
    {
        $this->ciudadanoId = $ciudadanoId;
    }

    // -------------------------------------------------------------------------
    // Datos
    // -------------------------------------------------------------------------

    /**
     * Ciudadano de la ficha, sin ámbito de UO (la ficha ya lo ha cargado igual).
     *
     * @return Ciudadano
     */
    #[Computed]
    public function ciudadano(): Ciudadano
    {
        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($this->ciudadanoId);
    }

    /**
     * Indica si el usuario puede subir documentos, versiones y desvincular.
     *
     * @return bool
     */
    #[Computed]
    public function puedeEditar(): bool
    {
        return Gate::allows('update', $this->ciudadano);
    }

    /**
     * Documentos vinculados al ciudadano que el usuario puede ver, los vigentes primero.
     *
     * @return Collection<int, Documento>
     */
    #[Computed]
    public function documentos(): Collection
    {
        return Documento::query()
            ->vinculadosA($this->ciudadano)
            ->where('estado', '!=', EstadoDocumento::Destruido->value)
            ->with(['tipo', 'versionVigente', 'versiones'])
            ->latest('updated_at')
            ->get()
            ->filter(fn (Documento $documento): bool => Gate::allows('view', $documento))
            ->values();
    }

    /**
     * Tipos documentales activos que admiten vincularse a una persona.
     *
     * @return Collection<int, TipoDocumental>
     */
    #[Computed]
    public function tipos(): Collection
    {
        return TipoDocumental::query()->activos()->orderBy('nombre')->get()
            ->filter(fn (TipoDocumental $tipo): bool => $tipo->permiteVincularA(Ciudadano::class))
            ->values();
    }

    /**
     * Tipo documental elegido en el formulario.
     *
     * @return TipoDocumental|null
     */
    #[Computed]
    public function tipoSeleccionado(): ?TipoDocumental
    {
        return $this->tipoId === null ? null : $this->tipos->firstWhere('id', $this->tipoId);
    }

    /**
     * Otros miembros activos de la unidad de convivencia vigente del ciudadano.
     *
     * @return Collection<int, UnidadConvivenciaMiembro>
     */
    #[Computed]
    public function otrosMiembros(): Collection
    {
        $uc = $this->ciudadano->unidadesConvivenciaActivas()->first();

        if ($uc === null) {
            return collect();
        }

        return $uc->miembrosActivos()
            ->with(['ciudadano' => fn ($q) => $q->withoutGlobalScope(AmbitoUoScope::class)])
            ->where('ciudadano_id', '!=', $this->ciudadanoId)
            ->get();
    }

    /**
     * Metadatos que exige el tipo elegido, salvo los que tienen campo propio.
     *
     * @return list<string>
     */
    public function metadatosAdicionales(): array
    {
        $propios = ['fecha_emision', 'organo_emisor', 'titulo'];

        return array_values(array_diff($this->tipoSeleccionado?->metadatos_requeridos ?? [], $propios));
    }

    /**
     * Indica si el tipo elegido exige un dato.
     *
     * @param string $clave Clave del metadato.
     *
     * @return bool
     */
    public function exige(string $clave): bool
    {
        return in_array($clave, $this->tipoSeleccionado?->metadatos_requeridos ?? [], true);
    }

    /**
     * URL firmada para ver el documento en el navegador.
     *
     * @param Documento $documento Documento.
     *
     * @return string
     */
    public function urlVer(Documento $documento): string
    {
        return app(LecturaDocumentoService::class)->urlTemporal($documento);
    }

    /**
     * URL firmada para descargar el documento.
     *
     * @param Documento $documento Documento.
     *
     * @return string
     */
    public function urlDescarga(Documento $documento): string
    {
        return app(LecturaDocumentoService::class)->urlDescarga($documento);
    }

    /**
     * Indica si el documento admite versiones nuevas (no procede de un informe firmado).
     *
     * @param Documento $documento Documento.
     *
     * @return bool
     */
    public function admiteVersiones(Documento $documento): bool
    {
        return $documento->versiones->whereNotNull('informe_id')->isEmpty();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Abre el modal para dar de alta un documento nuevo.
     *
     * @return void
     */
    public function abrirAlta(): void
    {
        $this->autorizarEdicion();
        $this->limpiarFormulario();
        $this->modalAbierto = true;
    }

    /**
     * Abre el modal para subir una nueva versión de un documento.
     *
     * @param int $documentoId Documento.
     *
     * @return void
     */
    public function abrirNuevaVersion(int $documentoId): void
    {
        $this->autorizarEdicion();
        $this->limpiarFormulario();
        $this->documentoVersionId = $this->documentoDeLaFicha($documentoId)->id;
        $this->modalAbierto = true;
    }

    /**
     * Cierra el modal y limpia el formulario.
     *
     * @return void
     */
    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->limpiarFormulario();
    }

    /**
     * Despliega o pliega el historial de versiones de un documento.
     *
     * @param int $documentoId Documento.
     *
     * @return void
     */
    public function alternarHistorial(int $documentoId): void
    {
        $this->historialAbierto = in_array($documentoId, $this->historialAbierto, true)
            ? array_values(array_diff($this->historialAbierto, [$documentoId]))
            : [...$this->historialAbierto, $documentoId];
    }

    /**
     * Guarda el alta o la nueva versión. Los rechazos de la ingesta se muestran en el modal.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->autorizarEdicion();
        $this->errorIngesta = null;

        $maxKb = (int) config('documentos.max_subida_kb');
        $this->validate(
            [
                'fichero' => ['required', 'file', "max:{$maxKb}"],
                'canal' => ['required', 'in:presencial,escaneo'],
                'tipoId' => $this->documentoVersionId === null ? ['required', 'integer'] : ['nullable'],
                'fechaEmision' => ['nullable', 'date', 'before_or_equal:today'],
            ],
            [
                'fichero.required' => 'Elige el fichero que quieres subir.',
                'fichero.max' => 'El fichero supera el tamaño máximo de subida.',
                'tipoId.required' => 'Elige el tipo de documento.',
                'fechaEmision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
            ],
        );

        /** @var User $usuario */
        $usuario = auth()->user();
        $servicio = app(CicloVidaDocumentoService::class);
        $canal = CanalCaptura::from($this->canal);

        try {
            if ($this->documentoVersionId !== null) {
                $servicio->nuevaVersion(
                    $this->documentoDeLaFicha($this->documentoVersionId),
                    $this->fichero,
                    $usuario,
                    $canal,
                    $this->fichero->getClientOriginalName(),
                );
            } else {
                $servicio->altaDocumento($this->fichero, $this->datosAlta($usuario, $canal));
            }
        } catch (IngestaRechazadaException|\DomainException $e) {
            $this->errorIngesta = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            // Metadatos exigidos por el tipo: se muestran junto a su campo.
            throw ValidationException::withMessages($this->erroresMetadatos($e->errors()));
        }

        $this->cerrarModal();
        unset($this->documentos);
        session()->flash('documentos-ok', 'Documento guardado.');
    }

    /**
     * Da de baja el vínculo del documento con este ciudadano. No borra el documento.
     *
     * @param int $documentoId Documento.
     *
     * @return void
     */
    public function desvincular(int $documentoId): void
    {
        $this->autorizarEdicion();

        /** @var User $usuario */
        $usuario = auth()->user();
        app(CicloVidaDocumentoService::class)->desvincular($this->documentoDeLaFicha($documentoId), $this->ciudadano, $usuario);

        unset($this->documentos);
        session()->flash('documentos-ok', 'El documento ya no está asociado a esta persona.');
    }

    /**
     * Renderiza la tarjeta.
     *
     * @return View
     */
    public function render(): View
    {
        return view('documentos::livewire.documentos-ciudadano');
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    /**
     * Datos del alta a partir del formulario.
     *
     * @param User $usuario Quien sube el documento.
     * @param CanalCaptura $canal Canal de entrada.
     *
     * @return DatosIngesta
     */
    private function datosAlta(User $usuario, CanalCaptura $canal): DatosIngesta
    {
        $tipo = $this->tipoSeleccionado ?? throw ValidationException::withMessages(['tipoId' => 'Elige un tipo de documento válido.']);

        // Solo miembros reales de la UC vigente: el cliente no puede añadir a otras personas.
        $miembros = $this->otrosMiembros
            ->whereIn('ciudadano_id', array_map('intval', $this->otrosVinculados))
            ->map(fn (UnidadConvivenciaMiembro $m): ?Ciudadano => $m->ciudadano)
            ->filter()
            ->values()
            ->all();

        return new DatosIngesta(
            tipo: $tipo,
            usuario: $usuario,
            canal: $canal,
            vinculos: [$this->ciudadano, ...$miembros],
            nombreOriginal: $this->fichero?->getClientOriginalName(),
            titulo: $this->titulo !== '' ? $this->titulo : null,
            fechaEmision: $this->fechaEmision !== '' ? Carbon::parse($this->fechaEmision) : null,
            organoEmisor: $this->organoEmisor !== '' ? $this->organoEmisor : null,
            metadatos: array_filter($this->metadatos, fn ($valor): bool => $valor !== ''),
        );
    }

    /**
     * Traduce las claves de metadatos a los nombres de campo del formulario.
     *
     * @param array<string, array<int, string>> $errores Errores por clave de metadato.
     *
     * @return array<string, array<int, string>>
     */
    private function erroresMetadatos(array $errores): array
    {
        $campos = ['fecha_emision' => 'fechaEmision', 'organo_emisor' => 'organoEmisor', 'titulo' => 'titulo'];

        return collect($errores)
            ->mapWithKeys(fn (array $mensajes, string $clave): array => [$campos[$clave] ?? "metadatos.{$clave}" => $mensajes])
            ->all();
    }

    /**
     * Documento con vínculo activo a este ciudadano; cualquier otro id se rechaza.
     *
     * @param int $documentoId Documento.
     *
     * @return Documento
     */
    private function documentoDeLaFicha(int $documentoId): Documento
    {
        return Documento::query()->vinculadosA($this->ciudadano)->findOrFail($documentoId);
    }

    /**
     * Exige poder editar al ciudadano.
     *
     * @return void
     */
    private function autorizarEdicion(): void
    {
        abort_unless($this->puedeEditar, 403);
    }

    /**
     * Vacía el formulario y el último rechazo.
     *
     * @return void
     */
    private function limpiarFormulario(): void
    {
        $this->reset(['documentoVersionId', 'tipoId', 'fichero', 'titulo', 'fechaEmision', 'organoEmisor', 'metadatos', 'otrosVinculados', 'canal', 'errorIngesta']);
        $this->resetValidation();
    }
}
