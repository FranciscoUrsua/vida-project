# Tests funcionales — Documentos: custodia v2

**TF-DOC-26 a TF-DOC-78** · Complementa TF-DOC-01 a TF-DOC-25 (`docs/modulo-documentos.md`).
Instrucciones de implementación: `docs/instrucciones-cli/documentos-custodia-implementacion.md`.

> Especificaciones de comportamiento, no código. Claude CLI implementa cada test siguiendo los patrones del proyecto. Al terminar, actualizar la tabla de estado en `docs/modulo-documentos.md`.

---

## Convenciones

- **Framework:** PHPUnit con atributo `#[Test]`. No usar Pest.
- **Base de datos:** PostgreSQL (`vida_testing`). No usar SQLite.
- **Ubicación:** `Modules/Documentos/tests/Feature/`, un fichero por grupo (`TiposDocumentalesTest`, `VinculosDocumentoTest`, `AlmacenamientoCifradoTest`, `IngestaDocumentoTest`, `CicloVidaDocumentoTest`, `RetencionDestruccionTest`, `AccesoDocumentoTest`).
- **Patrón:** Dado / Cuando / Entonces.
- **Negativo obligatorio:** cada test de restricción debe fallar si se elimina la validación que protege. Compruébalo al menos una vez por grupo comentando temporalmente la protección, y anótalo en el CHANGELOG.
- **Almacenamiento:** `Storage::fake()` sobre el disco de documentos. Clave maestra de test fija en `phpunit.xml`.
- **Dependencias externas:** `EscanerAntivirusFake` y un `ConversorPdf` real si los binarios existen. Los tests marcados **[binarios]** se agrupan con `#[Group('binarios')]` y usan `markTestSkipped` con motivo si falta el binario. Nunca simular un binario para hacer pasar un test [binarios].
- **Fixtures:** en `Modules/Documentos/tests/fixtures/`: `valido.pdf` (2 páginas), `con-javascript.pdf`, `con-adjunto.pdf`, `protegido.pdf`, `largo.pdf` (60 páginas), `foto.jpg`, `foto.png`, `documento.docx`, `con-macros.docm`, `comprimido.zip`, `ejecutable.exe` (bytes de cabecera MZ, no un ejecutable real), `eicar.txt` (cadena de prueba EICAR), `pdf-disfrazado.jpg` (PDF con extensión .jpg), `zip-disfrazado.pdf` (zip con extensión .pdf). Genéralos con un script en la misma carpeta si no se pueden versionar.

## Actores y datos reutilizados

Definir en un trait `DocumentosTestSetup`:

- `$admin` — rol `adm_sistema`.
- `$profesional` — rol `intervencion`, adscrito a `$uo`, con acceso a los ciudadanos de su UO.
- `$profesionalOtraUo` — rol `intervencion` en una UO sin relación con `$uo`.
- `$uc` — unidad de convivencia con cuatro miembros activos: `$ana`, `$luis`, `$eva`, `$pablo`, todos en ámbito de `$uo`.
- `$ciudadanoAjeno` — ciudadano fuera del ámbito de `$profesional`.
- `$tipoDni` — tipo `dni`, `caduca = false`, `politica_versiones = purgar_no_retenidas`.
- `$tipoEmpadronamiento` — tipo `certificado_empadronamiento`, `caduca = true`, `validez_dias = 90`, `politica_versiones = conservar`.
- `$tipoInformeMedico` — tipo `informe_medico`, `politica_versiones = conservar`, `metadatos_requeridos = ["fecha_emision", "organo_emisor"]`.
- `$tipoConConservacion` — `conservacion_anyos = 5`.

---

## Grupo A — Tipos documentales

Requisito de referencia: Paso 1.

**TF-DOC-26 — Crear un tipo documental desde Filament con sus atributos**
- **Dado** `$admin` autenticado en el panel.
- **Cuando** crea un tipo con `codigo = libro_familia`, `familia = aportado_ciudadano`, `origen_eni = ciudadano`, `caduca = false`, `max_paginas = 30`.
- **Entonces** existe el registro con esos valores y hay una entrada de auditoría de creación.

