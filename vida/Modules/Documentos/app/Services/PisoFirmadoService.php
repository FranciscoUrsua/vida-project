<?php

namespace Modules\Documentos\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\MetodoConformidadCiudadano;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\PisoFirmado;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Subida del PISO firmado de un plan (fase 5, paso 4, F5-4).
 *
 * El PISO impreso y firmado a mano por el profesional y la persona se escanea y entra
 * por la ingesta normal (canal `escaneo`: se normaliza a PDF/A como cualquier escaneo).
 *
 * - Primera subida: documento nuevo del tipo `piso_firmado`, vinculado a la persona
 *   y al plan, y un `PisoFirmado` activo.
 * - Sustitución: el escaneo nuevo es una **versión nueva del mismo documento** (la
 *   anterior queda en la custodia como versión anterior) y un `PisoFirmado` nuevo; el
 *   anterior queda con `sustituido_en` (el pasado no se sobrescribe).
 *
 * Es el único que escribe en `piso_firmados`.
 */
class PisoFirmadoService
{
    /**
     * Crea el servicio.
     *
     * @param CicloVidaDocumentoService $cicloVida Alta y versiones de documentos.
     */
    public function __construct(private CicloVidaDocumentoService $cicloVida) {}

    /**
     * PISO firmado activo del plan, si lo hay.
     *
     * @param PlanDeIntervencion $plan Plan.
     *
     * @return PisoFirmado|null
     */
    public function activo(PlanDeIntervencion $plan): ?PisoFirmado
    {
        return PisoFirmado::query()
            ->with(['subidoPor', 'documento'])
            ->where('plan_de_intervencion_id', $plan->id)
            ->activos()
            ->first();
    }

    /**
     * Sube el escaneo del PISO firmado; si ya hay uno activo, lo sustituye.
     *
     * @param PlanDeIntervencion $plan Plan principal activo.
     * @param UploadedFile $fichero Escaneo (PDF o imagen).
     * @param string|null $observaciones Observaciones de quien sube.
     * @param User $actor Responsable del plan o supervisión de su centro.
     *
     * @return PisoFirmado El nuevo PISO firmado activo.
     *
     * @throws AuthorizationException si no puede subirlo
     * @throws IngestaRechazadaException si la ingesta rechaza el fichero
     * @throws \DomainException si falta el tipo documental o el documento anterior ya no está vigente
     */
    public function subir(PlanDeIntervencion $plan, UploadedFile $fichero, ?string $observaciones, User $actor): PisoFirmado
    {
        Gate::forUser($actor)->authorize('subir', [PisoFirmado::class, $plan]);

        $tipo = TipoDocumental::where('codigo', 'piso_firmado')->first()
            ?? throw new \DomainException('Falta el tipo documental «piso_firmado» (migración de la fase 5).');

        return DB::transaction(function () use ($plan, $fichero, $observaciones, $actor, $tipo): PisoFirmado {
            $anterior = PisoFirmado::query()
                ->where('plan_de_intervencion_id', $plan->id)
                ->activos()
                ->lockForUpdate()
                ->first();

            $anterior?->update(['sustituido_en' => now()]);

            // Si el documento anterior se retiró o destruyó, el nuevo escaneo abre otro documento.
            if ($anterior !== null && $anterior->documento?->estado === EstadoDocumento::Vigente) {
                $this->cicloVida->nuevaVersion($anterior->documento, $fichero, $actor, CanalCaptura::Escaneo, $fichero->getClientOriginalName());
                $documentoId = $anterior->documento_id;
            } else {
                $documentoId = $this->cicloVida->altaDocumento($fichero, new DatosIngesta(
                    tipo: $tipo,
                    usuario: $actor,
                    canal: CanalCaptura::Escaneo,
                    vinculos: [$this->ciudadanoDe($plan), $plan],
                    nombreOriginal: $fichero->getClientOriginalName(),
                    titulo: 'PISO firmado',
                ))->id;
            }

            return PisoFirmado::create([
                'plan_de_intervencion_id' => $plan->id,
                'documento_id' => $documentoId,
                'subido_por' => $actor->id,
                'metodo_conformidad_ciudadano' => MetodoConformidadCiudadano::ManuscritaEscaneada->value,
                'observaciones' => $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null,
            ]);
        });
    }

    /**
     * Persona del plan (sin el scope de ámbito: la autorización ya se ha decidido).
     *
     * @param PlanDeIntervencion $plan Plan.
     *
     * @return Ciudadano
     */
    private function ciudadanoDe(PlanDeIntervencion $plan): Ciudadano
    {
        $historia = HistoriaSocial::withoutGlobalScopes()->findOrFail($plan->historia_id);

        return Ciudadano::withoutGlobalScopes()->findOrFail($historia->ciudadano_id);
    }
}
