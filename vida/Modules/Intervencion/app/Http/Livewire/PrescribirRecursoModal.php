<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\Red;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Services\Actividades\InscripcionCentroRequerida;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\PrescripcionService;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Modal de prescripción de recurso en tres pasos montado en CiudadanoPage.
 *
 * Paso 1 — Tipo de recurso (tipo_plaza de las colecciones disponibles, o «Actividad»).
 * Paso 2 — Selección de destino (ColeccionPlazas o Red; en una actividad, la actividad y la sesión).
 * Paso 3 — Confirmación y vínculo opcional con compromiso del plan.
 *
 * Actividad (fase 6, F6-7): actividades activas con prescripción o mixtas de cualquier
 * centro (la prescripción puede hacerse desde otro centro, F6-2) y sus sesiones con plazas
 * de prescripción libres. La crea PrescripcionService con su inscripción; sin plaza se
 * rechaza (F6-3). Si la actividad exige inscripción en el centro, se pide el
 * consentimiento para hacerla (F6-4).
 *
 * @property-read Collection<int, string> $tiposRecurso
 * @property-read Collection<int, ColeccionPlazas>|Collection<int, Red>|Collection<int, SesionActividad> $opcionesDestino
 * @property-read Collection<int, Actividad> $actividadesPrescribibles
 * @property-read Actividad|null $actividadElegida
 * @property-read bool $requiereInscripcionCentro
 * @property-read Collection<int, PlanActuacionAyuntamiento> $compromisosSugeridos
 * @property-read bool $hayPlazaDisponible
 */
class PrescribirRecursoModal extends Component
{
    /** Tipo de recurso de las sesiones de actividad en el paso 1. */
    public const RECURSO_ACTIVIDAD = 'actividad';

    /** @var int ID de la Historia Social del ciudadano */
    public int $historiaId;

    /** @var bool Controla la visibilidad del modal */
    public bool $abierto = false;

    /** @var int Paso activo: 1, 2 o 3 */
    public int $paso = 1;

    /** @var string|null Tipo de plaza seleccionado (p. ej. 'pernocta', 'dia') */
    public ?string $tipoRecurso = null;

    /** @var int|null ID del destino seleccionado (ColeccionPlazas o Red) */
    public ?int $destinoId = null;

    /** @var int|null Actividad elegida en el paso 2 (tipo de recurso «Actividad»). */
    public ?int $actividadId = null;

    /** @var bool Consentimiento para inscribir a la persona en el centro de la actividad (F6-4). */
    public bool $inscribirEnCentro = false;

    /** @var string Tipo de destino: 'coleccion_plazas' | 'red' | 'sesion_actividad' */
    public string $tipoDestino = 'coleccion_plazas';

    /** @var int|null ID del compromiso del plan al que se vincula opcionalmente */
    public ?int $compromisoId = null;

    /** @var string Notas para el TS del centro receptor */
    public string $notas = '';

    /** Prestación del catálogo, si la prescripción nace de «Solicitar prestación» (fase 3 bis). */
    public ?int $prestacionId = null;

    // -------------------------------------------------------------------------
    // Eventos
    // -------------------------------------------------------------------------

    /**
     * Abre el modal y resetea su estado interno; con prestación si viene de «Solicitar prestación».
     *
     * @param int|null $prestacionId
     *
     * @return void
     */
    #[On('abrir-prescribir-recurso')]
    public function abrir(?int $prestacionId = null): void
    {
        // R1: prescribir es una actuación; se rechaza al abrir, con el mensaje de RN-15
        app(CentroDeUsuario::class)->exigirActuacion(Auth::user());

        $this->reset(['paso', 'tipoRecurso', 'destinoId', 'tipoDestino', 'compromisoId', 'notas', 'actividadId', 'inscribirEnCentro']);
        $this->prestacionId = $prestacionId;
        $this->paso = 1;
        $this->tipoDestino = 'coleccion_plazas';
        $this->abierto = true;
        unset($this->opcionesDestino, $this->compromisosSugeridos, $this->hayPlazaDisponible);
    }

    /**
     * Al cambiar de tipo de recurso se vacía el destino elegido.
     *
     * @return void
     */
    public function updatedTipoRecurso(): void
    {
        $this->reset(['destinoId', 'actividadId', 'inscribirEnCentro']);
        unset($this->opcionesDestino, $this->hayPlazaDisponible);
    }

