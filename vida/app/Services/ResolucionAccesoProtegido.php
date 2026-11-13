<?php

namespace App\Services;

use App\Enums\AccionAuditEnum;
use App\Models\AccesoProtegido;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\Centro;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Quién resuelve las solicitudes de acceso a una persona protegida y cómo
 * (RN-18 de docs/modulo-tipo-centro.md).
 *
 * Resuelve el director vigente del centro de la historia de la persona; si el
 * centro no tiene director con cuenta, quien tiene rol `supervision` adscrito
 * directamente a la UO del centro. Nadie resuelve su propia solicitud.
 * Restricción de dominio crítica (CLAUDE.md §3): se comprueba en el servidor,
 * al aprobar y al denegar. El acceso aprobado caduca (por defecto, los días de
 * `dias_acceso_protegido`); cada resolución se audita y se avisa al solicitante.
 */
class ResolucionAccesoProtegido
{
    /** Clave de configuración con los días de validez por defecto de un acceso aprobado. */
    public const CLAVE_DIAS = 'dias_acceso_protegido';

    /** Días de validez si no hay configuración. */
    public const DIAS_POR_DEFECTO = 30;

    /**
     * @param AuditService $auditoria
     * @param AlertaService $alertas
     */
    public function __construct(
        private readonly AuditService $auditoria,
        private readonly AlertaService $alertas,
    ) {}

    /**
     * Usuarios que pueden resolver solicitudes sobre una persona (RN-18).
     *
     * @param int $ciudadanoId
     *
     * @return Collection<int, User>
     */
    public function resolutores(int $ciudadanoId): Collection
    {
        $uoId = $this->historiaDe($ciudadanoId)?->unidad_organizativa_id;
        if ($uoId === null) {
            return new Collection;
        }

        // Director vigente del centro, si tiene cuenta de usuario
        $centro = Centro::where('unidad_organizativa_id', $uoId)->first();
        $profesionalDirector = $centro?->directorActivo()?->profesional_id;
        if ($profesionalDirector !== null) {
            $director = User::where('profesional_id', $profesionalDirector)->get();
            if ($director->isNotEmpty()) {
                return $director;
            }
        }

        // Sin director: supervisión adscrita directamente a la UO del centro
        return User::role('supervision')
            ->whereHas('adscripcionesVigentes', fn ($q) => $q->where('unidad_organizativa_id', $uoId))
            ->get();
    }

    /**
     * Si el usuario puede resolver la solicitud: es resolutor y no es la suya.
     *
     * @param User $usuario
     * @param AccesoProtegido $acceso
     *
     * @return bool
     */
    public function puedeResolver(User $usuario, AccesoProtegido $acceso): bool
    {
        return $acceso->usuario_id !== $usuario->id
            && $acceso->solicitante_id !== $usuario->id
            && $this->resolutores($acceso->ciudadano_id)->contains('id', $usuario->id);
    }