**TF-DOC-27 — Un profesional no puede gestionar tipos documentales**
- **Dado** `$profesional` autenticado.
- **Cuando** intenta acceder a la creación o edición de `TipoDocumentalResource`.
- **Entonces** recibe acceso denegado y no se crea ni modifica ningún registro.

**TF-DOC-28 — El código es único**
- **Dado** un tipo con `codigo = dni` existente.
- **Cuando** se intenta crear otro con el mismo código.
- **Entonces** falla la validación y no se crea el registro.

**TF-DOC-29 — Código, familia y origen son inmutables si hay documentos del tipo**
- **Dado** `$tipoDni` con al menos un documento.
- **Cuando** se intenta cambiar su `codigo`, su `familia` o su `origen_eni` (tres casos).
- **Entonces** cada cambio es rechazado y los valores originales se conservan. **Negativo:** el mismo cambio en un tipo sin documentos sí se permite.

**TF-DOC-30 — Un tipo con documentos no se puede borrar, solo desactivar**
- **Dado** `$tipoDni` con documentos.
- **Cuando** se intenta borrarlo y, después, desactivarlo.
- **Entonces** el borrado falla; la desactivación funciona y el tipo deja de aparecer en el selector de alta de documentos, pero sus documentos siguen accesibles.

**TF-DOC-31 — El seeder migra los tipos de catalogos_sistema de forma idempotente**
- **Dado** tres claves en `catalogos_sistema` grupo `documento.tipo`.
- **Cuando** se ejecuta el seeder dos veces.
- **Entonces** existen exactamente tres tipos con esas claves más el tipo `informe_profesional`, con los valores por defecto del Paso 1, sin duplicados.

---

## Grupo B — Modelo y vínculos n:M

Requisito de referencia: Paso 2 y Paso 5 (alta, desvincular).

**TF-DOC-32 — Un documento se vincula a varias personas en una sola alta**
- **Dado** `$profesional` y un certificado de empadronamiento válido.
- **Cuando** da de alta el documento vinculándolo a `$ana`, `$luis`, `$eva` y `$pablo`.
- **Entonces** existe un único documento, una única versión y un único objeto en almacenamiento, con cuatro vínculos activos.

**TF-DOC-33 — Todas las personas vinculadas ven la misma versión vigente**
- **Dado** el documento de TF-DOC-32.
- **Cuando** se consultan los documentos de `$ana` y de `$pablo`.
- **Entonces** ambos devuelven el mismo documento con la misma versión vigente.

**TF-DOC-34 — No se puede duplicar un vínculo activo**
- **Dado** un documento con vínculo activo a `$ana`.
- **Cuando** se intenta crear otro vínculo activo del mismo documento a `$ana`.
- **Entonces** se lanza una excepción de constraint único y hay un solo vínculo activo.

**TF-DOC-35 — Un documento no se vincula a la unidad de convivencia como entidad**
- **Dado** `$uc`.
- **Cuando** se intenta crear un vínculo con `vinculable_type = UnidadConvivencia`.
- **Entonces** la operación es rechazada.

**TF-DOC-36 — Solo se vincula a entidades permitidas por el tipo**
- **Dado** `$tipoDni` con `vinculables = ["ciudadano"]`.
- **Cuando** se intenta vincular un documento DNI a una valoración.
- **Entonces** la operación es rechazada y no se crea el vínculo.

**TF-DOC-37 — Los metadatos requeridos por el tipo son obligatorios**
- **Dado** `$tipoInformeMedico` (requiere `fecha_emision` y `organo_emisor`).
- **Cuando** se da de alta un informe médico sin `organo_emisor`.
- **Entonces** falla la validación y no queda nada en BBDD ni en almacenamiento. **Negativo:** con ambos metadatos, el alta funciona.

**TF-DOC-38 — Desvincular a una persona no afecta a las demás ni al fichero**
- **Dado** el documento de TF-DOC-32.
- **Cuando** se desvincula a `$luis`.
- **Entonces** el vínculo de `$luis` queda `activo = false` con `fecha_baja` y `baja_por`; los otros tres siguen activos; el documento, su versión y el objeto en almacenamiento siguen existiendo.

---

