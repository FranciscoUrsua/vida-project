<?php

namespace App\Support\Ui;

/**
 * Código PHP que devuelve clases CSS para pintarlas en una vista.
 *
 * `ui:auditar` no puede leer qué clase sale de una tabla o de un `match`, así
 * que toda clase que se decide en PHP sale de una fuente que declara, en
 * `clasesCss()`, todas las que puede devolver. El auditor las comprueba contra
 * el CSS compilado del bundle operativo (regla R1), y en las vistas solo admite
 * interpolaciones de literales o de llamadas a métodos `clases…()`.
 *
 * @see docs/instrucciones-cli/2026-09-bootstrap-unico.md
 */
interface FuenteClasesCss
{
    /**
     * Todas las clases que esta fuente puede devolver.
     *
     * @return list<string>
     */
    public static function clasesCss(): array;
}
