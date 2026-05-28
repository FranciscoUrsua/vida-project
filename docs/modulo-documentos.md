# Módulo Documentos

**Módulo:** `Documentos`
**Namespace:** `Modules\Documentos\Models`
**Directorio:** `vida/Modules/Documentos/`
**Estado:** Implementado. 25/25 tests funcionales pasan (última actualización: mayo 2026).

---

## 1. Introducción funcional

El módulo Documentos cubre dos necesidades diferenciadas que comparten infraestructura pero tienen ciclos de vida y reglas de negocio distintos.

**Custodia de documentos externos:** los profesionales incorporan al expediente documentos generados fuera de VIDA 360 — informes médicos, certificados, documentación de identidad, resoluciones de otras administraciones, etc. Estos documentos se asocian a un Ciudadano o a una Unidad de Convivencia, se clasifican por tipo y quedan disponibles para cualquier profesional con acceso al expediente.

**Generación y firma de informes profesionales:** los profesionales del sistema (trabajadores sociales, psicólogos, abogados u otros perfiles colegiados) generan informes a partir de plantillas configurables. El informe nace como borrador, se completa con datos estructurados extraídos de la Historia Social y contenido libre redactado por el profesional, y finalmente se firma con el Certificado de Empleado Público del autor mediante AutoFirma. Una vez firmado, el informe queda inmutable y puede publicarse en la carpeta ciudadana.

El aspecto formal de los informes (logotipos, cabeceras, pies de página) se gestiona mediante **estilos de informe** vinculados a la jerarquía de Unidades Organizativas. Un supervisor define el estilo de su UO; las UOs descendientes heredan ese estilo campo a campo y pueden sobreescribir campos concretos sin afectar al resto. La tipografía es transversal a toda la organización y la configura el administrador del sistema. Las plantillas de informe (estructura de secciones y contenido) son independientes del estilo y también tienen alcance jerárquico: una plantilla creada en una UO está disponible para todos los profesionales de esa UO y sus descendientes.

El Plan de Intervención (PISO) es un caso especial: requiere firma del profesional **y** conformidad del ciudadano. En v1.0 esta doble firma se resuelve mediante impresión, firma manuscrita de ambas partes y custodia del documento escaneado. Las opciones de firma electrónica del ciudadano (Cl@ve Firma, firma biométrica en tablet) quedan documentadas como evolución futura.

### Principios de diseño

- **Almacenamiento desacoplado.** Los ficheros nunca se sirven desde rutas públicas. Todo acceso pasa por un controlador que verifica permisos y genera URLs firmadas temporales. El disco de almacenamiento es configurable por entorno (local en desarrollo, S3-compatible o SFTP en producción) sin cambios de código.
- **Integridad verificable.** Todo documento custodiado incluye un hash SHA-256 calculado en el momento de la subida. Cualquier alteración posterior del fichero es detectable.
- **Inmutabilidad de lo firmado.** Un informe en estado `firmado` no puede editarse ni eliminarse.

---

## 2. Entidades

### 2.1 Documento

**Tabla:** `documentos`
**Descripción:** Fichero custodiado en el sistema. Puede ser un documento externo subido por un profesional o el PDF generado al firmar un informe.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `ciudadano_id` | bigint FK nullable | Ciudadano al que pertenece el documento |
| `unidad_convivencia_id` | bigint FK nullable | Unidad de convivencia (alternativa a ciudadano_id) |
| `tipo` | varchar(100) | Clave de `catalogos_sistema` grupo `documento.tipo` |
| `nombre_original` | varchar(255) | Nombre del fichero tal como lo subió el profesional |
| `ruta_almacenamiento` | varchar(500) | Ruta interna en el disco configurado |
| `disco` | varchar(50) | Disco Laravel en el que se almacenó |
| `mime_type` | varchar(100) | MIME verificado en el momento de la subida |
| `tamanyo_bytes` | bigint | Tamaño del fichero |
| `hash_sha256` | char(64) | Hash para verificación de integridad |
| `subido_por` | bigint FK | Ref. `users` |
| `created_at` / `updated_at` | timestamp | |

### 2.2 EstiloInforme

**Tabla:** `estilos_informe`
**Descripción:** Define el aspecto formal de los informes generados desde una UO. Los campos son independientes entre sí: cada UO hija puede sobreescribir campos concretos sin afectar a los demás.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `unidad_organizativa_id` | bigint FK unique | UO propietaria de este estilo |
| `logo_path` | varchar(500) nullable | Ruta al logotipo |
| `nombre_cabecera` | varchar(200) nullable | Nombre de la unidad a mostrar en cabecera |
| `direccion_cabecera` | varchar(300) nullable | Dirección postal |
| `telefono_cabecera` | varchar(50) nullable | Teléfono de contacto |
| `html_pie` | text nullable | HTML de pie de página |
| `creado_por` | bigint FK | Ref. `users` |
| `created_at` / `updated_at` | timestamp | |

