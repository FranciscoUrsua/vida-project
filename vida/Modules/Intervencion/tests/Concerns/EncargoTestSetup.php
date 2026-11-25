<?php

namespace Modules\Intervencion\Tests\Concerns;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use App\Services\AmbitoUsuario;
use Modules\Centro\Models\Centro;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\EncargoService;

/**
 * Datos de los tests del encargo profesional (docs/instrucciones-cli/2026-11-encargo-profesional.md,
 * «Tests»): el escenario del circuito más `$abogadaVallecas` (`consulta_profesional`, adscrita
 * a `$ciamVallecas`) y `$educadorMulti` (`consulta_profesional`, adscrito a `$cssNorte` y a
 * `$ciamVallecas`).
 */
trait EncargoTestSetup
{
    use CircuitoTestSetup;

    protected User $abogadaVallecas;

    protected User $educadorMulti;

    /**
     * Monta el escenario del encargo profesional sobre el del circuito.
     *
     * @return void
     */
    protected function montarEncargo(): void
    {
        $this->montarCircuito();

        $this->abogadaVallecas = $this->profesionalCon('abogada.vallecas', 'consulta_profesional', $this->ciamVallecas, 'Abogado/a');
        $this->educadorMulti = $this->profesionalCon('educador.multi', 'consulta_profesional', $this->cssNorte, 'Educador/a social');
        $this->adscribir($this->educadorMulti, $this->ciamVallecas->unidadOrganizativa);

        app(AmbitoUsuario::class)->olvidar();
    }

    /**
     * Encargo pendiente hecho por `$tsNorte` desde la Historia Social.
     *
     * @param HistoriaSocial $historia
     * @param Centro $destino
     * @param list<int> $documentoIds
     *
     * @return Derivacion
     */
    protected function encargoPendiente(HistoriaSocial $historia, Centro $destino, array $documentoIds = []): Derivacion
    {
        return app(EncargoService::class)->encargar($historia, null, null, $destino, 'Orientación jurídica sobre el desahucio', $documentoIds, $this->tsNorte);
    }

    /**
     * Encargo aceptado por `$directoraVallecas` y asignado al profesional.
     *
     * @param HistoriaSocial $historia
     * @param User $profesional
     * @param list<int> $documentoIds
     *
     * @return Derivacion
     */
    protected function encargoAceptado(HistoriaSocial $historia, User $profesional, array $documentoIds = []): Derivacion
    {
        $encargo = $this->encargoPendiente($historia, $this->ciamVallecas, $documentoIds);
        app(EncargoService::class)->aceptar($encargo, $profesional, $this->directoraVallecas);

        return $encargo->refresh();
    }

    /**
     * Encargo cerrado por el profesional asignado.
     *
     * @param HistoriaSocial $historia
     * @param User $profesional
     *
     * @return Derivacion
     */
    protected function encargoCerrado(HistoriaSocial $historia, User $profesional): Derivacion
    {
        $encargo = $this->encargoAceptado($historia, $profesional);
        app(EncargoService::class)->cerrar($encargo, 'Se informa de los recursos de defensa.', $profesional);

        return $encargo->refresh();
    }

    /**
     * Tipo documental «Informe externo», vinculable a personas (y, por tanto, a encargos).
     *
     * @return TipoDocumental
     */
    protected function tipoInformeExterno(): TipoDocumental
    {
        return TipoDocumental::firstOrCreate(['codigo' => 'informe_externo'], [
            'nombre' => 'Informe externo',
            'familia' => FamiliaDocumental::AportadoCiudadano,
            'origen_eni' => OrigenEni::Ciudadano,
            'caduca' => false,
            'politica_versiones' => PoliticaVersiones::Conservar,
            'vinculables' => ['ciudadano'],
            'activo' => true,
        ]);
    }

    /**
     * Documento vigente en la carpeta de una persona (sin fichero: basta para vínculos y listados).
     *
     * @param Ciudadano $persona
     * @param string $titulo
     *
     * @return Documento
     */
    protected function documentoEnCarpeta(Ciudadano $persona, string $titulo): Documento
    {
        $tipo = $this->tipoInformeExterno();

        $documento = Documento::create([
            'tipo_documental_id' => $tipo->id,
            'titulo' => $titulo,
            'estado' => EstadoDocumento::Vigente,
            'metadatos' => [],
            'created_by' => $this->tsNorte->id,
        ]);

        DocumentoVinculo::create([
            'documento_id' => $documento->id,
            'vinculable_type' => $persona->getMorphClass(),
            'vinculable_id' => $persona->id,
            'activo' => true,
            'fecha_alta' => now(),
            'creado_por' => $this->tsNorte->id,
        ]);

        return $documento;
    }
}
