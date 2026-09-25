# Módulo Documentos

**Módulo:** `Documentos`
**Namespace:** `Modules\Documentos\Models`
**Directorio:** `vida/Modules/Documentos/`
**Estado:** Parcial (revisado el 2026-09-25 contra el código). Backend de estilos, plantillas e informes implementado; custodia v2 implementada (pasos 1 a 7) con la tarjeta «Documentos» en la ficha del ciudadano; 88 tests pasan. **Sin UI operativa de informes ni PISO** (sección 4) y **sin variables auxiliares** (2.6). La custodia v2 (`docs/instrucciones-cli/documentos-custodia-implementacion.md`) sustituye a la custodia v1.

> **Revisión 2026-09-25.** Versiones anteriores de este documento daban por implementados las variables auxiliares (TF-DOC-22 a 25), `ParametroInformeResource`, `ConfiguracionTipografiaResource` y los componentes Livewire de la sección 4. No existen en el código ni en el historial de git. Se marcan abajo como ⏳ pendientes.

---

## 1. Introducción funcional

El módulo Documentos cubre dos necesidades diferenciadas que comparten infraestructura pero tienen ciclos de vida y reglas de negocio distintos.

**Custodia de documentos externos:** los profesionales incorporan al expediente documentos generados fuera de VIDA 360 — informes médicos, certificados, documentación de identidad, resoluciones de otras administraciones, etc. Cada documento se clasifica con un tipo documental, se guarda cifrado y se vincula a una o varias personas (un certificado de convivencia se sube una vez y se vincula a cada miembro de la unidad), y según el tipo también a planes de intervención o valoraciones.

**Generación y firma de informes profesionales:** los profesionales del sistema (trabajadores sociales, psicólogos, abogados u otros perfiles colegiados) generan informes a partir de plantillas configurables. El informe nace como borrador, se completa con datos estructurados extraídos de la Historia Social y contenido libre redactado por el profesional, y finalmente se firma con el Certificado de Empleado Público del autor mediante AutoFirma. Una vez firmado, el informe queda inmutable y puede publicarse en la carpeta ciudadana.

El aspecto formal de los informes (logotipos, cabeceras, pies de página) se gestiona mediante **estilos de informe** vinculados a la jerarquía de Unidades Organizativas. Un supervisor define el estilo de su UO; las UOs descendientes heredan ese estilo campo a campo y pueden sobreescribir campos concretos sin afectar al resto. La tipografía es transversal a toda la organización y la configura el administrador del sistema. Las plantillas de informe (estructura de secciones y contenido) son independientes del estilo y también tienen alcance jerárquico: una plantilla creada en una UO está disponible para todos los profesionales de esa UO y sus descendientes.

El Plan de Intervención (PISO) es un caso especial: requiere firma del profesional **y** conformidad del ciudadano. En v1.0 esta doble firma se resuelve mediante impresión, firma manuscrita de ambas partes y custodia del documento escaneado. Las opciones de firma electrónica del ciudadano (Cl@ve Firma, firma biométrica en tablet) quedan documentadas como evolución futura.

### Principios de diseño

- **Almacenamiento desacoplado.** Los ficheros nunca se sirven desde rutas públicas. Todo acceso pasa por un controlador que verifica permisos y genera URLs firmadas temporales. El disco de almacenamiento es configurable por entorno (local en desarrollo, S3-compatible o SFTP en producción) sin cambios de código.
- **Integridad verificable.** Todo documento custodiado incluye un hash SHA-256 calculado en el momento de la subida. Cualquier alteración posterior del fichero es detectable.
- **Inmutabilidad de lo firmado.** Un informe en estado `firmado` no puede editarse ni eliminarse.

---

## 2. Entidades

### 2.1 Custodia v2 — tipos documentales, documentos, versiones y vínculos

> Implementados el 2026-09-25 los pasos 1 a 5 de `docs/instrucciones-cli/documentos-custodia-implementacion.md` (fuente de verdad del diseño): fases 2a y 2b, y el ciclo de vida de la 2c. Implementados también el paso 6 (acceso y auditoría) y el 7 (UI operativa: tarjeta «Documentos» de la ficha).

