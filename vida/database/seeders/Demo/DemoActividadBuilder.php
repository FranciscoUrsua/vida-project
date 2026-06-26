<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Models\TipoActividad;

/**
 * Constructor de actividades grupales y sus sesiones para mundos demo.
 *
 * A partir de la sección 'actividades' del YAML, crea Actividad,
 * SesionActividad y los registros pivot de profesionales, usando
 * los índices de centros, salas y profesionales ya construidos por
 * DemoWorldBuilder.
 *
 * Las fechas de sesión se expresan como offsets relativos al día de
 * ejecución del reset (ej. '-14d', '0d', '+7d').
 *
 * @see DemoWorldBuilder
 * @see DemoWorldLoader
 */
class DemoActividadBuilder
{
    /** @var callable|null Función para avisos no fatales */
    private mixed $output;

    /**
     * @param callable|null $output Callable para avisos no fatales (p.ej. $this->warn(...))
     */
    public function __construct(?callable $output = null)
    {
        $this->output = $output;
    }

    /**
     * Construye todas las actividades y sus sesiones definidas en el YAML.
     *
     * Para cada entrada de actividad:
     * 1. Resuelve el Centro por id YAML.
     * 2. Resuelve el TipoActividad por slug (falla si no existe en catálogo).
     * 3. Crea la Actividad y asocia los profesionales coordinadores.
     * 4. Crea cada SesionActividad con su sala y profesionales de sesión.
     *
     * @param list<array{centro: string, tipo: string, nombre: string, modo_acceso: string, descripcion?: string, aforo_total?: int, aforo_prescripcion?: int, requiere_inscripcion_centro?: bool, notas?: string, profesionales?: list<string>, sesiones?: list<array{fecha: string, hora_inicio: string, hora_fin?: string, estado: string, sala?: string, aforo_total?: int, notas?: string, profesionales?: list<string>}>}> $actividadesConfig
     * @param array<string, Centro> $centros Indexado por id YAML
     * @param array<string, Sala> $salas Indexado por id YAML
     * @param array<string, User> $profesionales Indexado por login
     *
     * @throws \RuntimeException Si un slug de TipoActividad no existe en la BD
     */
    public function buildActividades(
        array $actividadesConfig,
        array $centros,
        array $salas,
        array $profesionales
    ): void {
        if (empty($actividadesConfig)) {
            return;
        }

        /** @var array<string, TipoActividad|null> Cache de tipos por slug para evitar queries repetidas */
        $tipoCache = [];

        foreach ($actividadesConfig as $idx => $config) {
            $pos = $idx + 1;
            $centro = $centros[$config['centro']] ?? null;

            if ($centro === null) {
                $this->warn("  [DemoActividadBuilder] Centro '{$config['centro']}' no encontrado — actividad #{$pos} omitida.");

                continue;
            }

            $slug = $config['tipo'];

            if (! array_key_exists($slug, $tipoCache)) {
                $tipoCache[$slug] = TipoActividad::where('slug', $slug)->first();
            }

            $tipoActividad = $tipoCache[$slug];

            if ($tipoActividad === null) {
                throw new \RuntimeException(
                    "TipoActividad con slug '{$slug}' no existe en la base de datos. ".
                    'Ejecuta CentroSeeder antes de construir el mundo.'
                );
            }

            $actividad = Actividad::create([
                'centro_id' => $centro->id,
                'tipo_actividad_id' => $tipoActividad->id,
                'nombre' => $config['nombre'],
                'descripcion' => $config['descripcion'] ?? null,
                'modo_acceso' => $config['modo_acceso'],
                'aforo_total' => $config['aforo_total'] ?? null,
                'aforo_prescripcion' => $config['aforo_prescripcion'] ?? null,
                'requiere_inscripcion_centro' => $config['requiere_inscripcion_centro'] ?? false,
                'activa' => true,
                'fecha_alta' => today(),
                'notas' => $config['notas'] ?? null,
            ]);

            $this->warn("  Actividad: {$config['nombre']} (tipo={$slug}, centro={$config['centro']})");

            $this->adjuntarProfesionalesActividad($actividad, $config['profesionales'] ?? [], $profesionales);

            foreach ($config['sesiones'] ?? [] as $sesionConfig) {
                $this->buildSesion($actividad, $sesionConfig, $salas, $profesionales);
            }
        }
    }

    /**
     * Crea una SesionActividad y asocia sala y profesionales.
     *
     * @param Actividad $actividad Actividad a la que pertenece la sesión
     * @param array{fecha: string, hora_inicio: string, hora_fin?: string, estado: string, sala?: string, aforo_total?: int, notas?: string, profesionales?: list<string>} $config
     * @param array<string, Sala> $salas Indexado por id YAML
     * @param array<string, User> $profesionales Indexado por login
     */
    private function buildSesion(
        Actividad $actividad,
        array $config,
        array $salas,
        array $profesionales
    ): void {
        $salaId = null;

        if (! empty($config['sala'])) {
            $sala = $salas[$config['sala']] ?? null;

            if ($sala !== null) {
                $salaId = $sala->id;
            } else {
                $this->warn("    [DemoActividadBuilder] Sala '{$config['sala']}' no encontrada — sesión sin sala.");
            }
        }

        $sesion = SesionActividad::create([
            'actividad_id' => $actividad->id,
            'fecha' => $this->resolverFecha($config['fecha'])->toDateString(),
            'hora_inicio' => $config['hora_inicio'],
            'hora_fin' => $config['hora_fin'] ?? null,
            'aforo_total' => $config['aforo_total'] ?? null,
            'estado' => $config['estado'],
            'sala_id' => $salaId,
            'notas' => $config['notas'] ?? null,
        ]);

        $this->adjuntarProfesionalesSesion($sesion, $config['profesionales'] ?? [], $profesionales);
    }

    /**
     * Asocia profesionales a una actividad mediante el pivot actividad_profesional.
     *
     * @param Actividad $actividad Actividad destino
     * @param list<string> $logins Logins declarados en el YAML
     * @param array<string, User> $profesionalesMap Indexado por login
     */
    private function adjuntarProfesionalesActividad(
        Actividad $actividad,
        array $logins,
        array $profesionalesMap
    ): void {
        foreach ($logins as $login) {
            $user = $profesionalesMap[$login] ?? null;

            if ($user !== null && $user->profesional_id !== null) {
                $actividad->profesionales()->attach($user->profesional_id);
            }
        }
    }

    /**
     * Asocia profesionales a una sesión mediante el pivot sesion_actividad_profesional.
     *
     * @param SesionActividad $sesion Sesión destino
     * @param list<string> $logins Logins declarados en el YAML
     * @param array<string, User> $profesionalesMap Indexado por login
     */
    private function adjuntarProfesionalesSesion(
        SesionActividad $sesion,
        array $logins,
        array $profesionalesMap
    ): void {
        foreach ($logins as $login) {
            $user = $profesionalesMap[$login] ?? null;

            if ($user !== null && $user->profesional_id !== null) {
                $sesion->profesionales()->attach($user->profesional_id);
            }
        }
    }

    /**
     * Convierte un offset relativo en string (ej. '-14d', '0d', '+7d') a Carbon.
     *
     * @param string $fechaStr Offset relativo con sufijo 'd'
     *
     * @return Carbon Fecha calculada desde hoy
     */
    private function resolverFecha(string $fechaStr): Carbon
    {
        $days = (int) rtrim($fechaStr, 'd');

        return now()->addDays($days)->startOfDay();
    }

    /**
     * Emite un aviso no fatal si hay un callable de output configurado.
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
