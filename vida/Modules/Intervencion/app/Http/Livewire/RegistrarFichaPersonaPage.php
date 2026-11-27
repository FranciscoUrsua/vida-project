<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AccesoExpediente;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\FichaPersonaService;

/**
 * Registro de una ficha de la persona («Datos sociodemográficos» y cualquier otro tipo de uso
 * persona), desde la tarjeta de la ficha del ciudadano (bloque D de
 * docs/instrucciones-cli/2026-11-ficha-sociodemografica.md).
 *
 * Tres casos al abrir:
 * - **Registrar**: la persona no tiene ninguna ficha de ese tipo; el formulario sale vacío.
 * - **Actualizar**: hay una situación vigente; el formulario sale pre-rellenado con ella y al
 *   guardar se crea una ficha nueva (pasado inmutable, SD-8). Los valores de catálogo que ya no
 *   se pueden elegir se dejan vacíos y se avisa arriba.
 * - **Continuar el borrador**: la ficha más reciente de ese tipo es un borrador; se retoma
 *   (con el schema con el que se creó) en lugar de abrir otro, para no acumular borradores.
 *
 * Todo es voluntario y cada campo admite «No desea contestar» (SD-9). Autoriza con
 * `FichaPolicy::crearDePersona` (permiso, ámbito y personas protegidas) y exige poder actuar en
 * el centro (R1); `FichaPersonaService` lo vuelve a comprobar al guardar.
 *
 * @property-read Ciudadano $ciudadano
 * @property-read TipoFicha $tipoFicha
 * @property-read list<array<string, mixed>> $campos
 */
#[Layout('layouts.operativo')]
class RegistrarFichaPersonaPage extends Component
{
    /** Persona de la ficha. */
    #[Locked]
    public int $ciudadanoId;

    /** Tipo de ficha de uso persona. */
    #[Locked]
    public int $tipoFichaId;

    /** Borrador que se retoma, si lo hay. */
    #[Locked]
    public ?int $borradorId = null;

    /** Si se parte de una situación vigente (texto «Actualizar»). */
    #[Locked]
    public bool $esActualizacion = false;

    /**
     * Campos de catálogo vaciados en el pre-relleno porque su valor ya no se puede elegir (SD-8).
     *
     * @var list<array{campo_id: string, etiqueta: string, valor_anterior: string}>
     */
    #[Locked]
    public array $avisos = [];

    /** @var array<string, mixed> Valores por id de campo. */
    public array $datos = [];

    /** @var array<string, bool> «No desea contestar» por id de campo. */
    public array $noContesta = [];

    /** Notas libres. */
    public string $notas = '';

    /**
     * Autoriza, comprueba el centro y prepara el formulario (vacío, pre-rellenado o del borrador).
     *
     * @param int $ciudadano Persona (parámetro de ruta).
     * @param TipoFicha $tipoFicha Tipo de ficha (parámetro de ruta).
     *
     * @return void
     */
    public function mount(int $ciudadano, TipoFicha $tipoFicha): void
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        // Policy de la persona (ámbito, colectivo protegido) y apertura auditada
        $persona = app(AccesoExpediente::class)->ciudadano($usuario, $ciudadano);

        abort_unless($tipoFicha->esDePersona() && $tipoFicha->activo, 404);
        Gate::authorize('crearDePersona', [Ficha::class, $persona]);

        // R1: registrar es una actuación; sin centro no se abre el formulario
        app(CentroDeUsuario::class)->exigirActuacionAlAbrir($usuario);

        $this->ciudadanoId = $persona->id;
        $this->tipoFichaId = $tipoFicha->id;

