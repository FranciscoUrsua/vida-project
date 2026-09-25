<?php

namespace Modules\Documentos\Data;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Models\TipoDocumental;

/**
 * Datos de un alta de documento que acompañan al fichero en la tubería de entrada.
 */
final readonly class DatosIngesta
{
    /**
     * @param TipoDocumental $tipo Tipo documental del documento.
     * @param User $usuario Quien recibe o sube el documento.
     * @param CanalCaptura $canal Canal de entrada.
     * @param list<Model> $vinculos Entidades a las que se vincula (personas, intervenciones, valoraciones).
     * @param string|null $nombreOriginal Nombre original del fichero; se guarda cifrado.
     * @param string|null $titulo Descripción libre breve.
     * @param Carbon|null $fechaEmision Fecha de emisión del documento.
     * @param string|null $organoEmisor Órgano que lo emitió.
     * @param array<string, mixed> $metadatos Metadatos adicionales exigidos por el tipo.
     * @param bool|null $visibleCiudadano null = el valor por defecto del tipo.
     * @param int|null $informeId Informe firmado de origen (solo canal generado).
     * @param int|null $plantillaInformeId Plantilla del informe (solo canal generado).
     */
    public function __construct(
        public TipoDocumental $tipo,
        public User $usuario,
        public CanalCaptura $canal,
        public array $vinculos = [],
        public ?string $nombreOriginal = null,
        public ?string $titulo = null,
        public ?Carbon $fechaEmision = null,
        public ?string $organoEmisor = null,
        public array $metadatos = [],
        public ?bool $visibleCiudadano = null,
        public ?int $informeId = null,
        public ?int $plantillaInformeId = null,
    ) {}

    /**
     * Valor de un metadato, buscando primero en los campos propios del documento.
     *
     * @param string $clave Clave del metadato (p. ej. fecha_emision, organo_emisor).
     *
     * @return mixed
     */
    public function metadato(string $clave): mixed
    {
        return match ($clave) {
            'fecha_emision' => $this->fechaEmision,
            'organo_emisor' => $this->organoEmisor,
            'titulo' => $this->titulo,
            default => $this->metadatos[$clave] ?? null,
        };
    }
}
