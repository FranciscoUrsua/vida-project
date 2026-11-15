<?php

namespace Modules\Prestaciones\Tests\Concerns;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Services\Gestor\AdaptadorGestorExpedientes;
use Modules\Prestaciones\Services\Gestor\GestorManual;
use Modules\Prestaciones\Services\Gestor\GestorSimulado;
use Modules\Prestaciones\Services\SolicitudPrestacionService;

/**
 * Datos de los tests de solicitudes de prestación (fase 3 bis,
 * docs/instrucciones-cli/2026-11-fase3bis-solicitudes-expedientes.md, «Datos»).
 *
 * Sobre el escenario del circuito (fase 3): unidades y usuarios de tramitación, las cuatro
 * prestaciones de prueba, el formulario y los documentos exigidos de `$pSad`, y el gestor
 * simulado ligado como adaptador.
 */
trait SolicitudesTestSetup
{
    use CircuitoTestSetup;

    protected UnidadOrganizativa $uoTramitacionSad;

    protected User $tramitadora1;

    protected User $tramitadora2;

    protected User $tramitadoraOtra;

    protected User $tramitadoraCentro;

    protected TipoDocumental $tipoIdentidad;

    protected TipoDocumental $tipoInformeMedico;

    protected TipoFicha $formularioSad;

    protected Prestacion $pSad;

    protected Prestacion $pInterna;

    protected Prestacion $pDirecta;

    protected Prestacion $pPlaza;

    protected GestorSimulado $gestor;

    /**
     * Monta el escenario de solicitudes con el gestor simulado.
     *
     * @return void
     */
    protected function montarSolicitudes(): void
    {
        $this->montarCircuito();

        Storage::fake('documentos');
        $temporal = storage_path('framework/testing/ingesta');
        File::deleteDirectory($temporal);
        config(['documentos.ingesta.directorio_temporal' => $temporal]);

        $this->uoTramitacionSad = $this->uo('Servicio de tramitación de ayuda a domicilio', 'departamento', $this->uoArea);
        $this->tramitadora1 = $this->usuario('tramitadora1', 'tramitacion', $this->uoTramitacionSad);
        $this->tramitadora2 = $this->usuario('tramitadora2', 'tramitacion', $this->uoTramitacionSad);
        $this->tramitadoraOtra = $this->usuario('tramitadora.otra', 'tramitacion', $this->uo('Otra unidad de tramitación', 'departamento', $this->uoArea));
        $this->tramitadoraCentro = $this->usuario('tramitadora.centro', 'tramitacion', $this->uoCssNorte);

        $this->tipoIdentidad = $this->tipoDocumental('documento_identidad', 'Documento de identidad');
        $this->tipoInformeMedico = $this->tipoDocumental('informe_medico', 'Informe médico');

        $this->formularioSad = TipoFicha::create([
            'nombre' => 'Solicitud de ayuda a domicilio',
            'uso' => 'solicitud',
            'activo' => true,
            'schema' => ['campos' => [
                ['id' => 'horas', 'tipo' => 'numero', 'etiqueta' => 'Horas semanales', 'obligatorio' => true, 'orden' => 1],
                ['id' => 'observaciones', 'tipo' => 'texto', 'etiqueta' => 'Observaciones', 'obligatorio' => false, 'orden' => 2],
            ]],
        ]);

        $this->pSad = $this->prestacionDe('SAD', 'Servicio de Ayuda a Domicilio', ViaAcceso::ExpedienteExterno, [
            'unidad_tramitadora_tipo' => 'uo', 'unidad_tramitadora_id' => $this->uoTramitacionSad->id,
            'formulario_solicitud_id' => $this->formularioSad->id, 'codigo_gestor' => 'PROC-SAD',
        ]);
        $this->pSad->documentosExigidos()->create(['tipo_documental_id' => $this->tipoIdentidad->id, 'obligatorio' => true, 'orden' => 1]);
        $this->pSad->documentosExigidos()->create(['tipo_documental_id' => $this->tipoInformeMedico->id, 'obligatorio' => false, 'orden' => 2]);

        $this->pInterna = $this->prestacionDe('INT', 'Ayuda municipal de comedor', ViaAcceso::TramitacionInterna, ['unidad_tramitadora_tipo' => 'centro_solicitud']);
        $this->pDirecta = $this->prestacionDe('DIR', 'Información y orientación', ViaAcceso::Directa);
        $this->pPlaza = $this->prestacionDe('PLZ', 'Centro de día', ViaAcceso::Plaza);

        $this->usarGestor($this->gestor = new GestorSimulado);
    }