**Resolución jerárquica:** `ResolverEstiloInforme` recorre la cadena de ancestros de la UO del autor (vía `laravel-adjacency-list`) hasta encontrar valor para cada campo. Resultado cacheado por UO con TTL configurable.

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

### 2.6 ParametroInforme

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

### ServicioAlmacenamiento

Abstracción sobre Laravel Filesystem. Centraliza subida, descarga y eliminación lógica. Genera URLs temporales firmadas. Valida tipo MIME, calcula hash SHA-256, construye ruta interna `documentos/{año}/{mes}/{uuid}.pdf`.

### ServicioGeneracionPDF

Genera el PDF a partir del contenido del informe y la plantilla. Combina datos automáticos (vía `ResolverFuentesInforme`) con texto libre del profesional, aplica cabecera y pie de `EstiloInforme`, produce el PDF mediante `barryvdh/laravel-dompdf`. El PDF de borrador es sobrescribible; el PDF firmado es inmutable.

### ServicioFirmaInforme

Coordina la firma con AutoFirma (integración Livewire). Recibe el PDF de borrador, invoca AutoFirma en el cliente, recibe el PDF firmado con firma CAdES, verifica validez, extrae número de colegiado del certificado, persiste el documento via `ServicioAlmacenamiento`.

### ResolverFuentesInforme

Resuelve todas las variables que pueden aparecer en las plantillas de informe. Opera con tres categorías de variables, en orden de prioridad decreciente:

**1. Tags contextuales** (mayor prioridad) — dependen del ciudadano, profesional y fecha del informe concreto. Se construyen en `construirMapaValores()` a partir de las entidades del expediente. Incluyen: datos del ciudadano, del expediente, de las escalas de valoración (último pase de Barthel, Pfeiffer y Lawton-Brody), del plan de intervención activo, del profesional autor y del centro.

**2. Variables dinámicas de sistema** — calculadas en tiempo de ejecución, iguales para todos los informes. Implementadas en `VariablesDinamicas::resolver()`. Variables disponibles: `fecha_hoy` (dd/mm/aaaa), `año_actual`, `mes_actual` (nombre del mes en español).

**3. Parámetros configurables** (menor prioridad) — leídos de `parametros_informe` vía `ParametroInforme::comoMapa()` con caché de 1 hora. Ejemplos: `ciudad`, `web_municipal`, `telefono_atencion`.

En caso de colisión de clave, los tags contextuales siempre ganan frente a los parámetros configurables. Esto garantiza que ningún administrador puede romper un informe creando un parámetro `nombre_ciudadano`.

El método `resolverMergeTags(string $html, int $ciudadanoId, int $profesionalId, Carbon $fechaInforme): string` sustituye todos los tags en el HTML de una sección y devuelve el HTML con los valores reales.

### MergeTagsCatalogo

Clase de soporte que centraliza el catálogo de variables disponibles en el editor de plantillas. `todos()` devuelve el array `['clave' => 'etiqueta']` que consume `RichEditor::mergeTags()` en Filament. Incluye las tres categorías: tags contextuales (estáticos), variables dinámicas de sistema (vía `VariablesDinamicas::etiquetas()`), y parámetros configurables (leídos de BD con caché).

### VariablesDinamicas

Clase de soporte sin estado. `etiquetas()` devuelve el mapa de claves y descripciones para el editor. `resolver()` devuelve el mapa de claves y valores calculados en tiempo de ejecución.

---

## 4. Interfaces de usuario

### Filament (backoffice)

Grupo de navegación **«Informes y Plantillas»** (accesible a supervisores y administradores):

- **`EstiloInformeResource`** — gestión del estilo formal por UO. El supervisor ve y edita solo los estilos de su UO y sus descendientes. Incluye vista previa del aspecto resultante.
- **`PlantillaInformeResource`** — CRUD de plantillas. Editor de secciones con `Builder` de Filament v5: secciones colapsables con drag-and-drop, campo `RichEditor` con merge tags nativos para secciones de tipo `texto_libre`, `Select` de fuentes para secciones de tipo `automatico`. Layout: datos generales en dos columnas, bloque de secciones a ancho completo.
- **`InformeResource`** — listado de informes con filtros por estado y autor.
- **`DocumentoResource`** — listado de documentos custodiados.
- **`TipoEscalaResource`** — ver módulo Escalas.
- **`ParametroInformeResource`** — gestión de parámetros configurables de plantillas. Accesible solo a `adm_sistema`. Formulario con validación de formato de clave (`/^[a-z][a-z0-9_]*$/`).