| Tabla | Modelo | Contenido |
|---|---|---|
| `tipos_documentales` | `TipoDocumental` | Configuración por tipo (familia, origen ENI, caducidad, política de versiones, conservación, límites, metadatos exigidos, entidades vinculables). Filament: `TipoDocumentalResource` (solo `adm_sistema`). Código, familia y origen son inmutables si hay documentos; un tipo con documentos no se borra, se desactiva |
| `documentos` | `Documento` | Documento lógico: `uuid`, tipo, título, fechas de emisión y validez, órgano emisor, estado, metadatos. «Caducado» se calcula (`caducados()`, `estaCaducado()`) |
| `documento_versiones` | `DocumentoVersion` | Un PDF cifrado por versión: `clave_almacenamiento` (UUID opaco), hash y tamaño del PDF en claro, páginas, `nombre_original` cifrado (cast `encrypted`), canal, clave de datos cifrada e id de clave maestra. Una sola vigente por documento (índice parcial) |
| `documento_vinculos` | `DocumentoVinculo` | Vínculos n:M (morph) a `Ciudadano` y, según el tipo, `PlanDeIntervencion` o `Valoracion`. Nunca a `UnidadConvivencia`. Baja lógica. Un solo vínculo activo por entidad (índice parcial) |
| `documento_retenciones` | `DocumentoRetencion` | Retenciones del documento o de una versión (`intervencion_cerrada`, `manual`). `RetencionService::retener()` existe; ningún evento la llama todavía |
| `actas_eliminacion` | `ActaEliminacion` | Constancia inmutable de lo destruido (solo metadatos; todos los ciudadanos que estuvieron vinculados, también los dados de baja) |
| `propuestas_eliminacion` | `PropuestaEliminacion` | Propuestas de destrucción por plazo vencido: estado (`pendiente`, `aprobada`, `rechazada`), ids de versiones propuestas y de las excluidas al aprobar, quién y cuándo la resolvió, acta. No se borran |

Las tablas `informes.documento_id`, `piso_firmados.documento_id` y `firmas_plan.documento_firmado_id` apuntan al documento lógico.

**Custodia v1 (sustituida).** La tabla anterior (`documentable` morph, `tipo_documento_id` a `catalogos_sistema`, fichero en claro) se eliminó sin migrar datos porque no había ninguna fila. El grupo `documento.tipo` de `catalogos_sistema` se conserva hasta decidir su retirada.

### 2.2 EstiloInforme

**Tabla:** `estilos_informe`
**Descripción:** Define el aspecto formal de los informes generados desde una UO. Los campos son independientes entre sí: cada UO hija puede sobreescribir campos concretos sin afectar a los demás.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `unidad_organizativa_id` | bigint FK unique | UO propietaria de este estilo |
| `logo_cabecera` | varchar(500) nullable | **Sin uso desde 2026-09-24** (ver más abajo) |
| `nombre_unidad_cabecera` | varchar(200) nullable | Nombre de la unidad a mostrar en cabecera |
| `direccion_cabecera` | varchar(300) nullable | Dirección postal |
| `telefono_cabecera` | varchar(50) nullable | Teléfono de contacto |
| `html_pie` | text nullable | HTML de pie de página. Admite el marcador `{{ numero_pagina }}` (`EstiloInforme::MARCADOR_NUMERO_PAGINA`), insertable desde un botón en el formulario, que `ServicioGeneracionPDF` sustituye por el número de página real de cada página del PDF (vía `Canvas::page_text()` de dompdf). |
| `creado_por` | bigint FK | Ref. `users` |
| `created_at` / `updated_at` | timestamp | |

**Resolución jerárquica:** `ResolverEstiloInforme` recorre la cadena de ancestros de la UO del autor (vía `laravel-adjacency-list`) hasta encontrar valor para cada campo. Resultado cacheado por UO con TTL configurable.

**Logotipo (decisión 2026-09-24, ver `docs/decisiones-tecnicas.md` Sección 12):** de momento se asume un único logotipo por organización, no uno por UO. `logo_cabecera` ya no se expone en el formulario de `EstiloInformeResource` ni determina el logo del PDF: `ServicioGeneracionPDF` usa siempre `Modules\Organizacion\Models\Configuracion::logoPathAbsoluto()`, que lee el logotipo de identidad visual subido en Sistema → Configuración → «Identidad visual» (el mismo que se muestra en el sidebar operativo). La columna y la resolución jerárquica de `logo_cabecera` se mantienen en el código por si se necesita revertir a un logo por UO.

### 2.3 PlantillaInforme

**Tabla:** `plantillas_informe`
**Descripción:** Plantilla configurable para la generación de informes profesionales. Define la estructura del informe mediante secciones. El aspecto formal lo aporta `EstiloInforme` en el momento de la generación.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `unidad_organizativa_id` | bigint FK | UO desde la que es visible hacia abajo |
| `nombre` | varchar(200) | Nombre de la plantilla |
| `descripcion` | text nullable | Descripción para el profesional en el selector |
| `tipo_informe` | enum | `informe_social` / `informe_psicologico` / `informe_juridico` / `otro` |
| `secciones` | jsonb | Array ordenado de secciones (cast: `array`) |
| `activa` | boolean | Solo las activas aparecen en el selector operativo |
| `creada_por` | bigint FK | Ref. `users` |
| `created_at` / `updated_at` | timestamp | |

