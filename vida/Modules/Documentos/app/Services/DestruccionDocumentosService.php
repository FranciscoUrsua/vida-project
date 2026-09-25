<?php

namespace Modules\Documentos\Services;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\EstadoPropuestaEliminacion;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Models\ActaEliminacion;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Models\PropuestaEliminacion;

/**
 * Destrucción de versiones con el plazo de conservación vencido: propuesta,
 * aprobación con acta y rechazo.
 *
 * Nada se destruye sin una propuesta aprobada por un adm_sistema (la purga de
 * versiones sustituidas va aparte, en CicloVidaDocumentoService). Al aprobar se
 * vuelve a comprobar cada versión: las que han quedado retenidas desde la propuesta
 * se excluyen y no aparecen en el acta.
 */
class DestruccionDocumentosService
{
    /** Motivo del acta cuando quien aprueba no indica otro. */
    public const MOTIVO_DEFECTO = 'Plazo de conservación vencido.';

    /**
     * Inyecta el destructor de versiones.
     *
     * @param DestructorVersiones $destructor Crypto-shredding y borrado del objeto.
     */
    public function __construct(private readonly DestructorVersiones $destructor) {}

    /**
     * Genera una propuesta pendiente con las versiones destruibles que no estén ya
     * en otra propuesta pendiente. No destruye nada.
     *
     * @return PropuestaEliminacion|null null si no hay ninguna versión que proponer.
     */
    public function proponer(): ?PropuestaEliminacion
    {
        $yaPropuestas = PropuestaEliminacion::query()->pendientes()->pluck('versiones')->flatten()->all();

        $ids = $this->versionesVencidas()
            ->whereNotIn('documento_versiones.id', $yaPropuestas)
            ->pluck('documento_versiones.id')
            ->filter(fn (int $id): bool => ! DocumentoVersion::findOrFail($id)->estaRetenida())
            ->values()
            ->all();

        if ($ids === []) {
            return null;
        }

        return PropuestaEliminacion::create([
            'estado' => EstadoPropuestaEliminacion::Pendiente,
            'versiones' => $ids,
        ]);
    }

    /**
     * Aprueba la propuesta: destruye las versiones que sigan siendo destruibles y
     * levanta el acta. Las excluidas quedan en `excluidas` de la propuesta.
     *
     * @param PropuestaEliminacion $propuesta Propuesta pendiente.
     * @param User $usuario Quien aprueba; debe ser adm_sistema.
     * @param string $motivo Motivo que consta en el acta.
     *
     * @throws AuthorizationException si el usuario no es adm_sistema
     * @throws \DomainException si la propuesta ya está resuelta
     *
     * @return PropuestaEliminacion La propuesta resuelta, con su acta (si se destruyó algo).
     */
    public function aprobar(PropuestaEliminacion $propuesta, User $usuario, string $motivo = self::MOTIVO_DEFECTO): PropuestaEliminacion
    {
        $this->exigirAdministrador($usuario);

        /** @var list<DocumentoVersion> $destruidas */
        $destruidas = [];

        $propuesta = DB::transaction(function () use ($propuesta, $usuario, $motivo, &$destruidas): PropuestaEliminacion {
            $propuesta = $this->bloquearPendiente($propuesta);
            $excluidas = [];
            $detalle = [];

            foreach ($propuesta->versionesPropuestas() as $version) {
                // Se revisa de nuevo: la situación puede haber cambiado desde la propuesta.
                if (! $this->sigueSiendoDestruible($version)) {
                    $excluidas[] = $version->id;

                    continue;
                }

                $detalle[] = $this->detalleActa($version);
                $this->destructor->triturarClave($version, EstadoVersion::Destruida, $usuario, $motivo);
                $this->marcarDocumentoSiDestruido($version);
                $destruidas[] = $version;
            }

            $acta = $detalle === [] ? null : ActaEliminacion::create([
                'numero' => ActaEliminacion::siguienteNumero((int) now()->year),
                'aprobada_por' => $usuario->id,
                'aprobada_en' => now(),
                'motivo' => $motivo,
                'detalle' => $detalle,
            ]);

            $propuesta->update([
                'estado' => EstadoPropuestaEliminacion::Aprobada,
                'excluidas' => $excluidas,
                'resuelta_por' => $usuario->id,
                'resuelta_en' => now(),
                'acta_eliminacion_id' => $acta?->id,
            ]);

            return $propuesta;
        });

        // Tras el commit: si la transacción hubiera fallado, los objetos seguirían en disco con su clave.
        foreach ($destruidas as $version) {
            $this->destructor->eliminarObjeto($version);
        }

        return $propuesta->load('acta');
    }

