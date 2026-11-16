<?php

namespace Modules\Documentos\Http\Livewire;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\InformeService;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Services\ServicioFirmaInforme;

/**
 * Historial de informes de una persona (fase 5, paso 1), en la caja de herramientas de
 * la Historia Social y en el plan.
 *
 * Lista los informes de la historia (plantilla, tipo, autor, estado y fechas) con sus
 * acciones según el estado y el autor: «Continuar» y «Eliminar borrador» (autor, en
 * borrador), «Ver PDF» (firmado, quien puede ver el informe) y «Anular» (autor, firmado,
 * con motivo). Quien no puede leer la historia (`consulta_basica`, `tramitacion`) no ve
 * nada: el componente se pinta vacío. Toda acción se vuelve a autorizar en el servidor.
 *
 * @property-read HistoriaSocial|null $historia
 * @property-read bool $puedeVer
 * @property-read bool $puedeCrear
 * @property-read Collection<int, Informe> $informes
 */
class InformesHistorial extends Component
{
    /** Historia Social de la persona. */
    public int $historiaId;

    /** Informe firmado que se va a anular (modal abierto), o null. */
    public ?int $anularId = null;

    /** Motivo de anulación que escribe el autor. */
    public string $motivoAnulacion = '';

    /**
     * Historia Social (sin scope: decide la policy).
     *
     * @return HistoriaSocial|null
     */
    #[Computed]
    public function historia(): ?HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()->find($this->historiaId);
    }

    /**
     * Si el usuario puede ver los informes de la historia.
     *
     * @return bool
     */
    #[Computed]
    public function puedeVer(): bool
    {
        return $this->historia !== null && Gate::forUser($this->usuario())->allows('verDeHistoria', [Informe::class, $this->historia]);
    }

    /**
     * Si el usuario puede redactar un informe nuevo de la persona.
     *
     * @return bool
     */
    #[Computed]
    public function puedeCrear(): bool
    {
        return $this->historia !== null
            && $this->historia->estado !== 'cerrada'
            && Gate::forUser($this->usuario())->allows('crear', [Informe::class, $this->historia]);
    }

    /**
     * Informes de la historia, más recientes primero.
     *
     * @return Collection<int, Informe>
     */
    #[Computed]
    public function informes(): Collection
    {
        if (! $this->puedeVer) {
            return new Collection;
        }

        return Informe::query()
            ->with(['plantilla', 'autor'])
            ->where('historia_social_id', $this->historiaId)
            ->latest('id')
            ->get();
    }

    /**
     * URL firmada para ver el PDF firmado, si el usuario puede verlo.
     *
     * @param Informe $informe Informe firmado o anulado.
     *
     * @return string|null
     */
    public function urlPdf(Informe $informe): ?string
    {
        if ($informe->documento_id === null || ! Gate::forUser($this->usuario())->allows('view', $informe)) {
            return null;
        }

        return app(LecturaDocumentoService::class)->urlTemporal($informe->documento);
    }

    /**
     * Elimina un borrador propio.
     *
     * @param int $informeId Borrador.
     *
     * @return void
     */
    public function eliminarBorrador(int $informeId): void
    {
        $informe = $this->informeDeLaHistoria($informeId);
        abort_unless(Gate::forUser($this->usuario())->allows('delete', $informe), 403);

        app(InformeService::class)->eliminarBorrador($informe, $this->usuario());

        session()->flash('informes-ok', 'Borrador eliminado.');
        unset($this->informes);
    }

    /**
     * Abre el modal para anular un informe firmado propio.
     *
     * @param int $informeId Informe firmado.
     *
     * @return void
     */
    public function abrirAnulacion(int $informeId): void
    {
        $informe = $this->informeDeLaHistoria($informeId);
        abort_unless(Gate::forUser($this->usuario())->allows('anular', $informe), 403);

        $this->resetErrorBag();
        $this->anularId = $informe->id;
        $this->motivoAnulacion = '';
    }

    /**
     * Cierra el modal de anulación.
     *
     * @return void
     */
    public function cerrarAnulacion(): void
    {
        $this->anularId = null;
        $this->motivoAnulacion = '';
    }

    /**
     * Anula el informe firmado con el motivo indicado.
     *
     * @return void
     */
    public function anular(): void
    {
        abort_if($this->anularId === null, 404);
        $informe = $this->informeDeLaHistoria($this->anularId);
        abort_unless(Gate::forUser($this->usuario())->allows('anular', $informe), 403);

        $this->validate(
            ['motivoAnulacion' => ['required', 'string', 'min:10', 'max:2000']],
            [
                'motivoAnulacion.required' => 'Indica el motivo de la anulación.',
                'motivoAnulacion.min' => 'Explica el motivo con al menos 10 caracteres.',
            ],
        );

        app(ServicioFirmaInforme::class)->anular($informe, $this->usuario()->id, $this->motivoAnulacion);

        $this->cerrarAnulacion();
        session()->flash('informes-ok', 'Informe anulado. El documento firmado se conserva en la carpeta de la persona.');
        unset($this->informes);
    }

    /**
     * Renderiza el historial (vacío si el usuario no puede ver los informes).
     *
     * @return View
     */
    public function render(): View
    {
        return view('documentos::livewire.informes-historial');
    }

    /**
     * Informe de esta historia, o 404 (no se actúa sobre informes de otra persona).
     *
     * @param int $informeId Informe.
     *
     * @return Informe
     */
    private function informeDeLaHistoria(int $informeId): Informe
    {
        return Informe::query()->where('historia_social_id', $this->historiaId)->findOrFail($informeId);
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