**Estructura del campo `secciones`:**

```json
[
  {
    "id": "datos_ciudadano",
    "titulo": "Datos del ciudadano",
    "tipo": "automatico",
    "fuente": "ciudadano.datos_basicos",
    "editable": false
  },
  {
    "id": "situacion_actual",
    "titulo": "Situación actual",
    "tipo": "texto_libre",
    "instrucciones": "Describa la situación actual de la persona...",
    "contenido_plantilla": "<p>En relación a {{ nombre_ciudadano }}...</p>",
    "obligatorio": true
  }
]
```

Los tipos de sección son `automatico` (datos pre-cargados desde la Historia Social, no editables por el profesional) y `texto_libre` (campo redactable con soporte de merge tags).

El campo `contenido_plantilla` de las secciones de tipo `texto_libre` almacena HTML con nodos de merge tag de TipTap. Se sustituyen en `ResolverFuentesInforme::resolverMergeTags()` al generar el informe.

**Fuentes disponibles para secciones automáticas:**

| Clave | Descripción |
|---|---|
| `ciudadano.datos_basicos` | Nombre, NIF, fecha de nacimiento, dirección |
| `ciudadano.datos_contacto` | Teléfono, email |
| `ciudadano.unidad_convivencia` | Miembros de la unidad de convivencia |
| `historia_social.resumen` | Resumen y motivo de apertura |
| `historia_social.prestaciones_activas` | Prestaciones activas del plan vigente |
| `historia_social.prestaciones_historico` | Historial completo de prestaciones |
| `historia_social.plan_activo` | Objetivos del plan de intervención activo |
| `escalas.barthel_ultimo` | Último pase Barthel (score e interpretación) |
| `escalas.pfeiffer_ultimo` | Último pase Pfeiffer SPMSQ |
| `escalas.lawton_ultimo` | Último pase Lawton-Brody |
| `escalas.historico_barthel` | Histórico de pases Barthel |
| `profesional.datos` | Nombre, cargo, número de colegiado, centro |

**Scopes:** `scopeVisiblesParaUo($uoId)` — plantillas activas cuya UO es la indicada o cualquiera de sus ancestros.

### 2.4 Informe

**Tabla:** `informes`
**Descripción:** Instancia concreta de un informe profesional. Nace como borrador y culmina con la firma del autor.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `plantilla_id` | bigint FK | Ref. `plantillas_informe` |
| `historia_social_id` | bigint FK nullable | |
| `ciudadano_id` | bigint FK | |
| `autor_id` | bigint FK | Ref. `users` |
| `estado` | enum | `borrador` / `firmado` / `anulado` |
| `contenido` | jsonb | Mapa `seccion_id → texto` con el contenido del informe |
| `documento_id` | bigint FK nullable | PDF firmado; null hasta firma |
| `firmado_en` | timestamp nullable | |
| `metodo_firma` | enum nullable | `autofirma_certificado_empleado_publico` |
| `numero_colegiado_firmante` | varchar nullable | |
| `motivo_anulacion` | text nullable | Obligatorio si estado = `anulado` |
| `anulado_en` | timestamp nullable | |
| `created_at` / `updated_at` | timestamp | |

**Transiciones:** `borrador → firmado` (requiere PDF y certificado válido) · `firmado → anulado` (solo el autor, con motivo). Un informe anulado no puede reabrirse.

### 2.5 PisoFirmado

**Tabla:** `piso_firmados`
**Descripción:** Custodia del PISO con doble firma manuscrita escaneada. Un `PlanDeIntervencion` puede tener como máximo un `PisoFirmado` activo.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `plan_de_intervencion_id` | bigint FK | |
| `documento_id` | bigint FK | PDF escaneado |
| `subido_por` | bigint FK | Ref. `users` |
| `metodo_conformidad_ciudadano` | enum | `manuscrita_escaneada` |
| `observaciones` | text nullable | |
| `created_at` / `updated_at` | timestamp | |

### 2.6 ParametroInforme — ⏳ no implementado

> Diseño pendiente de implementar según `docs/instrucciones-cli/documentos-variables-auxiliares.md`. No existen la tabla, el modelo ni el recurso.