## Grupo C — Almacenamiento y cifrado

Requisito de referencia: Paso 3.

**TF-DOC-39 — El fichero almacenado está cifrado**
- **Dado** `valido.pdf`.
- **Cuando** se ingiere.
- **Entonces** el objeto en el disco no empieza por `%PDF`, no contiene ninguna cadena de texto legible del PDF original y su hash no coincide con `hash_sha256` de la versión.

**TF-DOC-40 — El fichero descifrado coincide con el hash registrado**
- **Dado** una versión ingerida.
- **Cuando** se lee a través de `CifradorDocumentos` y `AlmacenDocumentos`.
- **Entonces** el contenido es un PDF válido y su SHA-256 es igual a `hash_sha256`.

**TF-DOC-41 — Cada versión tiene su propia clave de datos**
- **Dado** el mismo fichero ingerido dos veces como documentos distintos.
- **Cuando** se comparan ambas versiones.
- **Entonces** `clave_cifrada` es distinta y los objetos cifrados en disco son distintos, aunque `hash_sha256` sea igual.

**TF-DOC-42 — La clave de almacenamiento no contiene información**
- **Dado** un documento vinculado a `$ana`, de tipo `dni`, con nombre original `dni_ana_garcia.pdf`.
- **Cuando** se inspecciona la ruta del objeto en el disco.
- **Entonces** la ruta solo contiene el UUID (y, como mucho, un subdirectorio con sus dos primeros caracteres); no contiene `dni`, `ana`, `garcia`, el id del ciudadano ni extensión.

**TF-DOC-43 — El nombre original se guarda cifrado en BBDD**
- **Dado** el documento de TF-DOC-42.
- **Cuando** se lee la columna `nombre_original` directamente con una consulta SQL sin Eloquent.
- **Entonces** el valor no contiene `ana` ni `garcia`; a través del modelo devuelve `dni_ana_garcia.pdf`.

**TF-DOC-44 — Sin clave maestra configurada, el servicio no funciona**
- **Dado** `documentos.clave_maestra` vacía.
- **Cuando** se intenta ingerir un documento.
- **Entonces** se lanza una excepción de configuración y no se escribe nada en disco. No existe ningún modo de almacenamiento en claro.

**TF-DOC-45 — La verificación de integridad detecta un fichero alterado**
- **Dado** dos versiones ingeridas; se altera un byte del objeto cifrado de una de ellas.
- **Cuando** se ejecuta `documentos:verificar-integridad`.
- **Entonces** el comando informa de un fallo en esa versión (por id) y de éxito en la otra; la salida no contiene contenido ni nombres originales.

---

## Grupo D — Tubería de entrada

Requisito de referencia: Paso 4. Salvo indicación, los rechazos deben dejar **cero** filas nuevas en `documentos`, `documento_versiones` y `documento_vinculos`, **cero** objetos nuevos en el disco y el directorio temporal vacío. Implementa esa comprobación como aserción reutilizable (`assertIngestaSinRastro()`).

**TF-DOC-46 — Un PDF válido se ingiere correctamente**
- **Dado** `valido.pdf`.
- **Cuando** se ingiere como `$tipoDni` para `$ana`.
- **Entonces** existe documento, versión 1 `vigente` con `paginas = 2`, `convertido = false`, `mime_original = application/pdf`, y el temporal queda vacío.

**TF-DOC-47 — El tipo se detecta por contenido, no por extensión**
- **Dado** `pdf-disfrazado.jpg` y `zip-disfrazado.pdf`.
- **Cuando** se ingieren.
- **Entonces** el primero se acepta como PDF (`mime_original = application/pdf`); el segundo se rechaza con `formato_no_admitido` y `assertIngestaSinRastro()`.

**TF-DOC-48 — Formatos no admitidos se rechazan**
- **Dado** `comprimido.zip`, `ejecutable.exe`, `con-macros.docm` (tres casos).
- **Cuando** se ingieren.
- **Entonces** cada uno se rechaza con el código correspondiente (`formato_no_admitido` o `macros_no_admitidas`) y `assertIngestaSinRastro()`.

