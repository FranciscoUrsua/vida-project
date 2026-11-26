<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LogicException;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoCita;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Services\Citas\PropuestaHueco;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\Concerns\ConEncargoAutorizado;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\Actuaciones\AnotacionService;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\ActuacionesEncargo;
use Modules\Intervencion\Services\CajaHerramientasEncargo;
use Modules\Intervencion\Services\CitasEncargo;
use Modules\Intervencion\Services\EncargoService;

/**
 * Detalle de un encargo profesional para el profesional asignado (C7 de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md): motivo y contexto, TSR que
 * encarga, documentos adjuntos y registro del resultado, que cierra el encargo
 * (EncargoService::cerrar, EP-4 y EP-5).
 *
 * Con el encargo en curso, el profesional sube documentos (bloque D, EP-8): van a la carpeta
 * de la persona y quedan vinculados al encargo; sin el límite del día de la consulta básica.
 * Los abre por AccesoDocumentosEncargo.
 *
 * También cita a la persona en sus propios huecos del centro del encargo (bloque E, EP-9):
 * elige el tipo de cita, VIDA propone huecos y él elige uno (CitasEncargo).
 *
 * Con el encargo en curso registra anotaciones (bloque C de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md, EA-4): quedan en la Historia Social
 * vinculadas al encargo y, si se marca la casilla, a una cita suya con la persona, que
 * completan (EA-10). «Atender» desde la agenda abre la página con `?cita=`. La lista de
 * actuaciones muestra solo las suyas en este encargo (EA-7, `ActuacionesEncargo`).
 *
 * Y abre las fichas de valoración y escalas del área (bloque D, EA-2), con la cita propuesta.
 *
 * Quién lo abre (profesional asignado, encargo aceptado o cerrado, EP-7) lo decide
 * ConEncargoAutorizado.
 *
 * @property-read Collection<int, Documento> $documentos
 * @property-read bool $puedeCerrar
 * @property-read bool $puedeSubir
 * @property-read Collection<int, TipoDocumental> $tipos
 * @property-read bool $puedeCitar
 * @property-read array<int, string> $tiposCita
 * @property-read Cita|null $citaVinculable
 * @property-read list<string> $herramientasArea
 * @property-read EloquentCollection<int, Apunte> $actuaciones
 */
#[Layout('layouts.operativo')]
class EncargoPage extends Component
{
    use ConEncargoAutorizado;
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null Fichero que se sube. */
    public $fichero = null;

    public ?int $tipoId = null;

    public string $titulo = '';

    public string $fechaEmision = '';

    public string $organoEmisor = '';

    /** Mensaje de rechazo de la tubería de entrada. */
    public ?string $errorIngesta = null;

    /** Tipo de cita elegido para citar desde el encargo. */
    public ?int $tipoCitaId = null;

    /** @var list<array{slot_id: int, fecha: string, hora: string}>|null Huecos propuestos; null sin buscar. */
    public ?array $huecos = null;

    /** @var string Resultado que se registra al cerrar. */
    public string $resultado = '';

    /** @var string|null Confirmación tras cerrar. */
    public ?string $aviso = null;

    /** @var int|null Cita con la que se abrió el encargo desde la agenda (?cita=). */
    #[Url(as: 'cita', except: null)]
    public ?int $citaPedida = null;

    /** @var bool Si la actuación que se guarde se vincula a la cita propuesta (marcado por defecto, EA-10). */
    public bool $vincularCita = true;

    /** @var array{contenido: string, visibilidad: string} Formulario de la anotación. */
    public array $formAnotacion = ['contenido' => '', 'visibilidad' => 'profesionales'];

    /**
     * Autoriza la apertura.
     *
     * @param int $encargo Id del encargo.
     *
     * @return void
     */
    public function mount(int $encargo): void
    {
        $this->encargoId = $encargo;
        $this->encargo();
    }

    /**
     * Documentos vinculados al encargo y disponibles.
     *
     * @return Collection<int, Documento>
     */
    #[Computed]
    public function documentos(): Collection
    {
        $ids = DocumentoVinculo::query()->activos()
            ->where('vinculable_type', $this->encargo->getMorphClass())
            ->where('vinculable_id', $this->encargo->id)
            ->pluck('documento_id');

        return Documento::whereKey($ids)->with('tipo')->orderBy('created_at')->get()
            ->filter(fn (Documento $documento) => $documento->estaDisponible())
            ->values()
            ->toBase();
    }