        $this->prepararFormulario($tipoFicha);
    }

    /**
     * Persona de la ficha, autorizada en cada petición.
     *
     * @return Ciudadano
     */
    #[Computed]
    public function ciudadano(): Ciudadano
    {
        return app(AccesoExpediente::class)->ciudadano(auth()->user(), $this->ciudadanoId, registrar: false);
    }

    /**
     * Tipo de ficha que se registra.
     *
     * @return TipoFicha
     */
    #[Computed]
    public function tipoFicha(): TipoFicha
    {
        return TipoFicha::findOrFail($this->tipoFichaId);
    }

    /**
     * Campos del formulario: los del borrador si se retoma (su schema) o los del tipo vigente.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function campos(): array
    {
        $schema = $this->borradorId !== null
            ? Ficha::findOrFail($this->borradorId)->schema_snapshot
            : $this->tipoFicha->schema;

        return array_values(array_filter($schema['campos'] ?? [], 'is_array'));
    }

    /**
     * Al marcar «No desea contestar», el campo se vacía (SD-9).
     *
     * @param mixed $valor Estado de la casilla.
     * @param string $campoId Campo.
     *
     * @return void
     */
    public function updatedNoContesta(mixed $valor, string $campoId): void
    {
        if ($valor) {
            $this->datos[$campoId] = null;
            $this->resetErrorBag("datos.{$campoId}");
        }
    }

    /**
     * Guarda sin completar: no cuenta como situación vigente hasta completarla.
     *
     * @return mixed
     */
    public function guardarBorrador(): mixed
    {
        return $this->guardar(completar: false);
    }

    /**
     * Guarda la ficha completada: pasa a ser la situación vigente de la persona.
     *
     * @return mixed
     */
    public function completar(): mixed
    {
        return $this->guardar(completar: true);
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
     * Renderiza el formulario.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.registrar-ficha-persona-page');
    }

    /**
     * Registra la ficha (o corrige el borrador) con el servicio y vuelve a la ficha del ciudadano.
     * Los errores de validación se pintan en su campo; RN-15 sale con el aviso de siempre.
     *
     * @param bool $completar Si queda completada.
     *
     * @return mixed
     */
    private function guardar(bool $completar): mixed
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $servicio = app(FichaPersonaService::class);
        $noContesta = array_keys(array_filter($this->noContesta));

        if ($this->borradorId !== null) {
            $servicio->corregir(Ficha::findOrFail($this->borradorId), $usuario, $this->datos, $noContesta, $this->notas, $completar);
        } else {
            $servicio->registrar($this->ciudadano, $this->tipoFicha, $usuario, $this->datos, $noContesta, $this->notas, $completar);
        }

        session()->flash('fichas-persona-ok', $completar ? 'Datos guardados.' : 'Borrador guardado. Puedes completarlo más tarde.');

        return $this->redirect($this->volverUrl(), navigate: true);
    }

    /**
     * Carga el borrador más reciente o pre-rellena desde la situación vigente.
     *
     * @param TipoFicha $tipo Tipo de ficha.
     *
     * @return void
     */
    private function prepararFormulario(TipoFicha $tipo): void
    {
        $ultima = Ficha::dePersona($this->ciudadanoId, $tipo->id)->first();

        if ($ultima !== null && ! $ultima->completada) {
            $this->borradorId = $ultima->id;
            $this->datos = is_array($ultima->datos) ? $ultima->datos : [];
            $this->noContesta = array_fill_keys($ultima->no_contesta ?? [], true);
            $this->notas = (string) $ultima->notas;

            return;
        }

        $vigente = Ficha::vigentePara($this->ciudadanoId, $tipo->id);
        if ($vigente !== null) {
            $this->esActualizacion = true;
            $prerelleno = Ficha::prerellenarConAvisos($vigente, $tipo);
            $this->datos = $prerelleno['datos'];
            $this->avisos = $prerelleno['avisos'];
            // Lo que no quiso contestar se mantiene marcado si el campo sigue en el schema
            $this->noContesta = array_fill_keys(array_intersect($vigente->no_contesta ?? [], array_keys($this->datos)), true);
        }

        foreach ($this->campos as $campo) {
            if (is_string($campo['id'] ?? null) && ! array_key_exists($campo['id'], $this->datos)) {
                $this->datos[$campo['id']] = null;
            }
        }
    }
}