**TF-DOC-49 — Un fichero con virus se rechaza**
- **Dado** `EscanerAntivirusFake` configurado para dar positivo.
- **Cuando** se ingiere `valido.pdf`.
- **Entonces** se rechaza con `virus_detectado` y `assertIngestaSinRastro()`.

**TF-DOC-50 — Si el antivirus no responde, se rechaza**
- **Dado** `EscanerAntivirusFake` configurado para lanzar error.
- **Cuando** se ingiere `valido.pdf`.
- **Entonces** se rechaza con `antivirus_no_disponible` y `assertIngestaSinRastro()`. **Negativo:** el fichero no debe entrar aunque el antivirus falle.

**TF-DOC-51 — [binarios] ClamAV real detecta la firma EICAR**
- **Dado** el escáner ClamAV real y `eicar.txt` renombrado a `.pdf`.
- **Cuando** se ingiere.
- **Entonces** se rechaza (por `virus_detectado` o `formato_no_admitido`, lo que ocurra antes) y `assertIngestaSinRastro()`.

**TF-DOC-52 — [binarios] Una imagen se convierte a PDF y el original no se conserva**
- **Dado** `foto.jpg`.
- **Cuando** se ingiere.
- **Entonces** la versión tiene `convertido = true`, `mime_original = image/jpeg`, el contenido descifrado es un PDF de 1 página, no hay ningún JPEG en el disco de documentos ni en el temporal.

**TF-DOC-53 — [binarios] Un DOCX se convierte a PDF**
- **Dado** `documento.docx`.
- **Cuando** se ingiere.
- **Entonces** la versión tiene `convertido = true` y el contenido descifrado es un PDF válido; no queda el DOCX en ningún almacenamiento.

**TF-DOC-54 — Un PDF protegido con contraseña se rechaza**
- **Dado** `protegido.pdf`.
- **Cuando** se ingiere.
- **Entonces** se rechaza con `pdf_protegido` y `assertIngestaSinRastro()`.

**TF-DOC-55 — [binarios] El saneado elimina JavaScript y adjuntos**
- **Dado** `con-javascript.pdf` y `con-adjunto.pdf`.
- **Cuando** se ingieren.
- **Entonces** ambos se aceptan; el PDF descifrado de cada uno no contiene objetos `/JavaScript`, `/JS`, `/EmbeddedFile` ni `/Launch`.

**TF-DOC-56 — Se rechaza un PDF que excede el máximo de páginas del tipo**
- **Dado** `$tipoDni` con `max_paginas = 50` y `largo.pdf` (60 páginas).
- **Cuando** se ingiere.
- **Entonces** se rechaza con `demasiadas_paginas` y `assertIngestaSinRastro()`. **Negativo:** con un tipo de `max_paginas = 100`, se acepta.

**TF-DOC-57 — Se rechaza un fichero que excede el tamaño máximo tras recompresión**
- **Dado** un tipo con `max_bytes` inferior al tamaño de `valido.pdf` que ni recomprimido puede cumplir.
- **Cuando** se ingiere.
- **Entonces** se rechaza con `tamanyo_excedido` y `assertIngestaSinRastro()`.

**TF-DOC-58 — Si falla la transacción de BBDD, no queda el objeto en disco**
- **Dado** una ingesta que supera todas las validaciones y un fallo forzado en la inserción de la versión.
- **Cuando** se ejecuta la ingesta.
- **Entonces** la excepción se propaga, no hay filas nuevas y el objeto escrito en disco se ha eliminado.

---

## Grupo E — Ciclo de vida: versiones, caducidad y purga

Requisito de referencia: Paso 5.

**TF-DOC-59 — Una nueva versión sustituye a la vigente para todas las personas**
- **Dado** el certificado de empadronamiento de TF-DOC-32 (versión 1).
- **Cuando** se sube la versión 2.
- **Entonces** la versión 2 es `vigente`, la 1 es `sustituida`, y los cuatro miembros ven la versión 2.

**TF-DOC-60 — Solo puede haber una versión vigente por documento**
- **Dado** un documento con versión 1 `vigente`.
- **Cuando** se intenta marcar directamente otra versión como `vigente` sin pasar por `nuevaVersion`.
- **Entonces** se lanza una excepción de constraint único.

