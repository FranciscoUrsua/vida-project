<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Documentos\Http\Livewire\DocumentosCiudadano;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Carpeta de documentos con varios roles (R-08 de la ronda 1 de staging, TF-STG-23 y 24): la
 * restricción de «documentos propios del día» solo se aplica a quien no ve la carpeta completa
 * por ningún otro rol.
 */
class CarpetaVariosRolesTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    /** Mensaje de la restricción de consulta básica. */
    private const SOLO_HOY = 'Solo ves los documentos que has subido hoy';

    /**
     * Prepara la custodia con fecha fija.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 10:00:00');
        $this->prepararDocumentos();
    }

    /**
     * Usuario con los roles dados adscrito a la UO de los documentos.
     *
     * @param list<string> $roles
     *
     * @return User
     */
    private function usuarioCon(array $roles): User
    {
        $usuario = User::factory()->create();
        $usuario->syncRoles($roles);
        UsuarioUo::create(['usuario_id' => $usuario->id, 'unidad_organizativa_id' => $this->uo->id, 'tipo_vinculo' => 'interno', 'fecha_inicio' => '2020-01-01']);

        return $usuario;
    }

    /** TF-STG-23 — La TS (solo intervención) ve la carpeta completa, sin el aviso de documentos del día. [negativo] */
    #[Test]
    public function tf_stg_23_ts_ve_la_carpeta_completa(): void
    {
        $otraTs = $this->usuarioCon(['intervencion']);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $antiguo = $this->alta([$this->ana], datos: ['usuario' => $otraTs]);
        Carbon::setTestNow('2026-11-02 10:00:00');
        $propio = $this->alta([$this->ana], datos: ['usuario' => $this->profesional]);

        $tarjeta = Livewire::actingAs($this->profesional)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id]);

        $this->assertEqualsCanonicalizing([$antiguo->id, $propio->id], $tarjeta->instance()->documentos->pluck('id')->all());
        $tarjeta->assertDontSee(self::SOLO_HOY);
    }

    /** TF-STG-24 — Intervención + consulta básica: carpeta completa; consulta básica sola, solo los suyos del día. */
    #[Test]
    public function tf_stg_24_varios_roles(): void
    {
        $mixta = $this->usuarioCon(['intervencion', 'consulta_basica']);
        $ventanilla = $this->usuarioCon(['consulta_basica']);

        Carbon::setTestNow('2026-10-20 10:00:00');
        $antiguo = $this->alta([$this->ana], datos: ['usuario' => $this->profesional]);
        Carbon::setTestNow('2026-11-02 10:00:00');
        $deVentanilla = $this->alta([$this->ana], $this->tipoConConservacion, datos: ['usuario' => $ventanilla]);

        $tarjeta = Livewire::actingAs($mixta)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id]);
        $this->assertEqualsCanonicalizing([$antiguo->id, $deVentanilla->id], $tarjeta->instance()->documentos->pluck('id')->all());
        $tarjeta->assertDontSee(self::SOLO_HOY);

        // [negativo] Consulta básica sola: solo lo suyo de hoy, con el aviso
        $soloVentanilla = Livewire::actingAs($ventanilla)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id]);
        $this->assertSame([$deVentanilla->id], $soloVentanilla->instance()->documentos->pluck('id')->all());
        $soloVentanilla->assertSee(self::SOLO_HOY);
    }
}
