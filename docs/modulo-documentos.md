# Módulo Documentos

**Módulo:** `Documentos`
**Namespace:** `Modules\Documentos\Models`
**Directorio:** `vida/Modules/Documentos/`
**Estado:** Implementado. 20/20 tests funcionales pasan (2026-05-18).

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
- **Inmutabilidad de lo firmado.** Un informe en estado `firmado` no puede editarse ni eliminarse. Solo puede ser anulado por el propio autor, con registro de motivo, generando un nuevo estado `anulado`. El fichero original permanece en el sistema.
- **La responsabilidad de autoría es personal.** El informe profesional es un acto personalísimo del profesional colegiado que lo firma, no de la institución. El sistema no ofrece mecanismo alternativo de firma para informes profesionales. No existe fallback de firma manuscrita para este tipo de documentos.
- **Filament para configuración, Livewire para operación** (Principio 3.12). Las plantillas de informe se configuran desde Filament. La creación, edición y firma de informes concretos se realiza desde interfaces Livewire.

---

## 2. Entidades

### 2.1 Documento

**Tabla:** `documentos`
**Descripción:** Fichero custodiado en el sistema. Puede ser un documento subido externamente o el PDF resultante de un informe generado. Asociado polimórficamente a cualquier entidad del sistema.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `documentable_type` | string | Tipo de entidad asociada (Ciudadano, UnidadConvivencia, HistoriaSocial…) |
| `documentable_id` | bigint | ID de la entidad asociada |
| `tipo_documento_id` | bigint FK | Ref. `catalogos_sistema` — tipo de documento (informe médico, DNI, certificado…) |
| `origen` | enum | `externo` / `generado` |
| `nombre_original` | string | Nombre del fichero tal como fue subido |
| `ruta_almacenamiento` | string | Ruta interna en el disco configurado. Nunca pública. |
| `disco` | string | Identificador del disco Laravel Filesystem (`local`, `s3`, `sftp`…) |
| `mime_type` | string | Tipo MIME verificado en subida |
| `tamano_bytes` | bigint | Tamaño del fichero en bytes |
| `hash_sha256` | string | Hash de integridad calculado en subida |
| `subido_por` | bigint FK | Ref. `users` — profesional que realizó la subida |
| `descripcion` | text nullable | Descripción libre opcional |
| `created_at` / `updated_at` | timestamp | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `documentable()` | `MorphTo` | Entidad a la que pertenece el documento |
| `tipo()` | `BelongsTo<CatalogoSistema>` | Tipo de documento |
| `subidoPor()` | `BelongsTo<User>` | Profesional que subió el documento |
| `informe()` | `HasOne<Informe>` | Informe profesional asociado, si lo hay |

**Scopes:**
- `scopeExternos()` — solo documentos de origen externo
- `scopeGenerados()` — solo PDFs generados por el sistema

**Nota:** los documentos de origen `externo` solo admiten PDF. Otros formatos (imágenes de documentos de identidad, etc.) se convierten a PDF en el momento de la subida o se rechazan con mensaje descriptivo.

---

### 2.2 EstiloInforme

**Tabla:** `estilos_informe`
**Descripción:** Define el aspecto formal de los informes generados por una Unidad Organizativa. Los campos se heredan por proximidad ascendente en la jerarquía de UOs: para cada campo, el sistema utiliza el valor definido en la UO más cercana al autor del informe que lo tenga establecido. Una UO puede sobreescribir campos concretos (p.ej. añadir el logotipo del centro) sin afectar a los campos que no define, que siguen resolviéndose en niveles superiores.

La tipografía no forma parte de esta entidad: es una configuración transversal gestionada por el administrador del sistema.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `unidad_organizativa_id` | bigint FK unique | Ref. `unidades_organizativas` — una UO tiene como máximo un estilo |
| `logo_cabecera` | string nullable | Ruta al fichero de logotipo institucional (disco configurado) |
| `nombre_unidad_cabecera` | string nullable | Nombre de la unidad a mostrar en cabecera (p.ej. «Centro de SS de Vallecas») |
| `direccion_cabecera` | string nullable | Dirección postal a mostrar en cabecera |
| `telefono_cabecera` | string nullable | Teléfono de contacto a mostrar en cabecera |
| `html_pie` | text nullable | HTML de pie de página libre: puede incluir textos legales, webs, redes, etc. |
| `creado_por` | bigint FK | Ref. `users` — supervisor que creó o modificó este estilo |
| `created_at` / `updated_at` | timestamp | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `unidadOrganizativa()` | `BelongsTo<UnidadOrganizativa>` | UO propietaria de este estilo |
| `creadoPor()` | `BelongsTo<User>` | Supervisor autor |

