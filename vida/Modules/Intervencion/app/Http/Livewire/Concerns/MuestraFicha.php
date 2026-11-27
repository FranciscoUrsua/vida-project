<?php

namespace Modules\Intervencion\Http\Livewire\Concerns;

use App\Support\Fichas\ValorCampo;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Presentación en lectura de una ficha de valoración, compartida por la ficha vista desde la
 * Historia Social (`VerFichaPage`) y desde un encargo profesional (`VerFichaEncargoPage`). Las
 * dos usan la misma vista.
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
     * Devuelve los campos del schema_snapshot con su valor correspondiente
     * en datos[], listos para renderizar. `texto` es el valor formateado con
     * `ValorCampo::mostrar()` (cadena vacía si no hay respuesta).
     *
     * @return array<int, array{etiqueta: string, tipo: string, valor: mixed, texto: string, unidad: string|null, obligatorio: bool}>
     */
    public function camposConValor(): array
    {
        $ficha = $this->fichaMostrada();
        $campos = $ficha->schema_snapshot['campos'] ?? [];
        $datos = $ficha->datos ?? [];

        return array_map(function (array $campo) use ($datos): array {
            return [
                'etiqueta' => $campo['etiqueta'] ?? $campo['id'],
                'tipo' => $campo['tipo'] ?? 'texto',
                'valor' => $datos[$campo['id']] ?? null,
                'texto' => ValorCampo::mostrar($campo, $datos[$campo['id']] ?? null),
                'unidad' => $campo['unidad'] ?? null,
                'obligatorio' => $campo['obligatorio'] ?? false,
            ];
        }, $campos);
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