    /**
     * Al cambiar de actividad se vacía la sesión elegida.
     *
     * @return void
     */
    public function updatedActividadId(): void
    {
        $this->reset(['destinoId', 'inscribirEnCentro']);
        unset($this->opcionesDestino, $this->hayPlazaDisponible, $this->actividadElegida, $this->requiereInscripcionCentro);
    }

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    /**
     * Tipos de plaza únicos disponibles en colecciones con acceso por prescripción.
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function tiposRecurso(): Collection
    {
        $tipos = ColeccionPlazas::activas()
            ->whereIn('modo_acceso', ['prescripcion_directa', 'prescripcion_lista_espera'])
            ->distinct()
            ->pluck('tipo_plaza');

        if ($this->actividadesPrescribibles->isNotEmpty()) {
            $tipos->push(self::RECURSO_ACTIVIDAD);
        }

        return $tipos;
    }

    /**
     * Actividades activas, de cualquier centro, que admiten prescripción y tienen alguna
     * sesión con plaza de prescripción libre.
     *
     * @return Collection<int, Actividad>
     */
    #[Computed]
    public function actividadesPrescribibles(): Collection
    {
        return Actividad::where('activa', true)
            ->whereNull('fecha_baja')
            ->whereIn('modo_acceso', ['prescripcion', 'mixta'])
            ->with('centro')
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Actividad $a) => $this->sesionesPrescribibles($a)->isNotEmpty())
            ->values()
            ->toBase();
    }

    /**
     * Actividad elegida en el paso 2.
     *
     * @return Actividad|null
     */
    #[Computed]
    public function actividadElegida(): ?Actividad
    {
        return $this->actividadesPrescribibles->firstWhere('id', $this->actividadId);
    }

    /**
     * Si la actividad elegida exige inscripción en el centro y la persona no la tiene (F6-4).
     *
     * @return bool
     */
    #[Computed]
    public function requiereInscripcionCentro(): bool
    {
        $actividad = $this->actividadElegida;
        $ciudadano = $this->ciudadano();

        return $actividad !== null && $ciudadano !== null
            && $actividad->requiere_inscripcion_centro
            && ! $actividad->ciudadanoInscritoEnCentro($ciudadano->id);
    }

    /**
     * Opciones de destino para el paso 2, según heurística del documento funcional §2.
     *
     * 1. Si existen Redes con el tipo de recurso requerido → devuelve Redes.
     * 2. Si no → devuelve ColeccionPlazas individuales.
     * Si criterio_territorial=true y ciudadano tiene dirección → ordena por proximidad (haversine).
     *
     * @return Collection<int, ColeccionPlazas>|Collection<int, Red>
     */
    #[Computed]
    public function opcionesDestino(): Collection
    {
        if (! $this->tipoRecurso) {
            return collect();
        }

        if ($this->tipoRecurso === self::RECURSO_ACTIVIDAD) {
            $this->tipoDestino = 'sesion_actividad';

            return $this->actividadElegida === null ? collect() : $this->sesionesPrescribibles($this->actividadElegida);
        }

        $ciudadano = $this->ciudadano();

        // Intentar primero redes con colecciones del tipo requerido
        $redes = Red::where('activa', true)
            ->whereHas('centros.coleccionesPlazas', fn ($q) => $q
                ->where('tipo_plaza', $this->tipoRecurso)
                ->whereIn('modo_acceso', ['prescripcion_directa', 'prescripcion_lista_espera'])
                ->where('activa', true)
            )
            ->get();

        if ($redes->isNotEmpty()) {
            $this->tipoDestino = 'red';

            return $redes->toBase();
        }

        $this->tipoDestino = 'coleccion_plazas';

        // Colecciones individuales del tipo requerido
        $query = ColeccionPlazas::activas()
            ->where('tipo_plaza', $this->tipoRecurso)
            ->whereIn('modo_acceso', ['prescripcion_directa', 'prescripcion_lista_espera'])
            ->with('centro');

        // Orden geográfico si criterio_territorial y ciudadano tiene coordenadas
        $lat = $ciudadano?->coordenadas_lat;
        $lng = $ciudadano?->coordenadas_lng;

        $necesitaOrdenGeo = $lat !== null && $lng !== null;

        if ($necesitaOrdenGeo) {
            // Verificar si alguna colección del resultado tiene criterio_territorial=true
            $hayConCriterio = (clone $query)->where('criterio_territorial', true)->exists();

            if ($hayConCriterio) {
                return $query->get()->toBase()->sortBy(function (ColeccionPlazas $cp) use ($lat, $lng) {
                    $cLat = $cp->centro?->coordenadas_lat;
                    $cLng = $cp->centro?->coordenadas_lng;

                    if ($cLat === null || $cLng === null || ! $cp->criterio_territorial) {
                        return PHP_INT_MAX;
                    }

                    return $this->haversineKm($lat, $lng, $cLat, $cLng);
                })->values();
            }
        }

        return $query->get()->toBase();
    }

    /**
     * Compromisos del plan activo del ciudadano sugeridos para vincular a esta prescripción.
     * Solo se muestran si existe un plan activo o borrador.
     *
     * @return Collection<int, PlanActuacionAyuntamiento>
     */
    #[Computed]
    public function compromisosSugeridos(): Collection
    {
        if (! $this->tipoRecurso) {
            return collect();
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->find($this->historiaId);
        if (! $historia) {
            return collect();
        }

        $plan = PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $historia->id)
            ->whereIn('estado', [EstadoPlan::Activo->value, EstadoPlan::Borrador->value])
            ->latest()
            ->first();

        if (! $plan) {
            return collect();
        }

        return PlanActuacionAyuntamiento::where('plan_id', $plan->id)
            ->whereIn('estado', ['pendiente', 'en_curso'])
            ->with('prestacion')
            ->get();
    }

    /**
     * Indica si el destino seleccionado tiene plazas libres en este momento.
     */
    #[Computed]
    public function hayPlazaDisponible(): bool
    {
        if ($this->destinoId && $this->tipoDestino === 'sesion_actividad') {
            return SesionActividad::find($this->destinoId)?->plazasLibres(ViaInscripcion::Prescripcion) !== 0;
        }

        if (! $this->destinoId || $this->tipoDestino !== 'coleccion_plazas') {
            return false;
        }

        $coleccion = ColeccionPlazas::find($this->destinoId);

        return $coleccion?->plazasDisponibles() > 0;
    }

    // -------------------------------------------------------------------------
    // Métodos de navegación
    // -------------------------------------------------------------------------

    /**
     * Valida el paso actual y avanza al siguiente si es correcto.
     */
    public function avanzar(): void
    {
        if ($this->paso === 1 && ! $this->tipoRecurso) {
            return;
        }

        if ($this->paso === 2 && ! $this->destinoId) {
            return;
        }

        if ($this->paso < 3) {
            $this->paso++;
            unset($this->opcionesDestino, $this->compromisosSugeridos, $this->hayPlazaDisponible);
        }
    }

    /**
     * Retrocede al paso anterior sin validar.
     */
    public function retroceder(): void
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    /**
     * Confirma la prescripción: crea Prescripcion + ListaEspera (si aplica) + Apunte.
     * Todo en transacción; en caso de error hace rollback completo.
     */
    public function confirmar(): void
    {
        // R1: también al guardar
        app(CentroDeUsuario::class)->exigirActuacion(Auth::user());

        // Verificar que el destino es válido (seguridad en servidor)
        $destinosValidos = $this->opcionesDestino->pluck('id')->toArray();
        if (! in_array($this->destinoId, $destinosValidos, true)) {
            $this->addError('destino', 'El destino seleccionado no es válido.');

            return;
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->findOrFail($this->historiaId);
        $profesionalId = Auth::user()?->profesional_id;
        $userId = Auth::id();

        if ($this->tipoDestino === 'sesion_actividad') {
            $this->confirmarSesion($historia, $profesionalId);

            return;
        }

        DB::transaction(function () use ($historia, $profesionalId, $userId) {
            $hayPlaza = $this->hayPlazaDisponible;
            $estado = $hayPlaza ? 'asignada' : 'en_lista_espera';

            $prescripcion = Prescripcion::create([
                'profesional_id' => $profesionalId,
                'ciudadano_id' => $historia->ciudadano_id,
                'prestacion_id' => $this->prestacionId,
                'plan_intervencion_id' => null,
                'compromiso_id' => $this->compromisoId ?: null,
                'tipo_destino' => $this->tipoDestino,
                'destino_id' => $this->destinoId,
                'estado' => $estado,
                'fecha_prescripcion' => today()->toDateString(),
                'notas' => $this->notas ?: null,
            ]);

            // Con plaza y prestación, la persona la tiene activa desde ya (fase 3 bis)
            if ($estado === 'asignada') {
                app(PrescripcionService::class)->plazaAsignada($prescripcion);
            }

            if ($estado === 'en_lista_espera') {
                $posicion = ListaEspera::where(
                    fn ($q) => $this->tipoDestino === 'coleccion_plazas'
                        ? $q->where('coleccion_plazas_id', $this->destinoId)
                        : $q->where('red_id', $this->destinoId)
                )->max('posicion') ?? 0;

                ListaEspera::create([
                    'prescripcion_id' => $prescripcion->id,
                    $this->tipoDestino === 'coleccion_plazas' ? 'coleccion_plazas_id' : 'red_id' => $this->destinoId,
                    'profesional_alerta_id' => $profesionalId,
                    'estado' => 'activa',
                    'posicion' => $posicion + 1,
                    'fecha_entrada' => now(),
                ]);
            }

            Apunte::create([
                'historia_id' => $historia->id,
                'autor_id' => $userId,
                'fecha' => today()->toDateString(),
                'tipo' => TipoApunte::Derivacion,
                'contenido' => 'Prescripción de recurso registrada.',
                'visibilidad' => VisibilidadApunte::Profesionales,
                'apuntable_type' => Prescripcion::class,
                'apuntable_id' => $prescripcion->id,
            ]);
        });

        $this->abierto = false;
        $this->dispatch('prescripcion-creada');
    }

    /**
     * Prescripción a una sesión de actividad: la crea PrescripcionService con su inscripción
     * por vía de prescripción, y deja el apunte. Sin plaza, o sin el consentimiento para la
     * inscripción en el centro, no se crea nada y se muestra el motivo.
     *
     * @param HistoriaSocial $historia
     * @param int|null $profesionalId
     *
     * @return void
     */
    private function confirmarSesion(HistoriaSocial $historia, ?int $profesionalId): void
    {
        $this->resetErrorBag('destino');

        try {
            DB::transaction(function () use ($historia, $profesionalId): void {
                $prescripcion = app(PrescripcionService::class)->crear([
                    'profesional_id' => $profesionalId,
                    'ciudadano_id' => $historia->ciudadano_id,
                    'prestacion_id' => $this->prestacionId,
                    'plan_intervencion_id' => null,
                    'compromiso_id' => $this->compromisoId ?: null,
                    'tipo_destino' => 'sesion_actividad',
                    'destino_id' => $this->destinoId,
                    'fecha_prescripcion' => today()->toDateString(),
                    'notas' => $this->notas ?: null,
                ], Auth::user(), $this->inscribirEnCentro);

                Apunte::create([
                    'historia_id' => $historia->id,
                    'autor_id' => Auth::id(),
                    'fecha' => today()->toDateString(),
                    'tipo' => TipoApunte::Derivacion,
                    'contenido' => 'Prescripción de sesión de actividad registrada.',
                    'visibilidad' => VisibilidadApunte::Profesionales,
                    'apuntable_type' => Prescripcion::class,
                    'apuntable_id' => $prescripcion->id,
                ]);
            });
        } catch (InscripcionCentroRequerida $e) {
            $this->addError('destino', $e->getMessage().' Marca la casilla para inscribirla.');

            return;
        } catch (DomainException $e) {
            $this->addError('destino', $e->getMessage());

            return;
        }

        $this->abierto = false;
        $this->dispatch('prescripcion-creada');
    }

    /**
     * Sesiones programadas futuras de una actividad con plaza de prescripción libre.
     *
     * @param Actividad $actividad
     *
     * @return Collection<int, SesionActividad>
     */
    private function sesionesPrescribibles(Actividad $actividad): Collection
    {
        return $actividad->sesiones()
            ->where('estado', 'programada')
            ->whereDate('fecha', '>=', today())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (SesionActividad $s) => $s->admiteInscripciones() && $s->plazasLibres(ViaInscripcion::Prescripcion) !== 0)
            ->values()
            ->toBase();
    }

    /**
     * Cancela y cierra el modal sin guardar cambios.
     */
    public function cancelar(): void
    {
        $this->abierto = false;
        $this->reset(['paso', 'tipoRecurso', 'destinoId', 'compromisoId', 'notas', 'actividadId', 'inscribirEnCentro']);
        $this->paso = 1;
    }

    // -------------------------------------------------------------------------
    // Utilidades privadas
    // -------------------------------------------------------------------------

    /**
     * Calcula la distancia en kilómetros entre dos puntos geográficos usando la fórmula haversine.
     *
     * @param float $lat1 Latitud del punto de referencia (ciudadano).
     * @param float $lng1 Longitud del punto de referencia.
     * @param float $lat2 Latitud del destino (centro).
     * @param float $lng2 Longitud del destino.
     *
     * @return float Distancia en kilómetros.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * asin(sqrt($a));
    }

    /**
     * Ciudadano titular de la Historia Social, sin scope de ámbito.
     */
    private function ciudadano(): ?Ciudadano
    {
        $historia = HistoriaSocial::withoutGlobalScopes()->find($this->historiaId);

        return $historia ? Ciudadano::find($historia->ciudadano_id) : null;
    }

    /**
     * Renderiza el modal de prescripción de recurso.
     */
    public function render(): View
    {
        return view('intervencion::livewire.prescribir-recurso-modal');
    }
}
