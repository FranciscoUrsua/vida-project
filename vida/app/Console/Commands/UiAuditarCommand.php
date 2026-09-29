<?php

namespace App\Console\Commands;

use App\Support\Ui\AuditorUi;
use App\Support\Ui\Infraccion;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Comando que comprueba que las superficies operativa y pública usan solo
 * Bootstrap: sin clases inexistentes, estilos inventados ni clases huérfanas.
 *
 * Es la definición de «terminado» de cualquier tarea que toque Blade o SCSS.
 * Devuelve 1 si hay infracciones (salvo con `--informe`) o si falta el CSS
 * compilado; 0 en otro caso.
 *
 * @see docs/instrucciones-cli/2026-09-bootstrap-unico.md §3
 */
class UiAuditarCommand extends Command
{
    /** @var string */
    protected $signature = 'ui:auditar
        {--modulo= : Solo las vistas de un módulo (o «app» para resources/views); aplica R1 a R3}
        {--informe : Lista las infracciones sin fallar (solo durante la migración)}
        {--generar-catalogo : Imprime en formato PHP las clases propias del SCSS que faltan en el catálogo}';

    /** @var string */
    protected $description = 'Audita que las vistas operativas y públicas usan solo Bootstrap, sin estilos inventados ni huérfanos.';

    /** Títulos de cada regla para la salida. */
    private const REGLAS = [
        'R1' => 'Clase que no existe en el CSS de su bundle',
        'R2' => 'Estilo inline o bloque <style>',
        'R3' => 'Color literal o variable CSS no definida',
        'R4' => 'Clase propia fuera del catálogo o pendiente',
        'R5' => 'Clase del catálogo sin uso',
        'R6' => 'Tailwind en una hoja del ámbito',
        'R7' => 'Clase exigida ausente del CSS compilado',
    ];

    /**
     * Ejecuta la auditoría y muestra el resultado.
     *
     * @return int Código de salida (0 = sin infracciones o modo informe, 1 = infracciones o error)
     */
    public function handle(): int
    {
        $auditor = new AuditorUi(
            (array) config('ui-auditoria'),
            (array) config('ui-catalogo.clases', []),
        );

        try {
            if ($this->option('generar-catalogo')) {
                return $this->generarCatalogo($auditor);
            }

            $modulo = $this->option('modulo');
            $hallazgos = $auditor->auditar(is_string($modulo) && $modulo !== '' ? $modulo : null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $infracciones = array_values(array_filter($hallazgos, fn (Infraccion $h) => ! $h->esAviso));
        $avisos = array_values(array_filter($hallazgos, fn (Infraccion $h) => $h->esAviso));

        $this->mostrarDetalle($infracciones, $avisos);
        $this->mostrarResumen($infracciones, $avisos);

        if ($infracciones === []) {
            $this->info('Sin infracciones.'.($avisos !== [] ? ' '.count($avisos).' avisos.' : ''));

            return self::SUCCESS;
        }

        $this->error(count($infracciones).' infracciones, '.count($avisos).' avisos.');

        if ($this->option('informe')) {
            $this->line('Modo informe: no bloquea.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * Lista las infracciones agrupadas por regla y, después, los avisos.
     *
     * @param list<Infraccion> $infracciones
     * @param list<Infraccion> $avisos
     * @return void
     */
    private function mostrarDetalle(array $infracciones, array $avisos): void
    {
        foreach (self::REGLAS as $regla => $titulo) {
            $deRegla = array_filter($infracciones, fn (Infraccion $h) => $h->regla === $regla);
            if ($deRegla === []) {
                continue;
            }
            $this->newLine();
            $this->line("<fg=red;options=bold>{$regla} · {$titulo} (".count($deRegla).')</>');
            foreach ($deRegla as $h) {
                $this->line("  {$h->posicion()}  {$h->detalle}");
            }
        }

        if ($avisos !== []) {
            $this->newLine();
            $this->line('<fg=yellow;options=bold>Avisos (no bloquean) ('.count($avisos).')</>');
            foreach ($avisos as $h) {
                $this->line("  {$h->regla}  {$h->posicion()}  {$h->detalle}");
            }
        }
        $this->newLine();
    }

    /**
     * Tabla de infracciones por ámbito (módulo, `app`, `scss`, `catalogo`) y regla.
     *
     * @param list<Infraccion> $infracciones
     * @param list<Infraccion> $avisos
     * @return void
     */
    private function mostrarResumen(array $infracciones, array $avisos): void
    {
        if ($infracciones === [] && $avisos === []) {
            return;
        }

        $filas = [];
        foreach ($infracciones as $h) {
            $filas[$h->ambito][$h->regla] = ($filas[$h->ambito][$h->regla] ?? 0) + 1;
        }
        foreach ($avisos as $h) {
            $filas[$h->ambito]['avisos'] = ($filas[$h->ambito]['avisos'] ?? 0) + 1;
        }
        ksort($filas);

        $columnas = [...array_keys(self::REGLAS), 'avisos'];
        $this->table(
            ['Ámbito', ...$columnas],
            array_map(
                fn (string $ambito, array $cuentas) => [$ambito, ...array_map(fn (string $c) => $cuentas[$c] ?? '', $columnas)],
                array_keys($filas),
                $filas,
            ),
        );
    }

    /**
     * Imprime las entradas de catálogo que faltan, como `pendiente`, para
     * pegarlas en config/ui-catalogo.php.
     *
     * @return int
     */
    private function generarCatalogo(AuditorUi $auditor): int
    {
        $catalogo = (array) config('ui-catalogo.clases', []);
        $porFichero = [];
        foreach ($auditor->clasesPropiasDefinidas() as $clase => $origen) {
            if (! isset($catalogo[$clase])) {
                $porFichero[$origen][] = $clase;
            }
        }
        ksort($porFichero);

        foreach ($porFichero as $origen => $clases) {
            $this->line("        // {$origen}");
            foreach ($clases as $clase) {
                $this->line("        '{$clase}' => ['tipo' => null, 'estado' => 'pendiente', 'descripcion' => ''],");
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