    /**
     * Solicitudes pendientes que el usuario puede resolver.
     *
     * @param User $usuario
     *
     * @return Collection<int, AccesoProtegido>
     */
    public function pendientesPara(User $usuario): Collection
    {
        return AccesoProtegido::where('estado', 'pendiente')
            ->with('usuario')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (AccesoProtegido $acceso) => $this->puedeResolver($usuario, $acceso))
            ->values();
    }

    /**
     * Fecha de caducidad por defecto de un acceso aprobado hoy.
     *
     * @return Carbon
     */
    public function caducidadPorDefecto(): Carbon
    {
        $dias = (int) app(ConfiguracionService::class)->get(self::CLAVE_DIAS, self::DIAS_POR_DEFECTO);

        return today()->addDays(max(1, $dias))->endOfDay();
    }

    /**
     * Aprueba la solicitud hasta la fecha indicada (o la de por defecto).
     *
     * @param AccesoProtegido $acceso
     * @param User $resolutor
     * @param Carbon|null $hasta Caducidad del acceso; debe ser futura.
     *
     * @return AccesoProtegido
     *
     * @throws AuthorizationException Si el usuario no puede resolverla.
     * @throws DomainException Si ya está resuelta o la caducidad no es futura.
     */
    public function aprobar(AccesoProtegido $acceso, User $resolutor, ?Carbon $hasta = null): AccesoProtegido
    {
        $this->comprobar($acceso, $resolutor);
        $hasta = $hasta?->copy()->endOfDay() ?? $this->caducidadPorDefecto();
        if ($hasta->isPast()) {
            throw new DomainException('La fecha de caducidad del acceso tiene que ser futura.');
        }

        return $this->resolver($acceso, $resolutor, [
            'estado' => 'aprobado',
            'acceso_valido_hasta' => $hasta,
        ], 'Tu solicitud de acceso a una persona protegida ha sido aprobada hasta el '.$hasta->format('d/m/Y').'.');
    }

    /**
     * Deniega la solicitud con motivo obligatorio.
     *
     * @param AccesoProtegido $acceso
     * @param User $resolutor
     * @param string $motivo
     *
     * @return AccesoProtegido
     *
     * @throws AuthorizationException Si el usuario no puede resolverla.
     * @throws DomainException Si ya está resuelta o falta el motivo.
     */
    public function denegar(AccesoProtegido $acceso, User $resolutor, string $motivo): AccesoProtegido
    {
        $this->comprobar($acceso, $resolutor);
        if (trim($motivo) === '') {
            throw new DomainException('El motivo es obligatorio para denegar el acceso.');
        }

        return $this->resolver($acceso, $resolutor, [
            'estado' => 'denegado',
            'motivo_resolucion' => trim($motivo),
        ], 'Tu solicitud de acceso a una persona protegida ha sido denegada. Motivo: '.trim($motivo));
    }

    /**
     * Comprueba que la solicitud sigue pendiente y que el usuario la puede resolver.
     *
     * @param AccesoProtegido $acceso
     * @param User $resolutor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    private function comprobar(AccesoProtegido $acceso, User $resolutor): void
    {
        if ($acceso->estado !== 'pendiente') {
            throw new DomainException('Esta solicitud ya está resuelta.');
        }
        if (! $this->puedeResolver($resolutor, $acceso)) {
            throw new AuthorizationException('Solo el director del centro de la persona (o, sin director, su supervisión) resuelve esta solicitud, y nadie la suya.');
        }
    }

    /**
     * Guarda la resolución, la audita y avisa al solicitante.
     *
     * @param AccesoProtegido $acceso
     * @param User $resolutor
     * @param array<string, mixed> $cambios
     * @param string $aviso
     *
     * @return AccesoProtegido
     */
    private function resolver(AccesoProtegido $acceso, User $resolutor, array $cambios, string $aviso): AccesoProtegido
    {
        return DB::transaction(function () use ($acceso, $resolutor, $cambios, $aviso): AccesoProtegido {
            $antes = $acceso->only(['estado', 'acceso_valido_hasta', 'motivo_resolucion']);
            $acceso->update($cambios + ['aprobado_por' => $resolutor->id, 'fecha_resolucion' => now()]);

            $this->auditoria->registrarAcceso(
                user: $resolutor,
                modelo: $acceso,
                accion: AccionAuditEnum::Editar,
                ciudadanoId: $acceso->ciudadano_id,
                contexto: ['resolucion_acceso_protegido' => $acceso->estado],
                datosAntes: $antes,
                datosDespues: $acceso->only(['estado', 'acceso_valido_hasta', 'motivo_resolucion']),
            );

            $this->alertas->crear([
                'tipo' => TipoAlerta::Aviso,
                'origen_type' => AccesoProtegido::class,
                'origen_id' => $acceso->id,
                'titulo' => $aviso,
                'cuerpo' => $aviso,
                'destinatario_type' => DestinatarioType::Usuario,
                'destinatario_usuario_id' => $acceso->usuario_id,
            ]);

            return $acceso;
        });
    }

    /**
     * Historia vigente de la persona (la que fija su centro), sin scope.
     *
     * @param int $ciudadanoId
     *
     * @return HistoriaSocial|null
     */
    private function historiaDe(int $ciudadanoId): ?HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)
            ->where('ciudadano_id', $ciudadanoId)
            ->where('estado', '!=', 'cerrada')
            ->latest()
            ->first();
    }
}
