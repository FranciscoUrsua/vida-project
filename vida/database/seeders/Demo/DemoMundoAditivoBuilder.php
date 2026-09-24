<?php

namespace Database\Seeders\Demo;

use App\Models\Ciudadano;
use App\Models\DemoWorldRegistro;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\Demo\Scenarios\CiamInformacion;
use Database\Seeders\Demo\Scenarios\CiamParticipanteActividad;
use Database\Seeders\Demo\Scenarios\CiamPiaActiva;
use Database\Seeders\Demo\Scenarios\CiamPiaCerrada;
use Database\Seeders\Demo\Scenarios\EscenarioCiam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Models\TipoActividad;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use Modules\Usuarios\Models\UsuarioRol;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Constructor de mundos demo en modo aditivo (`demo:load`).
 *
 * Añade el mundo a los datos existentes sin borrar ni modificar nada ajeno:
 * resuelve las referencias de 'existentes', y crea profesionales, actividades
 * y ciudadanas a través del DemoRegistrador, que etiqueta cada entidad creada
 * y hace la carga idempotente.
 *
 * La transacción, el dry-run y la protección de entorno son responsabilidad del
 * comando que lo invoca (DemoLoadCommand).
 *
 * @see DemoLoadCommand
 * @see DemoRegistrador
 */
class DemoMundoAditivoBuilder
{
    /** @var array<string, class-string<EscenarioCiam>> Escenario YAML → clase */
    private const ESCENARIOS = [
        'ciam_pia_activa' => CiamPiaActiva::class,
        'ciam_pia_cerrada' => CiamPiaCerrada::class,
        'ciam_participante_actividad' => CiamParticipanteActividad::class,
        'ciam_informacion' => CiamInformacion::class,
    ];

    /** Calles reales del distrito de Puente de Vallecas con su código postal. */
    private const CALLES = [
        ['Avenida', 'de la Albufera', '28038'],
        ['Calle', 'de Martínez de la Riva', '28018'],
        ['Calle', 'de Monte Igueldo', '28053'],
        ['Calle', 'de Peña Prieta', '28038'],
        ['Calle', 'del Puerto de Canfranc', '28038'],
        ['Calle', 'del Arroyo del Olivar', '28018'],
        ['Calle', 'de Sierra Carbonera', '28053'],
        ['Calle', 'de Picos de Europa', '28038'],
        ['Calle', 'de Villalobos', '28038'],
        ['Calle', 'de Pedro Laborde', '28038'],
        ['Avenida', 'de San Diego', '28053'],
        ['Calle', 'del Payaso Fofó', '28018'],
        ['Calle', 'de Carlos Martín Álvarez', '28018'],
        ['Calle', 'de Ramón Pérez de Ayala', '28038'],
    ];

    /** @var callable|null Función para mensajes de progreso */
    private mixed $output;

    /**
     * @param DemoRegistrador $registrador Registro de lo creado por el mundo
     * @param callable|null $output Callable para mensajes de progreso
     */
    public function __construct(private readonly DemoRegistrador $registrador, ?callable $output = null)
    {
        $this->output = $output;
    }

