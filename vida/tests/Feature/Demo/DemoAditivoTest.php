<?php

namespace Tests\Feature\Demo;

use App\Models\Ciudadano;
use App\Models\DemoWorldRegistro;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\Demo\DemoWorldLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionCentro;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\Sala;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Models\TipoActividad;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Intervencion\Models\SiaContacto;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use Modules\Usuarios\Models\UsuarioRol;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Tests funcionales del modo aditivo de mundos demo y del mundo «Prueba CIAM».
 *
 * TF-DEMO-CIAM-01 a 07: modo aditivo (loader, demo:load, demo:reset, dry-run, entorno).
 * TF-DEMO-CIAM-08 a 11: referencias a entidades existentes y etiquetado.
 * TF-DEMO-CIAM-15 y 16: contenido del mundo demo_ciam.
 * (TF-DEMO-CIAM-12 a 14 están en Modules/Intervencion/tests/Feature/PlanEntradaDirectaTest.php.)
 */
class DemoAditivoTest extends TestCase
{
    use RefreshDatabase;

    /** Tablas cuyo recuento se compara antes y después de las cargas. */
    private const TABLAS = [
        'ciudadanos', 'users', 'profesionales', 'centros', 'unidades_organizativas', 'salas',
        'cargos', 'tipos_actividad', 'tipos_plan', 'historias_sociales', 'planes_intervencion',
        'seguimientos_plan', 'entrevistas', 'asignaciones_profesional', 'sia_contactos',
        'actividades', 'sesiones_actividad', 'actividad_profesional', 'sesion_actividad_profesional',
        'prescripciones', 'inscripciones_centro', 'usuario_uo', 'usuario_rol', 'model_has_roles',
        'demo_world_registros', 'audits', 'versiones',
    ];

    private Centro $centroCiam;

