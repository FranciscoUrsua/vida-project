<?php

namespace Modules\Documentos\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Models\DocumentoVinculo;

/**
 * Ciclo de vida de los documentos: alta, nuevas versiones con purga, y baja de vínculos.
 *
 * Dar de baja un vínculo (o a un ciudadano) nunca borra documentos ni ficheros. Lo
 * único que elimina contenido aquí es la purga de la versión sustituida cuando el tipo
 * lo pide y no hay retenciones; la destrucción por plazo va por DestruccionDocumentosService.
 */
class CicloVidaDocumentoService
{
    /**
     * Inyecta la tubería de entrada y el destructor de versiones.
     *
     * @param IngestaDocumentoService $ingesta Tubería de entrada.
     * @param DestructorVersiones $destructor Purga de versiones sustituidas.
     */
    public function __construct(
        private readonly IngestaDocumentoService $ingesta,
        private readonly DestructorVersiones $destructor,
    ) {}

    /**
     * Da de alta un documento: versión 1 y vínculos con todas las entidades indicadas.
     *
     * Valida antes de tocar el almacenamiento: si algo falla no queda nada ni en BBDD ni en disco.
     *
     * @param UploadedFile|string $origen Fichero subido, ruta local o contenido si $esContenido.
     * @param DatosIngesta $datos Tipo, canal, vínculos y metadatos.
     * @param bool $esContenido true si $origen es el contenido binario.
     *
     * @throws ValidationException si faltan metadatos exigidos por el tipo
     * @throws \DomainException si el tipo está inactivo o algún vínculo no está permitido
     * @throws IngestaRechazadaException si la tubería de entrada rechaza el fichero
     *
     * @return Documento
     */
    public function altaDocumento(UploadedFile|string $origen, DatosIngesta $datos, bool $esContenido = false): Documento
    {
        if (! $datos->tipo->activo) {
            throw new \DomainException("El tipo documental «{$datos->tipo->codigo}» está desactivado.");
        }

        if ($datos->vinculos === []) {
            throw new \DomainException('Un documento se da de alta vinculado al menos a una persona o entidad.');
        }

        foreach ($datos->vinculos as $entidad) {
            $this->validarVinculable($datos, $entidad);
        }

        $this->validarMetadatos($datos);

        return $this->ingesta->ingerir($origen, $datos, $esContenido)->documento;
    }

    /**
     * Sube una nueva versión: pasa a vigente y la anterior queda sustituida para
     * todas las personas vinculadas.
     *
     * Si el tipo caduca, la validez se recalcula desde hoy. Si el tipo purga las
     * versiones no retenidas y la anterior no está retenida, su contenido se destruye
     * (estado purgada); sus metadatos se conservan.
     *
     * @param Documento $documento Documento que recibe la versión.
     * @param UploadedFile|string $origen Fichero subido, ruta local o contenido si $esContenido.
     * @param User $usuario Quien sube la versión.
     * @param CanalCaptura $canal Canal de entrada.
     * @param string|null $nombreOriginal Nombre original del fichero; se guarda cifrado.
     * @param bool $esContenido true si $origen es el contenido binario.
     *
     * @throws \DomainException si el documento procede de un informe firmado o ya no está vigente
     * @throws IngestaRechazadaException si la tubería de entrada rechaza el fichero
     *
     * @return DocumentoVersion
     */
    public function nuevaVersion(
        Documento $documento,
        UploadedFile|string $origen,
        User $usuario,
        CanalCaptura $canal = CanalCaptura::Presencial,
        ?string $nombreOriginal = null,
        bool $esContenido = false,
    ): DocumentoVersion {
        // Un informe firmado es inmutable: una corrección es un informe nuevo.
        if ($documento->versiones()->whereNotNull('informe_id')->exists()) {
            throw new \DomainException('El documento de un informe firmado no admite nuevas versiones: emite un informe nuevo.');
        }

        if ($documento->estado !== EstadoDocumento::Vigente) {
            throw new \DomainException('Solo se pueden añadir versiones a un documento vigente.');
        }

        $anterior = $documento->versionVigente;

        $version = $this->ingesta->ingerir(
            $origen,
            new DatosIngesta(tipo: $documento->tipo, usuario: $usuario, canal: $canal, nombreOriginal: $nombreOriginal),
            $esContenido,
            $documento,
        );

        if ($anterior !== null) {
            $this->purgarSiProcede($anterior->refresh());
        }

        return $version;
    }

