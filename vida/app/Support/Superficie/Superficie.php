<?php

namespace App\Support\Superficie;

use App\Models\User;

/**
 * Superficies operativas en las que un usuario puede empezar su jornada (R4 de la ronda 1 de
 * staging, `docs/modulo-usuarios-permisos.md` «Superficie operativa y página de inicio»).
 *
 * Es un enum porque el código decide con él: la prioridad de la página de inicio, qué opciones
 * de preferencia se ofrecen y si la preferencia guardada sigue siendo válida. El acceso a cada
 * superficie se decide por un permiso atómico, nunca por el rol: los roles se configuran en el
 * backoffice y solo los permisos se comprueban en código (R4.4).
 *
 * La administración (`/admin`) no es una superficie operativa ni se puede elegir (R4.3, R4.6).
 */
enum Superficie: string
{
    case Supervision = 'supervision';
    case Agenda = 'agenda';
    case Encargos = 'encargos';
    case Tramitacion = 'tramitacion';
    case Citacion = 'citacion';
    case Buscador = 'buscador';

    /**
     * Orden de la página de inicio por defecto (R4.5). El buscador no entra: solo es elegible
     * como preferencia. «Mis derivaciones» va después de Agenda (EP-10 del encargo profesional).
     *
     * @return list<self>
     */
    public static function prioridad(): array
    {
        return [self::Supervision, self::Agenda, self::Encargos, self::Tramitacion, self::Citacion];
    }

    /**
     * Superficies a las que el usuario tiene acceso, en el orden de la enumeración.
     *
     * @param User $usuario
     *
     * @return list<self>
     */
    public static function accesiblesPara(User $usuario): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->accesiblePara($usuario)));
    }

    /**
     * Nombre visible de la superficie en el menú de usuario.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Supervision => 'Inicio de Supervisión',
            self::Agenda => 'Agenda',
            self::Encargos => 'Mis derivaciones',
            self::Tramitacion => 'Bandeja de tramitación',
            self::Citacion => 'Bandeja de citación',
            self::Buscador => 'Buscador',
        };
    }

    /**
     * Si el usuario puede abrir la superficie: el mismo permiso que protege su ruta.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function accesiblePara(User $usuario): bool
    {
        return match ($this) {
            self::Supervision => $usuario->can('supervision.panel'),
            self::Agenda => $usuario->can('agenda.ver_propia'),
            self::Encargos => $usuario->can('derivacion.ver_asignadas'),
            self::Tramitacion => $usuario->can('tramitacion.bandeja'),
            // La bandeja de citación la abre quien da citas o quien las supervisa
            self::Citacion => $usuario->can('citas.gestionar') || $usuario->can('citas.supervisar'),
            self::Buscador => $usuario->can('ciudadano.buscar'),
        };
    }

    /**
     * URL de la superficie.
     *
     * @return string
     */
    public function url(): string
    {
        return match ($this) {
            self::Supervision => route('supervision.inicio'),
            self::Agenda => route('intervencion.agenda.index'),
            self::Encargos => route('intervencion.encargos.index'),
            self::Tramitacion => route('prestaciones.bandeja'),
            self::Citacion => route('agenda.citas.bandeja'),
            self::Buscador => route('ciudadania.buscar'),
        };
    }
}
