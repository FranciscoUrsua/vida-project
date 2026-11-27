<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\User;
use App\Services\AccesoExpediente;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Intervencion\Http\Livewire\Concerns\MuestraFicha;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Services\Actuaciones\FichaPersonaService;

/**
 * Pantalla de solo lectura de una ficha de la persona (vigente, anterior o borrador), desde el
 * historial de la tarjeta de la ficha del ciudadano (bloque D de la ficha sociodemográfica).
 *
 * Una ficha que no es de la persona de la ruta responde 404. Autoriza con
 * `FichaPolicy::view` (vía persona) y deja la lectura en la auditoría.
 *
 * @property Ficha $ficha
 */
#[Layout('layouts.operativo')]
class VerFichaPersonaPage extends Component
{
    use MuestraFicha;

    /** Ficha que se visualiza. */
    #[Locked]
    public Ficha $ficha;

    /** Persona de la ficha, para el enlace de vuelta. */
    #[Locked]
    public int $ciudadanoId;

    /**
     * Autoriza la persona y la ficha y anota la lectura.
     *
     * @param int $ciudadano Persona (parámetro de ruta).
     * @param Ficha $ficha Ficha de la persona.
     *
     * @return void
     */
    public function mount(int $ciudadano, Ficha $ficha): void
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        // Policy de la persona antes de leer nada; la ficha se audita aparte
        $persona = app(AccesoExpediente::class)->ciudadano($usuario, $ciudadano, registrar: false);

        abort_unless($ficha->esDePersona() && (int) $ficha->ciudadano_id === $persona->id, 404);
        Gate::authorize('view', $ficha);

        app(FichaPersonaService::class)->registrarLectura($ficha, $usuario);

        $this->ciudadanoId = $persona->id;
        $this->ficha = $ficha;
    }

    /**
     * Ficha que se muestra.
     *
     * @return Ficha
     */
    protected function fichaMostrada(): Ficha
    {
        return $this->ficha;
    }

    /**
     * URL de vuelta: la ficha del ciudadano.
     *
     * @return string
     */
    public function volverUrl(): string
    {
        return route('ciudadania.ciudadano.ficha', $this->ciudadanoId);
    }

    /**
     * Texto del enlace de vuelta.
     *
     * @return string
     */
    public function volverTexto(): string
    {
        return 'Volver a la ficha de la persona';
    }

    /**
     * Los mensajes sobre fichas van ligados a la Historia Social: aquí no se ofrecen.
     *
     * @return bool
     */
    public function conMensaje(): bool
    {
        return false;
    }

    /**
     * Renderiza la vista de solo lectura (la misma que las fichas de valoración).
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.ver-ficha-page');
    }
}