    /**
     * Da de baja todos los vínculos activos de una entidad (baja de ciudadano).
     *
     * Los documentos, sus versiones y ficheros siguen existiendo y las demás personas
     * vinculadas los siguen viendo.
     *
     * @param Model $entidad Entidad dada de baja.
     * @param User|null $usuario Quien da la baja; null si no hay usuario autenticado.
     *
     * @return int Número de vínculos dados de baja.
     */
    public function desvincularEntidad(Model $entidad, ?User $usuario): int
    {
        $vinculos = DocumentoVinculo::query()
            ->activos()
            ->where('vinculable_type', $entidad->getMorphClass())
            ->where('vinculable_id', $entidad->getKey())
            ->get();

        // Uno a uno (no update masivo) para que cada baja quede en la auditoría.
        $vinculos->each(fn (DocumentoVinculo $vinculo) => $vinculo->update([
            'activo' => false,
            'fecha_baja' => now(),
            'baja_por' => $usuario?->id,
        ]));

        return $vinculos->count();
    }

    /**
     * Purga la versión sustituida si el tipo lo pide y no está retenida.
     *
     * @param DocumentoVersion $version Versión recién sustituida.
     *
     * @return void
     */
    private function purgarSiProcede(DocumentoVersion $version): void
    {
        $politica = $version->documento->tipo->politica_versiones;

        if ($politica !== PoliticaVersiones::PurgarNoRetenidas
            || $version->estado !== EstadoVersion::Sustituida
            || $version->estaRetenida()) {
            return;
        }

        $this->destructor->destruir($version, EstadoVersion::Purgada);
    }

    /**
     * Baja lógica del vínculo de un documento con una entidad. Nunca borra el documento ni su fichero.
     *
     * @param Documento $documento Documento vinculado.
     * @param Model $entidad Entidad que deja de estar vinculada.
     * @param User $usuario Quien da de baja el vínculo.
     *
     * @throws \DomainException si no hay vínculo activo con esa entidad
     *
     * @return DocumentoVinculo
     */
    public function desvincular(Documento $documento, Model $entidad, User $usuario): DocumentoVinculo
    {
        $vinculo = $documento->vinculosActivos()
            ->where('vinculable_type', $entidad->getMorphClass())
            ->where('vinculable_id', $entidad->getKey())
            ->first();

        if ($vinculo === null) {
            throw new \DomainException('El documento no tiene un vínculo activo con esa entidad.');
        }

        $vinculo->update([
            'activo' => false,
            'fecha_baja' => now(),
            'baja_por' => $usuario->id,
        ]);

        return $vinculo;
    }

    /**
     * Rechaza unidades de convivencia y entidades que el tipo no admite.
     *
     * @param DatosIngesta $datos Datos del alta.
     * @param Model $entidad Entidad que se vincularía.
     *
     * @throws \DomainException
     *
     * @return void
     */
    private function validarVinculable(DatosIngesta $datos, Model $entidad): void
    {
        if ($entidad instanceof UnidadConvivencia) {
            throw new \DomainException('Un documento no se vincula a la unidad de convivencia: vincúlalo a cada miembro.');
        }

        if (! $datos->tipo->permiteVincularA($entidad::class)) {
            throw new \DomainException("Los documentos de tipo «{$datos->tipo->codigo}» no se pueden vincular a esa entidad.");
        }
    }

    /**
     * Comprueba que están todos los metadatos que exige el tipo.
     *
     * @param DatosIngesta $datos Datos del alta.
     *
     * @throws ValidationException
     *
     * @return void
     */
    private function validarMetadatos(DatosIngesta $datos): void
    {
        $errores = [];

        foreach ($datos->tipo->metadatos_requeridos ?? [] as $clave) {
            $valor = $datos->metadato($clave);

            if ($valor === null || $valor === '') {
                $errores[$clave] = "El tipo «{$datos->tipo->nombre}» exige el dato «{$clave}».";
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }
}
