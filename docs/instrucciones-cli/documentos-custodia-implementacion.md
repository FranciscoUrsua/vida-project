# Instrucciones de implementación — Documentos: custodia v2

**Módulo:** `Documentos` (`Modules/Documentos/`)
**Tipo de trabajo:** evolución de un módulo existente, no módulo nuevo.
**Tests asociados:** `docs/instrucciones-cli/documentos-custodia-tests.md` (TF-DOC-26 a TF-DOC-78).

---

## Antes de empezar

Lee, en este orden:

1. `CLAUDE.md` y `docs/principios-vida360.md`.
2. `docs/modulo-documentos.md` completo. Describe lo que ya existe: tabla `documentos`, `Informe`, `EstiloInforme`, `PlantillaInforme`, firma con AutoFirma y los tests TF-DOC-01 a TF-DOC-25.
3. `docs/modulo-auditoria.md` (`AuditService`, trait `Auditable`, `AccionAuditEnum`).
4. `docs/modulo-ciudadania.md` (Ciudadano, UnidadConvivencia, CiudadanoIdentificador).
5. Este documento y el de tests.

Este documento es la fuente de verdad para esta fase. Si contradice a `docs/modulo-documentos.md`, manda este. Al terminar, `docs/modulo-documentos.md` debe quedar actualizado (paso 9).

**Regla de trabajo:** si encuentras un caso no cubierto aquí (un campo que falta, una relación ambigua, una decisión de arquitectura no especificada, un conflicto con código existente), **detente y pregunta** antes de decidir. No improvises decisiones de diseño. Lo marcado como *fuera de alcance* o *pendiente* no se implementa.

---

## 1. Qué cambia y por qué

La custodia actual guarda un fichero por fila de `documentos`, asociado a un ciudadano **o** a una unidad de convivencia, en claro, con el tipo en `catalogos_sistema`. Esta fase la sustituye por:

| Hoy | Después de esta fase |
|---|---|
| Un fichero por documento | Documento lógico con versiones; cada versión, un fichero |
| `ciudadano_id` / `unidad_convivencia_id` | Vínculos n:M a personas (y a intervenciones/valoraciones) |
| Tipo en `catalogos_sistema` (`documento.tipo`) | Tabla `tipos_documentales` gestionada en Filament |
| Fichero en claro | Cifrado por versión (envelope encryption) |
| Validación básica de MIME | Tubería de entrada: detección por contenido, antivirus, conversión a PDF, saneado PDF/A, límites |
| Borrado sin reglas explícitas | Ciclo de vida: caducidad, sustitución, retenciones, purga, destrucción con acta |

Principios que no se negocian:

- **La BBDD manda; el fichero es opaco.** Rutas y nombres de fichero en almacenamiento no contienen datos personales ni el tipo de documento.
- **Solo se almacena PDF.** Otros formatos se convierten en la entrada y el original se destruye tras una conversión correcta. No se conserva en ningún almacenamiento.
- **Búsqueda solo por metadatos.** No se extrae ni indexa texto de los documentos.
- **Un acceso al almacenamiento no revela nada.** Sin BBDD y sin clave maestra, los ficheros no se pueden leer ni asociar a una persona.
- **Dar de baja no es destruir.** La destrucción es un proceso explícito, con aprobación y acta.
- **Identidad por ID interno.** Los vínculos apuntan siempre al `id` del ciudadano, nunca a DNI/NIE/pasaporte.

---

## 2. Fuera de alcance (no construir)

- Remisión de documentos a otras administraciones (la estructura de retenciones debe permitirla, pero no se crea entidad ni UI de remisión).
- Portal del ciudadano, código seguro de verificación (CSV), representantes y tutores.
- Integración con la HSU (no intercambia documentos).
- Proveedor de claves de producción (KMS/Vault/HSM): solo la interfaz y una implementación local.
- Calendario de conservación real: el campo existe, los valores los decidirá archivo/protección de datos.
- OCR, extracción de texto, índices full-text.
- Cambios en `EstiloInforme`, `PlantillaInforme`, merge tags o el flujo de edición de borradores de `Informe`.

---

## Paso 0 — Verificación del entorno

