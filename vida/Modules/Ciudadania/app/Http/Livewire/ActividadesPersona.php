<?php

namespace Modules\Ciudadania\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AccesoExpediente;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Services\Actividades\InscripcionCentroRequerida;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;

/**
 * Bloque «Actividades» de la ficha de la persona (fase 6, paso 4).
 *
 * Muestra sus inscripciones en sesiones de actividad, próximas y anteriores, con
 * actividad, centro, sesión y vía, y permite darlas de baja con motivo. «Inscribir en
 * actividad» lo usa el personal adscrito al centro activo (RN-15): ofrece solo las
 * actividades activas de ese centro con acceso libre o mixto y sesiones con plaza libre, y
 * se inscribe sesión a sesión (F6-1). Si la actividad exige inscripción en el centro, se
 * ofrece hacerla antes (F6-4). La prescripción no está aquí: se hace desde el plan. Todo lo
 * decide en el servidor InscripcionActividadService con su policy.
 *
 * @property-read Collection<int, InscripcionSesion> $proximas
 * @property-read Collection<int, InscripcionSesion> $anteriores
 * @property-read Centro|null $centroActivo
 * @property-read bool $puedeInscribir
 * @property-read Collection<int, Actividad> $actividadesDisponibles
 * @property-read Collection<int, SesionActividad> $sesionesDisponibles
 */
class ActividadesPersona extends Component
{
    /** Persona de la ficha. */
    #[Locked]
    public int $ciudadanoId;

    /** Si está abierto el formulario de inscripción. */
    public bool $inscribiendo = false;

    public ?int $actividadId = null;

    public ?int $sesionId = null;

    /** Si hay que pedir el consentimiento para inscribir en el centro (F6-4). */
    public bool $pideInscripcionCentro = false;

    /** Inscripción que se va a dar de baja. */
    public ?int $bajaId = null;

    public string $motivoBaja = '';

    /**
     * Recibe la persona y comprueba que el usuario la puede ver.
     *
     * @param int $ciudadanoId
     *
     * @return void
     */
    public function mount(int $ciudadanoId): void
    {
        $this->ciudadanoId = $ciudadanoId;
        // La ficha ya anotó la apertura: aquí solo se autoriza
        $this->ciudadano();
    }

    // -------------------------------------------------------------------------
    // Inscripciones de la persona
    // -------------------------------------------------------------------------

    /**
     * Inscripciones vigentes en sesiones que aún no han empezado.
     *
     * @return Collection<int, InscripcionSesion>
     */
    #[Computed]
    public function proximas(): Collection
    {
        return $this->inscripciones()->filter(fn (InscripcionSesion $i) => $i->vigente() && $i->sesion->inicio()->isFuture())->values();
    }

    /**
     * El resto: dadas de baja o de sesiones ya empezadas, celebradas o canceladas.
     *
     * @return Collection<int, InscripcionSesion>
     */
    #[Computed]
    public function anteriores(): Collection
    {
        $proximas = $this->proximas->pluck('id');

        return $this->inscripciones()->reject(fn (InscripcionSesion $i) => $proximas->contains($i->id))->sortByDesc(fn (InscripcionSesion $i) => $i->sesion->inicio())->values();
    }

    /**
     * Si el usuario puede dar de baja una inscripción (policy).
     *
     * @param InscripcionSesion $inscripcion
     *
     * @return bool
     */
    public function puedeDarDeBaja(InscripcionSesion $inscripcion): bool
    {
        return Gate::allows('darDeBaja', $inscripcion);
    }

    /**
     * Abre la confirmación de baja de una inscripción de la persona.
     *
     * @param int $id
     *
     * @return void
     */
    public function abrirBaja(int $id): void
    {
        $this->bajaId = $this->inscripcionDeLaPersona($id)->id;
        $this->motivoBaja = '';
        $this->resetErrorBag();
    }

    /**
     * Da de baja la inscripción con el motivo indicado.
     *
     * @return void
     */
    public function confirmarBaja(): void
    {
        if ($this->bajaId === null) {
            return;
        }

        $this->resetErrorBag('motivoBaja');

        try {
            app(InscripcionActividadService::class)->darDeBaja($this->inscripcionDeLaPersona($this->bajaId), $this->motivoBaja, $this->usuario());
        } catch (DomainException|AuthorizationException $e) {
            $this->addError('motivoBaja', $e->getMessage());

            return;
        }

        $this->bajaId = null;
        $this->motivoBaja = '';
        unset($this->proximas, $this->anteriores);
    }

    // -------------------------------------------------------------------------
    // Inscribir en actividad
    // -------------------------------------------------------------------------

    /**
     * Centro activo del usuario: el único en que puede inscribir por acceso libre.
     *
     * @return Centro|null
     */
    #[Computed]
    public function centroActivo(): ?Centro
    {
        return app(CentroDeUsuario::class)->centroActivo($this->usuario());
    }

    /**
     * Si se ofrece «Inscribir en actividad»: hay centro activo y la policy deja inscribir
     * en él (el servidor vuelve a comprobarlo al inscribir).
     *
     * @return bool
     */
    #[Computed]
    public function puedeInscribir(): bool
    {
        $centro = $this->centroActivo;

        return $centro !== null && Gate::allows('inscribirEnCentro', [InscripcionSesion::class, $centro]);
    }