**TF-DOC-61 — Con política conservar, la versión anterior se mantiene legible**
- **Dado** un informe médico (`$tipoInformeMedico`, `conservar`) con versión 1.
- **Cuando** se sube la versión 2.
- **Entonces** la versión 1 queda `sustituida`, conserva `clave_cifrada` y su objeto en disco, y se puede descifrar.

**TF-DOC-62 — Con política purgar, la versión anterior no retenida se purga**
- **Dado** un DNI (`$tipoDni`, `purgar_no_retenidas`) con versión 1 sin retenciones.
- **Cuando** se sube la versión 2.
- **Entonces** la versión 1 queda `purgada`, `clave_cifrada = null`, su objeto no existe en disco y sus metadatos (hash, fechas, número) siguen en BBDD. Hay entrada de auditoría.

**TF-DOC-63 — Con política purgar, una versión retenida no se purga**
- **Dado** un DNI con versión 1 y una retención activa sobre el documento.
- **Cuando** se sube la versión 2.
- **Entonces** la versión 1 queda `sustituida`, conserva clave y objeto, y se puede descifrar. **Negativo:** este test debe fallar si se elimina la comprobación de retenciones de la purga.

**TF-DOC-64 — La fecha de validez se calcula por defecto y se recalcula con cada versión**
- **Dado** `$tipoEmpadronamiento` (`validez_dias = 90`).
- **Cuando** se da de alta el documento hoy y, después, se sube una versión 2 con la fecha simulada 30 días más tarde.
- **Entonces** tras el alta `fecha_validez` = hoy + 90; tras la versión 2, `fecha_validez` = (hoy + 30) + 90.

**TF-DOC-65 — Un documento caducado se detecta y no se borra**
- **Dado** un certificado de empadronamiento con `fecha_validez` ayer.
- **Cuando** se consulta `Documento::caducados()` y `estaCaducado()`.
- **Entonces** aparece en el scope, `estaCaducado()` es `true`, su versión sigue `vigente` y el fichero sigue en disco.

**TF-DOC-66 — Dar de baja un ciudadano no borra sus documentos**
- **Dado** `$ana` con dos documentos, uno de ellos compartido con `$luis`.
- **Cuando** se da de baja a `$ana` por el mecanismo existente de baja de ciudadano.
- **Entonces** los vínculos de `$ana` quedan inactivos; ambos documentos, sus versiones y objetos siguen existiendo; `$luis` sigue viendo el compartido.

---

## Grupo F — Retenciones, informes firmados y destrucción

Requisito de referencia: Paso 2 (retenciones, actas) y Paso 5 (informes, destrucción).

**TF-DOC-67 — Una retención con fecha fin pasada ya no retiene**
- **Dado** un documento con una retención `hasta` = ayer.
- **Cuando** se consulta `estaRetenido()`.
- **Entonces** devuelve `false`. Con `hasta = null` o futura, devuelve `true`.

**TF-DOC-68 — El documento de un informe firmado no admite nuevas versiones**
- **Dado** un `Informe` firmado cuyo documento tiene `informe_id` en su versión.
- **Cuando** se llama a `nuevaVersion` sobre ese documento.
- **Entonces** se lanza una excepción y el documento sigue con su única versión.

**TF-DOC-69 — Firmar un informe genera un documento cifrado con canal generado**
- **Dado** un informe en borrador listo para firmar (usar el mecanismo de firma que usan hoy TF-DOC-11 a 16).
- **Cuando** se completa la firma.
- **Entonces** existe un documento con tipo de familia `informe_profesional`, versión con `canal = generado`, `informe_id` y `plantilla_informe_id` rellenos, vínculo al ciudadano del informe, y objeto cifrado en disco.

**TF-DOC-70 — La propuesta de destrucción solo incluye versiones con plazo vencido y sin retenciones**
- **Dado** tres versiones de `$tipoConConservacion` (5 años): A con `fecha_captura` hace 6 años sin retenciones; B hace 6 años con retención activa; C hace 2 años. Y una versión D de un tipo con `conservacion_anyos = null` capturada hace 20 años.
- **Cuando** se ejecuta `documentos:proponer-destruccion`.
- **Entonces** se crea una propuesta `pendiente` que contiene solo A. No se ha destruido nada.

