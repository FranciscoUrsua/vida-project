<?php

namespace Modules\Intervencion\Tests\Feature\FichaPersona;

use App\Filament\Resources\TipoFichaResource\Pages\EditTipoFicha;
use App\Livewire\Fichas\SelectorCatalogo;
use App\Models\CatalogoSistema;
use App\Models\User;
use App\Support\Fichas\ValorCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Http\Livewire\VerFichaPage;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\ValoracionService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tipo de campo «catálogo» en las fichas (TF-SDE-09 a 17 y TF-SDE-31).
 *
 * El schema guarda el grupo; la ficha guarda la clave, nunca la etiqueta; el servicio
 * rechaza claves que no son vigentes del grupo aunque la petición llegue forjada; y todas
 * las vistas de fichas guardadas formatean el valor con `ValorCampo::mostrar()`.
 *
 * Usa los catálogos oficiales que deja la migración de datos (`comun.pais`) y un grupo de
 * prueba (`prueba.region`) cuando hace falta un valor no vigente.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque B
 */
class CampoCatalogoTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    private TipoFicha $tipo;

    /**
     * Circuito base y un tipo de ficha de valoración con un campo de país.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();

        $this->tipo = TipoFicha::create([
            'nombre' => 'Origen',
            'activo' => true,
            'schema' => ['campos' => [
                $this->campoPais(),
                ['id' => 'notas_origen', 'tipo' => 'texto', 'etiqueta' => 'Notas', 'obligatorio' => false, 'orden' => 2],
            ]],
        ]);
    }

    #[Test]
    public function tf_sde_09_un_schema_con_un_campo_catalogo_valido_se_guarda(): void
    {
        $this->assertSame('comun.pais', $this->tipo->fresh()->schema['campos'][0]['catalogo']);
        $this->assertContains('catalogo', TipoFicha::TIPOS_CAMPO);
    }

    #[Test]
    public function tf_sde_09_bis_el_backoffice_guarda_un_bloque_catalogo_con_su_grupo(): void
    {
        $admin = User::create(['email' => 'admin.cat@vida360.test', 'password' => 'secreto', 'email_verified_at' => now(), 'primer_acceso' => false]);
        $admin->syncRoles(['adm_sistema']);
        $this->actingAs($admin);

        $componente = Livewire::test(EditTipoFicha::class, ['record' => $this->tipo->getRouteKey()]);
        $bloques = $componente->get('data.schema');
        $bloques[(string) Str::uuid()] = ['type' => 'catalogo', 'data' => [
            'etiqueta' => 'Idioma principal', 'descripcion' => null, 'obligatorio' => false, 'catalogo' => 'comun.idioma',
        ]];

        $componente->set('data.schema', $bloques)->call('save')->assertHasNoErrors();

        $campo = collect($this->tipo->fresh()->schema['campos'])->firstWhere('id', 'idioma_principal');
        $this->assertSame('catalogo', $campo['tipo']);
        $this->assertSame('comun.idioma', $campo['catalogo']);
    }

    #[Test]
    public function tf_sde_10_grupo_inexistente_o_con_menos_de_dos_valores_se_rechaza(): void
    {
        $tiposAntes = TipoFicha::count();
        CatalogoSistema::create(['grupo' => 'prueba.uno', 'clave' => 'A', 'etiqueta' => 'Único']);
        CatalogoSistema::create(['grupo' => 'prueba.uno', 'clave' => 'B', 'etiqueta' => 'Caducado', 'vigente_hasta' => today()->subYear()]);

        foreach (['prueba.no_existe', 'prueba.uno', null] as $grupo) {
            try {
                TipoFicha::create(['nombre' => 'Mala', 'activo' => true, 'schema' => ['campos' => [$this->campoPais(['catalogo' => $grupo])]]]);
                $this->fail('Se esperaba ValidationException con el grupo '.var_export($grupo, true));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('schema.campos.0.catalogo', $e->errors());
            }
        }

        $this->assertSame($tiposAntes, TipoFicha::count(), 'Ningún schema inválido se guarda.');
    }

    #[Test]
    public function tf_sde_11_con_fichas_no_se_puede_cambiar_el_grupo_pero_si_la_etiqueta(): void
    {
        $this->registrar(['pais' => 'ESP']);

        $schema = $this->tipo->schema;
        $schema['campos'][0]['etiqueta'] = 'País de nacimiento';
        $this->tipo->update(['schema' => $schema]);
        $this->assertSame('País de nacimiento', $this->tipo->fresh()->schema['campos'][0]['etiqueta']);

        $schema['campos'][0]['catalogo'] = 'comun.idioma';
        $this->expectException(ValidationException::class);
        $this->tipo->update(['schema' => $schema]);
    }

    #[Test]
    public function tf_sde_12_la_ficha_guarda_la_clave_y_no_la_etiqueta(): void
    {
        $this->actingAs($this->tsNorte);

        Livewire::withQueryParams(['tipo_ficha' => $this->tipo->id])
            ->test(RegistrarValoracionPage::class, ['historia' => $this->historiaAna])
            ->assertSeeLivewire(SelectorCatalogo::class)
            ->set('datos.pais', 'ESP')
            ->call('guardarDefinitivo')
            ->assertHasNoErrors();

        $ficha = Ficha::where('tipo_ficha_id', $this->tipo->id)->sole();
        $this->assertSame('ESP', $ficha->datos['pais']);
        $this->assertSame('comun.pais', $ficha->schema_snapshot['campos'][0]['catalogo']);
    }

    #[Test]
    public function tf_sde_13_una_clave_de_otro_grupo_inexistente_o_no_vigente_se_rechaza(): void
    {
        $this->grupoRegionDePrueba();
        $tipoRegion = TipoFicha::create(['nombre' => 'Región', 'activo' => true, 'schema' => ['campos' => [
            $this->campoPais(['id' => 'region', 'etiqueta' => 'Región', 'catalogo' => 'prueba.region']),
        ]]]);

        // '01' existe, pero en comun.nivel_estudios; 'ZZZ' no existe; 'VIE' ya no está vigente
        foreach ([[$this->tipo, 'pais', '01'], [$this->tipo, 'pais', 'ZZZ'], [$tipoRegion, 'region', 'VIE']] as [$tipo, $campo, $clave]) {
            try {
                $this->registrar([$campo => $clave], $tipo);
                $this->fail("Se esperaba ValidationException con la clave {$clave}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey("datos.{$campo}", $e->errors());
            }
        }

        // Petición forjada desde la página: el selector no la ofrece, pero se envía igualmente
        $this->actingAs($this->tsNorte);
        Livewire::withQueryParams(['tipo_ficha' => $this->tipo->id])
            ->test(RegistrarValoracionPage::class, ['historia' => $this->historiaAna])
            ->set('datos.pais', 'España')
            ->call('guardarDefinitivo')
            ->assertHasErrors('datos.pais');

        $this->assertSame(0, Ficha::count(), 'Ninguna clave inválida deja ficha.');
    }

    #[Test]
    public function tf_sde_14_valor_campo_muestra_la_etiqueta_actual_aunque_no_este_vigente(): void
    {
        $this->grupoRegionDePrueba();
        $campo = ['tipo' => 'catalogo', 'catalogo' => 'prueba.region'];

        $this->assertSame('España', ValorCampo::mostrar($this->campoPais(), 'ESP'));
        $this->assertSame('Vieja región', ValorCampo::mostrar($campo, 'VIE'));
        // Clave que ya no existe: se muestra la clave, sin error
        $this->assertSame('XYZ', ValorCampo::mostrar($campo, 'XYZ'));
        $this->assertSame('', ValorCampo::mostrar($campo, null));
    }

    #[Test]
    public function tf_sde_15_los_tipos_antiguos_se_muestran_igual(): void
    {
        $this->assertSame('texto libre', ValorCampo::mostrar(['tipo' => 'texto'], 'texto libre'));
        $this->assertSame('42', ValorCampo::mostrar(['tipo' => 'numero', 'unidad' => 'm²'], 42));
        $this->assertSame('Alquiler', ValorCampo::mostrar(['tipo' => 'select'], 'Alquiler'));
        $this->assertSame('Sí', ValorCampo::mostrar(['tipo' => 'booleano'], '1'));
        $this->assertSame('No', ValorCampo::mostrar(['tipo' => 'booleano'], '0'));
        $this->assertSame('No', ValorCampo::mostrar(['tipo' => 'booleano'], false));
        // Mismo formato que tenían las vistas (`translatedFormat('j M Y')`)
        $fechaPantalla = Carbon::parse('2026-03-05')->translatedFormat('j M Y');
        $this->assertSame($fechaPantalla, ValorCampo::mostrar(['tipo' => 'fecha'], '2026-03-05'));
        $this->assertSame('05/03/2026', ValorCampo::mostrar(['tipo' => 'fecha'], '2026-03-05', 'd/m/Y'));
        $this->assertSame('7', ValorCampo::mostrar(['tipo' => 'escala'], '7'));

        // Y la ficha guardada se ve con su etiqueta, su unidad y «Sin respuesta» donde no hay dato
        $tipo = TipoFicha::create(['nombre' => 'Vivienda', 'activo' => true, 'schema' => ['campos' => [
            ['id' => 'metros', 'tipo' => 'numero', 'etiqueta' => 'Metros', 'unidad' => 'm²', 'obligatorio' => false, 'orden' => 1],
            ['id' => 'ascensor', 'tipo' => 'booleano', 'etiqueta' => 'Ascensor', 'obligatorio' => false, 'orden' => 2],
            ['id' => 'alta', 'tipo' => 'fecha', 'etiqueta' => 'Alta', 'obligatorio' => false, 'orden' => 3],
            ['id' => 'obs', 'tipo' => 'texto', 'etiqueta' => 'Observaciones', 'obligatorio' => false, 'orden' => 4],
            $this->campoPais(['orden' => 5]),
        ]]]);
        $ficha = $this->registrar(['metros' => 60, 'ascensor' => '0', 'alta' => '2026-03-05', 'obs' => null, 'pais' => 'MAR'], $tipo);

        $this->actingAs($this->tsNorte);
        Livewire::test(VerFichaPage::class, ['historia' => $this->historiaAna, 'ficha' => $ficha])
            ->assertSeeInOrder(['Metros', '60', 'm²', 'Ascensor', 'No', 'Alta', $fechaPantalla, 'Observaciones', 'Sin respuesta', 'País', 'Marruecos'])
            ->assertDontSee('>MAR<', false);
    }

    #[Test]
    public function tf_sde_16_el_prerrelleno_vacia_las_claves_no_vigentes_y_avisa(): void
    {
        $this->grupoRegionDePrueba();
        $tipo = TipoFicha::create(['nombre' => 'Residencia', 'activo' => true, 'schema' => ['campos' => [
            $this->campoPais(),
            $this->campoPais(['id' => 'region', 'etiqueta' => 'Región', 'catalogo' => 'prueba.region', 'orden' => 2]),
        ]]]);
        // La ficha anterior se guardó cuando la región aún era vigente
        $anterior = Ficha::create([
            'historia_id' => $this->historiaAna->id, 'tipo_ficha_id' => $tipo->id, 'schema_snapshot' => $tipo->schema,
            'profesional_id' => $this->tsNorte->id, 'datos' => ['pais' => 'ESP', 'region' => 'VIE'], 'completada' => true,
        ]);

        $resultado = Ficha::prerellenarConAvisos($anterior, $tipo);

        $this->assertSame(['pais' => 'ESP', 'region' => null], $resultado['datos']);
        $this->assertSame([['campo_id' => 'region', 'etiqueta' => 'Región', 'valor_anterior' => 'Vieja región']], $resultado['avisos']);
        $this->assertSame($resultado['datos'], Ficha::prerellenarDesde($anterior, $tipo));
    }

    #[Test]
    public function tf_sde_17_el_codigo_es_unico_con_formato_e_inmutable_con_fichas(): void
    {
        $this->tipo->update(['codigo' => 'origen']);

        $otro = TipoFicha::create(['nombre' => 'Otra', 'activo' => true, 'schema' => ['campos' => [$this->campoPais()]]]);
        $this->assertCodigoRechazado(fn () => $otro->update(['codigo' => 'origen']));
        $this->assertCodigoRechazado(fn () => $otro->update(['codigo' => 'Con Espacios']));

        // Sin fichas se puede cambiar; con fichas, no
        $this->tipo->update(['codigo' => 'origen_persona']);
        $this->registrar(['pais' => 'ESP']);
        $this->assertCodigoRechazado(fn () => $this->tipo->fresh()->update(['codigo' => 'otro_codigo']));
        $this->assertSame('origen_persona', $this->tipo->fresh()->codigo);

        // Cambiar otra cosa del tipo sigue permitido
        $this->tipo->fresh()->update(['nombre' => 'Origen (revisada)']);
        $this->assertSame('Origen (revisada)', $this->tipo->fresh()->nombre);
    }

    #[Test]
    public function tf_sde_31_el_selector_encuentra_un_pais_por_parte_de_su_nombre(): void
    {
        $selector = Livewire::test(SelectorCatalogo::class, ['grupo' => 'comun.pais']);

        // Catálogo largo: sin escribir no lista nada
        $this->assertSame([], $selector->instance()->resultados);

        $selector->set('busqueda', 'marru')->assertSee('Marruecos')->assertDontSee('Mauritania');
        // Sin acentos ni mayúsculas
        $selector->set('busqueda', 'ESPANA')->assertSee('España');
        $selector->call('elegir', 'ESP')->assertSet('valor', 'ESP')->assertSee('España');

        // Una clave que no es del grupo no se elige
        $selector->call('quitar')->call('elegir', '01')->assertSet('valor', null);

        // Catálogo corto: se lista entero sin escribir
        $corto = Livewire::test(SelectorCatalogo::class, ['grupo' => 'comun.situacion_laboral']);
        $this->assertCount(9, $corto->instance()->resultados);
    }

    /**
     * Campo de país (catálogo `comun.pais`) con los atributos obligatorios del schema.
     *
     * @param array<string, mixed> $cambios Atributos que se sobrescriben.
     *
     * @return array<string, mixed>
     */
    private function campoPais(array $cambios = []): array
    {
        return array_merge(['id' => 'pais', 'tipo' => 'catalogo', 'etiqueta' => 'País', 'catalogo' => 'comun.pais', 'obligatorio' => false, 'orden' => 1], $cambios);
    }

    /**
     * Grupo de prueba con dos valores vigentes y uno que dejó de estarlo.
     *
     * @return void
     */
    private function grupoRegionDePrueba(): void
    {
        CatalogoSistema::create(['grupo' => 'prueba.region', 'clave' => 'NOR', 'etiqueta' => 'Norte']);
        CatalogoSistema::create(['grupo' => 'prueba.region', 'clave' => 'SUR', 'etiqueta' => 'Sur']);
        CatalogoSistema::create(['grupo' => 'prueba.region', 'clave' => 'VIE', 'etiqueta' => 'Vieja región', 'vigente_hasta' => today()->subYear()]);
    }

    /**
     * Registra una ficha de valoración de Ana desde su Historia, como su TSR.
     *
     * @param array<string, mixed> $datos Valores por id de campo.
     * @param TipoFicha|null $tipo Tipo de ficha (el del setUp si es nulo).
     *
     * @return Ficha
     */
    private function registrar(array $datos, ?TipoFicha $tipo = null): Ficha
    {
        return app(ValoracionService::class)->registrar(
            ContextoActuacion::desdeHistoria($this->historiaAna, $this->tsNorte),
            $tipo ?? $this->tipo,
            $datos,
        );
    }

    /**
     * Comprueba que la llamada se rechaza por el código.
     *
     * @param callable $llamada Operación que cambia el código.
     *
     * @return void
     */
    private function assertCodigoRechazado(callable $llamada): void
    {
        try {
            $llamada();
            $this->fail('Se esperaba ValidationException por el código.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('codigo', $e->errors());
        }
    }
}