    /**
     * Prepara el entorno que el mundo demo_ciam referencia (lo que en staging ya existe).
     *
     * Sala Polivalente y el tipo 'taller-empoderamiento' se dejan sin crear a propósito
     * para ejercitar crear_si_no_existe.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $uo = UnidadOrganizativa::create(['nombre' => 'CIAM Puente de Vallecas', 'tipo' => 'centro', 'activa' => true]);

        $this->centroCiam = Centro::create([
            'nombre' => 'CIAM Puente de Vallecas',
            'tipo_gestion' => 'municipal_concertado',
            'unidad_organizativa_id' => $uo->id,
            'codigo_postal' => '28053',
            'activo' => true,
            'fecha_alta' => '2026-06-26',
        ]);

        Sala::create(['centro_id' => $this->centroCiam->id, 'nombre' => 'Sala Girasol', 'capacidad' => 20, 'accesible' => true, 'activa' => true]);

        TipoPlan::create([
            'slug' => 'pia', 'nombre' => 'PIA', 'ambito' => 'especializado',
            'admite_entrada_directa' => true, 'activo' => true, 'eliminable' => true,
        ]);

        foreach (['Coordinador/a de Centro', 'Trabajador/a Social', 'Psicólogo/a', 'Abogado/a', 'Administrativo/a', 'Auxiliar de Servicios Sociales'] as $cargo) {
            Cargo::create(['nombre' => $cargo, 'activo' => true]);
        }

        // Como en staging: el rol consulta_basica existe, y User::booted() lo auto-asigna a
        // usuarios con profesional_id creados sin roles. El mundo no debe heredarlo.
        Role::create(['name' => 'consulta_basica', 'guard_name' => 'web']);

        TipoRelacionProfesional::create(['nombre' => 'Funcionario/a de carrera', 'slug' => 'funcionario', 'activo' => true]);

        foreach (['formacion' => 'Formación y alfabetización', 'taller-empleo' => 'Taller de empleo', 'charla' => 'Charla'] as $slug => $nombre) {
            TipoActividad::create(['slug' => $slug, 'nombre' => $nombre, 'activo' => true]);
        }
    }

    // -------------------------------------------------------------------------
    // Modo aditivo
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-CIAM-01: el loader rechaza un mundo aditivo sin etiqueta.
     *
     * Dado un YAML con modo: aditivo y sin etiqueta; cuando se carga; entonces
     * lanza InvalidArgumentException. Con etiqueta y el resto igual, se acepta.
     */
    #[Test]
    public function tf_demo_ciam_01_loader_rechaza_mundo_aditivo_sin_etiqueta(): void
    {
        $dir = $this->directorioTemporal();
        $base = <<<'YAML'
meta: { nombre: "Aditivo", descripcion: "Test" }
modo: aditivo
%s
existentes:
  centros:
    - { id: c1, buscar_por: { nombre: "CIAM Puente de Vallecas" } }
  cargos:
    - { id: ts, buscar_por: { nombre: "Trabajador/a Social" } }
profesionales:
  - { login: a@test.es, nombre: Ana, apellido1: Test, centro: c1, cargo: ts, roles: [intervencion] }
YAML;
        file_put_contents("{$dir}/sin_etiqueta.yaml", sprintf($base, ''));
        file_put_contents("{$dir}/con_etiqueta.yaml", sprintf($base, 'etiqueta: TEST_X'));
        file_put_contents("{$dir}/etiqueta_mal.yaml", sprintf($base, 'etiqueta: test-x'));

        $loader = new DemoWorldLoader($dir);

        // Positivo de control: con etiqueta válida el mismo mundo es válido.
        $this->assertSame('TEST_X', $loader->load('con_etiqueta')['etiqueta']);

        try {
            $loader->load('etiqueta_mal');
            $this->fail('Una etiqueta con formato no válido debe rechazarse.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('etiqueta', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/'etiqueta' es obligatoria/");

        $loader->load('sin_etiqueta');
    }

    /**
     * TF-DEMO-CIAM-02: demo:reset rechaza un mundo aditivo y no ejecuta ningún TRUNCATE.
     *
     * Dado un centro preexistente; cuando se lanza demo:reset con demo_ciam; entonces
     * el comando falla, no se emite ninguna sentencia TRUNCATE y el centro sigue existiendo.
     */
    #[Test]
    public function tf_demo_ciam_02_demo_reset_rechaza_mundo_aditivo_sin_truncar(): void
    {
        $sentencias = [];
        DB::listen(function ($query) use (&$sentencias) {
            $sentencias[] = $query->sql;
        });

        $exitCode = Artisan::call('demo:reset', ['--world' => 'demo_ciam', '--no-interaction' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('demo:load', Artisan::output());
        $this->assertEmpty(array_filter($sentencias, fn (string $sql) => stripos($sql, 'truncate') !== false));
        $this->assertDatabaseHas('centros', ['id' => $this->centroCiam->id, 'deleted_at' => null]);
    }

    /**
     * TF-DEMO-CIAM-03: demo:load rechaza un mundo de modo reset.
     *
     * Dado el mundo ci_minimo (sin modo = reset); cuando se lanza demo:load; entonces
     * termina con error, sugiere demo:reset y no crea nada.
     */
    #[Test]
    public function tf_demo_ciam_03_demo_load_rechaza_mundo_reset(): void
    {
        $antes = $this->recuentos();

        $exitCode = Artisan::call('demo:load', ['--world' => 'ci_minimo']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('demo:reset', Artisan::output());
        $this->assertSame($antes, $this->recuentos());
    }

    /**
     * TF-DEMO-CIAM-04: demo:load no borra datos preexistentes.
     *
     * Dado un conjunto previo de ciudadanos, usuarios y centros; cuando se carga
     * demo_ciam; entonces el recuento de cada tabla es igual o mayor y todos los
     * ids previos siguen existiendo (y sin soft delete).
     */
    #[Test]
    public function tf_demo_ciam_04_demo_load_no_borra_datos_preexistentes(): void
    {
        $ciudadanos = Ciudadano::factory()->count(3)->create()->pluck('id');
        $usuarios = User::factory()->count(3)->create()->pluck('id');
        $otroCentro = Centro::create(['nombre' => 'CSS Entrevías', 'tipo_gestion' => 'municipal_directo', 'activo' => true, 'fecha_alta' => '2026-01-01']);
        $centros = collect([$this->centroCiam->id, $otroCentro->id]);
        $antes = $this->recuentos();

        $this->assertSame(0, Artisan::call('demo:load', ['--world' => 'demo_ciam']), Artisan::output());

        $despues = $this->recuentos();

        foreach (self::TABLAS as $tabla) {
            $this->assertGreaterThanOrEqual($antes[$tabla], $despues[$tabla], "La tabla {$tabla} ha perdido filas.");
        }

        $this->assertSame($ciudadanos->count(), Ciudadano::withoutGlobalScopes()->whereIn('id', $ciudadanos)->count());
        $this->assertSame($usuarios->count(), User::whereIn('id', $usuarios)->count());
        $this->assertSame($centros->count(), Centro::whereIn('id', $centros)->count());
    }

    /**
     * TF-DEMO-CIAM-05: demo:load es idempotente.
     *
     * Dada una primera carga de demo_ciam; cuando se ejecuta una segunda vez; entonces
     * ninguna tabla cambia su recuento y el comando informa de 0 registros creados.
     */
    #[Test]
    public function tf_demo_ciam_05_demo_load_es_idempotente(): void
    {
        $this->assertSame(0, Artisan::call('demo:load', ['--world' => 'demo_ciam']), Artisan::output());
        $trasPrimera = $this->recuentos();

        // Buffer propio: la segunda llamada al mismo comando no deja su salida en Artisan::output().
        $buffer = new BufferedOutput;
        $exitCode = Artisan::call('demo:load', ['--world' => 'demo_ciam'], $buffer);
        $output = $buffer->fetch();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('0 registros creados', $output);
        $this->assertSame($trasPrimera, $this->recuentos());
    }

    /**
     * TF-DEMO-CIAM-06: --dry-run no persiste nada.
     *
     * Dado el entorno sin el mundo cargado; cuando se lanza demo:load --dry-run;
     * entonces el comando termina bien, muestra el resumen y ningún recuento cambia.
     */
    #[Test]
    public function tf_demo_ciam_06_dry_run_no_persiste_nada(): void
    {
        $antes = $this->recuentos();

        $exitCode = Artisan::call('demo:load', ['--world' => 'demo_ciam', '--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('DRY-RUN', $output);
        $this->assertStringContainsString('Ciudadano', $output);
        $this->assertSame($antes, $this->recuentos());
    }

    /**
     * TF-DEMO-CIAM-07: demo:load se niega a ejecutarse en producción.
     *
     * Dado APP_ENV=production; cuando se lanza demo:load; entonces sale con código 1
     * sin crear nada.
     */
    #[Test]
    public function tf_demo_ciam_07_demo_load_se_niega_en_produccion(): void
    {
        $antes = $this->recuentos();
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('demo:load', ['--world' => 'demo_ciam']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('producción', Artisan::output());
        $this->assertSame($antes, $this->recuentos());
    }

    // -------------------------------------------------------------------------
    // Referencias y etiquetado
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-CIAM-08: una referencia sin resultado y sin crear_si_no_existe hace fallar toda la carga.
     *
     * Dado que no existe el cargo «Abogado/a» (referencia sin crear_si_no_existe); cuando
     * se carga demo_ciam; entonces falla indicando la entrada y no queda nada a medias
     * (tampoco lo que se habría creado antes de llegar a esa referencia).
     */
    #[Test]
    public function tf_demo_ciam_08_referencia_inexistente_falla_sin_dejar_datos(): void
    {
        Cargo::where('nombre', 'Abogado/a')->delete();
        $antes = $this->recuentos();

        $exitCode = Artisan::call('demo:load', ['--world' => 'demo_ciam']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("existentes.cargos 'abogacia'", $output);
        $this->assertStringContainsString('no existe', $output);
        $this->assertSame($antes, $this->recuentos());
    }

    /**
     * TF-DEMO-CIAM-09: una referencia ambigua hace fallar la carga.
     *
     * Dadas dos salas «Sala Girasol» en el centro (aunque la entrada tenga
     * crear_si_no_existe); cuando se carga demo_ciam; entonces falla por ambigüedad.
     */
    #[Test]
    public function tf_demo_ciam_09_referencia_ambigua_falla(): void
    {
        Sala::create(['centro_id' => $this->centroCiam->id, 'nombre' => 'Sala Girasol', 'capacidad' => 10, 'accesible' => false, 'activa' => true]);
        $antes = $this->recuentos();

        $exitCode = Artisan::call('demo:load', ['--world' => 'demo_ciam']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("existentes.salas 'girasol'", $output);
        $this->assertStringContainsString('ambigua', $output);
        $this->assertSame($antes, $this->recuentos());
    }

    /**
     * TF-DEMO-CIAM-10: el centro referenciado no se modifica.
     *
     * Dado el centro CIAM preexistente; cuando se carga demo_ciam; entonces todos sus
     * campos, incluido updated_at, quedan exactamente igual (y también los de su UO).
     */
    #[Test]
    public function tf_demo_ciam_10_centro_referenciado_no_se_modifica(): void
    {
        $this->travel(-3)->days();
        $this->centroCiam->touch();
        $this->travelBack();

        $centroAntes = (array) DB::table('centros')->where('id', $this->centroCiam->id)->first();
        $uoAntes = (array) DB::table('unidades_organizativas')->where('id', $this->centroCiam->unidad_organizativa_id)->first();

        $this->assertSame(0, Artisan::call('demo:load', ['--world' => 'demo_ciam']), Artisan::output());

        $this->assertSame($centroAntes, (array) DB::table('centros')->where('id', $this->centroCiam->id)->first());
        $this->assertSame($uoAntes, (array) DB::table('unidades_organizativas')->where('id', $this->centroCiam->unidad_organizativa_id)->first());
    }

    /**
     * TF-DEMO-CIAM-11: todo lo creado queda registrado; nada de lo referenciado.
     *
     * Dado el entorno de referencia; cuando se carga demo_ciam; entonces cada fila nueva
     * de las tablas de dominio está registrada con TEST_CIAM, lo creado por
     * crear_si_no_existe también, y el centro, el tipo pia, los cargos y la sala
     * preexistente no aparecen registrados.
     */
    #[Test]
    public function tf_demo_ciam_11_todo_lo_creado_queda_registrado_y_nada_referenciado(): void
    {
        $antes = $this->recuentos();

        $this->assertSame(0, Artisan::call('demo:load', ['--world' => 'demo_ciam']), Artisan::output());

        $despues = $this->recuentos();
        $registrados = fn (string $clase) => DemoWorldRegistro::de('TEST_CIAM')->deTipo($clase)->count();

        $tablasPorModelo = [
            Ciudadano::class => 'ciudadanos',
            User::class => 'users',
            Profesional::class => 'profesionales',
            Sala::class => 'salas',
            TipoActividad::class => 'tipos_actividad',
            HistoriaSocial::class => 'historias_sociales',
            PlanDeIntervencion::class => 'planes_intervencion',
            SeguimientoPlan::class => 'seguimientos_plan',
            Entrevista::class => 'entrevistas',
            AsignacionProfesional::class => 'asignaciones_profesional',
            SiaContacto::class => 'sia_contactos',
            Actividad::class => 'actividades',
            SesionActividad::class => 'sesiones_actividad',
            Prescripcion::class => 'prescripciones',
            InscripcionCentro::class => 'inscripciones_centro',
            UsuarioUo::class => 'usuario_uo',
            UsuarioRol::class => 'usuario_rol',
        ];

        foreach ($tablasPorModelo as $clase => $tabla) {
            $this->assertSame(
                $despues[$tabla] - $antes[$tabla],
                $registrados($clase),
                "Filas nuevas de {$tabla} sin registrar (o registradas de más)."
            );
        }

        // Creados mediante crear_si_no_existe: registrados.
        $this->assertSame(1, $registrados(Sala::class), 'Solo Sala Polivalente se crea.');
        $this->assertSame(1, $registrados(TipoActividad::class), 'Solo taller-empoderamiento se crea.');

        // Referenciados: nunca registrados.
        $this->assertSame(0, $registrados(Centro::class));
        $this->assertSame(0, $registrados(TipoPlan::class));
        $this->assertSame(0, $registrados(Cargo::class));
        $girasol = Sala::where('nombre', 'Sala Girasol')->first();
        $this->assertFalse(DemoWorldRegistro::where('registrable_type', $girasol->getMorphClass())->where('registrable_id', $girasol->id)->exists());
    }

    // -------------------------------------------------------------------------
    // Contenido del mundo
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-CIAM-15: contenido del mundo demo_ciam.
     *
     * Cuando se carga demo_ciam; entonces hay 10 profesionales y 10 usuarios TEST_CIAM con
     * los correos y roles de la tabla 5.2, que pueden iniciar sesión con su contraseña y sin
     * onboarding; 100 ciudadanas, todas mujeres; y exactamente 50 planes pia (30 activos,
     * 20 cerrados), todos especializados y sin plan ASP.
     */
    #[Test]
    public function tf_demo_ciam_15_contenido_del_mundo(): void
    {
        $this->assertSame(0, Artisan::call('demo:load', ['--world' => 'demo_ciam']), Artisan::output());

        $esperados = [
            'dir.ciam@vida.local' => ['dir987', ['adm_usuarios', 'intervencion', 'supervision']],
            'ts1.ciam@vida.local' => ['ts1987', ['intervencion']],
            'ts2.ciam@vida.local' => ['ts2987', ['intervencion']],
            'ts3.ciam@vida.local' => ['ts3987', ['intervencion']],
            'abogada.ciam@vida.local' => ['abogada987', ['intervencion']],
            'psicologa.ciam@vida.local' => ['psicologa987', ['intervencion']],
            'adm1.ciam@vida.local' => ['adm1987', ['tramitacion']],
            'adm2.ciam@vida.local' => ['adm2987', ['tramitacion']],
            'aux1.ciam@vida.local' => ['aux1987', ['intervencion']],
            'aux2.ciam@vida.local' => ['aux2987', ['intervencion']],
        ];

        $idsUsuarios = DemoWorldRegistro::de('TEST_CIAM')->deTipo(User::class)->pluck('registrable_id');
        $usuarios = User::whereIn('id', $idsUsuarios)->get()->keyBy('email');

        $this->assertCount(10, $usuarios);
        $this->assertSame(10, DemoWorldRegistro::de('TEST_CIAM')->deTipo(Profesional::class)->count());

        foreach ($esperados as $email => [$password, $roles]) {
            $user = $usuarios[$email] ?? null;
            $this->assertNotNull($user, "Falta el usuario {$email}.");
            $this->assertTrue(Hash::check($password, $user->password), "Contraseña incorrecta para {$email}.");
            $this->assertFalse((bool) $user->primer_acceso);
            $this->assertEqualsCanonicalizing($roles, $user->getRoleNames()->all(), "Roles incorrectos para {$email}.");
            $this->assertSame('F', Profesional::find($user->profesional_id)->sexo);
            $this->assertTrue($user->unidadesOrganizativas()->where('unidades_organizativas.id', $this->centroCiam->unidad_organizativa_id)->exists());
        }

        $idsCiudadanas = DemoWorldRegistro::de('TEST_CIAM')->deTipo(Ciudadano::class)->pluck('registrable_id');
        $this->assertCount(100, $idsCiudadanas);
        $this->assertSame(100, Ciudadano::withoutGlobalScopes()->whereIn('id', $idsCiudadanas)->where('sexo', 'F')->count());

        $idsPlanes = DemoWorldRegistro::de('TEST_CIAM')->deTipo(PlanDeIntervencion::class)->pluck('registrable_id');
        $planes = PlanDeIntervencion::withoutGlobalScopes()->whereIn('id', $idsPlanes)->get();
        $pia = TipoPlan::where('slug', 'pia')->value('id');

        $this->assertCount(50, $planes);
        $this->assertTrue($planes->every(fn ($p) => $p->tipo_plan_id === $pia && $p->tipo->value === 'especializado' && $p->plan_asp_id === null));
        $this->assertSame(30, $planes->where('estado.value', 'activo')->count());
        $this->assertSame(20, $planes->where('estado.value', 'cerrado')->count());

        // Ninguna usuaria tiene PISO (plan general ASP).
        $this->assertSame(0, PlanDeIntervencion::withoutGlobalScopes()
            ->whereIn('historia_id', DB::table('historias_sociales')->whereIn('ciudadano_id', $idsCiudadanas)->pluck('id'))
            ->where('tipo', 'general_asp')->count());
    }

    /**
     * TF-DEMO-CIAM-16: ninguna ciudadana TEST_CIAM es de colectivo protegido ni tiene prestaciones de VG.
     *
     * Cuando se carga demo_ciam; entonces ninguna ciudadana registrada está marcada como VVG
     * ni como colectivo protegido, ninguna historia suya es protegida y no tienen prestaciones
     * (ni resumen de prestaciones ni planes de tipo violencia de género).
     */
    #[Test]
    public function tf_demo_ciam_16_ninguna_ciudadana_es_de_colectivo_protegido_ni_tiene_prestaciones_vg(): void
    {
        $this->assertSame(0, Artisan::call('demo:load', ['--world' => 'demo_ciam']), Artisan::output());

        $ids = DemoWorldRegistro::de('TEST_CIAM')->deTipo(Ciudadano::class)->pluck('registrable_id');
        $this->assertCount(100, $ids);

        $protegidas = DB::table('ciudadanos')->whereIn('id', $ids)
            ->where(fn ($q) => $q->where('es_vvg', true)
                ->orWhere('colectivo_extra_protegido', true)
                ->orWhereNotNull('colectivo_principal'))
            ->count();
        $this->assertSame(0, $protegidas);

        $this->assertSame(0, DB::table('historias_sociales')->whereIn('ciudadano_id', $ids)->where('ciudadano_protegido', true)->count());
        $this->assertSame(0, DB::table('ciudadano_prestaciones_resumen')->whereIn('ciudadano_id', $ids)->count());

        $tiposVg = DB::table('tipos_plan')->where('slug', 'like', '%violencia%')->pluck('id');
        $this->assertSame(0, DB::table('planes_intervencion')
            ->whereIn('historia_id', DB::table('historias_sociales')->whereIn('ciudadano_id', $ids)->pluck('id'))
            ->whereIn('tipo_plan_id', $tiposVg)->count());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Recuento de filas de las tablas vigiladas.
     *
     * @return array<string, int>
     */
    private function recuentos(): array
    {
        return collect(self::TABLAS)
            ->mapWithKeys(fn (string $tabla) => [$tabla => DB::table($tabla)->count()])
            ->all();
    }

    /**
     * Directorio temporal para YAML de prueba.
     */
    private function directorioTemporal(): string
    {
        $dir = sys_get_temp_dir().'/demo_aditivo_'.uniqid();
        mkdir($dir);

        return $dir;
    }
}