**Tabla:** `parametros_informe`
**Descripción:** Par clave/valor configurable por el administrador. Permite crear variables auxiliares en plantillas de informe sin modificar código. Los valores se cachean con TTL de 1 hora; la caché se invalida automáticamente al guardar o borrar un parámetro.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `clave` | varchar(100) unique | Nombre del merge tag: `ciudad`, `web_municipal`, etc. Solo minúsculas, números y guiones bajos. |
| `etiqueta` | varchar(200) | Texto legible que aparece en el autocompletado del editor |
| `valor` | text | Valor que se sustituye al generar el informe |
| `descripcion` | text nullable | Para qué se usa; solo visible en backoffice |
| `created_at` / `updated_at` | timestamp | |

Los parámetros son **globales** (un único valor por instalación). La variante por UO está documentada en BACKLOG como evolución futura.

**Seeder de parámetros de ejemplo:** `ciudad`, `nombre_sistema`, `web_municipal`, `telefono_atencion`.

---

## 3. Servicios

### Custodia v2 (fases 2a y 2b)

- **`IngestaDocumentoService::ingerir()`** — tubería de entrada, único camino al almacén. Pasos: directorio de trabajo propio en la zona temporal (`storage/app/tmp/ingesta/{uuid}`, se borra entero al terminar), detección por contenido (`DetectorFormato`), antivirus (`EscanerAntivirus`), conversión a PDF (`ConversorPdf`), rechazo de PDF con contraseña, saneado a PDF/A-2b y verificación de que no queda contenido activo (`SaneadorPdf`), límite de páginas, límite de bytes con un intento de recompresión, hash del PDF normalizado, cifrado, almacenamiento y registro en una transacción. Si algo falla lanza `IngestaRechazadaException` (código de motivo + mensaje en castellano llano) y no queda nada ni en BBDD, ni en disco, ni en el temporal.
  - **Formatos admitidos:** PDF, JPEG, PNG, HEIC, ODT y DOCX. Los zip se abren para distinguir ODT/DOCX de cualquier otro contenedor; un paquete OOXML con proyecto VBA o un ODT con `Basic/` o `Scripts/` se rechaza con `macros_no_admitidas`.
  - **Antivirus:** `EscanerClamAv` envía el contenido a clamd por su socket (`INSTREAM`), porque clamd no puede leer la zona temporal (0600). Si clamd no responde o devuelve un error, el fichero se rechaza (`antivirus_no_disponible`). `EscanerAntivirusFake` solo se registra con `DOCUMENTOS_ANTIVIRUS=fake` y `APP_ENV=testing`.
  - **Conversión:** imágenes con Imagick (una página, ajustada a A4, sin EXIF, con la orientación de la cámara); ODT/DOCX con LibreOffice headless y un perfil propio por conversión. Si falla, `conversion_fallida`.
  - **Saneado:** Ghostscript a PDF/A-2b con `-dPreserveEmbeddedFiles=false -dPreserveDocView=false -dPreserveAnnots=false`. Sin esos tres parámetros, Ghostscript conserva la `OpenAction` con JavaScript y los adjuntos. Después, `qpdf --qdf` expande el resultado y se rechaza si quedan `/JavaScript`, `/JS`, `/EmbeddedFile(s)` o `/Launch`. Los PDF con solo contraseña de propietario se admiten; los que piden contraseña para abrirse, no (`pdf_protegido`).
  - **PDF firmados (canal `generado`):** no se convierten, sanean ni recomprimen, porque reescribirlos invalida la firma PAdES. Sí se comprueba que no pidan contraseña ni contengan contenido activo. **Decisión confirmada por el desarrollador (2026-09-25):** los genera la propia aplicación, así que se dan por buenos sin normalizarlos a PDF/A.
  - **Hash:** es el del PDF normalizado que se custodia, no el del fichero subido. Ghostscript incluye fechas e identificadores en el XMP, así que dos subidas del mismo fichero producen hashes distintos.
- **`CicloVidaDocumentoService`** — `altaDocumento()` (valida tipo activo, al menos un vínculo, entidades permitidas y metadatos exigidos antes de tocar el almacén), `nuevaVersion()`, `desvincular()` y `desvincularEntidad()` (bajas lógicas).
  - **`nuevaVersion()`** bloquea el documento y marca la vigente como sustituida antes de insertar la nueva (el índice parcial solo admite una vigente). Si el tipo caduca, recalcula `fecha_validez` desde hoy. Con `purgar_no_retenidas` y la anterior sin retenciones, la purga. Se rechaza (`DomainException`) si el documento viene de un informe firmado (alguna versión con `informe_id`) o no está vigente.
  - **Baja de ciudadano:** el provider escucha `Ciudadano::deleted` (el soft delete de `CiudadanoService::eliminar()`) y llama a `desvincularEntidad()`. Los documentos, las versiones y los ficheros se conservan.
