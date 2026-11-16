<?php

namespace Modules\Documentos\Http\Livewire;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Services\AccesoExpediente;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Services\InformeService;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Modules\Documentos\Support\SaneadorHtmlInforme;

/**
 * Asistente de redacción y firma de un informe profesional (fase 5, pasos 2 y 3).
 *
 * Cuatro pasos: (1) plantilla activa, filtrable por tipo; (2) redacción: secciones
 * automáticas precargadas y en lectura, texto libre con guardado automático y merge
 * tags; (3) vista previa del borrador; (4) firma: descargar el PDF para firmar y subir
 * el firmado (F5-1 a) o, si está activa, AutoFirma integrada (F5-1 b).
 *
 * El borrador se puede dejar y retomar desde el historial (ruta `intervencion.informes.editar`).
 * Cada acción vuelve a autorizar con `InformePolicy`; no se puede pasar de la vista
 * previa con secciones obligatorias vacías (comprobado también en el servidor).
 *
 * @property-read Informe|null $informe
 * @property-read Collection<int, PlantillaInforme> $plantillas
 * @property-read array<int, array<string, mixed>> $secciones
 * @property-read list<string> $obligatoriasVacias
 * @property-read bool $autofirmaActiva
 */
#[Layout('layouts.operativo')]
class NuevoInformeWizard extends Component
{
    use WithFileUploads;

    /** Historia Social de la persona del informe. */
    public HistoriaSocial $historia;

    /** Borrador en curso (null en el paso 1). */
    public ?int $informeId = null;

    /** Paso actual: 1 plantilla, 2 redacción, 3 vista previa, 4 firma. */
    public int $paso = 1;

    /** Filtro de tipo de informe en el paso 1. */
    public string $filtroTipo = '';

    /** PDF firmado que sube el autor (paso 4). */
    public ?TemporaryUploadedFile $pdfFirmado = null;

    /** Motivo del último rechazo de la firma, para mostrarlo. */
    public ?string $errorFirma = null;

    /** Momento del último guardado automático (para el aviso «Guardado»). */
    public ?string $guardadoEn = null;

    /**
     * Abre el asistente para un informe nuevo de la historia o para retomar un borrador.
     *
     * @param HistoriaSocial|null $historia Historia (ruta «nuevo»).
     * @param Informe|null $informe Borrador (ruta «editar»).
     *
     * @return void
     */
    public function mount(?HistoriaSocial $historia = null, ?Informe $informe = null): void
    {
        $usuario = $this->usuario();

        if ($informe !== null && $informe->exists) {
            abort_unless(Gate::forUser($usuario)->allows('update', $informe), 403);
            $this->historia = app(AccesoExpediente::class)->historia($usuario, (int) $informe->historia_social_id);
            $this->informeId = $informe->id;
            $this->paso = 2;

            return;
        }

        abort_if($historia === null, 404);
        // El binding carga sin scope: se autoriza y audita la apertura antes de leer nada.
        $this->historia = app(AccesoExpediente::class)->historia($usuario, $historia->id);
        abort_unless(Gate::forUser($usuario)->allows('crear', [Informe::class, $this->historia]), 403);
    }

    /**
     * Borrador en curso.
     *
     * @return Informe|null
     */
    #[Computed]
    public function informe(): ?Informe
    {
        return $this->informeId !== null ? Informe::with('plantilla')->find($this->informeId) : null;
    }

