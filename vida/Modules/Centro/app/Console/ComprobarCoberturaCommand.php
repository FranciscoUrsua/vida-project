<?php

namespace Modules\Centro\Console;

use Illuminate\Console\Command;
use Modules\Centro\Services\Asignacion\ResolucionCentroService;

/**
 * Lista las secciones censales activas que no cubre ningún centro de un tipo
 * con adscripción por domicilio. Los huecos se ven al configurar, no al dar de
 * alta a una persona (docs/modulo-asignacion.md §3.3). No bloquea nada.
 */
class ComprobarCoberturaCommand extends Command
{
    /** @var string */
    protected $signature = 'centros:comprobar-cobertura {tipo_centro : Clave del catálogo centro.tipo, ej: css_general}';

    /** @var string */
    protected $description = 'Lista las secciones censales sin centro del tipo indicado';

    /**
     * Ejecuta la comprobación y muestra los huecos.
     *
     * @param ResolucionCentroService $resolucion
     * @return int
     */
    public function handle(ResolucionCentroService $resolucion): int
    {
        $huecos = $resolucion->seccionesSinCobertura($this->argument('tipo_centro'));

        if ($huecos->isEmpty()) {
            $this->info('Todas las secciones censales activas tienen centro.');

            return self::SUCCESS;
        }

        $this->warn("{$huecos->count()} secciones censales sin centro:");
        $this->table(['Sección', 'Distrito', 'Barrio'], $huecos->load(['distrito', 'barrio'])->map(fn ($s) => [
            $s->codigo_ine,
            $s->distrito?->nombre,
            $s->barrio?->nombre,
        ])->all());

        return self::SUCCESS;
    }
}
