<?php

namespace Modules\Documentos\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;

/**
 * Ciclo de vida de los documentos: alta y baja de vínculos.
 *
 * Fase 2a de la custodia v2. Nueva versión, purga, baja de ciudadano, informes
 * firmados como versión inmutable y destrucción con acta llegan en la fase 2c.
 */
class CicloVidaDocumentoService
{
    /**
     * Inyecta la tubería de entrada.
     *
     * @param IngestaDocumentoService $ingesta Tubería de entrada.
     */
    public function __construct(private readonly IngestaDocumentoService $ingesta) {}

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