    /**
     * Liga el adaptador del gestor de expedientes.
     *
     * @param GestorSimulado|GestorManual|AdaptadorGestorExpedientes $gestor
     *
     * @return void
     */
    protected function usarGestor(AdaptadorGestorExpedientes $gestor): void
    {
        $this->app->instance(AdaptadorGestorExpedientes::class, $gestor);
        $this->app->forgetInstance(SolicitudPrestacionService::class);
    }

    /**
     * Prestación activa ofrecida por el tipo CSS.
     *
     * @param string $codigo
     * @param string $nombre
     * @param ViaAcceso $via
     * @param array<string, mixed> $extra
     *
     * @return Prestacion
     */
    protected function prestacionDe(string $codigo, string $nombre, ViaAcceso $via, array $extra = []): Prestacion
    {
        $prestacion = Prestacion::create($extra + [
            'codigo' => $codigo, 'nombre' => $nombre, 'tipo_prestacion' => 'servicio', 'nivel_garantia' => 'garantizada',
            'via_acceso' => $via, 'activa' => true,
        ]);
        $prestacion->tiposCentro()->create(['tipo_centro_id' => $this->tipoCss->id]);

        return $prestacion;
    }

    /**
     * Tipo documental vinculable a personas.
     *
     * @param string $codigo
     * @param string $nombre
     *
     * @return TipoDocumental
     */
    protected function tipoDocumental(string $codigo, string $nombre): TipoDocumental
    {
        return TipoDocumental::firstOrCreate(['codigo' => $codigo], [
            'nombre' => $nombre, 'familia' => FamiliaDocumental::AportadoCiudadano, 'origen_eni' => OrigenEni::Ciudadano,
            'caduca' => false, 'politica_versiones' => PoliticaVersiones::Conservar, 'vinculables' => ['ciudadano'], 'activo' => true,
        ]);
    }

    /**
     * Documento real (por la custodia) en la carpeta de una persona.
     *
     * @param Ciudadano $persona
     * @param TipoDocumental $tipo
     *
     * @return Documento
     */
    protected function documentoEnCarpeta(Ciudadano $persona, TipoDocumental $tipo): Documento
    {
        return app(CicloVidaDocumentoService::class)->altaDocumento(
            base_path('Modules/Documentos/tests/fixtures/valido.pdf'),
            new DatosIngesta(tipo: $tipo, usuario: $this->tsNorte, canal: CanalCaptura::Presencial, vinculos: [$persona]),
        );
    }

    /**
     * Solicitud de `$pSad` para Ana creada por su TSR en el CSS Norte.
     *
     * @return SolicitudPrestacion
     */
    protected function solicitudSad(): SolicitudPrestacion
    {
        return $this->servicio()->crear($this->ana, $this->pSad, $this->tsNorte, null, null, $this->cssNorte);
    }

    /**
     * Solicitud de `$pSad` en bandeja.
     *
     * @return SolicitudPrestacion
     */
    protected function sadEnBandeja(): SolicitudPrestacion
    {
        $solicitud = $this->solicitudSad();
        $this->servicio()->pasarABandeja($solicitud, $this->tsNorte);

        return $solicitud->fresh();
    }

    /**
     * Solicitud de `$pSad` tomada por la tramitadora 1.
     *
     * @return SolicitudPrestacion
     */
    protected function sadEnPreparacion(): SolicitudPrestacion
    {
        $solicitud = $this->sadEnBandeja();
        $this->servicio()->tomar($solicitud, $this->tramitadora1);

        return $solicitud->fresh();
    }

    /**
     * Solicitud de `$pSad` enviada con el simulador (con justificación: sin documentos).
     *
     * @return SolicitudPrestacion
     */
    protected function sadEnviada(): SolicitudPrestacion
    {
        $solicitud = $this->sadEnPreparacion();
        $this->servicio()->enviar($solicitud, $this->tramitadora1, 'La persona lo aporta en la cita');

        return $solicitud->fresh();
    }

    /**
     * Servicio de solicitudes con el adaptador actual.
     *
     * @return SolicitudPrestacionService
     */
    protected function servicio(): SolicitudPrestacionService
    {
        return app(SolicitudPrestacionService::class);
    }
}
