<?php

namespace App\Support\Menu;

use App\Models\Ciudadano;
use App\Models\User;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Registro único de las entradas del menú operativo (R4.1 y R4.2 de la ronda 1 de staging).
 *
 * El menú es del usuario, no de la página: todas las pantallas operativas pintan el mismo, con
 * la unión de las entradas que dan sus permisos (los roles suman capacidades, principio 3.3).
 * Cada entrada declara el permiso o la policy que la muestra, el mismo que protege su ruta;
 * las secciones vacías no se pintan. La administración (`/admin`) no está aquí: va en el menú
 * de usuario (R4.3).
 */
final class RegistroMenu
{
    /** Secciones fijas, en orden de pintado. */
    public const SECCIONES = [
        'mi_trabajo' => 'Mi trabajo',
        'personas' => 'Personas',
        'citacion' => 'Citación',
        'tramitacion' => 'Tramitación',
        'supervision' => 'Supervisión',
    ];

    /**
     * Secciones con las entradas visibles para el usuario; sin las secciones vacías.
     *
     * @param User $usuario
     *
     * @return array<string, list<EntradaMenu>> Clave de sección => entradas.
     */
    public static function para(User $usuario): array
    {
        $secciones = [];
        foreach (self::entradas() as $entrada) {
            if ($entrada->visiblePara($usuario)) {
                $secciones[$entrada->seccion][] = $entrada;
            }
        }

        return array_filter(array_replace(array_fill_keys(array_keys(self::SECCIONES), []), $secciones));
    }

    /**
     * Todas las entradas del menú operativo.
     *
     * @return list<EntradaMenu>
     */
    public static function entradas(): array
    {
        $ruta = fn (string ...$nombres) => fn () => request()->routeIs(...$nombres);
        $permiso = fn (string $permiso) => fn (User $u) => $u->can($permiso);
        $supervisa = $permiso('supervision.panel');
        $trabajoPropio = $permiso('agenda.ver_propia');
        $tienePlazas = fn () => (bool) app(ConfiguracionService::class)->get('tiene_plazas', false);

        return [
            // Mi trabajo
            new EntradaMenu('agenda', 'mi_trabajo', 'Agenda', 'calendar',
                fn () => route('intervencion.agenda.index'), $ruta('intervencion.agenda*'), $trabajoPropio),
            new EntradaMenu('casos', 'mi_trabajo', 'Mis casos', 'users',
                fn () => route('intervencion.casos.index'), $ruta('intervencion.casos*'), $trabajoPropio, 'casos'),
            ...self::bandeja($supervisa, $trabajoPropio),
            new EntradaMenu('recursos', 'mi_trabajo', 'Recursos', 'building-office-2',
                fn () => route('intervencion.recursos.index'), $ruta('intervencion.recursos*'),
                fn (User $u) => $u->can('agenda.ver_propia') && $tienePlazas()),

            // Personas
            new EntradaMenu('buscador', 'personas', 'Buscar ciudadano/a', 'magnifying-glass',
                fn () => route('ciudadania.buscar'), $ruta('ciudadania.buscar', 'intervencion.buscar*'), $permiso('ciudadano.buscar')),
            new EntradaMenu('alta', 'personas', 'Alta de ciudadano/a', 'user-plus',
                fn () => route('ciudadania.alta'), $ruta('ciudadania.alta'), fn (User $u) => $u->can('create', Ciudadano::class)),

            // Citación: quien da citas o las supervisa
            new EntradaMenu('citacion', 'citacion', 'Bandeja de citación', 'calendar-days',
                fn () => route('agenda.citas.bandeja'), $ruta('agenda.citas*'),
                fn (User $u) => $u->can('citas.gestionar') || $u->can('citas.supervisar')),

            // Tramitación
            new EntradaMenu('tramitacion', 'tramitacion', 'Bandeja de tramitación', 'inbox-stack',
                fn () => route('prestaciones.bandeja'), $ruta('prestaciones.bandeja', 'prestaciones.solicitud'),
                $permiso('tramitacion.bandeja'), 'tramitacion'),

            // Supervisión
            new EntradaMenu('supervision.inicio', 'supervision', 'Inicio', 'home',
                fn () => route('supervision.inicio'), $ruta('supervision.inicio'), $supervisa),
            new EntradaMenu('supervision.control-alertas', 'supervision', 'Control de alertas', 'shield-exclamation',
                fn () => route('supervision.control-alertas'), $ruta('supervision.control-alertas'), $supervisa, 'escaladas', true),
            new EntradaMenu('supervision.cuadrante', 'supervision', 'Cuadrante del centro', 'calendar-days',
                fn () => route('supervision.cuadrante'), $ruta('supervision.cuadrante', 'agenda.cuadrante'), $supervisa),
            new EntradaMenu('supervision.ausencias', 'supervision', 'Ausencias', 'exclamation-triangle',
                fn () => route('agenda.supervisor.ausencias'), $ruta('agenda.supervisor.ausencias'), $supervisa, 'ausencias', true),
            new EntradaMenu('supervision.excepciones', 'supervision', 'Excepciones', 'calendar',
                fn () => route('agenda.supervisor.excepciones'), $ruta('agenda.supervisor.excepciones'), $supervisa),
            new EntradaMenu('supervision.eventos', 'supervision', 'Eventos internos', 'users',
                fn () => route('agenda.supervisor.eventos'), $ruta('agenda.supervisor.eventos'), $supervisa),
            new EntradaMenu('supervision.actividades', 'supervision', 'Actividades grupales', 'user-group',
                fn () => route('supervision.actividades'), $ruta('supervision.actividades*'), $supervisa),
            new EntradaMenu('supervision.plazas', 'supervision', 'Plazas', 'building-office',
                fn () => route('supervision.plazas'), $ruta('supervision.plazas'),
                fn (User $u) => $u->can('supervision.panel') && $tienePlazas()),
            new EntradaMenu('supervision.equipo', 'supervision', 'Mi equipo', 'users',
                fn () => route('supervision.equipo'), $ruta('supervision.equipo*'), $supervisa),
            new EntradaMenu('supervision.asignaciones', 'supervision', 'Asignaciones', 'arrows-right-left',
                fn () => route('supervision.asignaciones'), $ruta('supervision.asignaciones*'), $supervisa, 'asignaciones'),
            new EntradaMenu('supervision.derivaciones', 'supervision', 'Derivaciones', 'arrow-right-circle',
                fn () => route('supervision.derivaciones'), $ruta('supervision.derivaciones'), $supervisa),
            new EntradaMenu('supervision.auditoria', 'supervision', 'Accesos', 'shield-check',
                fn () => route('supervision.auditoria'), $ruta('supervision.auditoria'), $supervisa),
            new EntradaMenu('supervision.configuracion', 'supervision', 'Configuración', 'cog-6-tooth',
                fn () => route('supervision.configuracion'), $ruta('supervision.configuracion'), $supervisa),
        ];
    }