1. `php artisan test --filter=Documentos` → los 25 tests actuales pasan. Si no, detente y avisa.
2. Comprueba si están disponibles en el entorno de desarrollo y en el contenedor de tests: `clamd`/`clamscan`, `gs` (Ghostscript), `qpdf`, `libreoffice`/`soffice`, extensión `imagick` o `img2pdf`, `pdfinfo` (poppler-utils). Informa de lo que falta. **No instales nada en el sistema sin preguntar.** Si falta algo, propón cómo añadirlo (Dockerfile, `composer`, etc.) y espera respuesta.
3. Comprueba cuántas filas hay en `documentos` en el entorno local y si hay ficheros asociados, para dimensionar la migración de datos del paso 2.

---

## Paso 1 — Tipos documentales

### Tabla `tipos_documentales`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `codigo` | varchar(100) unique | Inmutable una vez creado |
| `nombre` | varchar(200) | |
| `familia` | enum `aportado_ciudadano` \| `informe_profesional` | |
| `origen_eni` | enum `ciudadano` \| `administracion` | |
| `caduca` | boolean | |
| `validez_dias` | integer nullable | Validez por defecto si `caduca = true` |
| `politica_versiones` | enum `conservar` \| `purgar_no_retenidas` | Qué hacer con versiones anteriores sin retenciones |
| `conservacion_anyos` | integer nullable | `null` = sin plazo definido → nunca se propone destrucción |
| `visible_ciudadano_defecto` | boolean default false | |
| `requiere_firma` | boolean | |
| `max_bytes` | bigint | Por defecto 20 MB (configurable en `config/documentos.php`) |
| `max_paginas` | integer | Por defecto 50 |
| `metadatos_requeridos` | jsonb | Lista de claves obligatorias (p. ej. `fecha_emision`, `organo_emisor`) |
| `vinculables` | jsonb | Tipos de entidad a las que se puede vincular: `ciudadano`, `intervencion`, `valoracion` |
| `activo` | boolean | |
| timestamps | | |

- Modelo `TipoDocumental` con trait `Auditable` y `Versionable` (como el resto de configuración).
- `codigo` no se puede modificar si existen documentos de ese tipo; `familia` y `origen_eni` tampoco.
- Un tipo con documentos no se borra; solo se desactiva.

### Filament

`TipoDocumentalResource` en el grupo «Informes y Plantillas». Consulta el `sort` de los recursos existentes del grupo y colócalo a continuación; si no está claro, pregunta. Solo rol `adm_sistema` crea/edita.

### Migración desde `catalogos_sistema`

Seeder idempotente (`updateOrCreate`) que crea un `TipoDocumental` por cada clave del grupo `documento.tipo`, con valores por defecto: `familia = aportado_ciudadano`, `origen_eni = ciudadano`, `caduca = false`, `politica_versiones = conservar`, `conservacion_anyos = null`. Añade además un tipo `informe_profesional` genérico (`familia = informe_profesional`, `origen_eni = administracion`, `requiere_firma = true`). No elimines el grupo de `catalogos_sistema` hasta el paso 2 (pregunta antes de eliminarlo).

---

## Paso 2 — Modelo de datos

### `documentos` (reestructurada)

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `uuid` | uuid unique | Identificador estable (futuro identificador ENI) |
| `tipo_documental_id` | FK | |
| `titulo` | varchar(255) nullable | Descripción libre breve |
| `fecha_emision` | date nullable | |
| `fecha_validez` | date nullable | Por defecto: fecha de alta + `validez_dias` si el tipo caduca |
| `organo_emisor` | varchar(255) nullable | |
| `visible_ciudadano` | boolean | Por defecto el del tipo |
| `estado` | enum `pendiente_validacion` \| `vigente` \| `rechazado` \| `destruido` | `pendiente_validacion` solo para canal `portal` (futuro); en esta fase todo entra `vigente` |
| `metadatos` | jsonb | Metadatos adicionales exigidos por el tipo |
| `created_by` | FK users | |
| timestamps | | |

"Caducado" no es un estado almacenado: es `fecha_validez < hoy`. Scope `caducados()` y método `estaCaducado()`.

