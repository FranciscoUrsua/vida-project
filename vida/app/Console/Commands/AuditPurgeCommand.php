<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\CatalogoSistema;
use Illuminate\Console\Command;

/**
 * Purga los registros de auditoría que superan el período de retención.
 *
 * Es la única operación de DELETE legítima sobre la tabla `audits`.
 * El período de retención se lee del catálogo de sistema con clave
 * 'auditoria.retencion_dias'; si no existe, el defecto es 1825 (5 años).
 *
 * Se ejecuta diariamente vía el scheduler de Laravel.
 *
 * @see docs/modulo-auditoria.md §4
 */
class AuditPurgeCommand extends Command
{
    protected $signature = 'audit:purge';

    protected $description = 'Elimina registros de auditoría que superan el período de retención';

    /**
     * Purga la auditoría caducada y devuelve el número eliminado.
     *
     * @return int
     */
    public function handle(): int
    {
        $dias = (int) CatalogoSistema::valor('auditoria.retencion_dias', '1825');
        $corte = now()->subDays($dias);

        // DELETE directo — no pasa por el modelo para no activar el observer
        // ni lanzar la LogicException del método delete() de instancia.
        $eliminados = Audit::where('created_at', '<', $corte)->delete();

        $this->info("Audit purge: {$eliminados} registros eliminados (retención: {$dias} días, corte: {$corte->toDateString()}).");

        return Command::SUCCESS;
    }
}