- **`DestructorVersiones`** — purga o destrucción de una versión: primero borra la clave de datos (`clave_cifrada = null`, crypto-shredding) y después el objeto del disco. Si ese borrado fallara, el objeto queda huérfano e ilegible y lo retira `limpiar-huerfanos`.
- **`DestruccionDocumentosService`** — `proponer()` (versiones con contenido, plazo del tipo vencido, sin retenciones y no incluidas ya en otra propuesta pendiente), `aprobar()` (solo `adm_sistema`: revisa cada versión en ese momento, excluye las retenidas, tritura las claves, marca el documento `destruido` si no le quedan versiones con contenido, levanta el acta y borra los objetos después del commit) y `rechazar()`. Sin versiones destruidas no se levanta acta.
- **`LecturaDocumentoService`** — descifra en memoria y verifica el hash; URL firmadas temporales a `documentos.ver` (`urlTemporal`) y `documentos.descargar` (`urlDescarga`); nombre de descarga genérico `{codigo}-{fecha}.pdf`.
- **`DocumentoController`** — única salida de documentos (`auth` + `signed`). Autoriza con `DocumentoPolicy` antes de descifrar, entrega la versión vigente (`ver` en línea, `descargar` como adjunto) y audita con `AuditService`: `ver` o `exportar` sobre el `Documento`, con `ciudadano_id` de la persona por la que se concede el acceso y `documento_id`, `documento_version_id` y `ciudadanos_vinculados` en el contexto. Los servicios se resuelven al atender la petición para que `route:list` no exija la clave maestra.
- **`DocumentoPolicy`** (`view`, `download`) — puede quien pueda ver al menos una persona con vínculo activo según `CiudadanoPolicy::view`. Es la misma regla que la ficha: lectura amplia con `ciudadano.leer`, salvo colectivos protegidos sin acceso aprobado y vigente. Los vínculos a planes o valoraciones no dan acceso. En Filament, «Ver PDF» solo aparece si la policy lo permite.
- **Auditoría de borrados:** la purga y la destrucción se registran con la acción `borrar` (`AccionAuditEnum::Borrar`) y el motivo, no como `editar`.
- **`AlmacenDocumentos` / `AlmacenFlysystem`** — único acceso al disco `documentos` (`DOCUMENTOS_RUTA`, permisos 0700/0600, sin URL). Ruta del objeto: `{2 primeros caracteres del UUID}/{UUID}`. Falla con mensaje claro si el directorio no existe o no se puede escribir.
- **`ProveedorClavesMaestras` / `ProveedorClavesLocal`** — clave maestra de `DOCUMENTOS_CLAVE_MAESTRA` (`base64:`, 32 bytes, distinta de `APP_KEY`). Sin ella la custodia no funciona; no hay modo en claro.
- **`CifradorDocumentos`** — AES-256-GCM con una clave de datos aleatoria por versión, cifrada con la clave maestra (envelope encryption).
- **Comandos:** `documentos:verificar-integridad [--muestra=N]`, `documentos:limpiar-huerfanos [--ejecutar]` y `documentos:proponer-destruccion` (no destruye nada; se puede programar, pero no está en el scheduler).

### ServicioGeneracionPDF

Genera el PDF a partir del contenido del informe y la plantilla. Combina datos automáticos (vía `ResolverFuentesInforme`) con texto libre del profesional, aplica cabecera y pie de `EstiloInforme`, produce el PDF mediante `barryvdh/laravel-dompdf`. El PDF de borrador es sobrescribible; el PDF firmado es inmutable.

### ServicioFirmaInforme

Coordina la firma con AutoFirma (integración Livewire pendiente). Recibe el PDF firmado y lo custodia con `CicloVidaDocumentoService` (tipo `informe_profesional`, canal `generado`, vinculado al ciudadano del informe, con informe y plantilla de origen en la versión). La validación de la firma y la extracción del número de colegiado siguen pendientes.

### ResolverFuentesInforme

Resuelve todas las variables que pueden aparecer en las plantillas de informe. Opera con tres categorías de variables, en orden de prioridad decreciente:

**1. Tags contextuales** (mayor prioridad) — dependen del ciudadano, profesional y fecha del informe concreto. Se construyen en `construirMapaValores()` a partir de las entidades del expediente. Incluyen: datos del ciudadano, del expediente, de las escalas de valoración (último pase de Barthel, Pfeiffer y Lawton-Brody), del plan de intervención activo, del profesional autor y del centro.

