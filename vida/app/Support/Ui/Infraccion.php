<?php

namespace App\Support\Ui;

/**
 * Un hallazgo del auditor de estilos: qué regla se incumple, dónde y con qué.
 *
 * Las infracciones hacen fallar `ui:auditar`; los avisos (`$esAviso`) solo se
 * informan, porque el auditor no puede verificarlos (p. ej. clases dinámicas).
 *
 * @see docs/instrucciones-cli/2026-09-bootstrap-unico.md §3
 */
final class Infraccion
{
    /**
     * @param string $regla R1 a R6.
     * @param string $fichero Ruta relativa a la base del proyecto.
     * @param int $linea Línea (1 = primera); 0 si no aplica.
     * @param string $detalle Qué se ha encontrado (clase, declaración, color…).
     * @param string $ambito Módulo de la vista (`Ciudadania`, `app`…) o `scss`/`catalogo`.
     * @param bool $esAviso Solo informativo: no hace fallar el comando.
     */
    public function __construct(
        public readonly string $regla,
        public readonly string $fichero,
        public readonly int $linea,
        public readonly string $detalle,
        public readonly string $ambito,
        public readonly bool $esAviso = false,
    ) {}

    /**
     * Posición legible (`fichero:línea`) para la salida de consola.
     *
     * @return string
     */
    public function posicion(): string
    {
        return $this->linea > 0 ? "{$this->fichero}:{$this->linea}" : $this->fichero;
    }
}