    /**
     * Plantillas activas visibles para el profesional (su UO y ancestros), por tipo.
     *
     * @return Collection<int, PlantillaInforme>
     */
    #[Computed]
    public function plantillas(): Collection
    {
        $uoIds = $this->usuario()->adscripcionesVigentes()->pluck('unidad_organizativa_id')
            ->flatMap(fn ($id) => UnidadOrganizativa::find($id)?->ancestorsAndSelf()->pluck('id') ?? [])
            ->unique()
            ->values()
            ->all();

        return PlantillaInforme::query()
            ->where('activa', true)
            ->whereIn('unidad_organizativa_id', $uoIds)
            ->when($this->filtroTipo !== '', fn ($q) => $q->where('tipo_informe', $this->filtroTipo))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Tipos de informe para el filtro del paso 1.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tipos(): array
    {
        return collect(TipoInforme::cases())->mapWithKeys(fn (TipoInforme $t): array => [$t->value => $t->label()])->all();
    }

    /**
     * Secciones de la plantilla con lo que se muestra de cada una en la redacción.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function secciones(): array
    {
        $informe = $this->informe;
        if ($informe === null) {
            return [];
        }

        $automaticos = app(ServicioGeneracionPDF::class)->datosAutomaticos($informe);
        $saneador = app(SaneadorHtmlInforme::class);

        return collect($informe->plantilla->secciones ?? [])
            ->filter(fn (array $s): bool => isset($s['id']))
            ->map(fn (array $s): array => [
                'id' => (string) $s['id'],
                'titulo' => (string) ($s['titulo'] ?? $s['id']),
                'tipo' => (string) ($s['tipo'] ?? 'texto_libre'),
                'obligatorio' => ! empty($s['obligatorio']),
                'instrucciones' => $s['instrucciones'] ?? null,
                'datos' => $automaticos[$s['id']] ?? [],
                'html' => $saneador->sanear($informe->contenido[$s['id']] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Títulos de las secciones obligatorias vacías.
     *
     * @return list<string>
     */
    #[Computed]
    public function obligatoriasVacias(): array
    {
        return $this->informe !== null ? app(InformeService::class)->obligatoriasVacias($this->informe) : [];
    }

    /**
     * Si AutoFirma integrada está activa (F5-1 b).
     *
     * @return bool
     */
    #[Computed]
    public function autofirmaActiva(): bool
    {
        return app(ServicioFirmaInforme::class)->firmador(MetodoFirma::AutofirmaCertificadoEmpleadoPublico)->activo();
    }

    /**
     * Crea el borrador con la plantilla elegida y pasa a la redacción.
     *
     * @param int $plantillaId Plantilla activa.
     *
     * @return void
     */
    public function elegirPlantilla(int $plantillaId): void
    {
        $plantilla = $this->plantillas->firstWhere('id', $plantillaId);
        abort_if($plantilla === null, 403, 'Esta plantilla no está disponible.');

        $informe = app(InformeService::class)->crear($plantilla, $this->historia, $this->usuario());

        $this->redirectRoute('intervencion.informes.editar', ['informe' => $informe->id], navigate: true);
    }

    /**
     * Guardado automático del texto de una sección libre.
     *
     * @param string $seccionId Sección.
     * @param string|null $html HTML del editor.
     *
     * @return void
     */
    public function guardarSeccion(string $seccionId, ?string $html): void
    {
        $informe = $this->informeEditable();
        app(InformeService::class)->guardarSeccion($informe, $seccionId, $html, $this->usuario());

        $this->guardadoEn = now()->format('H:i');
        unset($this->informe, $this->secciones, $this->obligatoriasVacias);
    }

    /**
     * Va a un paso anterior o a la vista previa.
     *
     * @param int $paso 2 o 3.
     *
     * @return void
     */
    public function irAPaso(int $paso): void
    {
        $this->informeEditable();
        abort_unless(in_array($paso, [2, 3], true), 422);

        $this->errorFirma = null;
        $this->paso = $paso;
    }

    /**
     * Pasa de la vista previa a la firma si no quedan secciones obligatorias vacías.
     *
     * @return void
     */
    public function irAFirma(): void
    {
        $informe = $this->informeEditable();

        if (app(InformeService::class)->obligatoriasVacias($informe) !== []) {
            $this->addError('obligatorias', 'Completa las secciones obligatorias antes de firmar.');
            $this->paso = 3;

            return;
        }

        $this->paso = 4;
    }

    /**
     * Firma con el PDF firmado fuera de VIDA que sube el autor (F5-1 a).
     *
     * @return void
     */
    public function subirFirmado(): void
    {
        $this->validate(
            ['pdfFirmado' => ['required', 'file', 'max:'.config('documentos.max_subida_kb')]],
            ['pdfFirmado.required' => 'Elige el PDF firmado.', 'pdfFirmado.max' => 'El fichero supera el tamaño máximo de subida.'],
        );

        $servicio = app(ServicioFirmaInforme::class);
        $metodo = MetodoFirma::PdfFirmadoExternamente;

        $this->firmar($metodo, fn (Informe $informe): string => $servicio->firmador($metodo)->pdfFirmado($informe, $this->pdfFirmado));
    }

    /**
     * Firma con el PDF que devuelve AutoFirma integrada (F5-1 b). Rechazada si no está activa.
     *
     * @param string $pdfBase64 PDF firmado en base64.
     *
     * @return void
     */
    public function firmarConAutoFirma(string $pdfBase64): void
    {
        abort_unless($this->autofirmaActiva, 403, 'AutoFirma integrada no está activa.');

        $servicio = app(ServicioFirmaInforme::class);
        $metodo = MetodoFirma::AutofirmaCertificadoEmpleadoPublico;

        $this->firmar($metodo, fn (Informe $informe): string => $servicio->firmador($metodo)->pdfFirmado($informe, $pdfBase64));
    }

    /**
     * Renderiza el asistente.
     *
     * @return View
     */
    public function render(): View
    {
        return view('documentos::livewire.nuevo-informe-wizard');
    }

    /**
     * Firma común a los dos métodos: autoriza, obtiene el PDF, valida y custodia.
     *
     * Un rechazo deja el informe en borrador y muestra el motivo; la causa técnica va al
     * log, como en la subida de documentos.
     *
     * @param MetodoFirma $metodo Método de firma.
     * @param \Closure(Informe): string $obtenerPdf Obtiene el PDF firmado del navegador.
     *
     * @return void
     */
    private function firmar(MetodoFirma $metodo, \Closure $obtenerPdf): void
    {
        $informe = $this->informeEditable();
        abort_unless(Gate::forUser($this->usuario())->allows('firmar', $informe), 403);
        abort_if(app(InformeService::class)->obligatoriasVacias($informe) !== [], 422, 'Hay secciones obligatorias vacías.');

        $this->errorFirma = null;

        try {
            app(ServicioFirmaInforme::class)->firmar($informe, $metodo, $obtenerPdf($informe), $this->usuario());
        } catch (FirmaRechazadaException $e) {
            if ($e->tieneCausaTecnica()) {
                report($e->getPrevious());
            }
            $this->errorFirma = $e->getMessage();
            $this->pdfFirmado = null;

            return;
        } catch (\DomainException $e) {
            $this->errorFirma = $e->getMessage();

            return;
        }

        session()->flash('informes-ok', 'Informe firmado y guardado en la carpeta de la persona.');
        $this->redirectRoute('intervencion.ciudadano.show', ['historia' => $this->historia->id, 'herramienta' => 'informes'], navigate: true);
    }

    /**
     * Borrador que el usuario puede seguir editando (autor y en borrador), o 403.
     *
     * @return Informe
     */
    private function informeEditable(): Informe
    {
        $informe = $this->informe;
        abort_if($informe === null, 404);
        abort_unless(Gate::forUser($this->usuario())->allows('update', $informe), 403);

        return $informe;
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
