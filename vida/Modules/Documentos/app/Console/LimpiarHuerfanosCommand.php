<?php

namespace Modules\Documentos\Console;

use Illuminate\Console\Command;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Models\DocumentoVersion;

/**
 * Detecta huérfanos en los dos sentidos entre el almacén y la BBDD.
 *
 * - Objetos del disco sin versión con contenido, con más de 24 h: se borran solo con --ejecutar.
 * - Versiones vigentes o sustituidas sin objeto en disco: solo se informan; nunca se borra un registro.
 */
class LimpiarHuerfanosCommand extends Command
{
    /** Antigüedad mínima de un objeto huérfano para borrarlo, en segundos (una ingesta en curso no lo es). */
    private const ANTIGUEDAD_MINIMA = 24 * 3600;

    /** @var string */
    protected $signature = 'documentos:limpiar-huerfanos {--ejecutar : Borra los objetos huérfanos (por defecto solo informa)}';

    /** @var string */
    protected $description = 'Informa de objetos sin registro y registros sin objeto en la custodia de documentos';

    /**
     * Ejecuta la comprobación.
     *
     * @param AlmacenDocumentos $almacen Almacén de objetos.
     *
     * @return int
     */
    public function handle(AlmacenDocumentos $almacen): int
    {
        $conContenido = [EstadoVersion::Vigente->value, EstadoVersion::Sustituida->value];

        $clavesValidas = DocumentoVersion::whereIn('estado', $conContenido)
            ->pluck('clave_almacenamiento')
            ->flip();

        $limite = now()->getTimestamp() - self::ANTIGUEDAD_MINIMA;
        $objetosHuerfanos = 0;

        foreach ($almacen->listar() as $clave => $modificado) {
            if ($clavesValidas->has($clave) || $modificado > $limite) {
                continue;
            }

            $objetosHuerfanos++;

            if ($this->option('ejecutar')) {
                $almacen->eliminar($clave);
                $this->line("Borrado objeto huérfano {$clave}");
            } else {
                $this->line("Objeto huérfano {$clave}");
            }
        }

        $registrosSinObjeto = 0;

        foreach (DocumentoVersion::whereIn('estado', $conContenido)->orderBy('id')->cursor() as $version) {
            if (! $almacen->existe($version->clave_almacenamiento, $version->disco)) {
                $registrosSinObjeto++;
                $this->warn("Versión #{$version->id} sin objeto en disco");
            }
        }

        $this->info("Objetos huérfanos: {$objetosHuerfanos}. Versiones sin objeto: {$registrosSinObjeto}.");

        return self::SUCCESS;
    }
}
