<?php

namespace Modules\Intervencion\Http\Livewire\Concerns;

use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Presentación en lectura de una ficha, compartida por la ficha de valoración vista desde la
 * Historia Social (`VerFichaPage`) o desde un encargo profesional (`VerFichaEncargoPage`) y por
 * la ficha de la persona (`VerFichaPersonaPage`). Las tres usan la misma vista.
 *
 * Renderiza los campos con `schema_snapshot`, por lo que es coherente aunque el TipoFicha haya
 * evolucionado desde que se guardó la ficha. La autorización la hace cada página.
 */
trait MuestraFicha
{
    /**
     * Ficha que se muestra (ya autorizada por la página).
     *
     * @return Ficha
     */
    abstract protected function fichaMostrada(): Ficha;

    /**
     * URL de vuelta (Historia Social o encargo).
     *
     * @return string
     */
    abstract public function volverUrl(): string;

    /**
     * Texto del enlace de vuelta.
     *
     * @return string
     */
    abstract public function volverTexto(): string;

    /**
     * Si se ofrece escribir un mensaje con la ficha como contexto.
     *
     * @return bool
     */
    abstract public function conMensaje(): bool;

    /**
     * Campos del schema_snapshot con su valor, listos para renderizar (`Ficha::camposConValor()`).
     *
     * @return list<array{id: string, etiqueta: string, tipo: string, valor: mixed, texto: string, unidad: string|null, obligatorio: bool, no_contesta: bool}>
     */
    public function camposConValor(): array
    {
        return $this->fichaMostrada()->camposConValor();
    }

    /**
     * Rótulo de la cabecera: una ficha de la persona no es una ficha de valoración.
     *
     * @return string
     */
    public function rotuloFicha(): string
    {
        return $this->fichaMostrada()->esDePersona() ? 'Datos de la persona' : 'Ficha de valoración';
    }

    /**
     * Nombre del TipoFicha de la ficha, para la cabecera.
     *
     * @return string
     */
    public function nombreFicha(): string
    {
        return TipoFicha::find($this->fichaMostrada()->tipo_ficha_id)->nombre ?? 'Valoración';
    }
}