    /**
     * Construye el mundo aditivo completo.
     *
     * @param array<string, mixed> $worldConfig Configuración validada por DemoWorldLoader (modo aditivo)
     *
     * @return list<int> Ids de los planes del mundo (para el verificador de invariantes)
     *
     * @throws \RuntimeException Si una referencia falla o un correo ya pertenece a otro usuario
     */
    public function build(array $worldConfig): array
    {
        $this->line('Resolviendo referencias a entidades existentes...');
        $refs = (new DemoReferenciaResolver($this->registrador))->resolver($worldConfig['existentes']);

        $this->line('Creando profesionales...');
        $profesionales = $this->buildProfesionales($worldConfig['profesionales'], $refs);

        $this->line('Creando actividades y sesiones...');
        $sesiones = $this->buildActividades($worldConfig['actividades'] ?? [], $refs, $profesionales);

        $centro = $this->centroPrincipal($worldConfig, $refs);
        /** @var TipoPlan|null $tipoPlan */
        $tipoPlan = isset($worldConfig['tipo_plan']) ? $refs['tipos_plan'][$worldConfig['tipo_plan']] : null;

        $ctx = new DemoContextoAditivo(
            $this->registrador,
            $centro,
            $this->uoDeCentro($centro),
            $tipoPlan,
            $profesionales,
            $sesiones,
        );

        $this->line('Creando ciudadanas y escenarios...');
        $this->buildEscenarios($worldConfig['escenarios'] ?? [], $profesionales, $ctx);

        // Devolver el generador global a un estado no determinista tras sembrarlo por ciudadana.
        mt_srand();

        return DemoWorldRegistro::de($this->registrador->etiqueta())
            ->deTipo(PlanDeIntervencion::class)
            ->pluck('registrable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Crea usuarios, perfiles profesionales, adscripciones y roles del mundo.
     *
     * Los roles se crean como UsuarioRol en estado 'activo' (ya aprobados, también
     * los de aprobación previa como 'supervision'): el observer los sincroniza con
     * Spatie y así resisten `usuarios:reconciliar-roles`. Las cuentas no pasan por
     * el onboarding (primer_acceso = false), igual que en los mundos de reset.
     *
     * @param list<array<string, mixed>> $config Profesionales del YAML
     * @param array<string, array<string, Model>> $refs Referencias resueltas
     *
     * @return array<string, User> Usuarios por login
     *
     * @throws \RuntimeException Si un correo ya pertenece a un usuario ajeno al mundo
     */
    private function buildProfesionales(array $config, array $refs): array
    {
        $tipoRelacionId = TipoRelacionProfesional::where('nombre', 'like', '%Funcionario%')->value('id')
            ?? TipoRelacionProfesional::activos()->value('id');

        $usuarios = [];

        foreach ($config as $prof) {
            $login = $prof['login'];
            /** @var Centro $centro */
            $centro = $refs['centros'][$prof['centro']];
            /** @var Cargo $cargo */
            $cargo = $refs['cargos'][$prof['cargo']];
            $uo = $this->uoDeCentro($centro);

            // Nunca se sobrescribe un usuario que no ha creado este mundo.
            if ($this->registrador->buscar("{$login}.usuario", User::class) === null
                && User::where('email', $login)->exists()) {
                throw new \RuntimeException(
                    "Ya existe un usuario con el correo '{$login}' que no pertenece a ".
                    "{$this->registrador->etiqueta()}. No se sobrescribe."
                );
            }

            $profesional = $this->registrador->obtenerOCrear("{$login}.profesional", Profesional::class, fn () => Profesional::create([
                'nombre' => $prof['nombre'],
                'apellido1' => $prof['apellido1'],
                'apellido2' => $prof['apellido2'] ?? null,
                'sexo' => $prof['sexo'] ?? 'F',
                'cargo_id' => $cargo->id,
                'tipo_relacion_id' => $tipoRelacionId,
                'email_profesional' => $login,
                'fecha_inicio' => today(),
                'activo' => true,
                'unidad_organizativa_id' => $uo->id,
            ]));

            $user = $this->registrador->obtenerOCrear("{$login}.usuario", User::class, function () use ($login, $prof, $profesional) {
                // Sin profesional_id al crear: User::booted() auto-asigna consulta_basica a los
                // usuarios con profesional creados sin roles, y el mundo solo debe tener los roles
                // declarados en el YAML. Se vincula el profesional justo después (mismo patrón
                // que DemoWorldBuilder). booted() también fuerza name = email al crear.
                $user = User::create([
                    'email' => $login,
                    'password' => $prof['password'] ?? 'demo1234',
                    'email_verified_at' => now(),
                    'primer_acceso' => false,
                ]);

                $user->update([
                    'name' => trim("{$prof['nombre']} {$prof['apellido1']} ".($prof['apellido2'] ?? '')),
                    'profesional_id' => $profesional->id,
                ]);

                return $user;
            });

            $this->registrador->obtenerOCrear("{$login}.adscripcion", UsuarioUo::class, fn () => UsuarioUo::create([
                'usuario_id' => $user->id,
                'unidad_organizativa_id' => $uo->id,
                'tipo_vinculo' => 'interno',
                'fecha_inicio' => today(),
            ]));

            foreach ($prof['roles'] as $nombreRol) {
                $rol = Role::firstOrCreate(['name' => $nombreRol, 'guard_name' => 'web']);

                $this->registrador->obtenerOCrear("{$login}.rol.{$nombreRol}", UsuarioRol::class, fn () => UsuarioRol::create([
                    'usuario_id' => $user->id,
                    'rol_id' => $rol->id,
                    'fecha_inicio' => today(),
                    'estado' => 'activo',
                ]));

                // Red de seguridad por si el observer no sincronizó. El observer asigna el rol
                // sobre otra instancia de User: se recarga la relación para no leerla obsoleta.
                if (! $user->load('roles')->hasRole($nombreRol)) {
                    $user->assignRole($nombreRol);
                }
            }

            $usuarios[$login] = $user;
            $this->line("  {$login} (".implode(', ', $prof['roles']).')');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuarios;
    }

    /**
     * Crea las actividades del mundo y sus sesiones.
     *
     * El estado de cada sesión se deduce de su fecha si el YAML no lo indica:
     * pasada → 'celebrada', hoy o futura → 'programada'.
     *
     * @param list<array<string, mixed>> $config Actividades del YAML
     * @param array<string, array<string, Model>> $refs Referencias resueltas
     * @param array<string, User> $profesionales Usuarios por login
     *
     * @return array<string, list<SesionActividad>> Sesiones por id de actividad del YAML
     */
    private function buildActividades(array $config, array $refs, array $profesionales): array
    {
        $sesionesPorActividad = [];

        foreach ($config as $act) {
            /** @var Centro $centro */
            $centro = $refs['centros'][$act['centro']];
            /** @var TipoActividad $tipo */
            $tipo = $refs['tipos_actividad'][$act['tipo']];

            $actividad = $this->registrador->obtenerOCrear("actividad.{$act['id']}", Actividad::class, fn () => Actividad::create([
                'centro_id' => $centro->id,
                'tipo_actividad_id' => $tipo->id,
                'nombre' => $act['nombre'],
                'descripcion' => $act['descripcion'] ?? null,
                'modo_acceso' => $act['modo_acceso'],
                'aforo_total' => $act['aforo_total'] ?? null,
                'aforo_prescripcion' => $act['aforo_prescripcion'] ?? null,
                'requiere_inscripcion_centro' => $act['requiere_inscripcion_centro'] ?? false,
                'activa' => true,
                'fecha_alta' => today(),
            ]));

            $actividad->profesionales()->syncWithoutDetaching($this->idsProfesional($act['profesionales'] ?? [], $profesionales));

            $sesionesPorActividad[$act['id']] = [];

            foreach ($act['sesiones'] ?? [] as $n => $ses) {
                $fecha = today()->addDays((int) rtrim($ses['fecha'], 'd'));
                /** @var Sala|null $sala */
                $sala = isset($ses['sala']) ? $refs['salas'][$ses['sala']] : null;

                $sesion = $this->registrador->obtenerOCrear(
                    "actividad.{$act['id']}.sesion_".($n + 1),
                    SesionActividad::class,
                    fn () => SesionActividad::create([
                        'actividad_id' => $actividad->id,
                        'fecha' => $fecha->toDateString(),
                        'hora_inicio' => $ses['hora_inicio'],
                        'hora_fin' => $ses['hora_fin'] ?? null,
                        'estado' => $ses['estado'] ?? ($fecha->lt(today()) ? 'celebrada' : 'programada'),
                        'sala_id' => $sala?->id,
                    ])
                );

                $sesion->profesionales()->syncWithoutDetaching(
                    $this->idsProfesional($ses['profesionales'] ?? $act['profesionales'] ?? [], $profesionales)
                );

                $sesionesPorActividad[$act['id']][] = $sesion;
            }

            $this->line("  {$act['nombre']} (".count($act['sesiones'] ?? []).' sesiones)');
        }

        return $sesionesPorActividad;
    }

    /**
     * Crea las ciudadanas y construye su escenario.
     *
     * Cada ciudadana recibe una clave estable "usuaria_NNN" según su orden en el YAML;
     * esa clave es la base de la idempotencia y de la semilla del azar.
     *
     * @param list<array{profesional: string, ciudadanos: list<array{escenario: string, cantidad: int}>}> $config Escenarios del YAML
     * @param array<string, User> $profesionales Usuarios por login
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    private function buildEscenarios(array $config, array $profesionales, DemoContextoAditivo $ctx): void
    {
        $n = 0;

        foreach ($config as $entrada) {
            $responsable = $profesionales[$entrada['profesional']];

            foreach ($entrada['ciudadanos'] as $grupo) {
                $escenario = new (self::ESCENARIOS[$grupo['escenario']]);

                for ($i = 0; $i < $grupo['cantidad']; $i++) {
                    $clave = sprintf('usuaria_%03d', ++$n);
                    $ctx->sembrar($clave);

                    $ciudadana = $this->registrador->obtenerOCrear($clave, Ciudadano::class, fn () => $this->crearCiudadana($ctx));
                    $escenario->construir($clave, $ciudadana, $responsable, $ctx);
                }

                $this->line("  {$grupo['cantidad']}x {$grupo['escenario']} → {$entrada['profesional']}");
            }
        }
    }

    /**
     * Crea una ciudadana ficticia: mujer, 18–75 años, domicilio en Puente de Vallecas.
     *
     * Los datos personales se cifran por el cast 'encrypted' del modelo. Sin marca
     * de VVG ni de colectivo protegido (decisión explícita de esta fase). Sin documento
     * de identidad: los mundos demo existentes no generan ninguno.
     *
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo (Faker sembrado)
     */
    private function crearCiudadana(DemoContextoAditivo $ctx): Ciudadano
    {
        $f = $ctx->faker;
        [$tipoVia, $nombreVia, $cp] = $f->randomElement(self::CALLES);
        $numero = (string) $f->numberBetween(1, 120);
        $piso = $f->numberBetween(1, 8).'º';
        $puerta = $f->randomElement(['A', 'B', 'C', 'D', 'Izda.', 'Dcha.']);
        $telefono = '6'.$f->numerify('########');

        return Ciudadano::withoutGlobalScopes()->create([
            'nombre' => $f->firstNameFemale(),
            'apellido1' => $f->lastName(),
            'apellido2' => $f->lastName(),
            'fecha_nacimiento' => Carbon::instance($f->dateTimeBetween('-75 years', '-18 years'))->toDateString(),
            // Catálogo de sexo: M = masculino, F = femenino, D = no especificado.
            'sexo' => 'F',
            'direccion_texto' => "{$tipoVia} {$nombreVia}, {$numero}, {$piso} {$puerta}, {$cp} Madrid",
            'tipo_via' => $tipoVia,
            'nombre_via' => $nombreVia,
            'tipo_numeracion' => 'numero',
            'numero' => $numero,
            'piso' => $piso,
            'puerta' => $puerta,
            'codigo_postal' => $cp,
            'municipio' => 'Madrid',
            'telefono' => $telefono,
            'telefono_hash' => hash('sha256', $telefono),
            'nivel_identificacion' => 'identificado',
            'activo' => true,
            'es_vvg' => false,
            'es_psh' => false,
            'colectivo_extra_protegido' => false,
        ]);
    }

    /**
     * Centro donde se atiende a las ciudadanas: el del primer profesional del mundo.
     *
     * @param array<string, mixed> $worldConfig Configuración del mundo
     * @param array<string, array<string, Model>> $refs Referencias resueltas
     */
    private function centroPrincipal(array $worldConfig, array $refs): Centro
    {
        /** @var Centro */
        return $refs['centros'][$worldConfig['profesionales'][0]['centro']];
    }

    /**
     * UO del centro referenciado.
     *
     * @param Centro $centro Centro referenciado
     *
     * @throws \RuntimeException Si el centro no tiene UO
     */
    private function uoDeCentro(Centro $centro): UnidadOrganizativa
    {
        return UnidadOrganizativa::find($centro->unidad_organizativa_id)
            ?? throw new \RuntimeException("El centro '{$centro->nombre}' no tiene Unidad Organizativa.");
    }

    /**
     * Ids de Profesional de una lista de logins.
     *
     * @param list<string> $logins Logins del YAML
     * @param array<string, User> $profesionales Usuarios por login
     *
     * @return list<int>
     */
    private function idsProfesional(array $logins, array $profesionales): array
    {
        return collect($logins)
            ->map(fn (string $login) => $profesionales[$login]->profesional_id ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Emite un mensaje de progreso si hay callable de output configurado.
     *
     * @param string $message Mensaje
     */
    private function line(string $message): void
    {
        if ($this->output !== null) {
            ($this->output)($message);
        }
    }
}
