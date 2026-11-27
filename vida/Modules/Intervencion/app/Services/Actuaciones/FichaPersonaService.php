<?php

namespace Modules\Intervencion\Services\Actuaciones;

use App\Enums\AccionAuditEnum;
use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Fichas\ValidadorDatosFicha;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Registro y corrección de las fichas de la persona (SD-7 a SD-10 de la ficha sociodemográfica).
 *
 * Una ficha de la persona pertenece al ciudadano (`ciudadano_id`), no a una Historia Social:
 * no lleva Historia, valoración ni encargo y **no genera apunte** (no es un acto de intervención).
 * Es el único punto de entrada de las fichas de uso `persona`; a la inversa, la valoración y la
 * solicitud de prestación rechazan los tipos de uso persona.
 *
 * Reglas:
 * - Todo es voluntario y cada campo admite «No desea contestar» (`no_contesta`, SD-9): su
 *   valor en `datos` se guarda nulo, para que la analítica distinga «no contesta» de «no se preguntó».
 * - Actualizar la situación es una ficha nueva (pasado inmutable, principio 4.3); corregir un
 *   error de grabación es la corrección `Versionable` de la misma ficha (SD-8).
 * - Valida tipos y claves de catálogo en el servidor (`ValidadorDatosFicha`).
 * - Autoriza con `FichaPolicy` (permisos `ciudadano.registrar_fichas_persona`, ámbito y personas
 *   protegidas) y exige poder actuar en el centro (RN-15, R1).
 */
class FichaPersonaService
{
    /**
     * @param CentroDeUsuario $centros
     * @param AuditService $auditoria
     */
    public function __construct(
        private readonly CentroDeUsuario $centros,
        private readonly AuditService $auditoria,
    ) {}

    /**
     * Registra una ficha nueva de la persona (borrador o completada).
     *
     * @param Ciudadano $ciudadano Persona.
     * @param TipoFicha $tipo Tipo de ficha de uso persona.
     * @param User $autor Profesional que la registra.
     * @param array<string, mixed> $datos Valores por id de campo.
     * @param list<string> $noContesta Campos que la persona no desea contestar.
     * @param string|null $notas Notas libres.
     * @param bool $completar Si queda completada (cuenta como situación vigente) o en borrador.
     *
     * @return Ficha
     *
     * @throws DomainException Si el tipo no es de uso persona o no puede actuar en el centro (RN-15).
     * @throws AuthorizationException Si no puede registrar fichas de esta persona.
     * @throws ValidationException Si algún valor no es válido.
     */
    public function registrar(Ciudadano $ciudadano, TipoFicha $tipo, User $autor, array $datos, array $noContesta = [], ?string $notas = null, bool $completar = true): Ficha
    {
        if (! $tipo->esDePersona()) {
            throw new DomainException('Ese tipo de ficha no es una ficha de la persona.');
        }

        Gate::forUser($autor)->authorize('crearDePersona', [Ficha::class, $ciudadano]);
        $this->centros->exigirActuacion($autor);

        $campos = $tipo->schema['campos'] ?? [];
        [$datos, $noContesta] = $this->normalizar($campos, $datos, $noContesta);

        return Ficha::create([
            'ciudadano_id' => $ciudadano->id,
            'tipo_ficha_id' => $tipo->id,
            'schema_snapshot' => $tipo->schema,
            'profesional_id' => $autor->id,
            'datos' => $datos,
            'no_contesta' => $noContesta ?: null,
            'notas' => $notas ?: null,
            'completada' => $completar,
        ]);
    }

    /**
     * Corrige una ficha de la persona: completa un borrador o enmienda un error de grabación de
     * una completada (queda la versión anterior, Versionable). Usa el schema con el que se creó.
     *
     * @param Ficha $ficha Ficha de la persona.
     * @param User $autor Profesional que corrige.
     * @param array<string, mixed> $datos Valores por id de campo.
     * @param list<string> $noContesta Campos que la persona no desea contestar.
     * @param string|null $notas Notas libres.
     * @param bool $completar Si queda completada; una ficha completada no vuelve a borrador.
     *
     * @return Ficha
     *
     * @throws DomainException Si no es una ficha de la persona o no puede actuar (RN-15).
     * @throws AuthorizationException Si no puede corregirla.
     * @throws ValidationException Si algún valor no es válido.
     */
    public function corregir(Ficha $ficha, User $autor, array $datos, array $noContesta = [], ?string $notas = null, bool $completar = true): Ficha
    {
        if (! $ficha->esDePersona()) {
            throw new DomainException('Esa ficha no es una ficha de la persona.');
        }

        Gate::forUser($autor)->authorize('corregirDePersona', $ficha);
        $this->centros->exigirActuacion($autor);

        [$datos, $noContesta] = $this->normalizar($ficha->schema_snapshot['campos'] ?? [], $datos, $noContesta);

        $ficha->update([
            'datos' => $datos,
            'no_contesta' => $noContesta ?: null,
            'notas' => $notas ?: null,
            'completada' => $ficha->completada || $completar,
        ]);

        return $ficha;
    }

    /**
     * Deja constancia en la auditoría de que el usuario ha leído la ficha de la persona.
     *
     * @param Ficha $ficha Ficha leída.
     * @param User $lector Usuario que la lee.
     *
     * @return void
     */
    public function registrarLectura(Ficha $ficha, User $lector): void
    {
        $this->auditoria->registrarAcceso(user: $lector, modelo: $ficha, accion: AccionAuditEnum::Ver);
    }

    /**
     * Quita los campos que no son del schema, vacía los «No desea contestar» y valida.
     *
     * @param array<int, array<string, mixed>> $campos Campos del schema.
     * @param array<string, mixed> $datos Valores recibidos.
     * @param list<string> $noContesta Campos marcados como «No desea contestar».
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     *
     * @throws ValidationException Si algún valor no es válido.
     */
    private function normalizar(array $campos, array $datos, array $noContesta): array
    {
        $ids = array_values(array_filter(array_column($campos, 'id'), 'is_string'));
        $noContesta = array_values(array_intersect($ids, array_unique($noContesta)));

        $limpios = [];
        foreach ($ids as $id) {
            $valor = $datos[$id] ?? null;
            // «No desea contestar» manda sobre lo que se haya escrito (SD-9)
            $limpios[$id] = in_array($id, $noContesta, true) || $valor === '' ? null : $valor;
        }

        ValidadorDatosFicha::validarTipos($campos, $limpios);
        ValidadorDatosFicha::validar($campos, $limpios);

        return [$limpios, $noContesta];
    }
}
