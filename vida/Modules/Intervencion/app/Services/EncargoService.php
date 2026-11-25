<?php

namespace Modules\Intervencion\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\ModalidadDerivacion;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\DerivacionEvento;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Único punto de escritura de los encargos profesionales (EP-1 a EP-5 de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * El encargo es una derivación sin plan especializado: el TSR lo crea desde el plan
 * principal o desde la Historia Social para un centro; la dirección o la supervisión
 * adscrita al centro lo acepta asignando un profesional con
 * `derivacion.registrar_resultado` (o lo rechaza); ese profesional trabaja con la
 * persona sin acceso a la Historia Social y lo cierra con su resultado, que queda como
 * apunte `encargo` para los profesionales de intervención.
 *
 * Rechazar y anular un encargo pendiente son los de DerivacionService, que valen para
 * las dos modalidades. Cada cambio deja un evento inmutable y una alerta que no nombra
 * a la persona (pueden leerla usuarios que no la ven: personas protegidas).
 */
class EncargoService
{
    /** Permiso del profesional que recibe y cierra encargos. */
    public const PERMISO_PROFESIONAL = 'derivacion.registrar_resultado';

    /**
     * Inyecta las dependencias.
     *
     * @param CentroDeUsuario $centros
     * @param AlertaService $alertas
     * @param DerivacionService $derivaciones Para saber quién resuelve en el centro de destino.
     */
    public function __construct(
        private readonly CentroDeUsuario $centros,
        private readonly AlertaService $alertas,
        private readonly DerivacionService $derivaciones,
    ) {}

    /**
     * Crea un encargo profesional para la persona de la historia (EP-2).
     *
     * @param HistoriaSocial $historia Historia de la persona.
     * @param PlanDeIntervencion|null $principal Plan principal activo si se encarga desde él.
     * @param PlanObjetivo|null $objetivo Objetivo del plan principal, si se encarga desde él.
     * @param Centro $destino Centro que recibe el encargo.
     * @param string $motivo Motivo y contexto del encargo, visible para el profesional.
     * @param list<int> $documentoIds Documentos de la carpeta de la persona que se adjuntan.
     * @param User $actor Quien encarga.
     *
     * @return Derivacion
     *
     * @throws DomainException
     */
    public function encargar(HistoriaSocial $historia, ?PlanDeIntervencion $principal, ?PlanObjetivo $objetivo, Centro $destino, string $motivo, array $documentoIds, User $actor): Derivacion
    {
        $centroOrigen = $this->exigirOrigen($historia, $principal, $actor);

        // RN-15: encargar es una actuación en el centro del plan o de la historia
        $this->centros->exigirActuacion($actor, $centroOrigen);

        if ($objetivo !== null && ($principal === null || $objetivo->plan_id !== $principal->id)) {
            throw new DomainException('El objetivo no es del plan principal.');
        }

        if (trim($motivo) === '') {
            throw new DomainException('Indica el motivo y el contexto del encargo.');
        }

        $abierto = Derivacion::query()->encargos()
            ->where('historia_id', $historia->id)
            ->where('centro_destino_id', $destino->id)
            ->whereIn('estado', [EstadoDerivacion::Pendiente->value, EstadoDerivacion::Aceptada->value])
            ->exists();

        if ($abierto) {
            throw new DomainException("Ya hay un encargo en curso para esta persona en {$destino->nombre}.");
        }

        $documentos = $this->documentosAdjuntables($historia, $documentoIds, $actor);

        return DB::transaction(function () use ($historia, $principal, $objetivo, $destino, $motivo, $documentos, $actor): Derivacion {
            $encargo = Derivacion::create([
                'modalidad' => ModalidadDerivacion::Encargo,
                'historia_id' => $historia->id,
                'plan_origen_id' => $principal?->id,
                'plan_objetivo_id' => $objetivo?->id,
                'centro_destino_id' => $destino->id,
                'motivo' => trim($motivo),
                'estado' => EstadoDerivacion::Pendiente,
                'derivada_por_id' => $actor->id,
            ]);

            foreach ($documentos as $documento) {
                DocumentoVinculo::create([
                    'documento_id' => $documento->id,
                    'vinculable_type' => $encargo->getMorphClass(),
                    'vinculable_id' => $encargo->id,
                    'activo' => true,
                    'fecha_alta' => now(),
                    'creado_por' => $actor->id,
                ]);
            }

            $this->evento($encargo, null, $actor, null);
            $this->apunte($encargo, TipoApunte::Derivacion, $principal?->id, $actor, "Encargo profesional a {$destino->nombre}.");

            $this->alertas->crear([
                'tipo' => TipoAlerta::Alerta,
                'origen_type' => Derivacion::class,
                'origen_id' => $encargo->id,
                'titulo' => 'Nuevo encargo profesional pendiente',
                'cuerpo' => "{$actor->nombreVisible()} hace un encargo profesional a {$destino->nombre}. Asígnalo a un profesional en la bandeja de derivaciones.",
                'destinatario_type' => DestinatarioType::RolUo,
                'destinatario_rol' => 'supervision',
                'destinatario_uo_id' => $destino->unidad_organizativa_id,
            ]);

            return $encargo;
        });
    }

    /**
     * Acepta el encargo asignándolo a un profesional del centro de destino (EP-3).
     *
     * @param Derivacion $encargo Encargo pendiente.
     * @param User $profesional Adscrito al centro de destino, con `derivacion.registrar_resultado`.
     * @param User $actor Dirección o supervisión adscrita al centro de destino.
     *
     * @return void
     *
     * @throws DomainException
     */
    public function aceptar(Derivacion $encargo, User $profesional, User $actor): void
    {
        $this->exigirEncargo($encargo);
        $this->exigirEstado($encargo, EstadoDerivacion::Pendiente);

        $destino = $encargo->centroDestino;

        if (! $this->derivaciones->resuelveEn($actor, $destino)) {
            throw new DomainException('Solo la dirección o la supervisión del centro de destino asignan el encargo.');
        }

        if (! $this->esProfesionalDelCentro($profesional, $destino)) {
            throw new DomainException("El encargo se asigna a un profesional de encargos de {$destino->nombre}.");
        }

        DB::transaction(function () use ($encargo, $profesional, $actor, $destino): void {
            $antes = $encargo->estado;
            $encargo->update([
                'estado' => EstadoDerivacion::Aceptada,
                'profesional_responsable_id' => $profesional->id,
                'resuelta_por_id' => $actor->id,
                'resuelta_en' => now(),
            ]);
            $this->evento($encargo, $antes, $actor, null);

            $cuerpo = "{$destino->nombre} ha asignado el encargo profesional a {$profesional->nombreVisible()}.";
            $this->alertaA($encargo->derivada_por_id, $encargo, 'Encargo profesional asignado', $cuerpo);
            $this->alertaA($profesional->id, $encargo, 'Tienes un encargo profesional nuevo', "{$destino->nombre} te ha asignado un encargo profesional. Lo tienes en «Mis derivaciones».");
        });
    }

    /**
     * Cierra el encargo con el resultado del profesional asignado (EP-4, EP-5).
     *
     * @param Derivacion $encargo Encargo aceptado.
     * @param string $resultado Resultado de la actuación (obligatorio).
     * @param User $actor Profesional asignado.
     *
     * @return void
     *
     * @throws DomainException
     */
    public function cerrar(Derivacion $encargo, string $resultado, User $actor): void
    {
        $this->exigirEncargo($encargo);
        $this->exigirEstado($encargo, EstadoDerivacion::Aceptada);

        if ($encargo->profesional_responsable_id !== $actor->id || ! $actor->can(self::PERMISO_PROFESIONAL)) {
            throw new DomainException('Solo cierra el encargo el profesional que lo tiene asignado.');
        }

        // RN-15: el centro de la operación es el del encargo (D1.6), aunque hoy trabaje en otro
        $this->centros->exigirActuacion($actor, $encargo->centroDestino);

        $resultado = trim($resultado);
        if ($resultado === '') {
            throw new DomainException('Indica el resultado del encargo.');
        }

        DB::transaction(function () use ($encargo, $resultado, $actor): void {
            $antes = $encargo->estado;
            $encargo->update([
                'estado' => EstadoDerivacion::Cerrada,
                'resultado' => $resultado,
                'cerrada_en' => now(),
                'cerrada_por_id' => $actor->id,
            ]);
            $this->evento($encargo, $antes, $actor, null);

            $this->apunte($encargo, TipoApunte::Encargo, $encargo->plan_origen_id, $actor,
                "Resultado del encargo profesional ({$encargo->centroDestino->nombre}, {$actor->nombreVisible()}):\n{$resultado}");

            $this->alertaA($encargo->derivada_por_id, $encargo, 'Encargo profesional cerrado',
                "{$actor->nombreVisible()} ha cerrado el encargo profesional. El resultado está en la Historia Social.");
        });
    }

    /**
     * Profesionales del centro que pueden recibir un encargo: adscritos a él y con
     * `derivacion.registrar_resultado`.
     *
     * @param Centro $centro
     *
     * @return Collection<int, User>
     */
    public function profesionalesDe(Centro $centro): Collection
    {
        return User::query()
            ->whereHas('adscripcionesVigentes', fn ($q) => $q->where('unidad_organizativa_id', $centro->unidad_organizativa_id))
            ->with('profesional')
            ->get()
            ->filter(fn (User $usuario) => $usuario->can(self::PERMISO_PROFESIONAL) && ! $usuario->estaDesactivado())
            ->sortBy(fn (User $usuario) => $usuario->nombreVisible())
            ->values()
            ->toBase();
    }

    /**
     * Centros con algún profesional que puede recibir encargos (destinos posibles).
     *
     * @return Collection<int, Centro>
     */
    public function centrosDestino(): Collection
    {
        return Centro::query()->where('activo', true)->orderBy('nombre')->get()
            ->filter(fn (Centro $centro) => $this->profesionalesDe($centro)->isNotEmpty())
            ->values()
            ->toBase();
    }

    /**
     * Comprueba quién puede encargar y devuelve el centro de origen (EP-2).
     *
     * @param HistoriaSocial $historia
     * @param PlanDeIntervencion|null $principal
     * @param User $actor
     *
     * @return Centro|null Centro del plan o de la historia.
     *
     * @throws DomainException
     */
    private function exigirOrigen(HistoriaSocial $historia, ?PlanDeIntervencion $principal, User $actor): ?Centro
    {
        if ($principal !== null) {
            if ($principal->historia_id !== $historia->id || ! $principal->esPrincipal() || $principal->estado !== EstadoPlan::Activo) {
                throw new DomainException('Desde un plan, el encargo solo sale del plan principal activo.');
            }

            if (! Gate::forUser($actor)->allows('update', $principal)) {
                throw new DomainException('Para encargar desde el plan tienes que poder editar el plan principal.');
            }

            return $principal->centro;
        }

        if (! Gate::forUser($actor)->allows('update', $historia)) {
            throw new DomainException('Para hacer un encargo tienes que gestionar la Historia Social de la persona.');
        }

        return $historia->centro;
    }

    /**
     * Documentos que se adjuntan: de la carpeta de la persona, disponibles y visibles para quien encarga.
     *
     * @param HistoriaSocial $historia
     * @param list<int> $documentoIds
     * @param User $actor
     *
     * @return Collection<int, Documento>
     *
     * @throws DomainException
     */
    private function documentosAdjuntables(HistoriaSocial $historia, array $documentoIds, User $actor): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $documentoIds)));
        if ($ids === []) {
            return collect();
        }

        $dePersona = DocumentoVinculo::query()->activos()
            ->where('vinculable_type', (new Ciudadano)->getMorphClass())
            ->where('vinculable_id', $historia->ciudadano_id)
            ->whereIn('documento_id', $ids)
            ->pluck('documento_id')
            ->all();

        $documentos = Documento::whereIn('id', $dePersona)->get();

        if ($documentos->count() !== count($ids)
            || $documentos->contains(fn (Documento $d) => ! $d->estaDisponible() || ! Gate::forUser($actor)->allows('view', $d))) {
            throw new DomainException('Solo se adjuntan documentos disponibles de la carpeta de la persona.');
        }

        return $documentos->toBase();
    }

    /**
     * Si el usuario puede recibir encargos en el centro.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    private function esProfesionalDelCentro(User $usuario, Centro $centro): bool
    {
        return $usuario->can(self::PERMISO_PROFESIONAL) && $this->centros->adscritoA($usuario, $centro);
    }

    /**
     * Rechaza operar como encargo sobre una derivación a atención especializada.
     *
     * @param Derivacion $derivacion
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirEncargo(Derivacion $derivacion): void
    {
        if (! $derivacion->esEncargo()) {
            throw new DomainException('No es un encargo profesional.');
        }
    }

    /**
     * Rechaza operar sobre un encargo que no está en el estado esperado.
     *
     * @param Derivacion $encargo
     * @param EstadoDerivacion $esperado
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirEstado(Derivacion $encargo, EstadoDerivacion $esperado): void
    {
        if ($encargo->estado !== $esperado) {
            throw new DomainException("El encargo está {$encargo->estado->label()}.");
        }
    }

    /**
     * Inserta un evento en el historial inmutable.
     *
     * @param Derivacion $encargo
     * @param EstadoDerivacion|null $antes
     * @param User $actor
     * @param string|null $motivo
     *
     * @return void
     */
    private function evento(Derivacion $encargo, ?EstadoDerivacion $antes, User $actor, ?string $motivo): void
    {
        DerivacionEvento::create([
            'derivacion_id' => $encargo->id,
            'actor_id' => $actor->id,
            'estado_antes' => $antes,
            'estado_despues' => $encargo->estado,
            'motivo' => $motivo,
        ]);
    }

    /**
     * Apunte en la historia vinculado al encargo, visible para los profesionales.
     *
     * @param Derivacion $encargo
     * @param TipoApunte $tipo `derivacion` al encargar, `encargo` con el resultado.
     * @param int|null $planId Plan principal de origen, si lo hay.
     * @param User $autor
     * @param string $contenido
     *
     * @return void
     */
    private function apunte(Derivacion $encargo, TipoApunte $tipo, ?int $planId, User $autor, string $contenido): void
    {
        Apunte::create([
            'historia_id' => $encargo->historia_id,
            'plan_id' => $planId,
            'autor_id' => $autor->id,
            'fecha' => today()->toDateString(),
            'tipo' => $tipo,
            'apuntable_type' => Derivacion::class,
            'apuntable_id' => $encargo->id,
            'contenido' => $contenido,
            'visibilidad' => VisibilidadApunte::Profesionales,
        ]);
    }

    /**
     * Aviso a un usuario sobre el encargo.
     *
     * @param int $usuarioId
     * @param Derivacion $encargo
     * @param string $titulo
     * @param string $cuerpo
     *
     * @return void
     */
    private function alertaA(int $usuarioId, Derivacion $encargo, string $titulo, string $cuerpo): void
    {
        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => Derivacion::class,
            'origen_id' => $encargo->id,
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuarioId,
        ]);
    }
}
