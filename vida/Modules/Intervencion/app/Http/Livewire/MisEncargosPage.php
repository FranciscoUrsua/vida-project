<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Services\PresentacionPersona;
use App\Services\ResolucionAccesoProtegido;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Models\Derivacion;

/**
 * «Mis derivaciones»: página de inicio del profesional de encargo (`derivacion.ver_asignadas`,
 * EP-10 de docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * Lista los encargos profesionales asignados al usuario: los activos (aceptados) y el
 * histórico (cerrados), con la persona, el centro, el TSR que encarga, el motivo y la
 * antigüedad. Solo los suyos (scope `deProfesional`).
 *
 * Restricción de dominio (EP-7, RN-17 ampliada): una persona protegida de un encargo
 * cerrado aparece como «Persona con protección especial», sin enlace al encargo ni a su
 * ficha (PresentacionPersona decide con CiudadanoPolicy), con «Solicitar acceso» (RN-18). La
 * solicitud se pide por el encargo, no por la persona: la página no envía al navegador el id
 * de una persona que el usuario ya no puede ver.
 *
 * @property-read Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}, solicitado: bool}> $activos
 * @property-read Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}, solicitado: bool}> $historico
 */
#[Layout('layouts.operativo')]
class MisEncargosPage extends Component
{
    /** Encargo cuya persona protegida se pide ver («Solicitar acceso»). */
    public ?int $solicitarDe = null;

    public string $justificacion = '';

    /** Confirmación tras enviar la solicitud. */
    public ?string $aviso = null;

    /**
     * Encargos en curso (aceptados), los más antiguos primero.
     *
     * @return Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}, solicitado: bool}>
     */
    #[Computed]
    public function activos(): Collection
    {
        return $this->filas(EstadoDerivacion::Aceptada, 'asc');
    }

    /**
     * Encargos cerrados, los más recientes primero.
     *
     * @return Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}, solicitado: bool}>
     */
    #[Computed]
    public function historico(): Collection
    {
        return $this->filas(EstadoDerivacion::Cerrada, 'desc');
    }

    /**
     * Abre el formulario de solicitud de acceso para la persona de un encargo cerrado.
     *
     * @param int $encargoId
     *
     * @return void
     */
    public function prepararSolicitud(int $encargoId): void
    {
        $this->personaEnmascaradaDe($encargoId);
        $this->solicitarDe = $encargoId;
        $this->justificacion = '';
        $this->aviso = null;
        $this->resetErrorBag();
    }

    /**
     * Cierra el formulario de solicitud sin enviarla.
     *
     * @return void
     */
    public function cancelarSolicitud(): void
    {
        $this->solicitarDe = null;
        $this->justificacion = '';
        $this->resetErrorBag();
    }

    /**
     * Envía la solicitud de acceso a la persona protegida del encargo (RN-18).
     *
     * @return void
     */
    public function solicitarAcceso(): void
    {
        abort_if($this->solicitarDe === null, 404);
        $ciudadanoId = $this->personaEnmascaradaDe($this->solicitarDe);

        try {
            app(ResolucionAccesoProtegido::class)->solicitar(auth()->user(), $ciudadanoId, $this->justificacion);
        } catch (DomainException $e) {
            $this->addError('justificacion', $e->getMessage());

            return;
        }

        $this->cancelarSolicitud();
        $this->aviso = 'Solicitud enviada. Te avisaremos cuando la dirección del centro la resuelva.';
        unset($this->historico);
    }

    /**
     * Renderiza la página.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.mis-encargos-page');
    }

    /**
     * Encargos del usuario en un estado, con la persona presentada según lo que puede ver.
     *
     * Las personas se cargan sin AmbitoUoScope: el profesional de encargo no tiene ámbito de
     * UO sobre ellas; lo que se muestra lo decide PresentacionPersona con la policy.
     *
     * @param EstadoDerivacion $estado
     * @param string $orden 'asc' | 'desc'
     *
     * @return Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}, solicitado: bool}>
     */
    private function filas(EstadoDerivacion $estado, string $orden): Collection
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        $encargos = Derivacion::query()
            ->deProfesional($usuario)
            ->where('estado', $estado->value)
            ->with(['centroDestino', 'area', 'derivadaPor.profesional'])
            ->orderBy($estado === EstadoDerivacion::Cerrada ? 'cerrada_en' : 'resuelta_en', $orden)
            ->get();

        $ciudadanoDe = DB::table('historias_sociales')
            ->whereIn('id', $encargos->pluck('historia_id')->unique())
            ->pluck('ciudadano_id', 'id');
        $personas = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->whereKey($ciudadanoDe->values()->unique())
            ->get()
            ->keyBy('id');

        $resolucion = app(ResolucionAccesoProtegido::class);

        return $encargos->map(function (Derivacion $encargo) use ($personas, $ciudadanoDe, $usuario, $resolucion): array {
            $persona = $personas->get($ciudadanoDe->get($encargo->historia_id));
            $presentacion = PresentacionPersona::paraListado($persona, $usuario);

            return [
                'encargo' => $encargo,
                'persona' => $presentacion,
                // Solo para la persona enmascarada: si ya hay una solicitud de acceso esperando
                'solicitado' => ! $presentacion['visible'] && $persona !== null && $resolucion->tienePendiente($usuario, $persona->id),
            ];
        })->toBase();
    }

    /**
     * Persona de un encargo cerrado del usuario que ya no puede ver; si no lo es, 403.
     *
     * @param int $encargoId
     *
     * @return int Id de la persona.
     */
    private function personaEnmascaradaDe(int $encargoId): int
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        $encargo = Derivacion::query()->deProfesional($usuario)
            ->where('estado', EstadoDerivacion::Cerrada->value)
            ->find($encargoId);
        abort_if($encargo === null, 403);

        $ciudadanoId = (int) DB::table('historias_sociales')->where('id', $encargo->historia_id)->value('ciudadano_id');
        $persona = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadanoId);
        abort_if(PresentacionPersona::visible($persona, $usuario), 403);

        return $ciudadanoId;
    }
}