Grupo **«Sistema»** (solo administradores):

- **`ConfiguracionTipografiaResource`** — tipografía base para todos los informes generados.

### Livewire (operativo)

- **`DocumentosCiudadanoComponent`** — panel de documentos de un ciudadano. Subida, previsualización (URL firmada temporal), descarga.
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

**Cuotas de almacenamiento.** No definidas. Establecer límites operativos antes de producción.

---

## 6. Tests funcionales

Fichero: `Modules/Documentos/tests/Feature/DocumentosTest.php`

### Estado de ejecución — mayo 2026

| Área | Tests | Estado |
|---|---|---|
| Custodia de documentos (TF-DOC-01 a 05) | 5 | ✅ |
| Estilos e herencia jerárquica (TF-DOC-06 a 08) | 3 | ✅ |
| Plantillas de informe (TF-DOC-09, 10) | 2 | ✅ |
| Ciclo de vida del informe (TF-DOC-11 a 16) | 6 | ✅ |
| PISO firmado (TF-DOC-17, 18) | 2 | ✅ |
| Configuración y visibilidad (TF-DOC-19, 20) | 2 | ✅ |
| Merge tags contextuales (TF-DOC-21) | 1 | ✅ |
| Variables auxiliares (TF-DOC-22 a 25) | 4 | ✅ |
| **Total** | **25** | **25 ✅** |

### TF-DOC-01 a TF-DOC-20

*(Tests de la implementación inicial — sin cambios respecto a la versión anterior del documento.)*

### ✅ TF-DOC-21 — Merge tags contextuales se sustituyen al generar contenido

Dado un ciudadano «María López» con expediente «EXP-2026-001» y un `PaseEscala` Barthel completado con `score_total=75`; una sección `texto_libre` con `contenido_plantilla` que contiene `{{ nombre_ciudadano }}`, `{{ numero_expediente }}` y `{{ score_barthel }}`. Cuando se llama a `ResolverFuentesInforme::resolverMergeTags()`. Entonces el HTML resultante contiene «María López», «EXP-2026-001» y «75»; no contiene ningún tag sin sustituir.

### ✅ TF-DOC-22 — Variables dinámicas de sistema se resuelven correctamente

Dado ningún parámetro en BD; HTML con `{{ fecha_hoy }}` y `{{ año_actual }}`. Cuando se llama a `resolverMergeTags()`. Entonces `{{ fecha_hoy }}` se sustituye por la fecha de hoy en formato dd/mm/aaaa; `{{ año_actual }}` por el año actual como string de 4 dígitos.

### ✅ TF-DOC-23 — Parámetro configurable se resuelve en el informe

Dado un `ParametroInforme` con `clave='ciudad'` y `valor='Madrid'`; HTML con `{{ ciudad }}`. Cuando se llama a `resolverMergeTags()`. Entonces `{{ ciudad }}` se sustituye por «Madrid».

### ✅ TF-DOC-24 — Tag contextual tiene prioridad sobre parámetro configurable

Dado un `ParametroInforme` con `clave='nombre_ciudadano'` y `valor='VALOR_TRAMPA'`; ciudadano con nombre «María López». Cuando se llama a `resolverMergeTags()` con HTML que contiene `{{ nombre_ciudadano }}`. Entonces el resultado contiene «María López», no «VALOR_TRAMPA».

### ✅ TF-DOC-25 — Clave de parámetro con formato inválido no puede guardarse

Dado ningún parámetro existente. Cuando se intenta crear un `ParametroInforme` con `clave='Mi Ciudad'` (contiene espacio). Entonces falla la validación; no se crea ningún registro.

---

## 7. Dependencias con otros módulos

| Módulo | Dependencia |
|---|---|
| Organización | `UnidadOrganizativa` — jerarquía para resolución de estilos y alcance de plantillas |
| Ciudadanía | `Ciudadano`, `UnidadConvivencia` — entidades documentables |
| Intervención | `HistoriaSocial`, `PlanDeIntervencion` — fuentes de datos para informes |
| Escalas | `PaseEscala`, `TipoEscala` — scores de valoración disponibles como merge tags |
| Usuarios y Permisos | `User` — autor, firmante, control de acceso |
| Integraciones | `CarpetaCiudadanaInterface` — publicación futura de informes firmados (pendiente) |