**Servicio de resolución:** `ResolverEstiloInforme` recibe la UO del autor y recorre la cadena de ancestros (vía `laravel-adjacency-list`) hasta encontrar valor para cada campo. El resultado se cachea por UO con TTL configurable. En ausencia de cualquier estilo en la jerarquía, se aplican los valores por defecto definidos en configuración del sistema.

**Filament:** `EstiloInformeResource` (grupo *Diseño de informes*) — accesible solo para usuarios con rol supervisor o administrador. Cada supervisor solo puede editar el estilo de su propia UO y las descendientes bajo su responsabilidad.

---

### 2.3 PlantillaInforme

**Tabla:** `plantillas_informe`
**Descripción:** Plantilla configurable para la generación de informes profesionales. Define la estructura del informe y las secciones que lo componen. El aspecto formal (cabeceras, logotipos, pies) lo aporta `EstiloInforme` en el momento de la generación — la plantilla es independiente del estilo.

Las plantillas tienen **alcance jerárquico**: una plantilla creada en una UO está disponible para todos los profesionales de esa UO y de todas sus descendientes. Un supervisor crea la plantilla al nivel adecuado para que llegue exactamente a los profesionales que deben usarla.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `unidad_organizativa_id` | bigint FK | Ref. `unidades_organizativas` — UO desde la que es visible hacia abajo |
| `nombre` | string | Nombre de la plantilla (p.ej. «Informe Social de Valoración») |
| `descripcion` | text nullable | Descripción para el profesional en el selector |
| `tipo_informe` | enum | `informe_social` / `informe_psicologico` / `informe_juridico` / `otro` |
| `secciones` | jsonb | Array ordenado de secciones (ver estructura más abajo) |
| `activa` | boolean | Solo las plantillas activas aparecen en el selector operativo |
| `creada_por` | bigint FK | Ref. `users` — supervisor autor |
| `created_at` / `updated_at` | timestamp | |