**TF-DOC-71 — Aprobar una propuesta destruye por crypto-shredding y levanta acta**
- **Dado** la propuesta de TF-DOC-70.
- **Cuando** `$admin` la aprueba.
- **Entonces** A queda `destruida`, `clave_cifrada = null`, sin objeto en disco; su documento queda `destruido` si no le quedan versiones vivas; existe un `ActaEliminacion` cuyo `detalle` contiene uuid, número de versión, código de tipo, fecha de captura, hash e ids de ciudadanos vinculados, y **no** contiene nombre original ni contenido.

**TF-DOC-72 — Una retención creada después de la propuesta impide la destrucción**
- **Dado** una propuesta pendiente que incluye la versión A.
- **Cuando** se crea una retención sobre el documento de A y después se aprueba la propuesta.
- **Entonces** A no se destruye (clave y objeto intactos) y el acta no la incluye; el resultado de la aprobación informa de la exclusión.

**TF-DOC-73 — Las actas de eliminación son inmutables y la aprobación es solo de administrador**
- **Dado** un `ActaEliminacion` existente y una propuesta pendiente.
- **Cuando** se intenta actualizar o borrar el acta, y `$profesional` intenta aprobar la propuesta.
- **Entonces** las tres operaciones son rechazadas.

---

## Grupo G — Acceso y auditoría

Requisito de referencia: Paso 6.

**TF-DOC-74 — Un profesional con acceso a una persona vinculada puede descargar**
- **Dado** el documento compartido de TF-DOC-32 y `$profesional`.
- **Cuando** solicita la descarga por el controlador.
- **Entonces** recibe 200 con `Content-Type: application/pdf`, el contenido descifrado coincide con el hash y el nombre de fichero de la respuesta es genérico (no contiene el nombre original).

**TF-DOC-75 — Un profesional sin acceso a ninguna persona vinculada no puede descargar**
- **Dado** un documento vinculado solo a `$ciudadanoAjeno`.
- **Cuando** `$profesional` solicita la descarga.
- **Entonces** recibe 403 y no se descifra nada. **Negativo:** si se vincula también a `$ana`, la descarga funciona (regla provisional "al menos una persona").

**TF-DOC-76 — Un ciudadano de colectivo protegido restringe el acceso a sus documentos**
- **Dado** un documento vinculado solo a un ciudadano de colectivo protegido fuera del acceso ordinario de `$profesional`.
- **Cuando** `$profesional` solicita la descarga.
- **Entonces** recibe 403, igual que al intentar abrir la ficha de ese ciudadano.

**TF-DOC-77 — No existe acceso directo al fichero almacenado**
- **Dado** un documento ingerido.
- **Cuando** se inspeccionan las rutas registradas de la aplicación y la URL generada para descarga.
- **Entonces** no hay ninguna ruta que sirva el disco de documentos directamente; la URL de descarga apunta a la ruta del controlador de la aplicación.

**TF-DOC-78 — Visualizar y descargar quedan auditados**
- **Dado** el documento compartido de TF-DOC-32.
- **Cuando** `$profesional` lo visualiza y después lo descarga.
- **Entonces** existen dos entradas en `audits` vía `AuditService`, con acciones `ver` y `exportar`, `documento_id` y `documento_version_id` en el contexto y el usuario correcto. No se ha creado ninguna tabla de accesos propia del módulo.

---

## Tabla de estado (rellenar al implementar)

| Grupo | Tests | Estado |
|---|---|---|
| A — Tipos documentales | TF-DOC-26 a 31 | |
| B — Modelo y vínculos n:M | TF-DOC-32 a 38 | |
| C — Almacenamiento y cifrado | TF-DOC-39 a 45 | |
| D — Tubería de entrada | TF-DOC-46 a 58 | |
| E — Ciclo de vida | TF-DOC-59 a 66 | |
| F — Retenciones, informes y destrucción | TF-DOC-67 a 73 | |
| G — Acceso y auditoría | TF-DOC-74 a 78 | |
| **Total** | **53** | |
