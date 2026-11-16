<?php

namespace Modules\Documentos\Http\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\PisoFirmado;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Services\PisoFirmadoService;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * PISO firmado del plan (fase 5, paso 4, F5-4), en `PlanPage`.
 *
 * Muestra «PISO firmado el [fecha], subido por [usuario]» con enlace al documento y,
 * para el responsable del plan o la supervisión de su centro en un PISO activo, la
 * subida del escaneo con las dos firmas manuscritas (que sustituye al anterior si lo
 * hay). Solo se pinta en el plan principal (PISO). Todo se autoriza en el servidor.
 *
 * @property-read PlanDeIntervencion|null $plan
 * @property-read PisoFirmado|null $activo
 * @property-read bool $puedeSubir
 */
class PisoFirmadoUpload extends Component
{
    use WithFileUploads;

    /** Plan de intervención. */
    public int $planId;

    /** Modal de subida abierto. */
    public bool $modalAbierto = false;

    /** Escaneo del PISO firmado. */
    public ?TemporaryUploadedFile $fichero = null;

    /** Observaciones de quien sube. */
    public string $observaciones = '';

    /** Motivo del último rechazo de la ingesta. */
    public ?string $errorSubida = null;

    /**
     * Plan (sin scope: decide la policy).
     *
     * @return PlanDeIntervencion|null
     */
    #[Computed]
    public function plan(): ?PlanDeIntervencion
    {
        return PlanDeIntervencion::withoutGlobalScopes()->find($this->planId);
    }

    /**
     * PISO firmado activo del plan.
     *
     * @return PisoFirmado|null
     */
    #[Computed]
    public function activo(): ?PisoFirmado
    {
        return $this->plan !== null ? app(PisoFirmadoService::class)->activo($this->plan) : null;
    }

    /**
     * Si el usuario puede subir o sustituir el PISO firmado.
     *
     * @return bool
     */
    #[Computed]
    public function puedeSubir(): bool
    {
        return $this->plan !== null && Gate::forUser($this->usuario())->allows('subir', [PisoFirmado::class, $this->plan]);
    }

    /**
     * URL firmada del documento del PISO firmado activo, si el usuario puede verlo.
     *
     * @return string|null
     */
    public function urlDocumento(): ?string
    {
        $documento = $this->activo?->documento;

        return $documento !== null && Gate::forUser($this->usuario())->allows('view', $documento)
            ? app(LecturaDocumentoService::class)->urlTemporal($documento)
            : null;
    }

    /**
     * Abre el modal de subida.
     *
     * @return void
     */
    public function abrir(): void
    {
        abort_unless($this->puedeSubir, 403);

        $this->resetErrorBag();
        $this->reset(['fichero', 'observaciones', 'errorSubida']);
        $this->modalAbierto = true;
    }

    /**
     * Cierra el modal de subida.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $this->modalAbierto = false;
    }

    /**
     * Sube el escaneo del PISO firmado.
     *
     * @return void
     *
     * @throws ValidationException si falta el fichero o excede el tamaño
     */
    public function subir(): void
    {
        abort_unless($this->plan !== null && $this->puedeSubir, 403);

        $this->validate(
            [
                'fichero' => ['required', 'file', 'max:'.config('documentos.max_subida_kb')],
                'observaciones' => ['nullable', 'string', 'max:2000'],
            ],
            ['fichero.required' => 'Elige el escaneo del PISO firmado.', 'fichero.max' => 'El fichero supera el tamaño máximo de subida.'],
        );

        $this->errorSubida = null;

        try {
            app(PisoFirmadoService::class)->subir($this->plan, $this->fichero, $this->observaciones, $this->usuario());
        } catch (IngestaRechazadaException|\DomainException $e) {
            // La causa técnica de la ingesta (qpdf, gs…) va al log; al usuario, el mensaje llano.
            if ($e instanceof IngestaRechazadaException && $e->getPrevious() !== null) {
                report($e->getPrevious());
            }
            $this->errorSubida = $e->getMessage();

            return;
        }

        $this->modalAbierto = false;
        $this->reset(['fichero', 'observaciones']);
        unset($this->activo);
        session()->flash('piso-ok', 'PISO firmado guardado.');
    }

    /**
     * Renderiza el bloque (vacío si el plan no es el principal).
     *
     * @return View
     */
    public function render(): View
    {
        return view('documentos::livewire.piso-firmado-upload');
    }

    /**
     * Usuario autenticado.
     *
     * @return User
     */
    private function usuario(): User
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        return $usuario;
    }
}
