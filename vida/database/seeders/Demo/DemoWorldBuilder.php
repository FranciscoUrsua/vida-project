<?php

namespace Database\Seeders\Demo;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Modules\Centro\Models\Centro;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Constructor de infraestructura de mundo demo.
 *
 * A partir de la configuración validada por DemoWorldLoader,
 * crea (o actualiza) las Unidades Organizativas y los Usuarios
 * profesionales con sus roles y adscripciones de UO.
 *
 * No crea ciudadanos ni escenarios — esa responsabilidad es de DemoScenarioBuilder.
 *
 * @see DemoWorldLoader
 * @see DemoScenarioBuilder
 */
class DemoWorldBuilder
{
    /** @var callable|null Función para avisos no fatales */
    private mixed $output;

    /**
     * @param callable|null $output Callable para avisos no fatales
     */
    public function __construct(?callable $output = null)
    {
        $this->output = $output;
    }

    /**
     * Construye la infraestructura del mundo: UOs y usuarios profesionales.
     *
     * @param array{
     *   meta: array{nombre: string, descripcion: string, reset_cada: string},
     *   centros: list<array{id: string, nombre: string, tipo: string, distrito: string}>,
     *   profesionales: list<array{login: string, nombre: string, rol: string, centro: string}>,
     *   escenarios: list<mixed>
     * } $worldConfig Configuración validada del mundo
     *
     * @return array{
     *   unidades: array<string, UnidadOrganizativa>,
     *   profesionales: array<string, User>
     * } Índices de UOs por id YAML y usuarios por login
     */
    public function build(array $worldConfig): array
    {
        $unidades = $this->buildCentros($worldConfig['centros']);
        $profesionales = $this->buildProfesionales($worldConfig['profesionales'], $unidades);

        // Limpiar caché de Spatie para que el siguiente request relea roles desde BD
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'unidades' => $unidades,
            'profesionales' => $profesionales,
        ];
    }

    /**
     * Crea las Unidades Organizativas y los Centros definidos en el YAML.
     *
     * Cada entrada del YAML genera dos entidades:
     * - UnidadOrganizativa: ubica el centro en la jerarquía organizativa.
     * - Centro: representa la entidad operativa con sus atributos propios.
     *
     * El tipo YAML 'asp' se mapea a UO tipo 'css' (Centro de Servicios Sociales);
     * 'especializada' se mantiene como 'especializada'. Ambos usan gestión
     * 'municipal_directo' por defecto en entornos de demo.
     *
     * Los centros del mundo anterior son truncados por DemoResetCommand antes
     * de llamar a este método, por lo que se usa create() en lugar de firstOrCreate().
     *
     * @param list<array{id: string, nombre: string, tipo: string, distrito: string}> $centros
     *
     * @return array<string, UnidadOrganizativa> Indexado por id YAML
     */
    private function buildCentros(array $centros): array
    {
        $unidades = [];

        foreach ($centros as $centro) {
            $tipoUo = $centro['tipo'] === 'asp' ? 'css' : 'especializada';

            // firstOrCreate garantiza IDs estables de UO entre resets del mismo mundo.
            $uo = UnidadOrganizativa::firstOrCreate(
                ['nombre' => $centro['nombre'], 'tipo' => $tipoUo],
                ['parent_id' => null, 'activa' => true]
            );

            // Crear la entidad Centro vinculada a la UO (la tabla se truncó al inicio del reset).
            Centro::create([
                'nombre' => $centro['nombre'],
                'tipo_gestion' => 'municipal_directo',
                'unidad_organizativa_id' => $uo->id,
                'activo' => true,
                'fecha_alta' => today(),
            ]);

            $unidades[$centro['id']] = $uo;

            $this->warn("  UO + Centro: {$centro['nombre']} (uo_id={$uo->id}, tipo={$tipoUo})");
        }

        return $unidades;
    }

    /**
     * Crea o actualiza los usuarios y profesionales del mundo.
     *
     * Para cada entrada YAML genera o actualiza tres entidades:
     * - User: cuenta de acceso (email como clave, contraseña solo en creación)
     * - Profesional: perfil organizativo vinculado al User mediante profesional_id
     * - UsuarioUo: adscripción a la UO del centro asignado
     *
     * El cargo se infiere del rol YAML; sexo y tipo de relación usan valores
     * por defecto apropiados para entornos de demo.
     *
     * @param list<array{login: string, nombre: string, rol: string, centro: string}> $profesionalesConfig
     * @param array<string, UnidadOrganizativa> $unidades Indexado por id YAML
     *
     * @return array<string, User> Indexado por login
     */
    private function buildProfesionales(array $profesionalesConfig, array $unidades): array
    {
        $profesionales = [];

        // IDs de UO válidas en este mundo — se usan para limpiar adscripciones obsoletas
        $uoIdsValidos = array_map(fn ($uo) => $uo->id, $unidades);

        // Catálogos necesarios para el Profesional — resolvemos una sola vez
        $cargoTrabSocial = Cargo::where('nombre', 'like', '%Trabajador%')->first()?->id
            ?? Cargo::activos()->value('id');
        $cargoCoordinador = Cargo::where('nombre', 'like', '%Coordinador%')->first()?->id
            ?? $cargoTrabSocial;
        $cargoAuxiliar = Cargo::where('nombre', 'like', '%Auxiliar%Servicios%')->first()?->id
            ?? $cargoTrabSocial;
        $cargoPorRol = [
            'supervisor' => $cargoCoordinador,
            'intervencion' => $cargoTrabSocial,
            'consulta_basica' => $cargoAuxiliar,
        ];
        $tipoRelacionId = TipoRelacionProfesional::where('nombre', 'like', '%Funcionario%')->first()?->id
            ?? TipoRelacionProfesional::activos()->value('id');

        foreach ($profesionalesConfig as $profConfig) {
            // La contraseña solo se establece en la creación; updateOrCreate sin
            // 'password' en los valores de actualización evita re-hashear bcrypt
            // en cada reset para usuarios ya existentes (~200 ms por usuario).
            $existente = User::where('email', $profConfig['login'])->first();

            if ($existente !== null) {
                $existente->update([
                    'name' => $profConfig['nombre'],
                    'email_verified_at' => now(),
                    'primer_acceso' => false,
                ]);
                $user = $existente;
            } else {
                $user = User::create([
                    'email' => $profConfig['login'],
                    'name' => $profConfig['nombre'],
                    'password' => 'demo1234',
                    'email_verified_at' => now(),
                    'primer_acceso' => false,
                ]);
            }

            // Crear o actualizar el Profesional vinculado al User
            [$nombre, $apellido1, $apellido2] = $this->partirNombre($profConfig['nombre']);
            $cargoId = $cargoPorRol[$profConfig['rol']] ?? $cargoTrabSocial;

            $profesionalExistente = $user->profesional_id !== null
                ? Profesional::find($user->profesional_id)
                : null;

            if ($profesionalExistente !== null) {
                $profesionalExistente->update([
                    'nombre' => $nombre,
                    'apellido1' => $apellido1,
                    'apellido2' => $apellido2,
                    'cargo_id' => $cargoId,
                    'tipo_relacion_id' => $tipoRelacionId,
                    'activo' => true,
                ]);
            } else {
                $nuevoProfesional = Profesional::create([
                    'nombre' => $nombre,
                    'apellido1' => $apellido1,
                    'apellido2' => $apellido2,
                    'sexo' => 'D',
                    'cargo_id' => $cargoId,
                    'tipo_relacion_id' => $tipoRelacionId,
                    'fecha_inicio' => today(),
                    'activo' => true,
                ]);
                $user->update(['profesional_id' => $nuevoProfesional->id]);
            }

            // Garantizar que el rol existe antes de asignarlo
            Role::firstOrCreate(['name' => $profConfig['rol'], 'guard_name' => 'web']);

            // Establecer el conjunto de roles (syncRoles para no acumular)
            $user->syncRoles([$profConfig['rol']]);

            $uoId = $unidades[$profConfig['centro']]->id;

            // Eliminar adscripciones obsoletas a UOs que no pertenecen a este mundo
            UsuarioUo::where('usuario_id', $user->id)
                ->whereNotIn('unidad_organizativa_id', $uoIdsValidos)
                ->delete();

            UsuarioUo::updateOrCreate(
                [
                    'usuario_id' => $user->id,
                    'unidad_organizativa_id' => $uoId,
                ],
                [
                    'tipo_vinculo' => 'interno',
                    'fecha_inicio' => today(),
                ]
            );

            $profesionales[$profConfig['login']] = $user;

            $this->warn("  User + Profesional: {$profConfig['nombre']} ({$profConfig['login']}, rol={$profConfig['rol']})");
        }

        return $profesionales;
    }

    /**
     * Divide un nombre completo en nombre, primer apellido y segundo apellido.
     *
     * Ejemplos:
     *   "Ana López García"  → ['Ana', 'López', 'García']
     *   "Laura Supervisora" → ['Laura', 'Supervisora', null]
     *
     * @return array{string, string, string|null}
     */
    private function partirNombre(string $nombreCompleto): array
    {
        $partes = explode(' ', trim($nombreCompleto), 3);

        return [
            $partes[0],
            $partes[1] ?? '-',
            $partes[2] ?? null,
        ];
    }

    /**
     * Emite un aviso si hay callable de output configurado.
     *
     * @param string $message Mensaje del aviso
     */
    private function warn(string $message): void
    {
        if ($this->output !== null) {
            ($this->output)($message);
        }
    }
}
