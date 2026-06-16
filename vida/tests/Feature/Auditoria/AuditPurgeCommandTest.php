<?php

namespace Tests\Feature\Auditoria;

use App\Models\Audit;
use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Módulo Auditoría §10.5: AuditPurgeCommand.
 *
 * Verifica que el comando de purga elimina solo los registros fuera
 * del período de retención y que es la única vía legítima de DELETE.
 *
 * @see docs/modulo-auditoria.md §10.5
 */
class AuditPurgeCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $profesional;

    private Ciudadano $ciudadano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->profesional = User::factory()->create();
        $this->ciudadano = Ciudadano::factory()->create();
    }

    /**
     * Crea un registro de auditoría con una fecha específica.
     *
     * Se usa DB::table() directamente para controlar el created_at exacto
     * sin que Eloquent lo sobrescriba con la hora actual.
     */
    private function crearAuditConFecha(\DateTimeInterface $fecha): Audit
    {
        $id = DB::table('audits')->insertGetId([
            'user_id' => $this->profesional->id,
            'accion' => 'ver',
            'auditable_type' => Ciudadano::class,
            'auditable_id' => $this->ciudadano->id,
            'ciudadano_id' => $this->ciudadano->id,
            'datos_antes' => null,
            'datos_despues' => null,
            'ip' => null,
            'user_agent' => null,
            'contexto' => null,
            'created_at' => $fecha instanceof Carbon
                ? $fecha->toDateTimeString()
                : $fecha->format('Y-m-d H:i:s'),
        ]);

        return Audit::findOrFail($id);
    }

    /**
     * Configura el período de retención en el catálogo del sistema.
     */
    private function configurarRetencion(int $dias): void
    {
        CatalogoSistema::create([
            'grupo' => 'auditoria',
            'clave' => 'auditoria.retencion_dias',
            'etiqueta' => (string) $dias,
            'orden' => 1,
            'activo' => true,
        ]);
    }

    /**
     * TF-AUD-24 — Elimina registros que superan el período de retención.
     */
    #[Test]
    public function elimina_registros_que_superan_el_periodo_de_retencion(): void
    {
        $this->configurarRetencion(30);

        $antiguo = $this->crearAuditConFecha(now()->subDays(31));
        $reciente = $this->crearAuditConFecha(now()->subDays(5));

        $this->artisan('audit:purge')->assertSuccessful();

        $this->assertDatabaseMissing('audits', ['id' => $antiguo->id]);
        $this->assertDatabaseHas('audits', ['id' => $reciente->id]);
    }

    /**
     * TF-AUD-25 — Preserva registros dentro del período de retención.
     */
    #[Test]
    public function preserva_registros_dentro_del_periodo_de_retencion(): void
    {
        $this->configurarRetencion(30);

        $dentroDelPeriodo = $this->crearAuditConFecha(now()->subDays(29));

        $this->artisan('audit:purge')->assertSuccessful();

        $this->assertDatabaseHas('audits', ['id' => $dentroDelPeriodo->id]);
    }

    /**
     * TF-AUD-26 — La purga es la única vía legítima de DELETE sobre audits.
     *
     * Búsqueda estática: ningún otro fichero del código fuente (excluyendo el propio
     * AuditPurgeCommand y los tests) contiene llamadas directas a Audit::destroy(),
     * instancia->delete() o equivalentes sobre el modelo Audit.
     */
    #[Test]
    public function la_purga_es_la_unica_via_legitima_de_delete_sobre_audits(): void
    {
        $rutaApp = base_path('app');
        $rutaModulos = base_path('Modules');

        $patronesBusqueda = [
            'Audit::destroy(',
            'Audit::truncate(',
        ];

        $archivosVioladores = [];

        foreach ([$rutaApp, $rutaModulos] as $ruta) {
            $archivos = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($ruta, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($archivos as $archivo) {
                if ($archivo->getExtension() !== 'php') {
                    continue;
                }

                $nombreArchivo = $archivo->getPathname();

                // Excluir el propio comando de purga y los tests
                if (str_contains($nombreArchivo, 'AuditPurgeCommand')
                    || str_contains($nombreArchivo, 'Test.php')
                    || str_contains($nombreArchivo, '/tests/')
                ) {
                    continue;
                }

                $contenido = file_get_contents($nombreArchivo);

                foreach ($patronesBusqueda as $patron) {
                    if (str_contains($contenido, $patron)) {
                        $archivosVioladores[] = "{$nombreArchivo}: {$patron}";
                    }
                }
            }
        }

        $this->assertEmpty(
            $archivosVioladores,
            "Llamadas directas a DELETE sobre Audit encontradas fuera de AuditPurgeCommand:\n"
            .implode("\n", $archivosVioladores)
        );
    }
}
