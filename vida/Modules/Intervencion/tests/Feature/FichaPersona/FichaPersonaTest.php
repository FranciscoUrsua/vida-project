<?php

namespace Modules\Intervencion\Tests\Feature\FichaPersona;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\User;
use App\Models\Version;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\FichaPersonaService;
use Modules\Intervencion\Services\Actuaciones\ValoracionService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fichas de la persona (TF-SDE-18 a 25), sus permisos en la policy (TF-SDE-26 y 28, la parte
 * de interfaz va en el bloque D) y el cifrado de las fichas (decisión del 27 nov 2026).
 *
 * Datos: `$sinHistoria`, persona sin Historia Social; `$this->marta`, protegida con Historia
 * en el CSS Norte; `$this->tsNorte` (intervención), `$this->supNorte` (supervisión) y
 * `$cbNorte` (consulta básica), todos del CSS Norte.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque C
 */
class FichaPersonaTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    private TipoFicha $tipo;

    private Ciudadano $sinHistoria;

    private User $cbNorte;

    /**
     * Circuito, persona sin Historia, consulta básica y un tipo de ficha de la persona.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();

        $this->sinHistoria = Ciudadano::factory()->create();
        $this->cbNorte = $this->profesionalCon('cb.norte', 'consulta_basica', $this->cssNorte, 'Auxiliar administrativo/a');

        $this->tipo = TipoFicha::create([
            'nombre' => 'Datos sociodemográficos (prueba)',
            'codigo' => 'sociodemografica_prueba',
            'uso' => 'persona',
            'activo' => true,
            'schema' => ['campos' => [
                ['id' => 'nacionalidad', 'tipo' => 'catalogo', 'catalogo' => 'comun.pais', 'etiqueta' => 'Nacionalidad', 'obligatorio' => false, 'orden' => 1],
                ['id' => 'ingresos', 'tipo' => 'numero', 'etiqueta' => 'Ingresos netos mensuales', 'unidad' => '€', 'obligatorio' => false, 'orden' => 2],
                ['id' => 'fecha_ingresos', 'tipo' => 'fecha', 'etiqueta' => 'Fecha de referencia', 'obligatorio' => false, 'orden' => 3],
            ]],
        ]);
    }

    #[Test]
    public function tf_sde_18_se_registra_la_ficha_de_una_persona_sin_historia_y_sin_apunte(): void
    {
        $apuntesAntes = Apunte::withoutGlobalScopes()->count();

        $ficha = $this->registrar($this->sinHistoria, ['nacionalidad' => 'MAR', 'ingresos' => 640]);

        $ficha->refresh();
        $this->assertSame($this->sinHistoria->id, $ficha->ciudadano_id);
        $this->assertNull($ficha->historia_id);
        $this->assertNull($ficha->valoracion_id);
        $this->assertSame('comun.pais', $ficha->schema_snapshot['campos'][0]['catalogo']);
        $this->assertSame(['nacionalidad' => 'MAR', 'ingresos' => 640, 'fecha_ingresos' => null], $ficha->datos);
        $this->assertTrue($ficha->completada);
        $this->assertSame($this->sinHistoria->id, $ficha->getCiudadanoId());
        $this->assertSame($apuntesAntes, Apunte::withoutGlobalScopes()->count(), 'Una ficha de la persona no genera apunte.');
    }

    #[Test]
    public function tf_sde_19_una_ficha_de_persona_sin_ciudadano_o_con_historia_se_rechaza(): void
    {
        // Sin ciudadano: la guarda del modelo, venga de donde venga
        try {
            Ficha::create(['tipo_ficha_id' => $this->tipo->id, 'schema_snapshot' => $this->tipo->schema, 'datos' => [], 'completada' => true]);
            $this->fail('Una ficha de la persona sin ciudadano no debe guardarse.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('ciudadano', $e->getMessage());
        }

        // Con ciudadano y además colgada de una Historia: el CHECK de la BD
        $ficha = $this->registrar($this->sinHistoria, ['ingresos' => 500]);
        $this->expectException(QueryException::class);
        DB::table('fichas')->where('id', $ficha->id)->update(['historia_id' => $this->historiaAna->id]);
    }

    #[Test]
    public function tf_sde_20_cada_tipo_entra_solo_por_su_servicio(): void
    {
        $valoracion = TipoFicha::create(['nombre' => 'Valoración', 'activo' => true, 'schema' => ['campos' => [
            ['id' => 'obs', 'tipo' => 'texto', 'etiqueta' => 'Obs', 'obligatorio' => false, 'orden' => 1],
        ]]]);

        $this->assertLanza(DomainException::class, fn () => $this->registrar($this->sinHistoria, ['obs' => 'x'], $valoracion));
        $this->assertLanza(DomainException::class, fn () => app(ValoracionService::class)->registrar(
            ContextoActuacion::desdeHistoria($this->historiaAna, $this->tsNorte), $this->tipo, ['ingresos' => 10],
        ));
        // La solicitud de prestación crea su ficha con historia_id: la guarda del modelo la rechaza
        $this->assertLanza(DomainException::class, fn () => Ficha::create([
            'historia_id' => $this->historiaAna->id, 'tipo_ficha_id' => $this->tipo->id, 'schema_snapshot' => $this->tipo->schema, 'datos' => [],
        ]));

        $this->assertSame(0, Ficha::where('tipo_ficha_id', $this->tipo->id)->count());
    }

    #[Test]
    public function tf_sde_21_la_situacion_vigente_es_la_ultima_completada(): void
    {
        $this->travelTo(now()->subDays(10));
        $primera = $this->registrar($this->sinHistoria, ['ingresos' => 500]);
        $this->travelBack();
        $segunda = $this->registrar($this->sinHistoria, ['ingresos' => 700]);
        $this->registrar($this->sinHistoria, ['ingresos' => 900], completar: false);

        $this->assertSame($segunda->id, Ficha::vigentePara($this->sinHistoria->id, $this->tipo->id)?->id);
        $this->assertNotSame($primera->id, $segunda->id);
        $this->assertNull(Ficha::vigentePara($this->ana->id, $this->tipo->id));
        $this->assertCount(3, Ficha::dePersona($this->sinHistoria->id, $this->tipo->id)->get());
        $this->assertSame(['persona'], TipoFicha::dePersona()->pluck('uso')->unique()->values()->all());
    }

    #[Test]
    public function tf_sde_22_actualizar_crea_ficha_nueva_y_corregir_genera_version(): void
    {
        $anterior = $this->registrar($this->sinHistoria, ['ingresos' => 500]);

        // Actualizar la situación: ficha nueva, la anterior intacta
        $nueva = $this->registrar($this->sinHistoria, ['ingresos' => 800]);
        $this->assertNotSame($anterior->id, $nueva->id);
        $this->assertSame(500, $anterior->fresh()->datos['ingresos']);
        $this->assertSame(0, Version::where('versionable_type', Ficha::class)->where('versionable_id', $anterior->id)->count());

        // Corregir un error de grabación: misma ficha, versión anterior guardada
        app(FichaPersonaService::class)->corregir($nueva, $this->tsNorte, ['ingresos' => 850]);
        $this->assertSame(850, $nueva->fresh()->datos['ingresos']);
        $this->assertSame(1, Version::where('versionable_type', Ficha::class)->where('versionable_id', $nueva->id)->count());

        // Un borrador se completa al corregirlo; una completada no vuelve a borrador
        $borrador = $this->registrar($this->sinHistoria, ['ingresos' => 1], completar: false);
        app(FichaPersonaService::class)->corregir($borrador, $this->tsNorte, ['ingresos' => 2], completar: true);
        $this->assertTrue($borrador->fresh()->completada);
        app(FichaPersonaService::class)->corregir($nueva, $this->tsNorte, ['ingresos' => 850], completar: false);
        $this->assertTrue($nueva->fresh()->completada);
    }

    #[Test]
    public function tf_sde_23_no_desea_contestar_vacia_el_campo_y_queda_marcado(): void
    {
        $ficha = $this->registrar($this->sinHistoria, ['nacionalidad' => 'ESP', 'ingresos' => 1200], noContesta: ['ingresos', 'campo_que_no_existe']);

        $ficha->refresh();
        $this->assertSame(['ingresos'], $ficha->no_contesta);
        $this->assertNull($ficha->datos['ingresos'], '«No desea contestar» manda sobre lo escrito.');
        $this->assertSame('ESP', $ficha->datos['nacionalidad']);
        // «No se preguntó» (fecha) se distingue de «no contesta» (ingresos)
        $this->assertNull($ficha->datos['fecha_ingresos']);
        $this->assertNotContains('fecha_ingresos', $ficha->no_contesta);
    }

    #[Test]
    public function tf_sde_24_un_tipo_de_persona_con_un_campo_obligatorio_se_rechaza(): void
    {
        $schema = $this->tipo->schema;
        $schema['campos'][1]['obligatorio'] = true;

        $this->expectException(ValidationException::class);
        $this->tipo->update(['schema' => $schema]);
    }

    #[Test]
    public function tf_sde_24_bis_tipos_y_claves_se_validan_en_el_servidor(): void
    {
        foreach ([['ingresos' => 'mucho'], ['fecha_ingresos' => '2026-02-31'], ['nacionalidad' => 'XYZ']] as $datos) {
            $this->assertLanza(ValidationException::class, fn () => $this->registrar($this->sinHistoria, $datos));
        }
        $this->assertSame(0, Ficha::where('tipo_ficha_id', $this->tipo->id)->count());
    }

    #[Test]
    public function tf_sde_25_registrar_y_leer_quedan_en_la_auditoria_con_el_ciudadano(): void
    {
        $ficha = $this->registrar($this->sinHistoria, ['ingresos' => 500]);
        app(FichaPersonaService::class)->registrarLectura($ficha, $this->supNorte);

        $auditoria = Audit::where('auditable_type', Ficha::class)->where('auditable_id', $ficha->id)->get();
        $this->assertTrue($auditoria->contains(fn (Audit $a) => $a->accion === AccionAuditEnum::Crear && $a->ciudadano_id === $this->sinHistoria->id && $a->user_id === $this->tsNorte->id));
        $this->assertTrue($auditoria->contains(fn (Audit $a) => $a->accion === AccionAuditEnum::Ver && $a->ciudadano_id === $this->sinHistoria->id && $a->user_id === $this->supNorte->id));
    }

    #[Test]
    public function tf_sde_26_intervencion_y_consulta_basica_registran_y_supervision_solo_lee(): void
    {
        foreach ([$this->tsNorte, $this->cbNorte] as $usuario) {
            $this->assertTrue(Gate::forUser($usuario)->allows('crearDePersona', [Ficha::class, $this->sinHistoria]), $usuario->email);
            $this->assertTrue(Gate::forUser($usuario)->allows('verDePersona', [Ficha::class, $this->sinHistoria]), $usuario->email);
        }
        $this->assertTrue(Gate::forUser($this->supNorte)->allows('verDePersona', [Ficha::class, $this->sinHistoria]));
        $this->assertFalse(Gate::forUser($this->supNorte)->allows('crearDePersona', [Ficha::class, $this->sinHistoria]));

        $this->assertLanza(AuthorizationException::class, fn () => $this->registrar($this->sinHistoria, ['ingresos' => 1], autor: $this->supNorte));

        // Y la consulta básica registra de verdad (en la acogida)
        $this->assertSame($this->cbNorte->id, $this->registrar($this->sinHistoria, ['ingresos' => 1], autor: $this->cbNorte)->profesional_id);
    }

    #[Test]
    public function tf_sde_28_consulta_basica_no_ve_ni_registra_fichas_de_una_persona_protegida(): void
    {
        $this->assertFalse(Gate::forUser($this->cbNorte)->allows('verDePersona', [Ficha::class, $this->marta]));
        $this->assertFalse(Gate::forUser($this->cbNorte)->allows('crearDePersona', [Ficha::class, $this->marta]));
        $this->assertLanza(AuthorizationException::class, fn () => $this->registrar($this->marta, ['ingresos' => 1], autor: $this->cbNorte));

        // Su TSR de referencia sí; y la ficha ya registrada tampoco la abre la consulta básica
        $ficha = $this->registrar($this->marta, ['ingresos' => 1], autor: $this->tsNorte);
        $this->assertTrue(Gate::forUser($this->tsNorte)->allows('view', $ficha));
        $this->assertFalse(Gate::forUser($this->cbNorte)->allows('view', $ficha));
        $this->assertFalse(Gate::forUser($this->cbNorte)->allows('corregirDePersona', $ficha));
    }

    #[Test]
    public function tf_sde_cifrado_datos_y_notas_se_guardan_cifrados_tambien_en_versiones_y_auditoria(): void
    {
        $ficha = $this->registrar($this->sinHistoria, ['ingresos' => 987654], notas: 'Vive con su hermana');

        $fila = DB::table('fichas')->where('id', $ficha->id)->first();
        $this->assertStringNotContainsString('987654', (string) $fila->datos);
        $this->assertStringNotContainsString('hermana', (string) $fila->notas);
        $this->assertSame(987654, $ficha->fresh()->datos['ingresos']);
        $this->assertSame('Vive con su hermana', $ficha->fresh()->notas);

        $this->actingAs($this->tsNorte);
        app(FichaPersonaService::class)->corregir($ficha, $this->tsNorte, ['ingresos' => 111], [], 'Vive sola');

        $version = DB::table('versiones')->where('versionable_type', Ficha::class)->where('versionable_id', $ficha->id)->value('datos');
        $this->assertStringNotContainsString('987654', (string) $version);
        $this->assertStringNotContainsString('hermana', (string) $version);

        $edicion = DB::table('audits')->where('auditable_type', Ficha::class)->where('auditable_id', $ficha->id)->where('accion', 'editar')->first();
        $this->assertNotNull($edicion);
        $this->assertStringNotContainsString('987654', (string) $edicion->datos_antes);
        $this->assertStringNotContainsString('hermana', (string) $edicion->datos_antes);
    }

    /**
     * Registra una ficha de la persona con el servicio, como el autor indicado (TSR por defecto).
     *
     * @param Ciudadano $ciudadano Persona.
     * @param array<string, mixed> $datos Valores.
     * @param TipoFicha|null $tipo Tipo (el del setUp si es nulo).
     * @param list<string> $noContesta «No desea contestar».
     * @param bool $completar Si queda completada.
     * @param User|null $autor Autor (TSR del CSS Norte si es nulo).
     * @param string|null $notas Notas.
     *
     * @return Ficha
     */
    private function registrar(Ciudadano $ciudadano, array $datos, ?TipoFicha $tipo = null, array $noContesta = [], bool $completar = true, ?User $autor = null, ?string $notas = null): Ficha
    {
        $autor ??= $this->tsNorte;
        $this->actingAs($autor);

        return app(FichaPersonaService::class)->registrar($ciudadano, $tipo ?? $this->tipo, $autor, $datos, $noContesta, $notas, $completar);
    }

    /**
     * Comprueba que la llamada lanza una excepción de la clase indicada.
     *
     * @param class-string<\Throwable> $clase Excepción esperada.
     * @param callable $llamada Operación.
     *
     * @return void
     */
    private function assertLanza(string $clase, callable $llamada): void
    {
        try {
            $llamada();
            $this->fail("Se esperaba {$clase}.");
        } catch (\Throwable $e) {
            $this->assertInstanceOf($clase, $e, $e->getMessage());
        }
    }
}