### `documento_versiones`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `documento_id` | FK | |
| `numero` | integer | 1, 2, 3… Unique (`documento_id`, `numero`) |
| `clave_almacenamiento` | uuid unique | Nombre del objeto en el disco. Nada más |
| `disco` | varchar(50) | |
| `hash_sha256` | char(64) | Del PDF **en claro** normalizado |
| `tamanyo_bytes` | bigint | Del PDF en claro |
| `paginas` | integer | |
| `nombre_original` | text cifrado (cast `encrypted`) | Puede contener datos personales |
| `mime_original` | varchar(100) | MIME detectado en la entrada, antes de convertir |
| `convertido` | boolean | |
| `canal` | enum `presencial` \| `escaneo` \| `generado` \| `portal` | `generado` = informe firmado |
| `subido_por` | FK users | |
| `fecha_captura` | timestamp | |
| `plantilla_informe_id` | FK nullable | Solo informes |
| `informe_id` | FK nullable | Solo informes |
| `estado` | enum `vigente` \| `sustituida` \| `purgada` \| `destruida` | Una sola `vigente` por documento (índice parcial único) |
| `clave_cifrada` | text | Clave de datos de esta versión, cifrada con la clave maestra |
| `id_clave_maestra` | varchar(100) | Qué clave maestra la cifró (para rotación futura) |
| timestamps | | |

> `clave_cifrada` vive en esta tabla en esta fase. La separación física de claves es responsabilidad del proveedor de claves maestras (paso 3), no de dónde se guarde la clave de datos ya cifrada. Si ves un motivo para separarla en otra tabla o BBDD, pregunta.

### `documento_vinculos`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `documento_id` | FK | |
| `vinculable_type` / `vinculable_id` | morph | `Ciudadano`, y cuando existan, intervención/valoración según `vinculables` del tipo |
| `activo` | boolean | |
| `fecha_alta` / `fecha_baja` | timestamp / nullable | |
| `creado_por` / `baja_por` | FK users / nullable | |

Unique parcial (`documento_id`, `vinculable_type`, `vinculable_id`) donde `activo = true`. **No se vincula a `UnidadConvivencia`**: un documento de la UC se vincula a cada persona miembro.

### `documento_retenciones`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `documento_id` | FK | Retiene el documento con todas sus versiones |
| `documento_version_id` | FK nullable | Si retiene una versión concreta |
| `motivo` | enum `intervencion_cerrada` \| `manual` | Extensible; en el futuro, `remision`, `requerimiento_judicial` |
| `retenedor_type` / `retenedor_id` | morph nullable | Entidad que causa la retención |
| `desde` / `hasta` | timestamp / nullable | `hasta = null` = indefinida |
| `observaciones` | text nullable | |
| `creado_por` | FK users | |

Método `Documento::estaRetenido()` y `DocumentoVersion::estaRetenida()` (considera retenciones de documento y de versión activas a fecha actual).

**Qué crea una retención de tipo `intervencion_cerrada`:** en esta fase no hay un evento claro de "intervención cerrada" enlazado a documentos. Crea el servicio `RetencionService::retener()` y el motivo, pero **no conectes ningún evento automático**; pregunta qué hito debe dispararlo.

### `actas_eliminacion`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `numero` | varchar unique | Correlativo por año |
| `aprobada_por` | FK users | |
| `aprobada_en` | timestamp | |
| `motivo` | text | |
| `detalle` | jsonb | Por cada versión destruida: `documento_uuid`, `numero_version`, `tipo_documental_codigo`, `fecha_captura`, `hash_sha256`, `ids_ciudadanos_vinculados`. **Nunca contenido ni nombre original** |

Inmutable: sin update ni delete (a nivel de modelo y de policy).

### Migración de datos existentes

Migración que, para cada fila actual de `documentos`:

1. Crea el documento en el nuevo esquema con el `TipoDocumental` correspondiente.
2. Crea la versión 1 **pasando el fichero existente por el cifrado** (paso 3), sin conversión ni saneado, con `canal = presencial`.
3. Crea el vínculo a `ciudadano_id`; si tenía `unidad_convivencia_id`, un vínculo por cada miembro activo de la UC.
4. Verifica que el hash coincide con `hash_sha256` antiguo antes de borrar el fichero en claro.

Si algún fichero no existe o el hash no coincide, **no borres nada**: registra la incidencia, continúa con el resto y al final informa. Pregunta antes de ejecutar la migración en cualquier entorno distinto de local/testing.

### Adaptación del código existente

Busca todos los usos de `documentos.ciudadano_id`, `unidad_convivencia_id`, `ruta_almacenamiento` y del grupo `documento.tipo` (Livewire, Filament, `Informe`, seeders de demo, tests) y adáptalos. Lista los ficheros afectados en el CHANGELOG. Los tests TF-DOC-01 a TF-DOC-05 deben reescribirse contra el nuevo modelo **manteniendo la intención** de cada uno; si alguno pierde sentido, pregunta antes de eliminarlo.