    /**
     * Si el documento se puede abrir (DocumentoPolicy, con la vía AccesoDocumentosEncargo).
     *
     * @param Documento $documento
     *
     * @return bool
     */
    public function puedeVerDocumento(Documento $documento): bool
    {
        return Gate::allows('view', $documento);
    }

    /**
     * URL firmada para ver un documento.
     *
     * @param Documento $documento
     *
     * @return string
     */
    public function urlVer(Documento $documento): string
    {
        return app(LecturaDocumentoService::class)->urlTemporal($documento);
    }

    /**
     * Si se ofrece registrar el resultado: encargo en curso y permiso para cerrarlo.
     *
     * @return bool
     */
    #[Computed]
    public function puedeCerrar(): bool
    {
        return $this->encargo->estado === EstadoDerivacion::Aceptada
            && auth()->user()->can(EncargoService::PERMISO_PROFESIONAL);
    }

    /**
     * Registra el resultado y cierra el encargo. Las reglas (profesional asignado, RN-15,
     * resultado obligatorio) las aplica EncargoService.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $this->resetErrorBag();

        try {
            app(EncargoService::class)->cerrar($this->encargo, $this->resultado, auth()->user());
        } catch (DomainException $e) {
            $this->addError('resultado', $e->getMessage());

            return;
        }

        $this->resultado = '';
        $this->aviso = 'Encargo cerrado. El resultado ya está en la Historia Social y el TSR tiene un aviso.';
        unset($this->encargo, $this->puedeCerrar, $this->puedeSubir, $this->puedeCitar, $this->puedeActuar, $this->citaVinculable);
    }

    /**
     * Si se ofrece subir documentos: encargo en curso, `documento.subir` y adscripción al
     * centro del encargo (RN-15; se vuelve a comprobar al guardar).
     *
     * @return bool
     */
    #[Computed]
    public function puedeSubir(): bool
    {
        return $this->encargo->estado === EstadoDerivacion::Aceptada
            && auth()->user()->can('documento.subir')
            && app(CentroDeUsuario::class)->puedeActuar(auth()->user(), $this->encargo->centroDestino);
    }

    /**
     * Tipos documentales activos que admiten vincularse a una persona (y, por tanto, al encargo).
     *
     * @return Collection<int, TipoDocumental>
     */
    #[Computed]
    public function tipos(): Collection
    {
        return TipoDocumental::query()->activos()->orderBy('nombre')->get()
            ->filter(fn (TipoDocumental $tipo): bool => $tipo->permiteVincularA(Derivacion::class))
            ->values()
            ->toBase();
    }