**2. Variables dinámicas de sistema** (⏳ no implementado) — calculadas en tiempo de ejecución, iguales para todos los informes. Implementadas en `VariablesDinamicas::resolver()`. Variables disponibles: `fecha_hoy` (dd/mm/aaaa), `año_actual`, `mes_actual` (nombre del mes en español).

**3. Parámetros configurables** (⏳ no implementado; menor prioridad) — leídos de `parametros_informe` vía `ParametroInforme::comoMapa()` con caché de 1 hora. Ejemplos: `ciudad`, `web_municipal`, `telefono_atencion`.

En caso de colisión de clave, los tags contextuales siempre ganan frente a los parámetros configurables. Esto garantiza que ningún administrador puede romper un informe creando un parámetro `nombre_ciudadano`.

El método `resolverMergeTags(string $html, int $ciudadanoId, int $profesionalId, Carbon $fechaInforme): string` sustituye todos los tags en el HTML de una sección y devuelve el HTML con los valores reales.

### MergeTagsCatalogo

Clase de soporte que centraliza el catálogo de variables disponibles en el editor de plantillas. `todos()` devuelve el array `['clave' => 'etiqueta']` que consume `RichEditor::mergeTags()` en Filament. Hoy solo incluye los tags contextuales. Las variables dinámicas y los parámetros configurables llegarán con las variables auxiliares (⏳).

### VariablesDinamicas — ⏳ no implementado

Clase de soporte sin estado. `etiquetas()` devuelve el mapa de claves y descripciones para el editor. `resolver()` devuelve el mapa de claves y valores calculados en tiempo de ejecución.

---

## 4. Interfaces de usuario

### Filament (backoffice)

Grupo de navegación **«Informes y Plantillas»** (accesible a supervisores y administradores):

- **`EstiloInformeResource`** — gestión del estilo formal por UO (cabecera de texto y pie de página). El supervisor ve y edita solo los estilos de su UO y sus descendientes. El logotipo no se gestiona aquí (ver 2.2) — el formulario enlaza a Sistema → Configuración → «Identidad visual».
- **`PlantillaInformeResource`** — CRUD de plantillas. Editor de secciones con `Builder` de Filament v5: secciones colapsables con drag-and-drop, campo `RichEditor` con merge tags nativos para secciones de tipo `texto_libre`, `Select` de fuentes para secciones de tipo `automatico`. Layout: datos generales en dos columnas, bloque de secciones a ancho completo.
- **`InformeResource`** — listado de informes con filtros por estado y autor.
- **`DocumentoResource`** — listado de documentos custodiados.
- **`TipoEscalaResource`** — ver módulo Escalas.
- ⏳ **`ParametroInformeResource`** (no implementado) — gestión de parámetros configurables de plantillas. Accesible solo a `adm_sistema`. Formulario con validación de formato de clave (`/^[a-z][a-z0-9_]*$/`).

Grupo **«Sistema»** (solo administradores):

- **`PropuestaEliminacionResource`** (orden 7, solo `adm_sistema`) — listado y detalle de las propuestas de destrucción, con las acciones «Aprobar y destruir» (pide el motivo del acta) y «Rechazar». No permite crear, editar ni borrar.

- ⏳ **`ConfiguracionTipografiaResource`** (no implementado) — tipografía base para todos los informes generados. Hoy la tipografía sale de `config/documentos.php`.

### Livewire (operativo)

- ✅ **`DocumentosCiudadano`** (`documentos.documentos-ciudadano`, 2026-09-25): tarjeta «Documentos» en la columna principal de la ficha del ciudadano, debajo de «Unidad de convivencia».
  - **Lista:** documentos con vínculo activo que el usuario puede ver (`DocumentoPolicy`), sin los destruidos. Muestra tipo, descripción, versión vigente, fechas y órgano emisor, una etiqueta «Caducado el…» o «Válido hasta…», y el historial desplegable de versiones anteriores con su estado. Las versiones anteriores no se pueden abrir: el controlador sirve solo la vigente.
  - **Acciones:** «Ver» y «Descargar» (URL firmadas al controlador). Además, «Subir documento», «Nueva versión» (no en documentos de informes firmados) y «Desvincular» (con confirmación), que solo aparecen a quien puede editar al ciudadano (`CiudadanoPolicy::update`: `ciudadano.editar` y ámbito de UO; supervisión nunca). El servidor lo vuelve a comprobar (403).
  - **Modal de subida** (Bootstrap, controlado por Livewire, como «Nueva atención»): tipo documental (activos y vinculables a personas), fichero, cómo se ha recibido (presencial o escaneo), descripción, fecha de emisión, órgano emisor, metadatos adicionales que exige el tipo y casillas para asociar a otros miembros activos de la unidad de convivencia (solo se aceptan miembros reales). Los rechazos de la ingesta se muestran en el modal con su mensaje.
  - **Límite de subida:** `DOCUMENTOS_MAX_SUBIDA_KB` (50 MB por defecto, como `client_max_body_size` de nginx). El provider sube a ese valor el límite temporal de Livewire (12 MB por defecto); el tipo documental aplica después su propio límite.