---

## Paso 3 — Almacenamiento y cifrado

### Interfaces (en `Modules/Documentos/app/Contracts/`)

- `AlmacenDocumentos` — `guardar(string $clave, string $contenidoCifrado)`, `leer(string $clave): string`, `existe()`, `eliminar()`. Implementación `AlmacenFlysystem` sobre el disco configurado en `config/documentos.php` (`documentos.disco`). Ningún otro código del módulo usa `Storage::disk()` directamente para documentos.
- `ProveedorClavesMaestras` — `cifrarClave(string $claveDatos): array{clave_cifrada, id_clave_maestra}`, `descifrarClave(string $claveCifrada, string $idClaveMaestra): string`. Implementación `ProveedorClavesLocal` que usa una clave maestra de `config('documentos.clave_maestra')` (variable de entorno `DOCUMENTOS_CLAVE_MAESTRA`), **distinta de `APP_KEY`**. Si no está configurada, el servicio falla al arrancar (no hay modo en claro).
- `CifradorDocumentos` — genera una clave de datos aleatoria de 256 bits por versión, cifra con AES-256-GCM (libsodium o `openssl`), devuelve contenido cifrado + clave cifrada vía el proveedor.

### Reglas

- `clave_almacenamiento` = UUID v4 sin extensión ni prefijos con significado. La ruta en disco puede usar subdirectorios por los dos primeros caracteres del UUID; nada más.
- Destruir una versión = poner `clave_cifrada` a `null` y `estado = destruida`, **y además** eliminar el objeto del almacenamiento. El crypto-shredding garantiza que las copias en backups sean ilegibles.
- Comando `documentos:verificar-integridad` (descifra y compara hash; muestra solo ids, nunca contenido). Parámetro `--muestra=N` para verificación parcial.
- Comando `documentos:limpiar-huerfanos`: objetos en disco sin versión en BBDD (más antiguos de 24 h) y versiones `vigente`/`sustituida` sin objeto en disco. Por defecto solo informa; `--ejecutar` borra objetos huérfanos. Nunca borra registros de BBDD: los registros sin fichero se informan.

---

## Paso 4 — Tubería de entrada

Servicio `IngestaDocumentoService::ingerir(UploadedFile|string $origen, DatosIngesta $datos): DocumentoVersion`. Pasos en este orden; si cualquiera falla, se lanza `IngestaRechazadaException` con un código de motivo y **no queda nada** ni en BBDD ni en disco:

1. **Zona temporal.** Copia a un directorio temporal local del servidor (`storage/app/tmp/ingesta/`, nunca el disco de documentos). Se elimina en un `finally`.
2. **Detección de tipo por contenido** (`finfo`/magic bytes). La extensión se ignora.
   - Aceptados: `application/pdf`, `image/jpeg`, `image/png`, `image/heic`, ODT, DOCX.
   - Todo lo demás: rechazo (`formato_no_admitido`). Explícitamente: zip y otros contenedores, ejecutables, scripts, formatos con macros (`docm`, `xlsm`…).
3. **Antivirus** vía interfaz `EscanerAntivirus` (implementación ClamAV + `EscanerAntivirusFake` para tests). Positivo → `virus_detectado`. Error del escáner → rechazo (`antivirus_no_disponible`), nunca se deja pasar.
4. **Conversión a PDF** vía interfaz `ConversorPdf`: imágenes → PDF (una página por imagen); ODT/DOCX → LibreOffice headless. DOCX con macros → `macros_no_admitidas`.
5. **Saneado y normalización.** PDF cifrado con contraseña → `pdf_protegido`. Reescritura a PDF/A con Ghostscript eliminando JavaScript, ficheros incrustados y acciones de lanzamiento. Si la normalización falla → `pdf_no_normalizable`.
6. **Límites** del tipo documental: páginas > `max_paginas` → `demasiadas_paginas`. Bytes > `max_bytes` → un intento de recompresión; si sigue excediendo, `tamanyo_excedido`.
7. **Hash** SHA-256 del PDF normalizado, **cifrado** y **almacenamiento**.
8. **Commit en BBDD** dentro de una transacción (documento si es nuevo, versión, vínculos). Si la transacción falla, se elimina el objeto recién escrito en disco.
9. **Destrucción del original.** El fichero original y los intermedios se eliminan del temporal. No se conserva copia en ningún sitio.

