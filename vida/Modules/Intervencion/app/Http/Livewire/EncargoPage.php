<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Services\PresentacionPersona;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\EncargoService;

/**
 * Detalle de un encargo profesional para el profesional asignado (C7 de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md): motivo y contexto, TSR que
 * encarga, documentos adjuntos y registro del resultado, que cierra el encargo
 * (EncargoService::cerrar, EP-4 y EP-5).
 *
 * Solo lo abre el profesional asignado y con el encargo aceptado o cerrado; el resto, 403.
 * Restricción de dominio (EP-7, RN-17 ampliada): si la persona es protegida y el encargo
 * ya está cerrado, deja de verla (salvo acceso protegido aprobado) y la página responde 403,
 * porque el motivo y el resultado la identifican.
 *
 * @property-read Derivacion $encargo
 * @property-read Ciudadano $persona
 * @property-read Collection<int, Documento> $documentos
 * @property-read bool $puedeCerrar
 */
#[Layout('layouts.operativo')]
class EncargoPage extends Component
{
    /** @var int Encargo (parámetro de ruta). */
    #[Locked]
    public int $encargoId;

    /** @var string Resultado que se registra al cerrar. */
    public string $resultado = '';

    /** @var string|null Confirmación tras cerrar. */
    public ?string $aviso = null;

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
     * Encargo autorizado para el usuario en cada petición.
     *
     * @return Derivacion
     */
    #[Computed]
    public function encargo(): Derivacion
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        $encargo = Derivacion::query()->deProfesional($usuario)
            ->with(['centroDestino', 'derivadaPor.profesional', 'cerradaPor.profesional'])
            ->find($this->encargoId);

        abort_if($encargo === null, 403);

        $ciudadanoId = (int) DB::table('historias_sociales')->where('id', $encargo->historia_id)->value('ciudadano_id');
        $persona = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadanoId);
        abort_unless(PresentacionPersona::visible($persona, $usuario), 403);

        return $encargo;
    }

    /**
     * Persona del encargo (ya autorizada por encargo()).
     *
     * @return Ciudadano
     */
    #[Computed]
    public function persona(): Ciudadano
    {
        $ciudadanoId = (int) DB::table('historias_sociales')->where('id', $this->encargo->historia_id)->value('ciudadano_id');

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadanoId);
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
     * Si el documento se puede abrir (DocumentoPolicy; la vía del encargo llega en el bloque D).
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
        unset($this->encargo, $this->puedeCerrar);
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