⏳ No implementados (hoy un profesional no puede redactar ni firmar informes ni subir el PISO desde la superficie operativa; solo hay visores en Filament):

- **`NuevoInformeWizard`** — asistente en 4 pasos: selección de plantilla → edición de secciones de texto libre → vista previa PDF → firma con AutoFirma. Las secciones `automatico` se pre-cargan y no son editables. No avanza al paso 4 si hay secciones `obligatorio: true` vacías.
- **`InformesHistorialComponent`** — listado de informes de una Historia Social. Acciones sobre informes firmados: ver PDF, anular (solo el autor, con motivo obligatorio).
- **`PisoFirmadoUploadComponent`** — subida del PISO escaneado con doble firma manuscrita.

---

## 5. Decisiones de diseño

**Estilo con herencia jerárquica por campos independientes.** Para cada campo del estilo, el sistema busca valor en la UO del autor y sube por la jerarquía hasta encontrarlo. Esto permite que una Dirección General defina el logo y un centro defina solo su nombre, sin conflicto.

**Parámetros configurables globales en v1.0.** Los parámetros de `parametros_informe` tienen un único valor por instalación. Variables como `{{ distrito }}` que podrían necesitar valores distintos por UO quedan documentadas en BACKLOG como evolución futura: añadir `unidad_organizativa_id nullable` con resolución jerárquica idéntica a `EstiloInforme`.

**Prioridad de resolución de merge tags.** Tags contextuales > variables dinámicas de sistema > parámetros configurables. Los tags contextuales siempre ganan.

**Campo `secciones` con cast `array`.** El modelo `PlantillaInforme` tiene `'secciones' => 'array'` en `$casts`. Sin este cast, Eloquent devuelve el campo como string JSON y el Repeater/Builder de Filament explota con `foreach() argument must be of type array|object, string given`.

**Versiones de `PlantillaInforme`.** No se implementa versionado. Si una plantilla cambia, los informes ya generados conservan el contenido con el que fueron creados (campo `contenido` en `Informe`).

**Firma del ciudadano en el PISO.** En v1.0, solo firma manuscrita escaneada. Opciones futuras documentadas: Cl@ve Firma (requiere Nivel Avanzado y despliegue limitado actualmente), firma biométrica en tablet (dependencia de hardware), OTP como evidencia de consentimiento (menor fricción, no es firma cualificada).

**Publicación en carpeta ciudadana.** Pendiente de diseño de integración con `CarpetaCiudadanaInterface` (Módulo Integraciones).

**Cuotas de almacenamiento.** Límites por tipo documental (por defecto 20 MB y 50 páginas, `config/documentos.php`). Cuotas globales no definidas.

**Acceso a documentos compartidos (regla provisional, pendiente de revisar).** Un usuario puede ver o descargar un documento si puede ver al menos una de las personas con vínculo activo, con `CiudadanoPolicy::view` (decisión del 2026-09-25: el mismo acceso amplio que la ficha, conforme al principio «acceso amplio, auditoría total»). Así, un profesional puede abrir un documento compartido por una persona protegida y otra que no lo es.

---

## 6. Tests funcionales

Ficheros: `Modules/Documentos/tests/Feature/DocumentosTest.php` (TF-DOC-01 a 21 y 79 a 81) y un fichero por grupo de la custodia v2. Fixtures en `Modules/Documentos/tests/fixtures/` (`generar-fixtures.sh`).

### Estado de ejecución — revisado 2026-09-25

