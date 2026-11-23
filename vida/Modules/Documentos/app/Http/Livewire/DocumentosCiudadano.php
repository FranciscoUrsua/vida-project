<?php

namespace Modules\Documentos\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Services\PresentacionPersona;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Documentos\Services\LecturaDocumentoService;

/**
 * Tarjeta «Documentos» de la ficha del ciudadano: lista, alta, nueva versión y desvinculación.
 *
 * Dos vistas según el permiso (fase 4, docs/roles-consulta-basica-tramitacion.md §3.3):
 * - Con `documento.ver_carpeta`, la carpeta completa: ver y descargar según DocumentoPolicy;
 *   nueva versión y desvincular si además puede editar al ciudadano (CiudadanoPolicy::update).
 * - Sin ella (consulta básica, tramitación), solo los documentos que subió hoy el usuario,
 *   con sustituir el fichero, cambiar sus datos y retirarlo (DocumentoPolicy::gestionarPropio).
 *
 * Subir exige `documento.subir` y poder editar al ciudadano (la operación, en su UO).
 * Todo cambio pasa por CicloVidaDocumentoService; los rechazos de la ingesta se muestran
 * con su mensaje en castellano llano.
 *
 * @property-read Ciudadano $ciudadano
 * @property-read bool $puedeSubir
 * @property-read bool $puedeGestionarCarpeta
 * @property-read bool $veCarpeta
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

    /** Documento propio del día cuyos datos se cambian; null = no se está editando. */
    #[Locked]
    public ?int $documentoEdicionId = null;

    /** Documento propio del día que se va a retirar; null = sin retirada abierta. */
    #[Locked]
    public ?int $documentoRetiradaId = null;

    /** Motivo de la retirada (obligatorio). */
    public string $motivoRetirada = '';

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
     * Indica si el usuario puede subir documentos a la carpeta de la persona.
     *
     * @return bool
     */
    #[Computed]
    public function puedeSubir(): bool
    {
        // R1: subir documentos es una actuación; exige además adscripción al centro activo
        return $this->puedeSubirPorPermiso() && app(CentroDeUsuario::class)->puedeActuar(auth()->user());
    }

    /**
     * Si el usuario puede subir documentos por sus permisos y su ámbito, sin mirar su centro:
     * lo que no se cumple es un 403; lo que sí, pero sin centro, un aviso con el motivo de RN-15.
     *
     * @return bool
     */
    private function puedeSubirPorPermiso(): bool
    {
        return auth()->user()->can('documento.subir') && Gate::allows('update', $this->ciudadano);
    }

    /**
     * Indica si el usuario ve la carpeta completa de la persona.
     *
     * @return bool
     */
    #[Computed]
    public function veCarpeta(): bool
    {
        return auth()->user()->can('documento.ver_carpeta');
    }

    /**
     * Indica si el usuario sube versiones y desvincula cualquier documento de la carpeta.
     *
     * @return bool
     */
    #[Computed]
    public function puedeGestionarCarpeta(): bool
    {
        return $this->veCarpeta && Gate::allows('update', $this->ciudadano);
    }

    /**
     * Documentos de la persona que el usuario ve: la carpeta completa o solo los suyos del
     * día. Ni destruidos ni retirados.
     *
     * @return Collection<int, Documento>
     */
    #[Computed]
    public function documentos(): Collection
    {
        $permiso = $this->veCarpeta ? 'view' : 'gestionarPropio';

        return Documento::query()
            ->vinculadosA($this->ciudadano)
            ->disponibles()
            ->with(['tipo', 'versionVigente', 'versiones'])
            ->latest('updated_at')
            ->get()
            ->filter(fn (Documento $documento): bool => Gate::allows($permiso, $documento))
            ->values();
    }

    /**
     * Indica si el documento es propio del día del usuario (sustituir, cambiar datos, retirar).
     *
     * @param Documento $documento Documento.
     *
     * @return bool
     */
    public function esPropio(Documento $documento): bool
    {
        return Gate::allows('gestionarPropio', $documento);
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

        // Un colectivo protegido que no se puede ver no se ofrece: ni su nombre ni la
        // opción de vincularle el documento (CLAUDE.md §3). datosAlta() solo acepta estos.
        return $uc->miembrosActivos()
            ->with(['ciudadano' => fn ($q) => $q->withoutGlobalScope(AmbitoUoScope::class)])
            ->where('ciudadano_id', '!=', $this->ciudadanoId)
            ->get()
            ->filter(fn (UnidadConvivenciaMiembro $m): bool => $m->ciudadano !== null && PresentacionPersona::visible($m->ciudadano, auth()->user()))
            ->values();
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
        abort_unless($this->puedeSubirPorPermiso(), 403);
        app(CentroDeUsuario::class)->exigirActuacion(auth()->user());
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
        $documento = $this->documentoDeLaFicha($documentoId);
        $this->autorizarVersion($documento);
        app(CentroDeUsuario::class)->exigirActuacion(auth()->user());
        $this->limpiarFormulario();
        $this->documentoVersionId = $documento->id;
        $this->modalAbierto = true;
    }

    /**
     * Abre el modal para cambiar los datos de un documento propio del día.
     *
     * @param int $documentoId Documento.
     *
     * @return void
     */
    public function abrirEdicion(int $documentoId): void
    {
        $documento = $this->documentoDeLaFicha($documentoId);
        abort_unless($this->esPropio($documento), 403);

        $this->limpiarFormulario();
        $this->documentoEdicionId = $documento->id;
        $this->tipoId = $documento->tipo_documental_id;
        $this->titulo = $documento->titulo ?? '';
        $this->fechaEmision = $documento->fecha_emision?->toDateString() ?? '';
        $this->organoEmisor = $documento->organo_emisor ?? '';
        $this->metadatos = array_map('strval', $documento->metadatos ?? []);
        $this->otrosVinculados = $documento->vinculosActivos()
            ->where('vinculable_type', (new Ciudadano)->getMorphClass())
            ->where('vinculable_id', '!=', $this->ciudadanoId)
            ->pluck('vinculable_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $this->modalAbierto = true;
    }

    /**
     * Abre la confirmación de retirada de un documento propio del día.
     *
     * @param int $documentoId Documento.
     *
     * @return void
     */
    public function abrirRetirada(int $documentoId): void
    {
        $documento = $this->documentoDeLaFicha($documentoId);
        abort_unless($this->esPropio($documento), 403);

        $this->documentoRetiradaId = $documento->id;
        $this->motivoRetirada = '';
        $this->resetValidation();
    }

    /**
     * Cierra la confirmación de retirada.
     *
     * @return void
     */
    public function cerrarRetirada(): void
    {
        $this->reset(['documentoRetiradaId', 'motivoRetirada']);
        $this->resetValidation();
    }

    /**
     * Retira el documento con su motivo (baja lógica: queda en el historial y la auditoría).
     *
     * @return void
     */
    public function retirar(): void
    {
        $documento = $this->documentoDeLaFicha((int) $this->documentoRetiradaId);
        abort_unless($this->esPropio($documento), 403);

        $this->validate(
            ['motivoRetirada' => ['required', 'string', 'max:1000']],
            ['motivoRetirada.required' => 'Indica por qué retiras el documento.'],
        );

        /** @var User $usuario */
        $usuario = auth()->user();
        app(CicloVidaDocumentoService::class)->retirar($documento, $usuario, $this->motivoRetirada);

        $this->cerrarRetirada();
        unset($this->documentos);
        session()->flash('documentos-ok', 'Documento retirado.');
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
        if ($this->documentoEdicionId !== null) {
            $this->guardarEdicion();

            return;
        }

        if ($this->documentoVersionId !== null) {
            $this->autorizarVersion($this->documentoDeLaFicha($this->documentoVersionId));
        } else {
            abort_unless($this->puedeSubirPorPermiso(), 403);
        }

        // R1: una subida (alta o versión) es una actuación, también al guardar
        app(CentroDeUsuario::class)->exigirActuacion(auth()->user());
        $this->errorIngesta = null;

        $maxKb = (int) config('documentos.max_subida_kb');
        $this->validate(
            [
                // Sin «min:1»: Laravel mide en KB con decimales y rechazaría todo fichero de menos de 1 KB.
                'fichero' => ['required', 'file', "max:{$maxKb}", $this->reglaNoVacio(...)],
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
            // Un rechazo con causa técnica (fallo de qpdf, gs, antivirus…) no es culpa del
            // fichero: se registra con su excepción previa para poder diagnosticarlo.
            if ($e instanceof IngestaRechazadaException && $e->getPrevious() !== null) {
                report($e->getPrevious());
            }
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
     * Regla de validación: el fichero subido no puede tener 0 bytes.
     *
     * @param string $atributo Nombre del campo.
     * @param mixed $valor Fichero subido.
     * @param \Closure(string): mixed $fallo Callback de error de la validación.
     *
     * @return void
     */
    private function reglaNoVacio(string $atributo, mixed $valor, \Closure $fallo): void
    {
        if ($valor instanceof UploadedFile && $valor->getSize() === 0) {
            $fallo('El fichero está vacío.');
        }
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
        abort_unless($this->puedeGestionarCarpeta, 403);

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
        return Documento::query()->vinculadosA($this->ciudadano)->disponibles()->findOrFail($documentoId);
    }

    /**
     * Guarda los nuevos datos de un documento propio del día (tipo, descripción, emisión,
     * metadatos y miembros de la unidad de convivencia vinculados).
     *
     * @return void
     */
    private function guardarEdicion(): void
    {
        $documento = $this->documentoDeLaFicha($this->documentoEdicionId);
        abort_unless($this->esPropio($documento), 403);

        $this->validate(
            [
                'tipoId' => ['required', 'integer'],
                'fechaEmision' => ['nullable', 'date', 'before_or_equal:today'],
            ],
            [
                'tipoId.required' => 'Elige el tipo de documento.',
                'fechaEmision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
            ],
        );

        // Solo miembros de la unidad de convivencia: cualquier otra persona se rechaza
        $miembros = $this->otrosMiembros->pluck('ciudadano_id')->map(fn ($id): int => (int) $id);
        $ajenos = collect($this->otrosVinculados)->map(fn ($id): int => (int) $id)->diff($miembros);
        if ($ajenos->isNotEmpty()) {
            throw ValidationException::withMessages(['otrosVinculados' => 'Solo se puede asociar el documento a miembros de la unidad de convivencia.']);
        }

        /** @var User $usuario */
        $usuario = auth()->user();

        try {
            app(CicloVidaDocumentoService::class)->actualizarDatos($documento, $this->datosAlta($usuario, CanalCaptura::Presencial));
        } catch (\DomainException $e) {
            $this->errorIngesta = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            throw ValidationException::withMessages($this->erroresMetadatos($e->errors()));
        }

        $this->cerrarModal();
        unset($this->documentos);
        session()->flash('documentos-ok', 'Datos del documento guardados.');
    }

    /**
     * Exige poder subir una versión del documento: gestión de la carpeta o propio del día.
     *
     * @param Documento $documento Documento.
     *
     * @return void
     */
    private function autorizarVersion(Documento $documento): void
    {
        abort_unless($this->puedeGestionarCarpeta || $this->esPropio($documento), 403);
    }

    /**
     * Vacía el formulario y el último rechazo.
     *
     * @return void
     */
    private function limpiarFormulario(): void
    {
        $this->reset(['documentoVersionId', 'documentoEdicionId', 'tipoId', 'fichero', 'titulo', 'fechaEmision', 'organoEmisor', 'metadatos', 'otrosVinculados', 'canal', 'errorIngesta']);
        $this->resetValidation();
    }
}
