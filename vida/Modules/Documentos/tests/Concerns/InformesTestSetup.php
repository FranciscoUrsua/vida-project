<?php

namespace Modules\Documentos\Tests\Concerns;

use App\Models\User;
use App\Services\AmbitoUsuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Modules\Documentos\Tests\Support\FirmadorSimulado;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;

/**
 * Datos de los tests de informes (fase 5, docs/instrucciones-cli/2026-11-fase5-informes.md,
 * «Datos»): el escenario del circuito (fase 3) con `$psicologaVallecas` responsable de
 * un PIA activo de `$ana`, `$ventanillaNorte` (`consulta_basica`), las plantillas
 * `$plantillaSocial` (automática + libre obligatoria + libre opcional) y `$plantillaPsico`,
 * la custodia preparada (disco falso y zona temporal) y un `FirmadorSimulado`.
 */
trait InformesTestSetup
{
    use CircuitoTestSetup;

    protected User $ventanillaNorte;

    protected PlanDeIntervencion $piaAna;

    protected PlantillaInforme $plantillaSocial;

    protected PlantillaInforme $plantillaPsico;

    protected PlantillaInforme $plantillaInactiva;

    protected FirmadorSimulado $firmador;

    /**
     * Monta el escenario de la fase 5.
     *
     * @return void
     */
    protected function montarInformes(): void
    {
        Storage::fake('documentos');
        $temporal = storage_path('framework/testing/ingesta');
        File::deleteDirectory($temporal);
        config(['documentos.ingesta.directorio_temporal' => $temporal]);

        $this->montarCircuito();

        $this->ventanillaNorte = $this->profesionalCon('ventanilla.norte', 'consulta_basica', $this->cssNorte, 'Auxiliar de información');
        $this->piaAna = $this->planActivo($this->historiaAna, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoAna);

        TipoDocumental::firstOrCreate(['codigo' => 'informe_profesional'], [
            'nombre' => 'Informe profesional',
            'familia' => FamiliaDocumental::InformeProfesional,
            'origen_eni' => OrigenEni::Administracion,
            'requiere_firma' => true,
        ]);

        $uoRaiz = $this->cssNorte->unidadOrganizativa->ancestorsAndSelf()->get()->last();

        $this->plantillaSocial = PlantillaInforme::create([
            'unidad_organizativa_id' => $uoRaiz->id,
            'nombre' => 'Informe social de prueba',
            'descripcion' => 'Informe social con datos de la persona y valoración profesional.',
            'tipo_informe' => 'informe_social',
            'activa' => true,
            'creada_por' => $this->tsNorte->id,
            'secciones' => [
                ['id' => 'datos', 'titulo' => 'Datos de la persona', 'tipo' => 'automatico', 'fuente' => 'ciudadano.datos_basicos'],
                ['id' => 'valoracion', 'titulo' => 'Valoración social', 'tipo' => 'texto_libre', 'obligatorio' => true, 'instrucciones' => 'Describa la situación.'],
                ['id' => 'observaciones', 'titulo' => 'Observaciones', 'tipo' => 'texto_libre', 'obligatorio' => false],
            ],
        ]);

        $this->plantillaPsico = PlantillaInforme::create([
            'unidad_organizativa_id' => $uoRaiz->id,
            'nombre' => 'Informe psicológico de prueba',
            'tipo_informe' => 'informe_psicologico',
            'activa' => true,
            'creada_por' => $this->tsNorte->id,
            'secciones' => [
                ['id' => 'datos', 'titulo' => 'Identificación', 'tipo' => 'automatico', 'fuente' => 'ciudadano.datos_basicos'],
                ['id' => 'evaluacion', 'titulo' => 'Evaluación psicológica', 'tipo' => 'texto_libre', 'obligatorio' => true],
            ],
        ]);

        $this->plantillaInactiva = PlantillaInforme::create([
            'unidad_organizativa_id' => $uoRaiz->id,
            'nombre' => 'Plantilla retirada',
            'tipo_informe' => 'otro',
            'activa' => false,
            'creada_por' => $this->tsNorte->id,
            'secciones' => [['id' => 'texto', 'titulo' => 'Texto', 'tipo' => 'texto_libre']],
        ]);

        $this->firmador = new FirmadorSimulado;

        app(AmbitoUsuario::class)->olvidar();
    }

    /**
     * Borrador de un informe creado directamente (datos de partida).
     *
     * @param User $autor
     * @param PlantillaInforme|null $plantilla
     * @param array<string, string> $contenido
     *
     * @return Informe
     */
    protected function borrador(User $autor, ?PlantillaInforme $plantilla = null, array $contenido = ['valoracion' => '<p>Valoración de la situación.</p>']): Informe
    {
        return Informe::create([
            'plantilla_id' => ($plantilla ?? $this->plantillaSocial)->id,
            'historia_social_id' => $this->historiaAna->id,
            'ciudadano_id' => $this->ana->id,
            'autor_id' => $autor->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => $contenido,
        ]);
    }

    /**
     * PDF para firmar del informe, firmado con el certificado de prueba.
     *
     * @param Informe $informe
     *
     * @return string
     */
    protected function pdfFirmadoDe(Informe $informe): string
    {
        return $this->firmador->firmar(app(ServicioGeneracionPDF::class)->generarParaFirmar($informe->fresh()));
    }

    /**
     * El PDF firmado como fichero subido.
     *
     * @param string $pdf
     *
     * @return UploadedFile
     */
    protected function subida(string $pdf): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('informe-firmado.pdf', $pdf);
    }

    /**
     * Informe firmado por su autor por el servicio (datos de partida).
     *
     * @param User $autor
     *
     * @return Informe
     */
    protected function firmado(User $autor): Informe
    {
        $informe = $this->borrador($autor);

        return app(ServicioFirmaInforme::class)->firmar($informe, MetodoFirma::PdfFirmadoExternamente, $this->pdfFirmadoDe($informe), $autor);
    }
}
