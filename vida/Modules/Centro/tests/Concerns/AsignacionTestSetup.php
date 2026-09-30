<?php

namespace Modules\Centro\Tests\Concerns;

use App\Events\DireccionCiudadanoNormalizada;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Models\AmbitoTerritorial;
use Modules\Centro\Models\Centro;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Organizacion\Models\Barrio;
use Modules\Organizacion\Models\Distrito;
use Modules\Organizacion\Models\SeccionCensal;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Actores y datos comunes de los tests de asignación (TF-ASG-01 a 34).
 *
 * Sustituye el catálogo territorial real por el mini-catálogo del documento de
 * tests: distritos 01 y 02; barrios 011, 012, 021; secciones 2807901001 y
 * 2807901002 (barrio 011), 2807901003 (barrio 012) y 2807902001 (barrio 021).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md «Actores y datos reutilizados»
 */
trait AsignacionTestSetup
{
    protected User $supervisor;

    protected User $supervisorSur;

    protected Centro $cssNorte;

    protected Centro $cssSur;

    protected Centro $ciam;

    protected User $ts1;

    protected User $ts2;

    protected User $ts3;

    protected User $educador;

    protected Ciudadano $ana;

    protected Ciudadano $pedro;

    protected Ciudadano $lucia;

    protected UnidadConvivencia $ucGarcia;

    protected Cargo $cargoTs;

    protected Cargo $cargoEducador;

    /** Tipo de centro de servicios sociales en el catálogo `centro.tipo`. */
    protected string $tipoCss = 'css_general';

    /**
     * Monta el escenario completo del documento de tests.
     *
     * @return void
     */
    protected function montarEscenarioAsignacion(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        Queue::fake();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->montarMiniCatalogo();

        $this->cargoTs = Cargo::create(['nombre' => 'Trabajador/a Social', 'slug' => 'ts', 'activo' => true, 'puede_ser_referencia' => true]);
        $this->cargoEducador = Cargo::create(['nombre' => 'Educador/a Social', 'slug' => 'educadorsocial', 'activo' => true, 'puede_ser_referencia' => false]);

        $this->cssNorte = $this->crearCentro('CSS Norte', $this->tipoCss);
        $this->cssSur = $this->crearCentro('CSS Sur', $this->tipoCss);
        $this->ciam = $this->crearCentro('CIAM', 'ciam', inscripcionLibre: true);

        $this->anadirAmbito($this->cssNorte, 'barrios', Barrio::where('codigo', '011')->value('id'));
        $this->anadirAmbito($this->cssSur, 'barrios', Barrio::where('codigo', '012')->value('id'));
        $this->anadirAmbito($this->cssSur, 'demarcacion_oficial', Distrito::where('codigo', '02')->value('id'));

        $this->supervisor = $this->crearUsuario('supervisor', $this->cssNorte, 'supervision');
        $this->supervisorSur = $this->crearUsuario('supervisor.sur', $this->cssSur, 'supervision');

        $this->ts1 = $this->crearProfesionalEnCentro('ts1', $this->cssNorte, $this->cargoTs, 35);
        $this->ts2 = $this->crearProfesionalEnCentro('ts2', $this->cssNorte, $this->cargoTs, 35);
        $this->ts3 = $this->crearProfesionalEnCentro('ts3', $this->cssNorte, $this->cargoTs, 17.5);
        $this->educador = $this->crearProfesionalEnCentro('educador', $this->cssNorte, $this->cargoEducador, 35);

        $this->ana = $this->crearCiudadano('Ana');
        $this->normalizarEn($this->ana, '2807901001', disparar: false);

        $this->pedro = $this->crearCiudadano('Pedro');
        $this->lucia = $this->crearCiudadano('Lucía');
        $this->ucGarcia = UnidadConvivencia::create(['fecha_constitucion' => '2020-01-01']);
        foreach ([$this->pedro, $this->lucia] as $miembro) {
            UnidadConvivenciaMiembro::create([
                'unidad_convivencia_id' => $this->ucGarcia->id,
                'ciudadano_id' => $miembro->id,
                'fecha_inicio' => '2020-01-01',
                'fuente' => 'manual',
            ]);
        }
    }

    /**
     * Sustituye el catálogo territorial por el mini-catálogo de los tests.
     *
     * @return void
     */
    protected function montarMiniCatalogo(): void
    {
        DB::table('ambitos_territoriales')->delete();
        SeccionCensal::query()->delete();
        Barrio::query()->delete();

        $d01 = Distrito::where('codigo', '01')->firstOrFail();
        $d02 = Distrito::where('codigo', '02')->firstOrFail();

        $b011 = Barrio::create(['distrito_id' => $d01->id, 'codigo' => '011', 'codigo_en_distrito' => '1', 'nombre' => 'Barrio 011']);
        $b012 = Barrio::create(['distrito_id' => $d01->id, 'codigo' => '012', 'codigo_en_distrito' => '2', 'nombre' => 'Barrio 012']);
        $b021 = Barrio::create(['distrito_id' => $d02->id, 'codigo' => '021', 'codigo_en_distrito' => '1', 'nombre' => 'Barrio 021']);

        foreach ([['2807901001', $b011], ['2807901002', $b011], ['2807901003', $b012], ['2807902001', $b021]] as [$codigo, $barrio]) {
            SeccionCensal::create([
                'distrito_id' => $barrio->distrito_id,
                'barrio_id' => $barrio->id,
                'codigo_ine' => $codigo,
                'codigo_en_distrito' => ltrim(substr($codigo, 7), '0'),
            ]);
        }
    }