**Estructura del campo `secciones` (JSON):**

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
    "obligatorio": true
  },
  {
    "id": "valoracion",
    "titulo": "Valoración profesional",
    "tipo": "texto_libre",
    "instrucciones": "Incluya el diagnóstico social y la valoración técnica.",
    "obligatorio": true
  },
  {
    "id": "prestaciones_activas",
    "titulo": "Prestaciones en vigor",
    "tipo": "automatico",
    "fuente": "historia_social.prestaciones_activas",
    "editable": false
  }
]
```

Los tipos de sección son `automatico` (datos pre-cargados desde la Historia Social, no editables por el profesional) y `texto_libre` (campo redactable).

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `unidadOrganizativa()` | `BelongsTo<UnidadOrganizativa>` | UO desde la que la plantilla es visible |
| `informes()` | `HasMany<Informe>` | Informes generados con esta plantilla |
| `creadaPor()` | `BelongsTo<User>` | Supervisor autor |

**Scopes:**
- `scopeVisiblesParaUo($uoId)` — devuelve plantillas activas cuya UO es la indicada o cualquiera de sus ancestros; es el scope que usa el selector del `NuevoInformeWizard`

**Filament:** `PlantillaInformeResource` (grupo *Diseño de informes*) — accesible solo para usuarios con rol supervisor o administrador. Cada supervisor solo puede crear y editar plantillas en su propia UO y las descendientes bajo su responsabilidad.

---

### 2.3 Informe

**Tabla:** `informes`
**Descripción:** Instancia concreta de un informe profesional. Nace como borrador, pasa por edición y culmina con la firma del profesional autor. Una vez firmado, el informe queda vinculado a un `Documento` que contiene el PDF final.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `plantilla_id` | bigint FK | Ref. `plantillas_informe` |
| `historia_social_id` | bigint FK nullable | Historia Social a la que pertenece este informe |
| `ciudadano_id` | bigint FK | Ciudadano al que se refiere el informe |
| `autor_id` | bigint FK | Ref. `users` — profesional autor y firmante |
| `estado` | enum | `borrador` / `firmado` / `anulado` |
| `contenido` | jsonb | Contenido actual del informe por secciones (mapa `seccion_id → texto`) |
| `documento_id` | bigint FK nullable | Ref. `documentos` — PDF firmado; null hasta firma |
| `firmado_en` | timestamp nullable | Momento de la firma |
| `metodo_firma` | enum nullable | `autofirma_certificado_empleado_publico` |
| `numero_colegiado_firmante` | string nullable | Nº de colegiación del autor en el momento de la firma |
| `motivo_anulacion` | text nullable | Obligatorio si estado = `anulado` |
| `anulado_en` | timestamp nullable | |
| `created_at` / `updated_at` | timestamp | |

**Transiciones de estado:**

```
borrador → firmado     (acción: firmar con AutoFirma; requiere PDF generado y certificado válido)
firmado  → anulado     (acción: anular; solo el autor; requiere motivo; el documento PDF permanece)
```

No existen otras transiciones. Un informe anulado no puede reabrirse — si es necesario, se crea un nuevo borrador.

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `plantilla()` | `BelongsTo<PlantillaInforme>` | |
| `historiaSocial()` | `BelongsTo<HistoriaSocial>` | |
| `ciudadano()` | `BelongsTo<Ciudadano>` | |
| `autor()` | `BelongsTo<User>` | |
| `documento()` | `BelongsTo<Documento>` | PDF firmado |

**Scopes:**
- `scopeBorradores()` — estado `borrador`
- `scopeFirmados()` — estado `firmado`
- `scopeDeAutor($userId)` — informes de un profesional concreto

---

### 2.4 PisoFirmado

**Tabla:** `piso_firmados`
**Descripción:** Custodia del Plan de Intervención (PISO) con doble firma. En v1.0 la doble firma se resuelve fuera del sistema digital: el PDF se imprime, ambas partes firman manuscritamente, y la copia escaneada se sube aquí. El registro vincula el documento custodiado con el `PlanDeIntervencion` correspondiente y deja constancia de quién realizó la subida y cuándo.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `plan_de_intervencion_id` | bigint FK | Ref. `planes_de_intervencion` |
| `documento_id` | bigint FK | Ref. `documentos` — PDF escaneado con firmas manuscritas |
| `subido_por` | bigint FK | Ref. `users` — profesional que subió el escáner |
| `metodo_conformidad_ciudadano` | enum | `manuscrita_escaneada` *(único valor en v1.0)* |
| `observaciones` | text nullable | Observaciones opcionales del profesional |
| `created_at` / `updated_at` | timestamp | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `planDeIntervencion()` | `BelongsTo<PlanDeIntervencion>` | |
| `documento()` | `BelongsTo<Documento>` | |
| `subidoPor()` | `BelongsTo<User>` | |

**Nota:** un `PlanDeIntervencion` puede tener como máximo un `PisoFirmado` activo. Si el plan se revisa y requiere nueva firma, el registro anterior no se elimina — queda como histórico y el nuevo sustituye al activo.

---

## 3. Servicios

### ServicioAlmacenamiento

Abstracción sobre Laravel Filesystem. Centraliza la subida, descarga y eliminación lógica de ficheros. Nunca expone rutas directas; genera URLs temporales firmadas para servir ficheros al navegador.

Responsabilidades: validar tipo MIME, calcular hash SHA-256, determinar el disco activo desde configuración, construir la ruta interna siguiendo la convención `documentos/{año}/{mes}/{uuid}.pdf`.

### ServicioGeneracionPDF

Genera el PDF de un informe a partir de su contenido y la plantilla asociada. Combina los datos automáticos (extraídos de la Historia Social mediante el resolver de fuentes) con el texto libre del profesional, aplica la cabecera y pie corporativos definidos en la plantilla, y produce el PDF mediante `barryvdh/laravel-dompdf`.

El PDF generado se almacena como borrador hasta que el profesional lo firma. El PDF de borrador es sobrescribible (el profesional puede generar una vista previa iterativa); el PDF final firmado es inmutable.

### ServicioFirmaInforme

Coordina el proceso de firma mediante AutoFirma. Recibe el PDF en borrador, invoca la API JavaScript de AutoFirma en el cliente (integración Livewire), recibe el PDF firmado con firma CAdES embebida, verifica que la firma es válida, extrae el número de colegiado del certificado si está presente, y persiste el documento firmado llamando a `ServicioAlmacenamiento`.

### ResolverFuentesInforme

Resuelve las fuentes de datos automáticas declaradas en las secciones de tipo `automatico` de una `PlantillaInforme`. Dado un `ciudadano_id` y una referencia de fuente (p.ej. `historia_social.prestaciones_activas`), devuelve los datos estructurados listos para renderizar en el PDF. Centraliza la lógica de extracción de datos para que las plantillas sean declarativas.

---

## 4. Interfaces de usuario

### Filament (backoffice / configuración)

Dos grupos de navegación diferenciados en el backoffice:

**Grupo *Diseño de informes*** (accesible a supervisores y administradores):
- **`EstiloInformeResource`** — gestión del estilo formal por UO: logotipo, nombre de unidad, dirección, pie de página. El supervisor ve y edita solo los estilos de su UO y sus descendientes. Incluye vista previa del aspecto resultante.
- **`PlantillaInformeResource`** — CRUD de plantillas de informe con selección de UO de alcance. Editor de secciones con vista previa del esquema JSON. Activación/desactivación de plantillas. El supervisor ve y edita solo las plantillas de su UO y sus descendientes.

**Grupo *Configuración del sistema*** (accesible solo a administradores):
- **`ConfiguracionTipografiaResource`** — selección de la familia tipográfica y tamaños base para todos los informes generados. Transversal a toda la organización.

### Livewire (operativo)

- **`DocumentosCiudadanoComponent`** — panel de documentos asociados a un ciudadano. Lista de documentos con tipo, fecha y profesional que los subió. Acciones: subir nuevo documento externo (con validación de tipo y tamaño), previsualizar (URL firmada temporal), descargar.
- **`NuevoInformeWizard`** — asistente de creación de informe. Pasos: (1) selección de plantilla, (2) pre-carga automática de datos y edición de secciones de texto libre, (3) vista previa del PDF generado, (4) firma con AutoFirma. El informe no avanza al paso 4 si hay secciones obligatorias vacías.
- **`InformesHistorialComponent`** — listado de informes de una Historia Social, con estado, autor y fecha. Acciones sobre informes firmados: ver PDF, anular (solo el autor, con campo de motivo obligatorio).
- **`PisoFirmadoUploadComponent`** — subida del PISO con doble firma manuscrita. Se activa desde el `PlanDeIntervencion` cuando está en estado que requiere conformidad. Sube el PDF escaneado y registra el `PisoFirmado`.

---

## 5. Decisiones de diseño y pendientes

**Estilo de informe con herencia jerárquica por campos independientes.** En lugar de una cabecera monolítica por plantilla, el aspecto formal se gestiona mediante `EstiloInforme` vinculado a la UO del autor. La herencia opera campo a campo: para cada campo (logo, nombre de unidad, dirección, pie), el sistema busca el valor en la UO del autor y sube por la jerarquía hasta encontrarlo. Esto permite que una Dirección General defina el logo institucional y un centro defina solo su nombre, sin conflicto. La resolución se realiza en el momento de la generación del PDF y se cachea por UO.

**Alcance jerárquico de plantillas.** Las plantillas se crean al nivel de UO adecuado y son visibles para todos los profesionales de esa UO y sus descendientes. Un supervisor de distrito puede crear plantillas para todos los centros del distrito; un supervisor de centro puede crear plantillas solo para su centro.

**Tipografía transversal gestionada por el administrador del sistema.** La familia tipográfica y los tamaños base son únicos para toda la organización. No es sobreescribible por UO.

**Firma electrónica del profesional — AutoFirma con Certificado de Empleado Público.** El informe profesional es un acto personalísimo del autor colegiado (Principio deontológico del Trabajo Social: el informe «elabora y firma con carácter exclusivo» el profesional). Un sello de órgano no es sustituto válido. No se implementa fallback de firma manuscrita para informes profesionales, ya que abriría la puerta a prácticas que diluyen la responsabilidad de autoría.

**PISO en v1.0 — impresión y firma manuscrita.** La doble firma (profesional + ciudadano) que requiere el PISO no tiene solución técnica satisfactoria sin dependencias externas significativas. En v1.0 se implementa el flujo de impresión + escáner + custodia del documento.

**Solo PDF como formato de custodia.** Todos los documentos se almacenan en PDF, independientemente del formato original. Documentos subidos en otros formatos se rechazan con mensaje claro.

**Hash SHA-256 obligatorio.** Calculado en subida y verificable a demanda. No es firma, pero sí evidencia de integridad suficiente para el contexto.

### Decisiones pendientes

**Firma electrónica del ciudadano en el PISO (evolución futura).** Se han evaluado tres alternativas en la fase de diseño:

- *Cl@ve Firma:* solución más robusta jurídicamente. Requiere que el ciudadano tenga registro de Nivel Avanzado en Cl@ve. Cl@ve PIN/Permanente/Móvil **no constituye firma** — es solo autenticación. Cl@ve Firma tiene despliegue limitado actualmente y requiere integración con la plataforma del Estado. Candidata principal para una iteración futura una vez que el despliegue de Cl@ve Firma sea más amplio.
- *Firma biométrica en tablet/Wacom:* captura la firma como imagen; suficiente para el contexto de servicios sociales pero introduce dependencia de hardware. Válida como complemento para ciudadanos sin Cl@ve.
- *OTP como evidencia de consentimiento:* el ciudadano recibe un código SMS al teléfono registrado en VIDA 360 y lo introduce para confirmar el PISO. No es firma electrónica cualificada pero es evidencia documentada de consentimiento informado. La solución de menor fricción para ciudadanos sin Cl@ve.

La implementación de cualquiera de estas opciones requiere decisión explícita antes de su desarrollo.

**Conversión de formatos en subida.** Se ha optado por solo admitir PDF en v1.0. Si en el futuro se decide aceptar otros formatos (JPG, PNG para DNIs, DOCX para informes externos), debe definirse la estrategia de conversión automática.

**Publicación en carpeta ciudadana.** Los informes firmados en estado `publicado` deberían ser accesibles desde la carpeta ciudadana del Ayuntamiento. La integración con `CarpetaCiudadanaInterface` (Módulo Integraciones) está pendiente de diseño.

**Número máximo de documentos por ciudadano / cuotas de almacenamiento.** No definido. Se recomienda establecer límites operativos antes de la puesta en producción.

---

## 6. Tests funcionales

Los siguientes tests deben pasar para considerar el módulo correctamente implementado.
Fichero: `Modules/Documentos/tests/Feature/DocumentosTest.php`.

### Estado de ejecución — 2026-05-18

| Área | Tests | Estado |
|---|---|---|
| Custodia de documentos (TF-DOC-01 a TF-DOC-05) | 5 | ✅ |
| Estilos e herencia jerárquica (TF-DOC-06 a TF-DOC-08) | 3 | ✅ |
| Plantillas de informe (TF-DOC-09, TF-DOC-10) | 2 | ✅ |
| Ciclo de vida del informe (TF-DOC-11 a TF-DOC-16) | 6 | ✅ |
| PISO firmado (TF-DOC-17, TF-DOC-18) | 2 | ✅ |
| Configuración y visibilidad (TF-DOC-19, TF-DOC-20) | 2 | ✅ |
| **Total** | **20** | **20 ✅** |

### ✅ TF-DOC-01: Subida de documento externo válido
Un profesional con acceso al expediente sube un PDF como documento externo a un ciudadano. El sistema lo almacena, calcula su hash SHA-256, lo asocia al ciudadano con el tipo indicado y lo lista en el panel de documentos. El fichero no es accesible por URL directa.

### ✅ TF-DOC-02: Rechazo de formato no PDF
Un profesional intenta subir un fichero `.docx` como documento externo. El sistema rechaza la subida con un mensaje descriptivo. No se crea ningún registro en base de datos ni se almacena ningún fichero.

### ✅ TF-DOC-03: Acceso con URL temporal
Un profesional solicita ver un documento. El sistema genera una URL firmada con tiempo de expiración. La URL funciona mientras no ha expirado y devuelve 403 o 404 una vez expirada. Una URL de otro documento no es válida para acceder a este.

### ✅ TF-DOC-04: Verificación de integridad
Dado un documento custodiado, el sistema calcula su hash en el momento de la subida. Si el fichero almacenado es alterado externamente, la verificación posterior del hash detecta la discrepancia.

### ✅ TF-DOC-05: Acceso denegado a profesional sin permiso
Un profesional sin acceso al expediente de un ciudadano intenta descargar un documento de ese ciudadano (incluso conociendo el ID del documento). El sistema devuelve 403.

### ✅ TF-DOC-06: Creación de estilo de informe por un supervisor
Un supervisor crea un `EstiloInforme` para su UO definiendo logotipo y nombre de unidad. El estilo queda asociado a esa UO. Un supervisor de otra UO no puede editar este estilo.

### ✅ TF-DOC-07: Herencia de estilo por proximidad
Una DG define logo y pie de página. Un centro dependiente de esa DG define solo su nombre de unidad. Al generar un informe desde el centro, el PDF resultante contiene: logo de la DG, nombre del centro, y pie de la DG. Los campos no definidos en el centro se resuelven en el nivel superior.

### ✅ TF-DOC-08: Campo sobreescrito en UO hija no afecta a UO hermana
Una DG define logo. El Centro A define su propio logo. El Centro B (mismo nivel que A) no define logo. Los informes del Centro A usan el logo del Centro A; los del Centro B usan el logo de la DG. Ningún cambio en el estilo del Centro A afecta al Centro B.

### ✅ TF-DOC-09: Plantilla visible para UO hija pero no para UO sin relación
Un supervisor de distrito crea una plantilla asignada a su distrito. Un profesional de un centro de ese distrito ve la plantilla en el selector. Un profesional de un centro de otro distrito no la ve.

### ✅ TF-DOC-10: Creación de plantilla de informe
Un supervisor crea una `PlantillaInforme` en su UO con dos secciones automáticas y dos de texto libre, una de ellas obligatoria. La plantilla queda activa y aparece en el selector de los profesionales de esa UO y sus descendientes.

### ✅ TF-DOC-11: Generación de informe en borrador
Un profesional abre el asistente `NuevoInformeWizard`, selecciona una plantilla, y el sistema pre-carga las secciones automáticas con datos reales del ciudadano. El profesional completa las secciones de texto libre. El sistema genera el PDF de vista previa con el estilo resuelto para la UO del autor. El informe queda en estado `borrador`.

### ✅ TF-DOC-12: Sección obligatoria vacía impide avance a firma
En el asistente de creación, si una sección marcada como `obligatorio: true` está vacía, el botón de avance al paso de firma está deshabilitado y el sistema muestra un mensaje indicando qué secciones faltan.

### ✅ TF-DOC-13: Firma de informe con AutoFirma
Un profesional firma un informe en estado `borrador` mediante AutoFirma con su Certificado de Empleado Público. El sistema recibe el PDF firmado, verifica que la firma es válida, persiste el documento, actualiza el informe a estado `firmado`, registra la fecha y el método de firma. El informe en estado `firmado` no muestra opción de edición.

### ✅ TF-DOC-14: Inmutabilidad del informe firmado
Un profesional intenta editar el contenido de un informe en estado `firmado` mediante llamada directa al endpoint. El sistema devuelve error y el informe permanece inalterado.

### ✅ TF-DOC-15: Anulación de informe por el autor
El autor de un informe firmado lo anula proporcionando un motivo. El estado pasa a `anulado`, se registra la fecha y el motivo. El PDF original permanece en el sistema y sigue siendo descargable. Otro profesional no puede anular el informe del autor.

### ✅ TF-DOC-16: Anulación denegada a no-autor
Un profesional distinto del autor intenta anular un informe firmado. El sistema devuelve 403. El informe permanece en estado `firmado`.

### ✅ TF-DOC-17: Subida de PISO firmado manualmente
El profesional responsable del PISO sube el PDF escaneado con las firmas manuscritas de ambas partes. El sistema crea el `PisoFirmado`, lo asocia al `PlanDeIntervencion` correcto y lista el documento en el panel del plan. El estado del `PlanDeIntervencion` refleja que tiene conformidad registrada.

### ✅ TF-DOC-18: Un PISO solo admite un registro de firma activo
Si ya existe un `PisoFirmado` para un `PlanDeIntervencion`, el sistema no permite crear un segundo sin que el primero haya sido reemplazado explícitamente. El registro anterior queda como histórico.

### ✅ TF-DOC-19: Disco de almacenamiento configurable sin cambios de código
El disco de almacenamiento puede cambiarse en la configuración de entorno (de `local` a `s3`) sin modificar código de la aplicación. Las subidas posteriores al cambio van al nuevo disco. Las referencias de documentos existentes siguen siendo válidas si el disco original permanece accesible.

### ✅ TF-DOC-20: El profesional solo ve sus propios borradores
En el listado de informes, los borradores de otros profesionales no son visibles ni accesibles. Solo los informes firmados son visibles para cualquier profesional con acceso al expediente.

---

## 7. Dependencias con otros módulos

| Módulo | Dependencia |
|---|---|
| Organización | `UnidadOrganizativa` — jerarquía para resolución de estilos y alcance de plantillas |
| Ciudadanía | `Ciudadano`, `UnidadConvivencia` — entidades documentable |
| Intervención | `HistoriaSocial`, `PlanDeIntervencion` — entidades documentable y fuentes de datos para informes |
| Usuarios y Permisos | `User` — autor, firmante, control de acceso; rol supervisor para gestión de estilos y plantillas |
| Integraciones | `CarpetaCiudadanaInterface` — publicación futura de informes firmados (pendiente) |