    /**
     * Las tres entradas de la bandeja (Alertas, Avisos, Mensajes), que abren la misma pantalla
     * con su pestaña. Hay dos rutas para esa pantalla: con la superficie de Supervisión se usa
     * la suya; si no, la de «Mi trabajo». Se pintan una sola vez aunque el usuario tenga ambas.
     *
     * @param \Closure(User): bool $supervisa
     * @param \Closure(User): bool $trabajoPropio
     *
     * @return list<EntradaMenu>
     */
    private static function bandeja(\Closure $supervisa, \Closure $trabajoPropio): array
    {
        $rutaDe = fn (User $u) => $u->can('supervision.panel') ? 'supervision.bandeja' : 'intervencion.mensajes.index';
        $visible = fn (User $u) => $supervisa($u) || $trabajoPropio($u);

        $entradas = [];
        foreach (['alertas' => ['Alertas', 'exclamation-triangle'], 'avisos' => ['Avisos', 'bell'], 'mensajes' => ['Mensajes', 'chat-bubble-left-right']] as $pestana => [$etiqueta, $icono]) {
            $entradas[] = new EntradaMenu(
                'bandeja.'.$pestana,
                'mi_trabajo',
                $etiqueta,
                $icono,
                fn (User $u) => route($rutaDe($u), $pestana),
                fn () => request()->routeIs('supervision.bandeja', 'intervencion.mensajes.index')
                    && (request()->route('pestana') ?? 'alertas') === $pestana,
                $visible,
                $pestana,
                $pestana === 'alertas',
            );
        }

        return $entradas;
    }
}