    /**
     * Actividades activas del centro activo con acceso libre o mixto y alguna sesión con plaza.
     *
     * @return Collection<int, Actividad>
     */
    #[Computed]
    public function actividadesDisponibles(): Collection
    {
        if (! $this->puedeInscribir) {
            return collect();
        }

        return Actividad::where('centro_id', $this->centroActivo->id)
            ->where('activa', true)
            ->whereNull('fecha_baja')
            ->whereIn('modo_acceso', ['libre', 'mixta'])
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Actividad $a) => $this->sesionesConPlaza($a)->isNotEmpty())
            ->values();
    }

    /**
     * Sesiones de la actividad elegida en que se puede inscribir a la persona.
     *
     * @return Collection<int, SesionActividad>
     */
    #[Computed]
    public function sesionesDisponibles(): Collection
    {
        $actividad = $this->actividadesDisponibles->firstWhere('id', $this->actividadId);

        return $actividad === null ? collect() : $this->sesionesConPlaza($actividad);
    }

    /**
     * Abre el formulario de inscripción.
     *
     * @return void
     */
    public function abrirInscripcion(): void
    {
        $this->reset(['actividadId', 'sesionId', 'pideInscripcionCentro']);
        $this->resetErrorBag();
        $this->inscribiendo = true;
    }

    /**
     * Al cambiar de actividad se vacía la sesión elegida.
     *
     * @return void
     */
    public function updatedActividadId(): void
    {
        $this->sesionId = null;
        $this->pideInscripcionCentro = false;
        unset($this->sesionesDisponibles);
    }

    /**
     * Inscribe a la persona en la sesión elegida. Si la actividad exige la inscripción en
     * el centro y no la tiene, pide el consentimiento antes (F6-4).
     *
     * @param bool $inscribirEnCentro Consentimiento ya dado.
     *
     * @return void
     */
    public function inscribir(bool $inscribirEnCentro = false): void
    {
        $this->resetErrorBag('sesionId');
        $sesion = $this->sesionId === null ? null : SesionActividad::find($this->sesionId);

        if ($sesion === null) {
            $this->addError('sesionId', 'Elige una sesión.');

            return;
        }

        try {
            app(InscripcionActividadService::class)->inscribir($sesion, $this->ciudadano(), $this->usuario(), $inscribirEnCentro);
        } catch (InscripcionCentroRequerida) {
            $this->pideInscripcionCentro = true;

            return;
        } catch (DomainException|AuthorizationException $e) {
            $this->addError('sesionId', $e->getMessage());

            return;
        }

        $this->inscribiendo = false;
        $this->reset(['actividadId', 'sesionId', 'pideInscripcionCentro']);
        unset($this->proximas, $this->anteriores, $this->actividadesDisponibles, $this->sesionesDisponibles);
    }

    /**
     * Renderiza el bloque.
     *
     * @return View
     */
    public function render(): View
    {
        return view('ciudadania::livewire.actividades-persona');
    }

    // -------------------------------------------------------------------------
    // Privados
    // -------------------------------------------------------------------------

    /**
     * Todas las inscripciones de la persona con su sesión, actividad y centro.
     *
     * @return Collection<int, InscripcionSesion>
     */
    private function inscripciones(): Collection
    {
        return InscripcionSesion::where('ciudadano_id', $this->ciudadanoId)
            ->with(['sesion.actividad.centro'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (InscripcionSesion $i) => $i->sesion->inicio())
            ->values();
    }

    /**
     * Sesiones programadas futuras de una actividad con plaza de acceso libre y sin la
     * persona ya inscrita.
     *
     * @param Actividad $actividad
     *
     * @return Collection<int, SesionActividad>
     */
    private function sesionesConPlaza(Actividad $actividad): Collection
    {
        $yaInscrita = InscripcionSesion::where('ciudadano_id', $this->ciudadanoId)->vigentes()->pluck('sesion_actividad_id');

        return $actividad->sesiones()
            ->where('estado', 'programada')
            ->whereDate('fecha', '>=', today())
            ->whereNotIn('id', $yaInscrita)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (SesionActividad $s) => $s->admiteInscripciones() && $s->plazasLibres(ViaInscripcion::Libre) !== 0)
            ->values();
    }

    /**
     * Inscripción de esta persona; 404 si es de otra.
     *
     * @param int $id
     *
     * @return InscripcionSesion
     */
    private function inscripcionDeLaPersona(int $id): InscripcionSesion
    {
        return InscripcionSesion::where('id', $id)->where('ciudadano_id', $this->ciudadanoId)->firstOrFail();
    }

    /**
     * Persona de la ficha, autorizada por AccesoExpediente sin anotar otra lectura.
     *
     * @return Ciudadano
     *
     * @throws AuthorizationException Si la policy no deja ver a la persona.
     */
    private function ciudadano(): Ciudadano
    {
        return app(AccesoExpediente::class)->ciudadano($this->usuario(), $this->ciudadanoId, registrar: false);
    }

    /**
     * Usuario autenticado.
     *
     * @return User
     */
    private function usuario(): User
    {
        /** @var User */
        return Auth::user();
    }
}