Los mensajes de rechazo al usuario se construyen a partir del código de motivo, en castellano llano, sin detalles técnicos. Timeouts de conversión y saneado configurables en `config/documentos.php`.

---

## Paso 5 — Ciclo de vida

Servicio `CicloVidaDocumentoService`:

- **`altaDocumento(...)`** — crea documento + versión 1 + vínculos (una o varias personas en la misma operación; en la UI, check "asociar también a otros miembros de la unidad de convivencia"). Valida `metadatos_requeridos` del tipo y que las entidades vinculadas estén permitidas por `vinculables`.
- **`nuevaVersion(Documento, ...)`** — la nueva versión pasa a `vigente`, la anterior a `sustituida`. Afecta a todas las personas vinculadas. Si el tipo caduca, recalcula `fecha_validez`. Tras sustituir, si `politica_versiones = purgar_no_retenidas` y la anterior no está retenida, se **purga**: se destruye su objeto y su clave (`estado = purgada`). La purga de versiones sustituidas no requiere acta (queda en auditoría).
- **`desvincular(Documento, entidad)`** — baja lógica del vínculo (`activo = false`, `fecha_baja`). Nunca borra ficheros. Un documento sin vínculos activos sigue existiendo.
- **Baja de ciudadano** (o de la entidad vinculada): desactiva sus vínculos. No borra documentos ni ficheros. Localiza dónde se da de baja un ciudadano hoy y engancha ahí; si no hay un punto claro, pregunta.
- **Caducidad** — scope `Documento::caducados()` y método `estaCaducado()`. No hay job que cambie estado.
- **Informes firmados** — donde hoy el flujo de firma de `Informe` genera un `Documento`, pásalo por la ingesta con `canal = generado`, sin conversión (ya es PDF) pero **con** saneado solo si no invalida la firma. Si el saneado a PDF/A invalida la firma PAdES (compruébalo), no sanees los PDF firmados y pregunta. El documento del informe es inmutable: `nuevaVersion` sobre un documento con `informe_id` está prohibido; una corrección es un informe nuevo.

### Destrucción

- Comando `documentos:proponer-destruccion` (programable): selecciona versiones cuyo tipo tiene `conservacion_anyos` no nulo, con `fecha_captura` + plazo vencido y sin retenciones. Genera una **propuesta** (tabla `propuestas_eliminacion` con estado `pendiente` \| `aprobada` \| `rechazada` y la lista de versiones). No destruye nada.
- Acción de aprobación en Filament (`PropuestaEliminacionResource`, solo `adm_sistema`, grupo «Sistema»): al aprobar, vuelve a comprobar retenciones en ese momento, destruye (crypto-shredding + borrado del objeto), marca versiones `destruida` y documentos `destruido` si no les queda ninguna versión, y crea el `ActaEliminacion`.
- Nada se destruye sin propuesta aprobada, salvo la purga de versiones sustituidas descrita arriba.

---

## Paso 6 — Acceso y auditoría

- `DocumentoPolicy`: un usuario puede ver/descargar un documento si puede ver **al menos una** de las personas con vínculo activo, según las policies/scopes existentes del ciudadano (incluidas las restricciones de colectivos protegidos). Es la regla vigente en el módulo actual ("disponibles para cualquier profesional con acceso al expediente") extendida a n:M. **Queda como regla provisional**: anótala en `docs/modulo-documentos.md` como decisión pendiente de revisar.
- `visible_ciudadano` se guarda pero no tiene efecto en esta fase (no hay portal).
- Controlador de descarga: autoriza, descifra en memoria, sirve con `Content-Disposition` usando un nombre genérico (`{codigo_tipo}-{fecha}.pdf`), nunca el nombre original. Sin URLs públicas. Si el disco lo requiere, URL firmada temporal a la **ruta de la aplicación**, no al objeto del almacenamiento.
- Auditoría: **no crees una tabla de accesos propia.** Usa `AuditService` con `AccionAuditEnum::ver` (visualización) y `exportar` (descarga), registrando `documento_id`, `documento_version_id` y resolviendo `ciudadano_id` de cada persona vinculada según lo haga `AuditService` hoy. Aplica `Auditable` a `Documento`, `DocumentoVersion`, `DocumentoVinculo`, `DocumentoRetencion`, `TipoDocumental`. Si `AccionAuditEnum` necesitara valores nuevos (p. ej. `destruir`), pregunta.

---

## Paso 7 — UI mínima

Solo lo necesario para operar lo que ya existe:

