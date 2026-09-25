<?php

namespace Modules\Documentos\Services;

use App\Enums\AccionAuditEnum;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Models\DocumentoVersion;

/**
 * Elimina de forma irreversible el contenido de una versión (purga o destrucción).
 *
 * Primero borra la clave de datos cifrada (crypto-shredding): desde ese momento el
 * objeto es ilegible en el disco y en cualquier copia de seguridad. Después borra el
 * objeto del almacén. Si ese borrado fallara, el objeto queda como huérfano ilegible
 * y lo retira `documentos:limpiar-huerfanos`. Los metadatos de la versión se conservan.
 *
 * Cada borrado queda en la auditoría con la acción «borrar» (no «editar» ni
 * «eliminar», que es la baja lógica), con quién lo hizo y por qué.
 */
class DestructorVersiones
{
    /**
     * Inyecta el almacén y la auditoría.
     *
     * @param AlmacenDocumentos $almacen Almacén de objetos cifrados.
     * @param AuditService $auditoria Registro de la acción «borrar».
     */
    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly AuditService $auditoria,
    ) {}

    /**
     * Destruye el contenido de la versión y la deja en el estado final indicado.
     *
     * @param DocumentoVersion $version Versión con contenido (vigente o sustituida).
     * @param EstadoVersion $estadoFinal Purgada o Destruida.
     * @param User $usuario Quien provoca el borrado.
     * @param string $motivo Motivo que consta en la auditoría.
     *
     * @throws \DomainException si la versión ya no tiene contenido o el estado final no es de destrucción
     *
     * @return void
     */
    public function destruir(DocumentoVersion $version, EstadoVersion $estadoFinal, User $usuario, string $motivo): void
    {
        DB::transaction(fn () => $this->triturarClave($version, $estadoFinal, $usuario, $motivo));

        $this->eliminarObjeto($version);
    }

    /**
     * Borra la clave de datos y cambia el estado (crypto-shredding), sin tocar el disco.
     *
     * Para destrucciones dentro de una transacción más amplia: el objeto se elimina
     * con eliminarObjeto() después del commit, nunca antes, para que un rollback no
     * deje una versión con clave y sin fichero.
     *
     * @param DocumentoVersion $version Versión con contenido (vigente o sustituida).
     * @param EstadoVersion $estadoFinal Purgada o Destruida.
     * @param User $usuario Quien provoca el borrado.
     * @param string $motivo Motivo que consta en la auditoría.
     *
     * @throws \DomainException si la versión ya no tiene contenido o el estado final no es de destrucción
     *
     * @return void
     */
    public function triturarClave(DocumentoVersion $version, EstadoVersion $estadoFinal, User $usuario, string $motivo): void
    {
        if (! in_array($estadoFinal, [EstadoVersion::Purgada, EstadoVersion::Destruida], true)) {
            throw new \DomainException('Una versión solo se destruye como purgada o destruida.');
        }

        if (! $version->tieneContenido()) {
            throw new \DomainException("La versión {$version->id} ya no tiene contenido.");
        }

        $antes = $version->estado->value;
        // Sin eventos: el observer lo registraría como «editar»; aquí se audita como «borrar».
        $version->forceFill(['clave_cifrada' => null, 'estado' => $estadoFinal])->saveQuietly();

        $this->auditoria->registrarAcceso(
            user: $usuario,
            modelo: $version,
            accion: AccionAuditEnum::Borrar,
            ciudadanoId: $version->documento->getCiudadanoId(),
            contexto: ['documento_id' => $version->documento_id, 'documento_version_id' => $version->id, 'motivo' => $motivo],
            datosAntes: ['estado' => $antes],
            datosDespues: ['estado' => $estadoFinal->value],
        );
    }

    /**
     * Elimina del almacén el objeto de una versión ya triturada.
     *
     * @param DocumentoVersion $version Versión purgada o destruida.
     *
     * @return void
     */
    public function eliminarObjeto(DocumentoVersion $version): void
    {
        $this->almacen->eliminar($version->clave_almacenamiento, $version->disco);
    }
}
