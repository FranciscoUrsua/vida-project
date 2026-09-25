<?php

namespace Modules\Documentos\Tests\Concerns;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\CicloVidaDocumentoService;

/**
 * Actores, tipos documentales y utilidades comunes de los tests de la custodia v2.
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md «Actores y datos reutilizados»
 */
trait DocumentosTestSetup
{
    protected User $admin;

    protected User $profesional;

    protected User $profesionalOtraUo;

    protected UnidadOrganizativa $uo;

    protected UnidadOrganizativa $otraUo;

    protected UnidadConvivencia $uc;

    protected Ciudadano $ana;

    protected Ciudadano $luis;

    protected Ciudadano $eva;

    protected Ciudadano $pablo;

    protected Ciudadano $ciudadanoAjeno;

    protected TipoDocumental $tipoDni;

    protected TipoDocumental $tipoEmpadronamiento;

    protected TipoDocumental $tipoInformeMedico;

    protected TipoDocumental $tipoConConservacion;

    /**
     * Prepara disco falso, roles, actores y tipos documentales.
     *
     * El ámbito de acceso de los profesionales a los ciudadanos se completará con
     * los tests de acceso (grupo G).
     *
     * @return void
     */
    protected function prepararDocumentos(): void
    {
        Storage::fake('documentos');

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Documentos', 'tipo' => 'centro', 'activa' => true]);
        $this->otraUo = UnidadOrganizativa::create(['nombre' => 'CSS Ajeno', 'tipo' => 'centro', 'activa' => true]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('adm_sistema');

        $this->profesional = $this->usuarioEnUo($this->uo);
        $this->profesionalOtraUo = $this->usuarioEnUo($this->otraUo);

        [$this->ana, $this->luis, $this->eva, $this->pablo, $this->ciudadanoAjeno] = Ciudadano::factory()->count(5)->create()->all();

        $this->uc = UnidadConvivencia::create(['domicilio' => 'Calle Prueba 1', 'fecha_constitucion' => now()->toDateString()]);
        foreach ([$this->ana, $this->luis, $this->eva, $this->pablo] as $miembro) {
            $this->uc->agregarMiembro($miembro->id);
        }

        $this->tipoDni = $this->tipo('dni', ['politica_versiones' => PoliticaVersiones::PurgarNoRetenidas]);
        $this->tipoEmpadronamiento = $this->tipo('certificado_empadronamiento', ['caduca' => true, 'validez_dias' => 90]);
        $this->tipoInformeMedico = $this->tipo('informe_medico', ['metadatos_requeridos' => ['fecha_emision', 'organo_emisor']]);
        $this->tipoConConservacion = $this->tipo('con_conservacion', ['conservacion_anyos' => 5]);
    }

    /**
     * Crea un usuario con rol intervencion adscrito a la UO indicada.
     *
     * @param UnidadOrganizativa $uo UO de adscripción.
     *
     * @return User
     */
    protected function usuarioEnUo(UnidadOrganizativa $uo): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Crea un tipo documental de familia «aportado por el ciudadano» con los atributos dados.
     *
     * @param string $codigo Código del tipo.
     * @param array<string, mixed> $atributos Atributos que sustituyen a los por defecto.
     *
     * @return TipoDocumental
     */
    protected function tipo(string $codigo, array $atributos = []): TipoDocumental
    {
        return TipoDocumental::create([
            'codigo' => $codigo,
            'nombre' => ucfirst(str_replace('_', ' ', $codigo)),
            'familia' => FamiliaDocumental::AportadoCiudadano,
            'origen_eni' => OrigenEni::Ciudadano,
            'caduca' => false,
            'politica_versiones' => PoliticaVersiones::Conservar,
            'vinculables' => ['ciudadano'],
            'activo' => true,
            ...$atributos,
        ]);
    }

    /**
     * Ruta de un fichero de prueba.
     *
     * @param string $nombre Nombre del fichero en tests/fixtures.
     *
     * @return string
     */
    protected function fixture(string $nombre): string
    {
        return dirname(__DIR__).'/fixtures/'.$nombre;
    }

    /**
     * Da de alta un documento a través del servicio de ciclo de vida.
     *
     * @param list<Model> $vinculos Entidades vinculadas.
     * @param TipoDocumental|null $tipo Tipo; por defecto, DNI.
     * @param string $fichero Fixture de origen.
     * @param array<string, mixed> $datos Argumentos adicionales de DatosIngesta.
     *
     * @return Documento
     */
    protected function alta(array $vinculos, ?TipoDocumental $tipo = null, string $fichero = 'valido.pdf', array $datos = []): Documento
    {
        return app(CicloVidaDocumentoService::class)->altaDocumento(
            $this->fixture($fichero),
            new DatosIngesta(
                tipo: $tipo ?? $this->tipoDni,
                usuario: $datos['usuario'] ?? $this->profesional,
                canal: $datos['canal'] ?? CanalCaptura::Presencial,
                vinculos: $vinculos,
                nombreOriginal: $datos['nombreOriginal'] ?? null,
                fechaEmision: $datos['fechaEmision'] ?? null,
                organoEmisor: $datos['organoEmisor'] ?? null,
            ),
        );
    }

    /**
     * Objetos presentes en el disco de documentos.
     *
     * @return list<string>
     */
    protected function objetosEnDisco(): array
    {
        return Storage::disk('documentos')->allFiles();
    }
}
