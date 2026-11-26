<?php

namespace Modules\Intervencion\Services;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Models\Informe;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\Ficha;

/**
 * Quién actúa en un encargo profesional y qué ve de lo actuado (EA-4 y EA-7 de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * - **Actuar** (EA-4): solo el profesional asignado, con el encargo aceptado, con
 *   `derivacion.registrar_resultado` y adscrito al centro del encargo, que es el centro de
 *   la operación (D1.6), aunque hoy trabaje en otro.
 * - **Ver** (EA-7): el profesional de encargo no tiene acceso a la Historia Social; solo ve
 *   las actuaciones con `derivacion_id` de un encargo suyo (aceptado o cerrado) de las que es
 *   autor. Nunca las de otros profesionales de la Historia ni las de otro encargo de la misma
 *   persona. Las policies de apuntes, fichas, pases e informes lo usan como vía adicional.
 *
 * Restricción de dominio (EA-11, EP-7): con el encargo cerrado de una persona protegida deja
 * de verla y, con ella, sus actuaciones, salvo acceso protegido aprobado (`EncargosDeUsuario`).
 */
class ActuacionesEncargo
{
    /**
     * Columna del autor de cada tipo de actuación.
     *
     * @var array<class-string<Model>, string>
     */
    private const AUTOR = [
        Apunte::class => 'autor_id',
        Ficha::class => 'profesional_id',
        PaseEscala::class => 'profesional_id',
        Informe::class => 'autor_id',
    ];

    /**
     * @param CentroDeUsuario $centros
     * @param EncargosDeUsuario $encargos
     */
    public function __construct(
        private readonly CentroDeUsuario $centros,
        private readonly EncargosDeUsuario $encargos,
    ) {}

    /**
     * Si el usuario puede registrar actuaciones en el encargo (EA-4).
     *
     * @param User $usuario
     * @param Derivacion $encargo
     *
     * @return bool
     */
    public function puedeActuar(User $usuario, Derivacion $encargo): bool
    {
        return $this->motivoSinActuacion($usuario, $encargo) === null;
    }

    /**
     * Exige que el usuario pueda registrar actuaciones en el encargo (EA-4).
     *
     * @param User $usuario
     * @param Derivacion $encargo
     *
     * @return void
     *
     * @throws DomainException Con el motivo; el de RN-15 es el de siempre.
     */
    public function exigirActuacion(User $usuario, Derivacion $encargo): void
    {
        $motivo = $this->motivoSinActuacion($usuario, $encargo);
        if ($motivo !== null) {
            throw new DomainException($motivo);
        }
    }

    /**
     * El encargo, si es del usuario (aceptado o cerrado) y sigue viendo a su persona; si no, null.
     *
     * Lo usan las páginas que abren un encargo fuera del módulo (el asistente de informes).
     *
     * @param User $usuario
     * @param int $encargoId
     *
     * @return Derivacion|null
     */
    public function encargoAutorizado(User $usuario, int $encargoId): ?Derivacion
    {
        if (! $this->encargoVisible($usuario, $encargoId)) {
            return null;
        }

        return Derivacion::query()->with(['centroDestino', 'area', 'derivadaPor.profesional'])->find($encargoId);
    }