    /**
     * Crea un centro activo con su UO.
     *
     * @param string $nombre
     * @param string $tipoCentro
     * @param bool $inscripcionLibre
     * @param ModoAsignacionReferenciaCentro $modo
     * @return Centro
     */
    protected function crearCentro(
        string $nombre,
        string $tipoCentro,
        bool $inscripcionLibre = false,
        ModoAsignacionReferenciaCentro $modo = ModoAsignacionReferenciaCentro::Sorteo,
    ): Centro {
        $uo = UnidadOrganizativa::create(['nombre' => 'UO '.$nombre, 'tipo' => 'centro', 'parent_id' => null, 'activa' => true]);

        return Centro::create([
            'nombre' => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'tipo_centro' => $tipoCentro,
            'unidad_organizativa_id' => $uo->id,
            'inscripcion_libre' => $inscripcionLibre,
            'modo_asignacion_referencia' => $modo,
            'activo' => true,
            'fecha_alta' => '2020-01-01',
        ]);
    }

    /**
     * Añade una unidad territorial al ámbito de un centro.
     *
     * @param Centro $centro
     * @param string $tipo Tipo de ámbito.
     * @param int|null $referenciaId Id de la unidad (null en ciudad_completa).
     * @return AmbitoTerritorial
     */
    protected function anadirAmbito(Centro $centro, string $tipo, ?int $referenciaId): AmbitoTerritorial
    {
        return AmbitoTerritorial::create([
            'centro_id' => $centro->id,
            'tipo' => $tipo,
            'descripcion' => "{$tipo} {$referenciaId}",
            'referencia_id' => $referenciaId,
        ]);
    }

    /**
     * Crea un usuario con rol y adscripción a la UO del centro.
     *
     * @param string $alias
     * @param Centro $centro
     * @param string $rol
     * @param Profesional|null $profesional
     * @return User
     */
    protected function crearUsuario(string $alias, Centro $centro, string $rol, ?Profesional $profesional = null): User
    {
        $usuario = User::create([
            'email' => "{$alias}@vida360.test",
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
            'profesional_id' => $profesional?->id,
        ]);
        $usuario->syncRoles([$rol]);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $centro->unidad_organizativa_id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => '2020-01-01',
        ]);

        return $usuario;
    }

    /**
     * Crea un profesional con cargo, rol de intervención y perfil horario activo en el centro.
     *
     * @param string $alias
     * @param Centro $centro
     * @param Cargo $cargo
     * @param float $jornada Horas semanales en el centro.
     * @param string $desde Inicio de vigencia del perfil horario.
     * @return User
     */
    protected function crearProfesionalEnCentro(string $alias, Centro $centro, Cargo $cargo, float $jornada, string $desde = '2025-01-01'): User
    {
        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(
            ['nombre' => 'Funcionario/a de carrera'],
            ['es_externo' => false, 'activo' => true],
        );

        $profesional = Profesional::create([
            'nombre' => ucfirst($alias),
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
        ]);

        $usuario = $this->crearUsuario($alias, $centro, 'intervencion', $profesional);

        PerfilHorarioProfesional::factory()->create([
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'jornada_semanal_horas' => $jornada,
            'vigente_desde' => $desde,
        ]);

        return $usuario;
    }

    /**
     * Crea un ciudadano sin dirección.
     *
     * @param string $nombre
     * @return Ciudadano
     */
    protected function crearCiudadano(string $nombre): Ciudadano
    {
        return Ciudadano::factory()->create(['nombre' => $nombre]);
    }

    /**
     * Simula la normalización de la dirección en una sección del mini-catálogo
     * (o sin códigos si es null), como haría el geocodificador, y dispara el evento.
     *
     * @param Ciudadano $ciudadano
     * @param string|null $seccion Código INE, o null para una dirección no geocodificable.
     * @param bool $disparar Si se dispara DireccionCiudadanoNormalizada.
     * @return Ciudadano
     */
    protected function normalizarEn(Ciudadano $ciudadano, ?string $seccion, bool $disparar = true): Ciudadano
    {
        $unidad = $seccion ? SeccionCensal::with(['barrio', 'distrito'])->where('codigo_ine', $seccion)->firstOrFail() : null;

        $ciudadano->forceFill([
            'direccion_texto' => 'Calle de prueba '.($seccion ?? 'sin códigos'),
            'direccion_normalizada' => $unidad !== null,
            'codigo_ndp' => $unidad ? 'NDP'.$seccion : null,
            'distrito_codigo' => $unidad?->distrito->codigo,
            'barrio_codigo' => $unidad?->barrio?->codigo,
            'seccion_censal_codigo' => $unidad?->codigo_ine,
        ])->saveQuietly();

        if ($disparar) {
            DireccionCiudadanoNormalizada::dispatch($ciudadano);
        }

        return $ciudadano;
    }

    /**
     * Fija la semilla del azar del sorteo (Mt19937) para los servicios que se resuelvan después.
     *
     * @param int $semilla
     * @return void
     */
    protected function fijarSemilla(int $semilla): void
    {
        $this->app->instance(Randomizer::class, new Randomizer(new Mt19937($semilla)));
    }

    /**
     * Crea la historia social de un ciudadano (sin asignación) en la UO del centro.
     *
     * @param Ciudadano $ciudadano
     * @param Centro|null $centro Por defecto, CSS Norte.
     * @return HistoriaSocial
     */
    protected function historiaDe(Ciudadano $ciudadano, ?Centro $centro = null): HistoriaSocial
    {
        return HistoriaSocial::factory()->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => ($centro ?? $this->cssNorte)->unidad_organizativa_id,
            'estado' => 'abierta',
        ]);
    }
}
