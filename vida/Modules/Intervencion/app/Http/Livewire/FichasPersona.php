<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AccesoExpediente;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\FichaPersonaService;

/**
 * Tarjetas de las fichas de la persona en la ficha del ciudadano (bloque D de
 * docs/instrucciones-cli/2026-11-ficha-sociodemografica.md): una por cada tipo de ficha de uso
 * persona activo, más los inactivos de los que la persona ya tiene fichas (en lectura).
 *
 * Cada tarjeta muestra la situación vigente (última ficha completada, SD-8) con sus valores,
 * fecha y autor, o «Sin datos registrados»; avisa de un borrador pendiente; ofrece
 * «Registrar», «Actualizar» o «Continuar borrador» a quien puede registrar y actuar en su
 * centro (R1); y despliega el historial de fichas anteriores, que se abren en lectura.
 *
 * Visible con `FichaPolicy::verDePersona` (permiso `ciudadano.leer_fichas_persona`, ámbito y
 * personas protegidas). Las situaciones vigentes que muestra al abrirse quedan en la auditoría
 * como lecturas de la ficha.
 *
 * @property-read Ciudadano $ciudadano
 * @property-read list<array{tipo: TipoFicha, vigente: Ficha|null, borrador: Ficha|null, anteriores: list<Ficha>}> $tarjetas
 * @property-read bool $puedeRegistrar
 */
class FichasPersona extends Component
{
    /** Persona de la ficha. */
    #[Locked]
    public int $ciudadanoId;

    /** @var array<int, bool> Historial desplegado por id de tipo de ficha. */
    public array $historialAbierto = [];

    /**
     * Autoriza y deja en la auditoría la lectura de las situaciones vigentes que se muestran.
     *
     * @param int $ciudadanoId Persona.
     *
     * @return void
     */
    public function mount(int $ciudadanoId): void
    {
        $this->ciudadanoId = $ciudadanoId;
        Gate::authorize('verDePersona', [Ficha::class, $this->ciudadano]);

        /** @var User $usuario */
        $usuario = auth()->user();
        foreach ($this->tarjetas as $tarjeta) {
            if ($tarjeta['vigente'] !== null) {
                app(FichaPersonaService::class)->registrarLectura($tarjeta['vigente'], $usuario);
            }
        }
    }

    /**
     * Persona autorizada en cada petición (la ficha ya anotó la apertura).
     *
     * @return Ciudadano
     */
    #[Computed]
    public function ciudadano(): Ciudadano
    {
        return app(AccesoExpediente::class)->ciudadano(auth()->user(), $this->ciudadanoId, registrar: false);
    }

    /**
     * Una tarjeta por tipo: situación vigente, borrador pendiente y fichas anteriores.
     *
     * @return list<array{tipo: TipoFicha, vigente: Ficha|null, borrador: Ficha|null, anteriores: list<Ficha>}>
     */
    #[Computed]
    public function tarjetas(): array
    {
        $fichas = Ficha::query()->where('ciudadano_id', $this->ciudadanoId)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->with('profesional.profesional')
            ->get()
            ->groupBy('tipo_ficha_id');

        $tipos = TipoFicha::query()->dePersona()
            ->where(fn ($q) => $q->where('activo', true)->orWhereIn('id', $fichas->keys()))
            ->orderBy('nombre')
            ->get();

        $tarjetas = [];
        foreach ($tipos as $tipo) {
            $deTipo = $fichas->get($tipo->id, collect());
            $vigente = $deTipo->first(fn (Ficha $f) => $f->completada);
            $ultima = $deTipo->first();
            $borrador = $ultima !== null && ! $ultima->completada ? $ultima : null;

            $tarjetas[] = [
                'tipo' => $tipo,
                'vigente' => $vigente,
                'borrador' => $borrador,
                'anteriores' => $deTipo->reject(fn (Ficha $f) => $f->is($vigente) || $f->is($borrador))->values()->all(),
            ];
        }

        return $tarjetas;
    }

    /**
     * Si el usuario puede registrar fichas de esta persona ahora: permiso, persona protegida y
     * centro en el que actuar (R1).
     *
     * @return bool
     */
    #[Computed]
    public function puedeRegistrar(): bool
    {
        return Gate::allows('crearDePersona', [Ficha::class, $this->ciudadano])
            && app(CentroDeUsuario::class)->puedeActuar(auth()->user());
    }

    /**
     * Despliega o pliega el historial de un tipo de ficha.
     *
     * @param int $tipoFichaId Tipo de ficha.
     *
     * @return void
     */
    public function alternarHistorial(int $tipoFichaId): void
    {
        $this->historialAbierto[$tipoFichaId] = ! ($this->historialAbierto[$tipoFichaId] ?? false);
    }

    /**
     * Renderiza las tarjetas.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.fichas-persona');
    }
}
