<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Centro\Models\Centro;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Aviso a la dirección del centro cuando alguien que no puede repartir casos (p. ej. la
 * administración del sistema) intenta desactivar a un profesional o cerrar su adscripción y
 * lo impiden sus casos o citas pendientes (R3, 23 nov 2026). El reparto es de la dirección
 * del centro (D5): este aviso le dice qué tiene que resolver.
 */
class AvisoDireccionSalida
{
    /**
     * @param AlertaService $alertas Única vía para crear alertas y avisos.
     */
    public function __construct(private readonly AlertaService $alertas) {}

    /**
     * Quién resuelve lo pendiente en un centro: la dirección vigente (`DirectorCentro`) si
     * tiene cuenta y, si no, la supervisión adscrita directamente a la UO del centro.
     *
     * @param Centro $centro Centro con casos o citas pendientes.
     *
     * @return Collection<int, User>
     */
    public function responsables(Centro $centro): Collection
    {
        $profesionalDirector = $centro->directorActivo()?->profesional_id;

        if ($profesionalDirector !== null) {
            $director = User::where('profesional_id', $profesionalDirector)->whereNull('desactivado_en')->get();

            if ($director->isNotEmpty()) {
                return $director;
            }
        }

        return User::role('supervision')
            ->whereNull('desactivado_en')
            ->whereHas('adscripcionesVigentes', fn ($q) => $q->where('unidad_organizativa_id', $centro->unidad_organizativa_id))
            ->get();
    }

    /**
     * Texto para el rechazo: cada centro afectado con su dirección, p. ej.
     * «CSS Norte (Ana Pérez)» o «CSS Norte (sin dirección con cuenta en VIDA)».
     *
     * @param SalidaCentroBloqueada $bloqueo Rechazo con los centros pendientes.
     *
     * @return string
     */
    public function describirResponsables(SalidaCentroBloqueada $bloqueo): string
    {
        return collect($bloqueo->pendientes)
            ->map(function (PendientesSalida $p): string {
                $nombres = $this->responsables($p->centro)->map(fn (User $u) => $u->nombreVisible())->implode(', ');

                return $p->centro->nombre.' ('.($nombres !== '' ? $nombres : 'sin dirección con cuenta en VIDA').')';
            })
            ->implode('; ');
    }

    /**
     * Envía un aviso a la dirección de cada centro afectado con el profesional, la operación
     * intentada y el número de casos y citas pendientes en ese centro.
     *
     * @param User $profesional Profesional al que se intentaba desactivar o sacar del centro.
     * @param string $accion Operación intentada («desactivar al usuario»…).
     * @param User $actor Quien lo intentó.
     * @param list<int> $centroIds Limita el aviso a estos centros (cierre de una adscripción); vacío, todos.
     *
     * @return int Número de destinatarios avisados (0 si ya no queda nada pendiente o no hay a quién avisar).
     */
    public function avisar(User $profesional, string $accion, User $actor, array $centroIds = []): int
    {
        // Se recalcula al avisar: entre el rechazo y el clic puede haberse resuelto algo
        $pendientes = collect(app(SalidaCentroService::class)->pendientesDeUsuario($profesional))
            ->filter(fn (PendientesSalida $p) => $centroIds === [] || in_array($p->centro->id, $centroIds, true));
        $avisados = 0;

        foreach ($pendientes as $p) {
            $titulo = "Pendiente para {$accion}: {$profesional->nombreVisible()}";
            $cuerpo = "{$actor->nombreVisible()} ha intentado {$accion} a {$profesional->nombreVisible()} y no ha sido posible: "
                ."en {$p->centro->nombre} tiene {$p->casos} ".($p->casos === 1 ? 'caso' : 'casos').' de referencia y '
                ."{$p->citas} ".($p->citas === 1 ? 'cita confirmada' : 'citas confirmadas').'. '
                .'Reparte sus casos desde Asignaciones y reasigna sus citas desde la gestión de ausencias.';

            foreach ($this->responsables($p->centro) as $responsable) {
                $this->alertas->crear([
                    'tipo' => TipoAlerta::Aviso,
                    'origen_type' => User::class,
                    'origen_id' => $profesional->id,
                    'titulo' => $titulo,
                    'cuerpo' => $cuerpo,
                    'destinatario_type' => DestinatarioType::Usuario,
                    'destinatario_usuario_id' => $responsable->id,
                ]);
                $avisados++;
            }
        }

        return $avisados;
    }
}