| Área | Tests | Estado |
|---|---|---|
| Custodia de documentos (TF-DOC-01 a 05) | 5 | ✅ reescritos contra la custodia v2 (02 usa un ZIP) |
| Estilos e herencia jerárquica (TF-DOC-06 a 08) | 3 | ✅ |
| Plantillas de informe (TF-DOC-09, 10) | 2 | ✅ |
| Ciclo de vida del informe (TF-DOC-11 a 16) | 6 | ✅ |
| PISO firmado (TF-DOC-17, 18) | 2 | ✅ |
| Configuración y visibilidad (TF-DOC-19, 20) | 2 | ✅ |
| Merge tags contextuales (TF-DOC-21) | 1 | ✅ |
| Variables auxiliares (TF-DOC-22 a 25) | 4 | ⏳ no implementado |
| Custodia v2 — tipos documentales (TF-DOC-26 a 31) | 6 | ✅ `TiposDocumentalesTest` |
| Custodia v2 — modelo y vínculos n:M (TF-DOC-32 a 38) | 7 | ✅ `VinculosDocumentoTest` |
| Custodia v2 — almacenamiento y cifrado (TF-DOC-39 a 45) | 7 | ✅ `AlmacenamientoCifradoTest` |
| Custodia v2 — tubería de entrada (TF-DOC-46 a 58) | 13 | ✅ `IngestaDocumentoTest` (51 a 53 y 55 en `#[Group('binarios')]`) |
| Custodia v2 — ciclo de vida (TF-DOC-59 a 66) | 8 | ✅ `CicloVidaDocumentoTest` |
| Custodia v2 — retenciones, informes y destrucción (TF-DOC-67 a 73) | 7 | ✅ `RetencionDestruccionTest` |
| Custodia v2 — acceso y auditoría (TF-DOC-74 a 78) | 5 | ✅ `AccesoDocumentoTest` (TF-DOC-75 adaptado a la regla de acceso amplio) |
| Pie con número de página y logo único (TF-DOC-79 a 81) | 3 | ✅ |
| UI operativa: tarjeta «Documentos» de la ficha (sin numerar) | 10 | ✅ `DocumentosCiudadanoTest` |
| **Total implementado** | **87** | **87 ✅** |

TF-DOC-79 a 81 se llamaban TF-DOC-26, 27 y 29 (2026-09-24); se renumeraron el 2026-09-25 para no chocar con la numeración de la custodia v2.

### TF-DOC-01 a TF-DOC-20

*(Tests de la implementación inicial — sin cambios respecto a la versión anterior del documento.)*

### ✅ TF-DOC-21 — Merge tags contextuales se sustituyen al generar contenido

Dado un ciudadano «María López» con expediente «EXP-2026-001» y un `PaseEscala` Barthel completado con `score_total=75`; una sección `texto_libre` con `contenido_plantilla` que contiene `{{ nombre_ciudadano }}`, `{{ numero_expediente }}` y `{{ score_barthel }}`. Cuando se llama a `ResolverFuentesInforme::resolverMergeTags()`. Entonces el HTML resultante contiene «María López», «EXP-2026-001» y «75»; no contiene ningún tag sin sustituir.

### ⏳ TF-DOC-22 — Variables dinámicas de sistema se resuelven correctamente

Dado ningún parámetro en BD; HTML con `{{ fecha_hoy }}` y `{{ año_actual }}`. Cuando se llama a `resolverMergeTags()`. Entonces `{{ fecha_hoy }}` se sustituye por la fecha de hoy en formato dd/mm/aaaa; `{{ año_actual }}` por el año actual como string de 4 dígitos.

### ⏳ TF-DOC-23 — Parámetro configurable se resuelve en el informe

Dado un `ParametroInforme` con `clave='ciudad'` y `valor='Madrid'`; HTML con `{{ ciudad }}`. Cuando se llama a `resolverMergeTags()`. Entonces `{{ ciudad }}` se sustituye por «Madrid».

### ⏳ TF-DOC-24 — Tag contextual tiene prioridad sobre parámetro configurable

Dado un `ParametroInforme` con `clave='nombre_ciudadano'` y `valor='VALOR_TRAMPA'`; ciudadano con nombre «María López». Cuando se llama a `resolverMergeTags()` con HTML que contiene `{{ nombre_ciudadano }}`. Entonces el resultado contiene «María López», no «VALOR_TRAMPA».

### ⏳ TF-DOC-25 — Clave de parámetro con formato inválido no puede guardarse

Dado ningún parámetro existente. Cuando se intenta crear un `ParametroInforme` con `clave='Mi Ciudad'` (contiene espacio). Entonces falla la validación; no se crea ningún registro.

---

## 7. Dependencias con otros módulos

| Módulo | Dependencia |
|---|---|
| Organización | `UnidadOrganizativa` — jerarquía para resolución de estilos y alcance de plantillas. `Configuracion::logoPathAbsoluto()` — logotipo único de organización usado en la cabecera del PDF (ver 2.2) |
| Ciudadanía | `Ciudadano`, `UnidadConvivencia` — entidades documentables |
| Intervención | `HistoriaSocial`, `PlanDeIntervencion` — fuentes de datos para informes |
| Escalas | `PaseEscala`, `TipoEscala` — scores de valoración disponibles como merge tags |
| Usuarios y Permisos | `User` — autor, firmante, control de acceso |
| Integraciones | `CarpetaCiudadanaInterface` — publicación futura de informes firmados (pendiente) |
