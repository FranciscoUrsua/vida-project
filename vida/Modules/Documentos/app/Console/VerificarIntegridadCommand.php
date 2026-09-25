<?php

namespace Modules\Documentos\Console;

use Illuminate\Console\Command;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\LecturaDocumentoService;

/**
 * Comprueba que cada versión con contenido se descifra y coincide con su hash.
 *
 * La salida solo muestra ids de versión: nunca contenido ni nombres originales.
 */
class VerificarIntegridadCommand extends Command
{
    /** @var string */
    protected $signature = 'documentos:verificar-integridad {--muestra= : Verificar solo N versiones elegidas al azar}';

    /** @var string */
    protected $description = 'Verifica la integridad de los documentos custodiados (descifra y compara el hash)';

    /**
     * Ejecuta la verificación.
     *
     * @param LecturaDocumentoService $lectura Lectura y verificación de versiones.
     *
     * @return int Código de salida: fallo si alguna versión no es íntegra.
     */
    public function handle(LecturaDocumentoService $lectura): int
    {
        $consulta = DocumentoVersion::query()
            ->with('documento.tipo')
            ->whereIn('estado', [EstadoVersion::Vigente->value, EstadoVersion::Sustituida->value]);

        $muestra = $this->option('muestra');
        $consulta = $muestra !== null ? $consulta->inRandomOrder()->limit((int) $muestra) : $consulta->orderBy('id');

        $correctas = 0;
        $fallidas = 0;

        foreach ($consulta->cursor() as $version) {
            if ($lectura->verificarIntegridad($version)) {
                $correctas++;
                $this->line("OK     versión #{$version->id}");
            } else {
                $fallidas++;
                $this->error("FALLO  versión #{$version->id}");
            }
        }

        $this->info("Versiones correctas: {$correctas}. Con fallo: {$fallidas}.");

        return $fallidas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
