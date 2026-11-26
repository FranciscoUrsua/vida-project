<?php

namespace Modules\Intervencion\Http\Livewire\Concerns;

use Livewire\Attributes\Computed;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Formulario de una ficha de valoración, compartido por la valoración desde la Historia Social
 * (`RegistrarValoracionPage`) y desde un encargo profesional (`ValoracionEncargoPage`, bloque D
 * de docs/instrucciones-cli/2026-11-encargo-actuaciones.md). Las dos usan la misma vista.
 *
 * Carga el schema del TipoFicha elegido y prepara los campos vacíos: no hay pre-relleno con
 * fichas anteriores (decisión del 26 nov; si se añade, desde un encargo solo con lo del
 * profesional en sus encargos, EA-8). Qué fichas se ofrecen lo decide cada página.
 *
 * @property-read TipoFicha|null $tipoFicha
 * @property-read array<int, string> $fichasDisponibles
 */
trait FormularioValoracion
{
    /** @var int|null ID del TipoFicha seleccionado */
    public ?int $tipoFichaId = null;

    /** @var array<string, mixed> Valores del formulario dinámico, indexados por campo id */
    public array $datos = [];

    /** @var string Notas libres del profesional sobre la valoración */
    public string $notas = '';

    /**
     * Fichas que se ofrecen en el selector, indexadas por id.
     *
     * @return array<int, string>
     */
    abstract public function fichasDisponibles(): array;

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
     * TipoFicha actualmente seleccionado, null si no hay selección.
     *
     * @return TipoFicha|null
     */
    #[Computed]
    public function tipoFicha(): ?TipoFicha
    {
        if (! $this->tipoFichaId) {
            return null;
        }

        return TipoFicha::find($this->tipoFichaId);
    }

    /**
     * Cambia la ficha seleccionada y reinicializa el formulario.
     *
     * @param int $id ID del tipo de ficha seleccionado.
     *
     * @return void
     */
    public function seleccionarFicha(int $id): void
    {
        $this->tipoFichaId = $id;
        $this->datos = [];
        $this->notas = '';
        $this->inicializarDatos();
    }

    /**
     * Comprueba que hay una ficha elegida y ofrecida (en el servidor, no solo en el selector) y
     * valida sus campos obligatorios.
     *
     * @param string $mensajeNoDisponible Mensaje si la ficha no se ofrece en esta página.
     *
     * @return bool Si se puede guardar; si no, deja el error en `tipoFichaId`.
     */
    protected function validarFormularioValoracion(string $mensajeNoDisponible): bool
    {
        if (! $this->tipoFichaId) {
            $this->addError('tipoFichaId', 'Selecciona un tipo de ficha.');

            return false;
        }

        if (! array_key_exists((int) $this->tipoFichaId, $this->fichasDisponibles)) {
            $this->addError('tipoFichaId', $mensajeNoDisponible);

            return false;
        }

        $reglas = [];
        foreach ($this->tipoFicha?->schema['campos'] ?? [] as $campo) {
            if ($campo['obligatorio'] ?? false) {
                $reglas["datos.{$campo['id']}"] = ['required'];
            }
        }

        if ($reglas !== []) {
            $this->validate($reglas);
        }

        return true;
    }

    /**
     * Lee `?tipo_ficha=` (Livewire 4 full-page: la query string no llega a mount()) y prepara el formulario.
     *
     * @return void
     */
    protected function iniciarFormularioValoracion(): void
    {
        $tipoFicha = request()->query('tipo_ficha');
        if ($tipoFicha && is_numeric($tipoFicha)) {
            $this->tipoFichaId = (int) $tipoFicha;
        }

        $this->inicializarDatos();
    }

    /**
     * Inicializa $datos con null para cada campo del schema del tipo seleccionado.
     *
     * @return void
     */
    private function inicializarDatos(): void
    {
        foreach ($this->tipoFicha?->schema['campos'] ?? [] as $campo) {
            if (! array_key_exists($campo['id'], $this->datos)) {
                $this->datos[$campo['id']] = null;
            }
        }
    }
}