    /**
     * Rechaza la propuesta sin destruir nada.
     *
     * @param PropuestaEliminacion $propuesta Propuesta pendiente.
     * @param User $usuario Quien rechaza; debe ser adm_sistema.
     * @param string|null $observaciones Motivo del rechazo.
     *
     * @throws AuthorizationException si el usuario no es adm_sistema
     * @throws \DomainException si la propuesta ya está resuelta
     *
     * @return PropuestaEliminacion
     */
    public function rechazar(PropuestaEliminacion $propuesta, User $usuario, ?string $observaciones = null): PropuestaEliminacion
    {
        $this->exigirAdministrador($usuario);

        return DB::transaction(function () use ($propuesta, $usuario, $observaciones): PropuestaEliminacion {
            $propuesta = $this->bloquearPendiente($propuesta);
            $propuesta->update([
                'estado' => EstadoPropuestaEliminacion::Rechazada,
                'resuelta_por' => $usuario->id,
                'resuelta_en' => now(),
                'observaciones' => $observaciones,
            ]);

            return $propuesta;
        });
    }

    /**
     * Versiones con contenido cuyo tipo tiene plazo de conservación y lo han superado.
     *
     * Los tipos con conservacion_anyos nulo (sin plazo definido) nunca se proponen.
     *
     * @return Builder<DocumentoVersion>
     */
    private function versionesVencidas(): Builder
    {
        return DocumentoVersion::query()
            ->join('documentos', 'documentos.id', '=', 'documento_versiones.documento_id')
            ->join('tipos_documentales', 'tipos_documentales.id', '=', 'documentos.tipo_documental_id')
            ->whereIn('documento_versiones.estado', [EstadoVersion::Vigente->value, EstadoVersion::Sustituida->value])
            ->whereNotNull('tipos_documentales.conservacion_anyos')
            ->whereRaw('documento_versiones.fecha_captura + make_interval(years => tipos_documentales.conservacion_anyos) < ?', [now()])
            ->orderBy('documento_versiones.id')
            ->select('documento_versiones.*');
    }

    /**
     * Comprueba en el momento de aprobar que la versión aún tiene contenido, sigue
     * vencida y no está retenida.
     *
     * @param DocumentoVersion $version Versión propuesta.
     *
     * @return bool
     */
    private function sigueSiendoDestruible(DocumentoVersion $version): bool
    {
        return $version->tieneContenido()
            && ! $version->estaRetenida()
            && $this->versionesVencidas()->where('documento_versiones.id', $version->id)->exists();
    }

    /**
     * Entrada del acta para una versión: solo metadatos, nunca contenido ni nombre original.
     *
     * @param DocumentoVersion $version Versión que se va a destruir.
     *
     * @return array{documento_uuid: string, numero_version: int, tipo_documental_codigo: string, fecha_captura: string, hash_sha256: string, ids_ciudadanos_vinculados: list<int>}
     */
    private function detalleActa(DocumentoVersion $version): array
    {
        $documento = $version->documento;

        return [
            'documento_uuid' => $documento->uuid,
            'numero_version' => $version->numero,
            'tipo_documental_codigo' => $documento->tipo->codigo,
            'fecha_captura' => $version->fecha_captura->toIso8601String(),
            'hash_sha256' => $version->hash_sha256,
            // Todos los vínculos, también los dados de baja: el acta dice de quién era.
            'ids_ciudadanos_vinculados' => $documento->vinculos()
                ->where('vinculable_type', (new Ciudadano)->getMorphClass())
                ->orderBy('vinculable_id')
                ->pluck('vinculable_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * Marca el documento como destruido si ya no le queda ninguna versión con contenido.
     *
     * @param DocumentoVersion $version Versión recién destruida.
     *
     * @return void
     */
    private function marcarDocumentoSiDestruido(DocumentoVersion $version): void
    {
        $documento = $version->documento;
        $quedanVivas = $documento->versiones()
            ->whereIn('estado', [EstadoVersion::Vigente->value, EstadoVersion::Sustituida->value])
            ->exists();

        if (! $quedanVivas) {
            $documento->update(['estado' => EstadoDocumento::Destruido]);
        }
    }

    /**
     * Relee la propuesta con bloqueo y exige que siga pendiente.
     *
     * @param PropuestaEliminacion $propuesta Propuesta.
     *
     * @throws \DomainException si ya está resuelta
     *
     * @return PropuestaEliminacion
     */
    private function bloquearPendiente(PropuestaEliminacion $propuesta): PropuestaEliminacion
    {
        $bloqueada = PropuestaEliminacion::query()->lockForUpdate()->findOrFail($propuesta->id);

        if (! $bloqueada->estaPendiente()) {
            throw new \DomainException('La propuesta ya está resuelta.');
        }

        return $bloqueada;
    }

    /**
     * Solo adm_sistema aprueba o rechaza propuestas de destrucción.
     *
     * @param User $usuario Usuario que actúa.
     *
     * @throws AuthorizationException
     *
     * @return void
     */
    private function exigirAdministrador(User $usuario): void
    {
        if (! $usuario->hasRole('adm_sistema')) {
            throw new AuthorizationException('Solo un administrador del sistema puede resolver propuestas de destrucción.');
        }
    }
}