    /**
     * Sube un documento a la carpeta de la persona vinculado al encargo. Los rechazos de la
     * tubería de entrada se muestran sin perder el formulario.
     *
     * @return void
     */
    public function subir(): void
    {
        abort_unless($this->puedeSubir, 403);
        // R1: subir es una actuación en el centro del encargo, también al guardar
        app(CentroDeUsuario::class)->exigirActuacion(auth()->user(), $this->encargo->centroDestino);
        $this->errorIngesta = null;

        $maxKb = (int) config('documentos.max_subida_kb');
        $this->validate(
            [
                // Sin «min:1»: Laravel mide en KB con decimales y rechazaría todo fichero de menos de 1 KB.
                'fichero' => ['required', 'file', "max:{$maxKb}", $this->reglaNoVacio(...)],
                'tipoId' => ['required', 'integer'],
                'fechaEmision' => ['nullable', 'date', 'before_or_equal:today'],
            ],
            [
                'fichero.required' => 'Elige el fichero que quieres subir.',
                'fichero.max' => 'El fichero supera el tamaño máximo de subida.',
                'tipoId.required' => 'Elige el tipo de documento.',
                'fechaEmision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
            ],
        );

        $tipo = $this->tipos->firstWhere('id', $this->tipoId);
        if ($tipo === null) {
            $this->addError('tipoId', 'Elige un tipo de documento válido.');

            return;
        }

        /** @var User $usuario */
        $usuario = auth()->user();

        try {
            app(CicloVidaDocumentoService::class)->altaDocumento($this->fichero, new DatosIngesta(
                tipo: $tipo,
                usuario: $usuario,
                canal: CanalCaptura::Presencial,
                vinculos: [$this->persona, $this->encargo],
                nombreOriginal: $this->fichero->getClientOriginalName(),
                titulo: $this->titulo !== '' ? $this->titulo : null,
                fechaEmision: $this->fechaEmision !== '' ? Carbon::parse($this->fechaEmision) : null,
                organoEmisor: $this->organoEmisor !== '' ? $this->organoEmisor : null,
            ));
        } catch (IngestaRechazadaException|DomainException $e) {
            // Un rechazo con causa técnica no es culpa del fichero: se registra para diagnosticarlo
            if ($e instanceof IngestaRechazadaException && $e->getPrevious() !== null) {
                report($e->getPrevious());
            }
            $this->errorIngesta = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            // Metadatos que exige el tipo: los propios del formulario van a su campo; el resto, al tipo
            $campos = ['fecha_emision' => 'fechaEmision', 'organo_emisor' => 'organoEmisor', 'titulo' => 'titulo'];
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn (array $mensajes, string $clave): array => [$campos[$clave] ?? 'tipoId' => $mensajes])
                ->all());
        }

        $this->reset(['fichero', 'tipoId', 'titulo', 'fechaEmision', 'organoEmisor']);
        $this->aviso = 'Documento guardado en la carpeta de la persona y vinculado al encargo.';
        unset($this->documentos);
    }

    /**
     * Si se ofrece citar desde el encargo (CitasEncargo::puedeCitar()).
     *
     * @return bool
     */
    #[Computed]
    public function puedeCitar(): bool
    {
        return app(CitasEncargo::class)->puedeCitar($this->encargo, auth()->user());
    }

    /**
     * Tipos de cita activos, con el nombre que ve el usuario.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function tiposCita(): array
    {
        return TipoCita::activos()->orderBy('nombre')->get()
            ->mapWithKeys(fn (TipoCita $tipo) => [$tipo->id => $tipo->nombreParaUsuario(auth()->user())])
            ->all();
    }

    /**
     * Propone huecos propios del centro del encargo para el tipo de cita elegido.
     *
     * @return void
     */
    public function buscarHuecos(): void
    {
        abort_unless($this->puedeCitar, 403);
        $this->validate(['tipoCitaId' => ['required', 'integer']], ['tipoCitaId.required' => 'Elige el tipo de cita.']);

        try {
            $propuestas = app(CitasEncargo::class)->huecos($this->encargo, (int) $this->tipoCitaId, auth()->user());
        } catch (ValidationException $e) {
            // Reglas de la solicitud (tipo inactivo, requiere Historia Social...): al campo del tipo
            $this->addError('tipoCitaId', collect($e->errors())->flatten()->first());
            $this->huecos = null;

            return;
        }

        $this->huecos = $propuestas
            ->map(fn (PropuestaHueco $p) => [
                'slot_id' => $p->slot->id,
                'fecha' => $p->slot->fecha->locale('es')->isoFormat('ddd D MMM YYYY'),
                'hora' => substr((string) $p->slot->hora_inicio, 0, 5),
            ])
            ->all();
    }

    /**
     * Cita a la persona en uno de los huecos propuestos.
     *
     * @param int $slotId
     *
     * @return void
     */
    public function citar(int $slotId): void
    {
        abort_unless($this->puedeCitar, 403);
        // Solo un hueco de los propuestos: el resto de comprobaciones las repite el servicio
        $propuesta = collect($this->huecos ?? [])->firstWhere('slot_id', $slotId);
        if ($propuesta === null || $this->tipoCitaId === null) {
            return;
        }

        try {
            app(CitasEncargo::class)->citar($this->encargo, $this->tipoCitaId, Slot::findOrFail($slotId), auth()->user());
        } catch (LogicException|InvalidArgumentException $e) {
            // DomainException es LogicException: hueco ocupado entretanto o ya no válido
            $this->addError('citar', $e->getMessage().' Vuelve a buscar huecos.');
            $this->huecos = null;

            return;
        }

        $this->reset(['tipoCitaId', 'huecos']);
        $this->aviso = "Cita dada el {$propuesta['fecha']} a las {$propuesta['hora']}. Está en tu agenda.";
    }

    /**
     * Cita que se propone vincular a la actuación: la de la agenda (?cita=) o la de hoy o
     * pendiente de cierre del profesional con la persona (docs/modulo-citas.md §3.5).
     *
     * @return Cita|null
     */
    #[Computed]
    public function citaVinculable(): ?Cita
    {
        if (! $this->puedeActuar) {
            return null;
        }

        return app(AtencionCitaService::class)->citaVinculable($this->persona->id, auth()->user(), $this->citaPedida);
    }

    /**
     * Actuaciones del profesional en este encargo, privadas incluidas (EA-7).
     *
     * @return EloquentCollection<int, Apunte>
     */
    #[Computed]
    public function actuaciones(): EloquentCollection
    {
        return app(ActuacionesEncargo::class)->visibles(auth()->user(), $this->encargo);
    }

    /**
     * Herramientas de valoración que ofrece el área del encargo (EA-2): «valoracion» y «escala»,
     * cada una solo si el área tiene alguna ficha o escala activa.
     *
     * @return list<string>
     */
    #[Computed]
    public function herramientasArea(): array
    {
        if (! $this->puedeActuar) {
            return [];
        }

        $caja = app(CajaHerramientasEncargo::class);

        return array_values(array_filter([
            $caja->tiposFicha($this->encargo)->isNotEmpty() ? 'valoracion' : null,
            $caja->tiposEscala($this->encargo)->isNotEmpty() ? 'escala' : null,
        ]));
    }

    /**
     * Abre la herramienta de valoración o escala del encargo, con la cita propuesta si la
     * casilla está marcada (EA-10).
     *
     * @param string $herramienta «valoracion» o «escala».
     *
     * @return void
     */
    public function abrirHerramienta(string $herramienta): void
    {
        abort_unless(in_array($herramienta, $this->herramientasArea, true), 403);

        $this->redirect(route("intervencion.encargos.{$herramienta}", array_filter([
            'encargo' => $this->encargo->id,
            'cita' => $this->vincularCita ? $this->citaVinculable?->id : null,
        ])), navigate: true);
    }

    /**
     * URL de la ficha de valoración de una actuación de la lista, en lectura (EA-11).
     *
     * @param Apunte $actuacion Apunte de tipo valoración.
     *
     * @return string|null
     */
    public function urlFicha(Apunte $actuacion): ?string
    {
        if ($actuacion->tipo !== TipoApunte::Valoracion || $actuacion->apuntable_id === null) {
            return null;
        }

        return route('intervencion.encargos.ficha', ['encargo' => $this->encargo->id, 'ficha' => $actuacion->apuntable_id]);
    }

    /**
     * Guarda una anotación desde el encargo. Quién actúa, la cita y RN-15 los comprueban
     * ContextoActuacion y AnotacionService; sus rechazos se muestran sin perder el texto.
     *
     * @return void
     */
    public function guardarAnotacion(): void
    {
        $this->resetErrorBag();
        $this->validate(
            [
                'formAnotacion.contenido' => ['required', 'string', 'max:10000'],
                'formAnotacion.visibilidad' => ['required', 'in:'.VisibilidadApunte::Profesionales->value.','.VisibilidadApunte::Privada->value],
            ],
            ['formAnotacion.contenido.required' => 'Escribe la anotación.'],
        );

        try {
            $contexto = ContextoActuacion::desdeEncargo(
                $this->encargo,
                auth()->user(),
                $this->vincularCita ? $this->citaVinculable?->id : null,
            );
            app(AnotacionService::class)->registrar(
                $contexto,
                trim($this->formAnotacion['contenido']),
                VisibilidadApunte::from($this->formAnotacion['visibilidad']),
            );
        } catch (LogicException $e) {
            // DomainException es LogicException: EA-4, RN-15 o cita no vinculable (también la del observer)
            $this->addError('formAnotacion.contenido', $e->getMessage());

            return;
        }

        $this->formAnotacion = ['contenido' => '', 'visibilidad' => 'profesionales'];
        $this->citaPedida = null;
        $this->vincularCita = true;
        $this->aviso = 'Anotación guardada en la Historia Social, vinculada a este encargo.';
        unset($this->citaVinculable, $this->actuaciones);
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
     * Renderiza la página.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.encargo-page');
    }
}
