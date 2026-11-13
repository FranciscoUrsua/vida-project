<?php

namespace Tests\Feature\Acceso;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Búsqueda por documento y por NI-HSU-CM en el buscador principal (TF-ACC-21 y 22,
 * B4 de docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md).
 *
 * Coincidencia exacta por el hash de `ciudadano_identificadores`, sin filtrar por UO.
 * Un colectivo protegido que la policy no deja ver sale en nivel 3, sin datos.
 */
class BusquedaPorDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoA;

    private UnidadOrganizativa $uoB;

    /** Profesional de intervención de la UO A. */
    private User $ts;

    /**
     * Dos UO y un profesional de la UO A.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uoA = UnidadOrganizativa::create(['nombre' => 'CSS Documento A', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);
        $this->uoB = UnidadOrganizativa::create(['nombre' => 'CSS Documento B', 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);

        $this->ts = User::create([
            'name' => 'ts.documento',
            'email' => 'ts.documento@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $this->ts->assignRole('intervencion');
        UsuarioUo::create([
            'usuario_id' => $this->ts->id,
            'unidad_organizativa_id' => $this->uoA->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);
    }

    /** TF-ACC-21 — Por documento exacto, con espacios alrededor y en minúsculas, encuentra a la persona. */
    #[Test]
    public function tf_acc_21_encuentra_por_documento_exacto_con_espacios(): void
    {
        $persona = $this->persona('Lucía', $this->uoA);
        $this->identificador($persona, 'nif', '12345678Z');
        $this->persona('Otra', $this->uoA);

        $resultados = $this->buscar('doc', '  12345678z  ');

        $this->assertCount(1, $resultados);
        $this->assertSame($persona->id, $resultados[0]['ciudadano_id']);
        $this->assertSame(1, $resultados[0]['nivel']);
        $this->assertStringContainsString('Lucía', $resultados[0]['nombre']);
    }

    /** TF-ACC-21 bis — Sin filtrar por UO: la persona de otra UO sale en nivel 2; un documento parcial no encuentra nada. */
    #[Test]
    public function tf_acc_21_bis_encuentra_en_otra_uo_y_solo_coincidencia_exacta(): void
    {
        $persona = $this->persona('Marta', $this->uoB);
        $this->identificador($persona, 'nie', 'X1234567L');

        $resultados = $this->buscar('doc', 'X1234567L');
        $this->assertCount(1, $resultados);
        $this->assertSame(2, $resultados[0]['nivel']);

        $this->assertSame([], $this->buscar('doc', 'X1234567'));
    }

    /** TF-ACC-21 ter — NI-HSU-CM busca solo identificadores de ese tipo; el documento, cualquier tipo. */
    #[Test]
    public function tf_acc_21_ter_hsu_filtra_por_tipo(): void
    {
        $conHsu = $this->persona('Elena', $this->uoA);
        $this->identificador($conHsu, 'ni_hsu_cm', 'HSU000123');
        $conNif = $this->persona('Sara', $this->uoA);
        $this->identificador($conNif, 'nif', '87654321X');

        $this->assertSame([$conHsu->id], array_column($this->buscar('hsu', 'hsu000123'), 'ciudadano_id'));
        $this->assertSame([], $this->buscar('hsu', '87654321X'));
        $this->assertSame([$conHsu->id], array_column($this->buscar('doc', 'HSU000123'), 'ciudadano_id'));
    }

    /** TF-ACC-21 quater — Un documento anterior (con fecha de fin) también identifica a la persona, una sola vez. */
    #[Test]
    public function tf_acc_21_quater_un_documento_anterior_tambien_encuentra(): void
    {
        $persona = $this->persona('Nadia', $this->uoA);
        $this->identificador($persona, 'nie', 'Y7654321G', '2025-12-31');
        $this->identificador($persona, 'nif', '11111111H');

        $this->assertSame([$persona->id], array_column($this->buscar('doc', 'Y7654321G'), 'ciudadano_id'));
    }

    /** TF-ACC-22 — Por documento de un protegido de otra UO sin acceso: nivel 3, sin nombre, alias ni documento. */
    #[Test]
    public function tf_acc_22_un_protegido_sin_acceso_sale_en_nivel_3_sin_datos(): void
    {
        $protegido = $this->persona('Remedios', $this->uoB, ['colectivo_extra_protegido' => true, 'alias' => 'la-escondida']);
        $this->identificador($protegido, 'nif', '22222222J');

        $componente = Livewire::actingAs($this->ts)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'doc')
            ->set('query', '22222222J')
            ->call('buscar');

        $resultados = $componente->get('resultados');
        $this->assertCount(1, $resultados);
        $this->assertSame(3, $resultados[0]['nivel']);
        $this->assertNull($resultados[0]['nombre']);
        $this->assertNull($resultados[0]['alias']);
        $this->assertNull($resultados[0]['historia_id']);
        $this->assertArrayNotHasKey('documento', $resultados[0]);

        $componente->assertSee('Solicitar acceso')
            ->assertDontSee('Remedios')
            ->assertDontSee('la-escondida');
    }

    /** El hash de la consulta es el mismo que el guardado (un único cálculo). */
    #[Test]
    public function el_hash_de_la_consulta_coincide_con_el_guardado(): void
    {
        $persona = $this->persona('Inés', $this->uoA);
        $identificador = $this->identificador($persona, 'nif', '33333333P');

        $this->assertSame($identificador->valor_hash, CiudadanoIdentificador::hashDe('33333333p'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Busca como el profesional de la UO A y devuelve los resultados.
     *
     * @param string $campo 'doc' | 'hsu'.
     * @param string $termino Texto introducido.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscar(string $campo, string $termino): array
    {
        return Livewire::actingAs($this->ts)
            ->test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', $campo)
            ->set('query', $termino)
            ->call('buscar')
            ->get('resultados');
    }

    /**
     * Persona con historia abierta en una UO.
     *
     * @param string $nombre Nombre.
     * @param UnidadOrganizativa $uo UO de la historia.
     * @param array<string, mixed> $attrs Atributos adicionales.
     *
     * @return Ciudadano
     */
    private function persona(string $nombre, UnidadOrganizativa $uo, array $attrs = []): Ciudadano
    {
        $ciudadano = Ciudadano::create(array_merge([
            'nombre' => $nombre,
            'apellido1' => 'Documento',
            'fecha_nacimiento' => '1980-01-01',
            'sexo' => 'F',
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'colectivo_extra_protegido' => false,
        ], $attrs));

        HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        return $ciudadano;
    }

    /**
     * Identificador de una persona; el hash lo calcula el modelo.
     *
     * @param Ciudadano $ciudadano Titular.
     * @param string $tipo Tipo de identificador.
     * @param string $valor Valor tal como se guarda.
     * @param string|null $fechaFin Fecha de fin si ya no está vigente.
     *
     * @return CiudadanoIdentificador
     */
    private function identificador(Ciudadano $ciudadano, string $tipo, string $valor, ?string $fechaFin = null): CiudadanoIdentificador
    {
        return CiudadanoIdentificador::create([
            'ciudadano_id' => $ciudadano->id,
            'tipo' => $tipo,
            'valor' => $valor,
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => $fechaFin,
            'verificado' => false,
            'fuente' => 'manual',
        ]);
    }
}