- Donde hoy se sube/lista documentos del ciudadano (localízalo), adaptar a: selección de tipo documental, metadatos requeridos, check de asociar a otros miembros de la UC, lista con estado (vigente / caducado / versiones anteriores), acción "subir nueva versión", acción "desvincular".
- Mostrar los rechazos de la ingesta con el mensaje en castellano llano.
- Sigue el design system (`docs/design-system/SKILL.md`). Si la UI requiere decisiones de diseño no triviales, pregunta.

---

## Paso 8 — Configuración

`config/documentos.php` con: `disco`, `clave_maestra`, `max_bytes_defecto` (20 MB), `max_paginas_defecto` (50), timeouts de conversión y saneado, directorio temporal de ingesta, binarios (`gs`, `qpdf`, `soffice`, `clamd` socket). Todo con variables de entorno y valores por defecto razonables para desarrollo. Documenta las variables en `.env.example`.

### Disco de documentos

En `config/filesystems.php`, un disco `documentos` con driver `local` cuya raíz sale de la variable de entorno `DOCUMENTOS_RUTA`:

- **Servidor de pruebas:** `DOCUMENTOS_RUTA=/srv/vida/documentos`.
- **Desarrollo local:** un valor por defecto fuera del repositorio o, si no es posible, `storage/app/documentos` (nunca `docs/`, que es la documentación del proyecto, ni nada bajo `public/`).
- **Tests:** `Storage::fake('documentos')`.

Reglas:

- El directorio está **fuera del directorio del proyecto**, para que despliegues, `git clean` o cambios de release no lo afecten, y fuera de cualquier ruta servida por el servidor web.
- Propietario: el usuario que ejecuta PHP. Permisos `0700` en el directorio y `0600` en los ficheros (`permissions` del disco en `filesystems.php`).
- Sin `url` ni enlace simbólico en `public/` (no se ejecuta `storage:link` para este disco).
- Si `DOCUMENTOS_RUTA` no existe o no se puede escribir, la aplicación falla con un mensaje claro al primer uso; no crea el directorio en otra ubicación ni cae a un disco por defecto.
- **No crees el directorio en el servidor de pruebas ni cambies sus permisos.** Indica en el CHANGELOG los comandos que hay que ejecutar allí (`mkdir`, `chown`, `chmod`) para que los lance una persona.
- El temporal de ingesta sigue en `storage/app/tmp/ingesta/`.

Cambiar a S3 u otro proveedor más adelante debe ser solo un cambio de driver y de variables de entorno en este disco, sin tocar código.

---

## Paso 9 — Tests, documentación y cierre

1. Implementa los tests de `docs/instrucciones-cli/documentos-custodia-tests.md`.
2. Ejecuta `php artisan test --filter=Documentos` y después **la suite completa**. No des la fase por terminada con fallos nuevos. Los fallos preexistentes documentados en BACKLOG se mencionan pero no bloquean.
3. Actualiza `docs/modulo-documentos.md`: entidades (sección 2), decisiones (incluida la regla provisional de acceso a documentos compartidos), tabla de estado de tests.
4. Actualiza `docs/documentacion-proyecto.md` sección 9 (Módulo Documentos).
5. Añade una entrada en `CHANGELOG.md` con el formato de las anteriores: módulos afectados, cambios realizados, **decisiones de implementación que no estaban en estas instrucciones**, ficheros adaptados.
6. Añade a `BACKLOG.md` lo que quede pendiente: evento que crea retenciones `intervencion_cerrada`, proveedor de claves de producción, permisos sobre documentos compartidos, portal/CSV, remisión.
7. Añade este fichero y el de tests a la tabla de `CLAUDE.md` (sección "Estructura de instrucciones CLI").

## Criterios de finalización

1. Los 25 tests existentes del módulo siguen pasando (TF-DOC-01..05 reescritos contra el nuevo modelo).
2. TF-DOC-26 a TF-DOC-78 implementados y pasando (salvo los marcados como condicionados a binarios si estos no están disponibles, que se marcan `markTestSkipped` con motivo).
3. Suite completa sin fallos nuevos.
4. Ningún fichero de documento se escribe en claro en el disco de documentos (verificado por test).
5. Ningún código fuera de `AlmacenDocumentos` accede al disco de documentos (verificable con `grep`; indica el resultado en el CHANGELOG).
6. Documentación, CHANGELOG, BACKLOG y CLAUDE.md actualizados.
