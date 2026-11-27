<?php

namespace App\Livewire\Fichas;

use App\Models\CatalogoSistema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Selector con búsqueda de un campo de ficha de tipo `catalogo` (SD-4).
 *
 * Mismo patrón que el domicilio del callejero (`DomicilioCallejero`): se escribe parte del
 * nombre (o el código) y se elige de la lista. Ofrece solo los valores vigentes del grupo;
 * el valor que guarda es la **clave**, nunca la etiqueta. Los catálogos cortos se listan
 * enteros sin escribir. La búsqueda ignora mayúsculas y acentos y se hace en el servidor
 * sobre la copia local del catálogo.
 *
 * Elegir aquí no valida nada por sí solo: el servicio que guarda la ficha vuelve a comprobar
 * la clave (`ValidadorDatosFicha`), por si la petición llega forjada.
 *
 * @property-read array<string, string> $opciones
 * @property-read list<array{clave: string, etiqueta: string}> $resultados
 * @property-read string|null $etiquetaElegida
 */
class SelectorCatalogo extends Component
{
    /** Hasta cuántos valores se listan enteros, sin tener que escribir. */
    private const LISTA_COMPLETA_HASTA = 30;

    /** Máximo de resultados de una búsqueda. */
    private const MAX_RESULTADOS = 20;

    /** Clave elegida, compartida con el formulario que contiene el campo. */
    #[Modelable]
    public ?string $valor = null;

    /** Grupo de `catalogos_sistema` del campo. */
    #[Locked]
    public string $grupo = '';

    /** Identificador HTML del campo de búsqueda, para la etiqueta del formulario. */
    public string $idCampo = 'catalogo';

    /** Si el formulario está en lectura. */
    public bool $deshabilitado = false;

    /** Texto de búsqueda. */
    public string $busqueda = '';

    /**
     * Valores vigentes del grupo, [clave => etiqueta], en su orden.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function opciones(): array
    {
        return CatalogoSistema::opcionesParaSelect($this->grupo);
    }

    /**
     * Valores que casan con la búsqueda (por etiqueta o clave, sin acentos), o todos si el
     * catálogo es corto y no se ha escrito nada.
     *
     * @return list<array{clave: string, etiqueta: string}>
     */
    #[Computed]
    public function resultados(): array
    {
        $texto = $this->normalizar($this->busqueda);
        if ($texto === '' && count($this->opciones) > self::LISTA_COMPLETA_HASTA) {
            return [];
        }

        $resultados = [];
        foreach ($this->opciones as $clave => $etiqueta) {
            if ($texto === '' || str_contains($this->normalizar($etiqueta), $texto) || $this->normalizar((string) $clave) === $texto) {
                $resultados[] = ['clave' => (string) $clave, 'etiqueta' => $etiqueta];
            }
            if (count($resultados) >= self::MAX_RESULTADOS && $texto !== '') {
                break;
            }
        }

        return $resultados;
    }

    /**
     * Etiqueta del valor elegido (aunque ya no esté vigente, para fichas pre-rellenadas), o null.
     *
     * @return string|null
     */
    #[Computed]
    public function etiquetaElegida(): ?string
    {
        return $this->valor === null || $this->valor === ''
            ? null
            : (CatalogoSistema::etiquetaDe($this->grupo, $this->valor) ?? $this->valor);
    }

    /**
     * Si el catálogo es largo y hay que escribir para ver valores.
     *
     * @return bool
     */
    public function hayQueEscribir(): bool
    {
        return count($this->opciones) > self::LISTA_COMPLETA_HASTA;
    }

    /**
     * Elige un valor de la lista.
     *
     * @param string $clave Clave elegida.
     *
     * @return void
     */
    public function elegir(string $clave): void
    {
        if ($this->deshabilitado || ! array_key_exists($clave, $this->opciones)) {
            return;
        }

        $this->valor = $clave;
        $this->busqueda = '';
    }

    /**
     * Quita el valor elegido para buscar otro.
     *
     * @return void
     */
    public function quitar(): void
    {
        if (! $this->deshabilitado) {
            $this->valor = null;
        }
    }

    /**
     * Pinta el selector.
     *
     * @return View
     */
    public function render(): View
    {
        return view('livewire.fichas.selector-catalogo');
    }

    /**
     * Texto en minúsculas y sin acentos, para comparar.
     *
     * @param string $texto Texto original.
     *
     * @return string
     */
    private function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }
}