    /**
     * Apuntes de las actuaciones del usuario en el encargo, del más reciente al más antiguo.
     *
     * Vacío si el encargo no es suyo o ya no ve a la persona (EA-11). Incluye sus anotaciones
     * privadas: es el autor.
     *
     * @param User $usuario
     * @param Derivacion $encargo
     *
     * @return Collection<int, Apunte>
     */
    public function visibles(User $usuario, Derivacion $encargo): Collection
    {
        if (! $this->encargoVisible($usuario, $encargo->id)) {
            return new Collection;
        }

        // Sin el scope de ámbito de UO: el profesional de encargo no gestiona la historia
        return Apunte::withoutGlobalScope('ambito_uo')
            ->where('derivacion_id', $encargo->id)
            ->where('autor_id', $usuario->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Borradores abiertos del encargo que impiden cerrarlo (EA-11): informes en borrador,
     * fichas sin completar y pases de escala en borrador vinculados al encargo.
     *
     * @param Derivacion $encargo
     *
     * @return list<string> Descripción de cada borrador, vacía si no hay ninguno.
     */
    public function borradoresAbiertos(Derivacion $encargo): array
    {
        $informes = Informe::query()->with('plantilla')
            ->where('derivacion_id', $encargo->id)
            ->where('estado', EstadoInforme::Borrador->value)
            ->get()
            ->map(fn (Informe $informe): string => "informe «{$informe->plantilla?->nombre}» en borrador");

        $fichas = Ficha::query()->with('tipoFicha')
            ->where('derivacion_id', $encargo->id)
            ->where('completada', false)
            ->get()
            ->map(fn (Ficha $ficha): string => "ficha «{$ficha->tipoFicha?->nombre}» sin completar");

        $pases = PaseEscala::query()->with('tipoEscala')
            ->where('derivacion_id', $encargo->id)
            ->where('estado', EstadoPase::Borrador->value)
            ->get()
            ->map(fn (PaseEscala $pase): string => "escala «{$pase->tipoEscala?->nombre}» en borrador");

        return $informes->concat($fichas)->concat($pases)->values()->all();
    }

    /**
     * Si el usuario ve la actuación por la vía del encargo (EA-7): la registró él desde un
     * encargo suyo cuya persona sigue viendo.
     *
     * @param User $usuario
     * @param Model $actuacion Apunte, Ficha, PaseEscala o Informe.
     *
     * @return bool
     */
    public function esVisible(User $usuario, Model $actuacion): bool
    {
        $columnaAutor = self::AUTOR[$actuacion::class] ?? null;
        $encargoId = $actuacion->getAttribute('derivacion_id');

        if ($columnaAutor === null || $encargoId === null) {
            return false;
        }

        return (int) $actuacion->getAttribute($columnaAutor) === $usuario->id
            && $this->encargoVisible($usuario, (int) $encargoId);
    }

    /**
     * Por qué el usuario no puede actuar en el encargo, o null si puede.
     *
     * @param User $usuario
     * @param Derivacion $encargo
     *
     * @return string|null
     */
    private function motivoSinActuacion(User $usuario, Derivacion $encargo): ?string
    {
        if (! $encargo->esEncargo() || $encargo->profesional_responsable_id !== $usuario->id || ! $usuario->can(EncargoService::PERMISO_PROFESIONAL)) {
            return 'Solo actúa en el encargo el profesional que lo tiene asignado.';
        }

        if ($encargo->estado !== EstadoDerivacion::Aceptada) {
            return 'El encargo no está en curso: no se pueden registrar actuaciones.';
        }

        // RN-15 con el centro del encargo (D1.6), con el mensaje de siempre
        if (! $this->centros->puedeActuar($usuario, $encargo->centroDestino)) {
            return $this->centros->motivoSinActuacion($usuario, $encargo->centroDestino);
        }

        return null;
    }

    /**
     * Si el encargo es del usuario (aceptado o cerrado) y sigue viendo a su persona.
     *
     * @param User $usuario
     * @param int $encargoId
     *
     * @return bool
     */
    private function encargoVisible(User $usuario, int $encargoId): bool
    {
        $historiaId = Derivacion::query()->deProfesional($usuario)->whereKey($encargoId)->value('historia_id');
        if ($historiaId === null) {
            return false;
        }

        // Sin los scopes de ámbito de las historias, como en AccesoDocumentosEncargo
        $ciudadanoId = (int) DB::table('historias_sociales')->where('id', $historiaId)->value('ciudadano_id');

        return in_array($ciudadanoId, $this->encargos->personaIds($usuario), true);
    }
}
