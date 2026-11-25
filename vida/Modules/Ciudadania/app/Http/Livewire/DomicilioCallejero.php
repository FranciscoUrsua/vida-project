<?php

namespace Modules\Ciudadania\Http\Livewire;

use App\Models\Callejero\CallejeroPortal;
use App\Services\Geocodificacion\Callejero\SeleccionDomicilio;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Campo de domicilio con autocompletado sobre el callejero oficial de Madrid.
 *
 * Se elige la vía (desde tres letras), después el número, y se escriben aparte portal,
 * escalera, piso y puerta. «Escribir la dirección a mano» deja el texto libre para
 * quien no tiene un domicilio del callejero (sin hogar, fuera de Madrid…). Lo usan el
 * alta de la persona y la edición de su ficha, enlazado con `wire:model` al estado del
 * domicilio (ver SeleccionDomicilio). No envía nada fuera de VIDA: busca en la copia
 * local del callejero.
 *
 * @property-read list<array{cod_via: string, etiqueta: string, distritos: string}> $vias
 * @property-read list<array{id: int, etiqueta: string, tipo: string|null}> $numeros
 * @property-read string|null $elegido
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md (paso 3)
 */
class DomicilioCallejero extends Component
{
    /** @var array<string, mixed> Estado del domicilio, compartido con el formulario que lo contiene. */
    #[Modelable]
    public array $domicilio = [];

    /** Identificador HTML del primer campo, para la etiqueta del formulario. */
    public string $idCampo = 'domicilio';

    /** Texto de búsqueda de la vía. */
    public string $busquedaVia = '';

    /** Vía elegida (código del callejero). */
    public ?string $codVia = null;

    /** Nombre de la vía elegida, para mostrarla. */
    public string $etiquetaVia = '';

    /** Número tecleado para filtrar los de la vía. */
    public string $busquedaNumero = '';

    /** Si hay callejero cargado; sin él solo se escribe a mano. */
    public bool $hayCallejero = true;

    /**
     * Completa el estado y, si ya hay un número elegido, recupera su vía.
     *
     * @param SeleccionDomicilio $seleccion
     *
     * @return void
     */
    public function mount(SeleccionDomicilio $seleccion): void
    {
        $this->hayCallejero = $seleccion->hayCallejero();
        $this->domicilio = array_merge($seleccion->vacio(), $this->domicilio);

        if (! $this->hayCallejero) {
            $this->domicilio['manual'] = true;
        }

        $portal = ! empty($this->domicilio['portal_id']) ? CallejeroPortal::find((int) $this->domicilio['portal_id']) : null;

        if ($portal !== null) {
            $this->codVia = $portal->cod_via;
            $this->etiquetaVia = $seleccion->etiquetaVia($portal->clase_via, $portal->particula_via, $portal->nombre_via_mostrar);
        }
    }

    /**
     * Vías que casan con lo escrito, mientras no se haya elegido una.
     *
     * @return list<array{cod_via: string, etiqueta: string, distritos: string}>
     */
    #[Computed]
    public function vias(): array
    {
        return $this->codVia === null ? app(SeleccionDomicilio::class)->buscarVias($this->busquedaVia) : [];
    }

    /**
     * Números de la vía elegida, mientras no se haya elegido uno.
     *
     * @return list<array{id: int, etiqueta: string, tipo: string|null}>
     */
    #[Computed]
    public function numeros(): array
    {
        return $this->codVia !== null && empty($this->domicilio['portal_id'])
            ? app(SeleccionDomicilio::class)->numerosDeVia($this->codVia, $this->busquedaNumero)
            : [];
    }

    /**
     * Número elegido, para mostrarlo («Calle Mayor 15 · 28013»), o null.
     *
     * @return string|null
     */
    #[Computed]
    public function elegido(): ?string
    {
        $portal = ! empty($this->domicilio['portal_id']) ? CallejeroPortal::find((int) $this->domicilio['portal_id']) : null;

        if ($portal === null) {
            return null;
        }

        return trim("{$this->etiquetaVia} {$portal->numero} ".($portal->calificador ?? ''))
            .($portal->codigo_postal ? " · {$portal->codigo_postal}" : '');
    }

    /**
     * Elige la vía y pasa a los números.
     *
     * @param string $codVia
     *
     * @return void
     */
    public function elegirVia(string $codVia): void
    {
        $portal = CallejeroPortal::where('cod_via', $codVia)->first();

        if ($portal === null) {
            return;
        }

        $this->codVia = $codVia;
        $this->etiquetaVia = app(SeleccionDomicilio::class)->etiquetaVia($portal->clase_via, $portal->particula_via, $portal->nombre_via_mostrar);
        $this->busquedaNumero = '';
        $this->domicilio['portal_id'] = null;
    }

    /**
     * Elige el número; tiene que ser de la vía elegida.
     *
     * @param int $portalId
     *
     * @return void
     */
    public function elegirNumero(int $portalId): void
    {
        if ($this->codVia === null || ! CallejeroPortal::whereKey($portalId)->where('cod_via', $this->codVia)->exists()) {
            return;
        }

        $this->domicilio['portal_id'] = $portalId;
        $this->domicilio['manual'] = false;
    }

    /**
     * Vuelve a buscar la vía.
     *
     * @return void
     */
    public function cambiarVia(): void
    {
        $this->codVia = null;
        $this->etiquetaVia = '';
        $this->busquedaNumero = '';
        $this->domicilio['portal_id'] = null;
    }

    /**
     * Vuelve a elegir el número de la misma vía.
     *
     * @return void
     */
    public function cambiarNumero(): void
    {
        $this->domicilio['portal_id'] = null;
    }

    /**
     * Pasa al texto libre, con lo elegido como punto de partida si lo había.
     *
     * @return void
     */
    public function escribirAMano(): void
    {
        if (! empty($this->domicilio['portal_id']) && trim((string) $this->domicilio['texto']) === '') {
            $this->domicilio['texto'] = (string) (app(SeleccionDomicilio::class)->atributos($this->domicilio)['direccion_texto'] ?? '');
        }

        $this->domicilio['manual'] = true;
        $this->domicilio['portal_id'] = null;
        $this->cambiarVia();
    }

    /**
     * Vuelve al autocompletado del callejero.
     *
     * @return void
     */
    public function buscarEnCallejero(): void
    {
        if ($this->hayCallejero) {
            $this->domicilio['manual'] = false;
        }
    }

    /**
     * Pinta el campo.
     *
     * @return View
     */
    public function render(): View
    {
        return view('ciudadania::livewire.domicilio-callejero');
    }
}
