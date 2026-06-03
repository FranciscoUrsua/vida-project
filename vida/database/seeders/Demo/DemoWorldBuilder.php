<?php

namespace Database\Seeders\Demo;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;

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

        return [
            'unidades' => $unidades,
            'profesionales' => $profesionales,
        ];
    }

    /**
     * Crea las Unidades Organizativas definidas en el YAML.
     *
     * El tipo YAML 'asp' se mapea a 'css' (Centro de Servicios Sociales);
     * 'especializada' se mantiene como 'especializada'.
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

            $uo = UnidadOrganizativa::create([
                'nombre' => $centro['nombre'],
                'tipo' => $tipoUo,
                'parent_id' => null,
                'activa' => true,
            ]);

            $unidades[$centro['id']] = $uo;

            $this->warn("  UO creada: {$centro['nombre']} (id={$uo->id}, tipo={$tipoUo})");
        }

        return $unidades;
    }

    /**
     * Crea o actualiza los usuarios profesionales del mundo.
     *
     * Para cada profesional:
     * - Crea o actualiza el User (email como clave)
     * - Asigna el rol Spatie correspondiente
     * - Crea o actualiza la adscripción a la UO (UsuarioUo)
     *
     * Mapeo de roles YAML → roles Spatie:
     * - 'supervisor'      → 'supervisor'
     * - 'intervencion'    → 'intervencion'
     * - 'consulta_basica' → 'consulta_basica'
     *
     * @param list<array{login: string, nombre: string, rol: string, centro: string}> $profesionalesConfig
     * @param array<string, UnidadOrganizativa> $unidades Indexado por id YAML
     *
     * @return array<string, User> Indexado por login
     */
    private function buildProfesionales(array $profesionalesConfig, array $unidades): array
    {
        $profesionales = [];

        foreach ($profesionalesConfig as $profConfig) {
            $user = User::updateOrCreate(
                ['email' => $profConfig['login']],
                [
                    'name' => $profConfig['nombre'],
                    'password' => 'demo1234',
                    'email_verified_at' => now(),
                    'primer_acceso' => false,
                ]
            );

            // Establecer el conjunto de roles (syncRoles para no acumular)
            $user->syncRoles([$profConfig['rol']]);

            $uoId = $unidades[$profConfig['centro']]->id;

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

            $this->warn("  Profesional: {$profConfig['nombre']} ({$profConfig['login']}, rol={$profConfig['rol']})");
        }

        return $profesionales;
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
