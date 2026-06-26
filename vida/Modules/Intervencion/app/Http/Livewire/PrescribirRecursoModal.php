<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\Red;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Modal de prescripción de recurso en tres pasos montado en CiudadanoPage.
 *
 * Paso 1 — Tipo de recurso (tipo_plaza de las colecciones disponibles).
 * Paso 2 — Selección de destino (ColeccionPlazas o Red).
 * Paso 3 — Confirmación y vínculo opcional con compromiso del plan.
 *
 * @property-read Collection $tiposRecurso
 * @property-read Collection $opcionesDestino
 * @property-read Collection $compromisosSugeridos
 * @property-read bool $hayPlazaDisponible
 */
class PrescribirRecursoModal extends Component
{
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

    /** @var string Tipo de destino: 'coleccion_plazas' | 'red' */
    public string $tipoDestino = 'coleccion_plazas';

    /** @var int|null ID del compromiso del plan al que se vincula opcionalmente */
    public ?int $compromisoId = null;

    /** @var string Notas para el TS del centro receptor */
    public string $notas = '';

    // -------------------------------------------------------------------------
    // Eventos
    // -------------------------------------------------------------------------

    /**
     * Abre el modal y resetea su estado interno.
     */
    #[On('abrir-prescribir-recurso')]
    public function abrir(): void
    {
        $this->reset(['paso', 'tipoRecurso', 'destinoId', 'tipoDestino', 'compromisoId', 'notas']);
        $this->paso = 1;
        $this->tipoDestino = 'coleccion_plazas';
        $this->abierto = true;
        unset($this->opcionesDestino, $this->compromisosSugeridos, $this->hayPlazaDisponible);
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
        return ColeccionPlazas::activas()
            ->whereIn('modo_acceso', ['prescripcion_directa', 'prescripcion_lista_espera'])
            ->distinct()
            ->pluck('tipo_plaza');
    }

    /**
     * Opciones de destino para el paso 2, según heurística del documento funcional §2.
     *
     * 1. Si existen Redes con el tipo de recurso requerido → devuelve Redes.
     * 2. Si no → devuelve ColeccionPlazas individuales.
     * Si criterio_territorial=true y ciudadano tiene dirección → ordena por proximidad (haversine).
     *
     * @return Collection<int, ColeccionPlazas|Red>
     */
    #[Computed]
    public function opcionesDestino(): Collection
    {
        if (! $this->tipoRecurso) {
            return collect();
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

            return $redes;
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
                return $query->get()->sortBy(function (ColeccionPlazas $cp) use ($lat, $lng) {
                    $cLat = $cp->centro?->coordenadas_lat;
                    $cLng = $cp->centro?->coordenadas_lng;

                    if ($cLat === null || $cLng === null || ! $cp->criterio_territorial) {
                        return PHP_INT_MAX;
                    }

                    return $this->haversineKm($lat, $lng, $cLat, $cLng);
                })->values();
            }
        }

        return $query->get();
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
        // Verificar que el destino es válido (seguridad en servidor)
        $destinosValidos = $this->opcionesDestino->pluck('id')->toArray();
        if (! in_array($this->destinoId, $destinosValidos, true)) {
            $this->addError('destino', 'El destino seleccionado no es válido.');

            return;
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->findOrFail($this->historiaId);
        $profesionalId = Auth::user()?->profesional_id;
        $userId = Auth::id();

        DB::transaction(function () use ($historia, $profesionalId, $userId) {
            $hayPlaza = $this->hayPlazaDisponible;
            $estado = $hayPlaza ? 'asignada' : 'en_lista_espera';

            $prescripcion = Prescripcion::create([
                'profesional_id' => $profesionalId,
                'ciudadano_id' => $historia->ciudadano_id,
                'plan_intervencion_id' => null,
                'compromiso_id' => $this->compromisoId ?: null,
                'tipo_destino' => $this->tipoDestino,
                'destino_id' => $this->destinoId,
                'estado' => $estado,
                'fecha_prescripcion' => today()->toDateString(),
                'notas' => $this->notas ?: null,
            ]);

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
     * Cancela y cierra el modal sin guardar cambios.
     */
    public function cancelar(): void
    {
        $this->abierto = false;
        $this->reset(['paso', 'tipoRecurso', 'destinoId', 'compromisoId', 'notas']);
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
