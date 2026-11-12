# Instrucciones CLI — Fase 3 bis: solicitudes de prestación y expedientes

**Plan:** `docs/plan-cierre-funcional-2026-11.md`, fase 3 bis.
**Diseño funcional:** `docs/modulo-solicitudes-expedientes.md` (es el **qué** y el **por qué**; este documento es el **cómo**).
**Requisito previo:** fase 3 terminada (usa `prestaciones.via_acceso`, el centro del plan, `AmbitoUsuario` y la regla de personas protegidas).
**Tests:** TF-SOL-01 a TF-SOL-44 (al final).

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se construye.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, el diseño funcional completo, `docs/modulo-prestaciones.md`, `docs/modulo-centros.md` §3, `docs/modulo-integraciones.md` §4.3, `docs/modulo-documentos.md` §2.1, `docs/roles-consulta-basica-tramitacion.md` §4, `docs/modulo-mensajes.md`, `docs/design-system/SKILL.md`, `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- El módulo `Prestaciones` solo tiene modelos (`Prestacion`, `PrestacionTipoCentro`). `tipo_prestacion` es un enum `servicio`/`economica` que hoy indica si hay tramitación económica externa.
- La fase 3 creó `prestaciones.via_acceso` con `plaza` y `derivacion`, y `tipo_plan_derivacion_id`.
- `Modules/Centro` tiene `Servicio` y `SolicitudServicio` (tabla `solicitudes_servicio`), usados solo en `SolicitudServicioTest`. Sin interfaz.
- **No existe el módulo `Integraciones` en el código.** El patrón de adaptadores del proyecto es una interfaz en el módulo que la usa, con un simulador ligado en su provider (`Modules\Agenda\Services\Citas\CitaPrevia\AdaptadorCitaPrevia` + `MockCitaPrevia` en `AgendaServiceProvider`; `app/Services/Geocodificacion`). **No hay rutas de API** en el proyecto (solo `web.php` y `console.php`).
- Las fichas de valoración usan `TipoFicha.schema` y guardan la copia en `Ficha.schema_snapshot`. El render del formulario está en `RegistrarValoracionPage` (+ su vista).
- `DocumentoVinculo` es polimórfico (`vinculable_type`/`vinculable_id`): sirve para vincular documentos a una solicitud sin tocar la custodia.
- `AlertaService::crear()` es el punto único de alertas.
- `CiudadanoPage::statPrestaciones()` devuelve `null`.

---

## Fuera de alcance

- **Endpoint HTTP de avisos del gestor** (no hay capa de API ni gestor). Se construye el servicio que procesa un aviso (`procesarAviso()`), usado por el simulador; el endpoint, con su autenticación, cuando exista un gestor real.
- Adaptador real de un gestor de expedientes.
- Comunicaciones al ciudadano (RN-06 del diseño).
- Gestión completa de listas de espera de plazas, aviso por demora de inicio, integración con proveedores, intercambio con la HSU (pendientes del diseño, §8).
- Resto del rol `tramitacion` (traslados y fusiones).

---

## Paso 0 — Verificación

1. `git pull`; suites de Prestaciones, Centro, Intervención, Documentos, Mensajes, Ciudadanía y `tests/Feature/Acceso`. Anota fallos previos.
2. Confirma `prestaciones.via_acceso` y `tipo_plan_derivacion_id` (fase 3). Si no existen, detente.
3. Lista las prestaciones del catálogo con su `tipo_prestacion` y propón en el CHANGELOG la `via_acceso` inicial de cada una (`economica` → `expediente_externo`; `servicio` → `directa`, salvo las que tengan plazas (`plaza`) o derivación). **Pide confirmación del mapeo antes de aplicarlo** a los datos reales; en seeders y demo, aplícalo.
4. Cuenta los registros de `solicitudes_servicio` (se espera 0 fuera de tests).
5. Localiza el componente o la lógica que pinta un formulario desde `schema` (`RegistrarValoracionPage`) y si se puede reutilizar como componente. Informa antes del paso 3.
6. Comprueba cómo se identifica al rol `tramitacion` (Spatie) y si tiene superficie operativa (menú, shell). Hoy no la tiene.

---

## Paso 1 — Catálogo

1. `via_acceso`: añade `directa`, `tramitacion_interna` y `expediente_externo`. Obligatoria en prestaciones activas (validación en `PrestacionResource`; los datos existentes según el paso 0.3).
2. Nuevos campos en `prestaciones` (versionados con la prestación):
   - `unidad_tramitadora_tipo` (`uo` / `centro_solicitud`) y `unidad_tramitadora_id` (FK UO, si `uo`). Obligatorios en las dos vías de tramitación.
   - `formulario_solicitud_id` (FK nullable a `tipos_ficha`).
   - `codigo_gestor` (varchar nullable), solo `expediente_externo`.
3. `tipos_ficha.uso` (`valoracion` / `solicitud`, por defecto `valoracion`). El selector de fichas de valoración filtra por `valoracion`; el de la prestación, por `solicitud`. `TipoFichaResource` muestra y filtra el uso.
4. Tabla `prestacion_documentos_exigidos`: `prestacion_id`, `tipo_documental_id`, `obligatorio` (bool), `orden`. Unique `(prestacion_id, tipo_documental_id)`.
5. `PrestacionResource`: sección «Acceso y tramitación» con todo lo anterior. Validaciones cruzadas: unidad tramitadora solo en vías de tramitación; código del gestor solo en expediente externo; tipo de plan solo en derivación.
6. `tipo_prestacion` se mantiene (es clasificatorio y lo usan informes y filtros), pero deja de decidir el comportamiento: nada nuevo lo lee para decidir el flujo.

---

## Paso 2 — Modelo

En el módulo `Prestaciones` (crea `app/Services`, `app/Http/Livewire`, `routes/web.php`, `resources/views` siguiendo la estructura de los demás módulos).

### `solicitudes_prestacion`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `ciudadano_id` | FK | Titular |
| `historia_id` | FK | |
| `prestacion_id` | FK | |
| `prestacion_version_id` | FK nullable | Versión de la prestación al crear (si `Versionable` la expone; si no, snapshot JSON en `configuracion_snapshot`) |
| `configuracion_snapshot` | jsonb | Vía, unidad tramitadora, documentos exigidos y código del gestor en el momento de crear |
| `via` | enum | `tramitacion_interna` / `expediente_externo` |
| `plan_id` | FK nullable | |
| `plan_objetivo_id` | FK nullable | |
| `centro_solicitud_id` | FK | Centro desde el que se crea (contexto o centro activo) |
| `unidad_tramitadora_id` | FK UO | Resuelta al pasar a bandeja |
| `creada_por_id` | FK users | |
| `sugerida_id` | FK users nullable | Persona de tramitación sugerida |
| `responsable_id` | FK users nullable | Quien la tiene tomada |
| `estado` | enum `EstadoSolicitudPrestacion` | `borrador` / `en_bandeja` / `en_preparacion` / `enviada` / `resuelta` / `cerrada_sin_resolucion` / `anulada` |
| `ficha_id` | FK nullable | Datos del formulario como `Ficha` (con `schema_snapshot`) |
| `numero_expediente` | varchar nullable | |
| `fecha_envio` | date nullable | |
| `estado_externo_codigo` / `estado_externo_texto` | varchar nullable | |
| `estado_externo_normalizado` | varchar nullable | Clave del catálogo `expediente.estado` |
| `ultima_actualizacion_en` | timestamp nullable | |
| `motivo_cierre` | text nullable | Obligatorio en `cerrada_sin_resolucion` y `anulada` |
| timestamps | | |

Personas afectadas adicionales: pivote `solicitud_prestacion_personas` (`solicitud_id`, `ciudadano_id`), solo miembros activos de la unidad de convivencia.

### `solicitud_prestacion_eventos` (inmutable, trigger que rechaza UPDATE y DELETE)

`solicitud_id`, `accion` (enum: `creada`, `a_bandeja`, `tomada`, `soltada`, `devuelta`, `documento_vinculado`, `documento_desvinculado`, `enviada`, `estado_actualizado`, `incidencia`, `incidencia_atendida`, `resolucion`, `cerrada_sin_resolucion`, `anulada`), `estado_antes`, `estado_despues`, `origen` (`usuario` / `aviso_gestor` / `consulta_automatica` / `consulta_manual` / `registro_manual` / `sistema`), `usuario_id` nullable, `detalle` jsonb (tipo de incidencia, plazo, estado externo, texto…), `nota` text nullable, `created_at`.

Incidencia abierta = evento `incidencia` sin un `incidencia_atendida` que lo referencie (`detalle.incidencia_evento_id`).

### Resolución

- `resoluciones_solicitud`: `solicitud_id` (unique), `fecha`, `sentido` (`favorable` / `parcial` / `desfavorable`), `origen` (como en eventos), `registrada_por_id` nullable, timestamps.
- `resolucion_lineas`: `resolucion_id`, `prestacion_id`, `concedida` (bool), `motivo_denegacion` nullable, `importe` decimal nullable, `intensidad` varchar nullable, `fecha_desde`/`fecha_hasta` nullable, `observaciones` nullable.

### `prestaciones_persona`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `ciudadano_id` | FK | |
| `prestacion_id` | FK | |
| `origen_tipo` / `origen_id` | morph | `ResolucionLinea`, `Prescripcion` o `null` (directa) |
| `plan_id` | FK nullable | |
| `estado` | enum | `concedida` / `activa` / `suspendida` / `finalizada` |
| `fecha_concesion` | date nullable | Null en directas |
| `fecha_inicio` | date nullable | Inicio efectivo |
| `fecha_fin` | date nullable | |
| `motivo_suspension` / `motivo_fin` | text nullable | |
| `condiciones` | jsonb nullable | Importe, intensidad, periodo |
| `registrada_por_id` | FK users | |
| timestamps | | |

Historial de estados: `prestacion_persona_eventos` (inmutable), con quién, cuándo, estado antes y después y motivo.

### Catálogos del sistema
- `expediente.estado` (normalizados): `en_tramite`, `incidencia`, `resuelta`, `desistida`, `archivada`.
- `expediente.incidencia`: `requerimiento_documentacion`, `subsanacion`, `audiencia`, `otra`.
- `prestacion_persona.motivo_fin`, `prestacion_persona.motivo_suspension`.

---

## Paso 3 — Servicios

`Modules\Prestaciones\Services\SolicitudPrestacionService`:
- `crear(Ciudadano, Prestacion, User $ts, ?PlanDeIntervencion, ?PlanObjetivo, ?Centro $contexto): SolicitudPrestacion` — solo vías de tramitación; guarda el snapshot; crea la `Ficha` vacía si hay formulario; estado `borrador`.
- `guardarDatos()`, `vincularDocumento()`, `desvincularDocumento()` (usa `DocumentoVinculo`; el documento debe estar vinculado a la persona o se sube y vincula a la persona y a la solicitud), `sugerir(User)`.
- `pasarABandeja()` — resuelve la unidad tramitadora (UO de la prestación o UO del centro de solicitud).
- `tomar(User)`, `soltar(User, ?string $nota)`, `devolver(User, string $nota)` (a `borrador`, alerta al TS).
- `enviar(User, ?string $justificacion)` — exige justificación si faltan documentos obligatorios. Expediente externo: llama al adaptador; con el adaptador manual, exige número de expediente y fecha de presentación. Tramitación interna: pasa a `enviada` sin adaptador.
- `registrarActualizacion(SolicitudPrestacion, ActualizacionExpediente $dto, string $origen, ?User)` — punto único para las cuatro vías de RN-07: estado externo, incidencias y resolución. Idempotente: la misma actualización (mismo estado y fecha, o mismo identificador externo de incidencia) no se registra dos veces.
- `consultarAhora(User)` — llama a `consultarEstado()` del adaptador y pasa por `registrarActualizacion()` con origen `consulta_manual`. Con el adaptador manual, no disponible (la interfaz no muestra el botón).
- `atenderIncidencia(evento, User, array $documentos)` — con adaptador que lo permita, `aportarDocumentacion()`.
- `anular(User, string $motivo)`, `cerrarSinResolucion(...)`.

`ResolucionService::incorporar(SolicitudPrestacion, ResolucionDto, string $origen, ?User)`: crea resolución y líneas, una `PrestacionPersona` `concedida` por línea concedida (con plan si la solicitud lo tenía), un apunte automático (`fuente: gestor_expedientes` o `registro_manual`) y una alerta al TS. Estado `resuelta`.

`PrestacionPersonaService`: `registrarDirecta()`, `registrarInicio(fecha)`, `suspender()`, `reanudar()`, `finalizar()`. Al asignar una plaza (prescripción `activa`), crea la `PrestacionPersona` activa con origen `Prescripcion` (engancha en `PrescripcionService`).

### Adaptador del gestor

En `Modules\Prestaciones\Services\Gestor\`:
- `AdaptadorGestorExpedientes` (interfaz): `iniciarSolicitud(SolicitudParaGestor): string`, `consultarEstado(string): ActualizacionExpediente`, `aportarDocumentacion(string, array): void`, `admite(string $operacion): bool`, `nombre(): string`.
- `GestorManual` (por defecto): `admite()` falso para todo; la interfaz ofrece formularios de registro manual.
- `GestorSimulado` (desarrollo y tests): números de expediente deterministas; estados y resoluciones programables desde el test; `simularAviso()` que llama a `SolicitudPrestacionService::procesarAviso()`.
- Selección por `config('prestaciones.gestor_expedientes')` (`manual` por defecto). Mapeo de estados externos a normalizados en `config/prestaciones.php` por adaptador.
- Comando `expedientes:consultar` (scheduler, frecuencia configurable): solo si el adaptador admite `consultarEstado`; recorre las solicitudes `enviadas`; origen `consulta_automatica`; un fallo en una no para las demás y queda en el log con la causa.

---

## Paso 4 — Permisos

Policies `SolicitudPrestacionPolicy` y `PrestacionPersonaPolicy` según §5 del diseño. Además:
- Tramitación ve una solicitud solo si su unidad tramitadora está en las adscripciones del usuario (UO de adscripción o descendientes orgánicas), y solo ve de la persona identificación, contacto, parámetros, documentos vinculados a la solicitud y el historial. **Nunca** Historia Social, apuntes ni motivo profesional. Todo acceso a una solicitud de persona protegida se audita como `acceso_restringido`.
- Personas protegidas: el TS y la supervisión, según RN-17 ampliada en la fase 3. Tramitación accede porque la solicitud está en su bandeja (necesario para tramitar, D3.8), sin ver la Historia Social.
- Permisos atómicos nuevos (seeder): `solicitud.crear`, `solicitud.ver`, `solicitud.tramitar`, `solicitud.registrar_manual`, `prestacion_persona.gestionar`. Asignación por rol según §5 del diseño.

---

## Paso 5 — Interfaz

Design system y `2026-09-bootstrap-unico.md` antes de cualquier Blade.

1. **«Solicitar prestación»** en la ficha de la persona (caja de herramientas) y en cada objetivo del plan: selector de prestaciones disponibles en el centro (`ConfiguracionEfectivaCentro::prestaciones()`), y según `via_acceso`: directa → modal de registro; plaza → `PrescribirRecursoModal`; derivación → modal de derivación (fase 3); tramitación → crea la solicitud y abre su pantalla.
2. **Pantalla de la solicitud** (Livewire, ruta en `Prestaciones`): parámetros (render del formulario reutilizando el de fichas), documentos exigidos con su estado (aportado / pendiente) y vinculación desde la carpeta o subida, personas afectadas, sugerencia de tramitador, historial, acciones según estado y rol. Para el TS, «Consultar ahora» y estado normalizado con la fecha de la última actualización.
3. **Bandeja de tramitación** (nueva superficie para el rol `tramitacion`: entrada de menú y página de inicio): pestañas «Pendientes de tomar», «En preparación» (mías / todas), «Enviadas con incidencia abierta» y «Enviadas». Filtros por prestación y antigüedad.
4. **Registro manual** (adaptador manual o `solicitud.registrar_manual`): número de expediente y fecha al enviar; estado; incidencia (tipo, fecha, descripción, plazo); resolución con sus líneas.
5. **Ficha de la persona:** bloque «Prestaciones» (sustituye el `null` de `statPrestaciones()`): activas, concedidas pendientes de inicio (con días desde la concesión), suspendidas; solicitudes en curso con estado. Acciones de registrar inicio, suspender o finalizar según permisos. `consulta_basica` no ve este bloque.
6. **Plan:** cada objetivo muestra sus solicitudes y prestaciones de la persona vinculadas.

---

## Paso 6 — `SolicitudServicio`

1. Si el paso 0.4 da 0 registros: elimina `SolicitudServicio`, su tabla, la relación en `Servicio` y su test (sustituido por TF-SOL). Si hay registros, **detente y pregunta** cómo migrarlos.
2. `Servicio` se mantiene como posible unidad tramitadora (vía su UO).

---

## Paso 7 — Seguimiento del plan

`SeguimientoPlan.nuevas_prestaciones` (array de ids del catálogo) se mantiene para los datos existentes; en la interfaz del seguimiento, «Nuevas prestaciones» pasa a ser la acción «Solicitar prestación», y el seguimiento enlaza las solicitudes y prestaciones de la persona creadas desde él (pivote `seguimiento_solicitudes` o campo de contexto en la solicitud; elige y explícalo).

---

## Cierre

1. Suites de los módulos tocados y la suite completa. Sin fallos nuevos. `npm run build` y `ui:auditar` en verde.
2. Documentación: `modulo-solicitudes-expedientes.md` (estado «implementado», decisiones de implementación), `modulo-prestaciones.md`, `modulo-centros.md` §3.3 y §5.4 (retirada de `SolicitudServicio`), `modulo-integraciones.md` §4.3 (interfaz real, adaptadores manual y simulado, endpoint pendiente), `modulo-intervencion.md` (§6.3), `modulo-documentos.md` §2.1 (vínculo con solicitud), `roles-consulta-basica-tramitacion.md` §4 (lo implantado de `tramitacion`), `decisiones-pendientes.md` (P-01 y P-03 resueltas).
3. CHANGELOG (mapeo de vías aplicado, decisiones no previstas, negativos), BACKLOG (nuevas entradas: endpoint de avisos, pendientes del diseño §8), SESSION, `CLAUDE.md` §6. Push y despliegue en verde.

## Criterios de finalización

1. TF-SOL-01 a 44 en verde, con negativos comprobados.
2. Con el adaptador manual se puede recorrer una solicitud de expediente externo de principio a fin desde la interfaz.
3. `statPrestaciones()` y el bloque «Prestaciones» muestran datos reales.
4. Ninguna lectura de `tipo_prestacion` decide el flujo.
5. Documentación, CHANGELOG, BACKLOG, SESSION y CLAUDE.md al día; despliegue en verde.

---

# Tests — TF-SOL-01 a TF-SOL-44

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), Dado / Cuando / Entonces. Ubicación: `Modules/Prestaciones/tests/Feature/Solicitudes/` (`CatalogoAccesoTest`, `SolicitudCicloTest`, `BandejaTramitacionTest`, `EnvioTest`, `ActualizacionTest`, `ResolucionTest`, `PrestacionPersonaTest`, `PermisosSolicitudTest`, `InterfazSolicitudTest`). **[negativo]** = debe fallar sin la protección; compruébalo.

**Datos (trait `SolicitudesTestSetup`):** `CircuitoTestSetup` de la fase 3 (`$cssNorte`, `$tsNorte`, `$supNorte`, `$ana`, `$pisoAna`, `$marta` protegida). Además:
- `$uoTramitacionSad`: UO de tramitación central; `$tramitadora1`, `$tramitadora2` (`tramitacion`, adscritas a ella); `$tramitadoraOtra` (`tramitacion`, otra UO).
- `$tramitadoraCentro` (`tramitacion`, adscrita a `$uoCssNorte`).
- `$pSad`: prestación `expediente_externo`, unidad tramitadora `$uoTramitacionSad`, formulario con dos campos (uno obligatorio), documentos exigidos: «Documento de identidad» (obligatorio) e «Informe médico» (opcional).
- `$pInterna`: `tramitacion_interna`, unidad «centro de solicitud».
- `$pDirecta`: `directa`. `$pPlaza`: `plaza`.
- Gestor: `GestorSimulado` salvo donde se diga `GestorManual`.

## Grupo A — Catálogo

**TF-SOL-01 — Vía obligatoria y coherente** **[negativo]**: guardar una prestación activa sin vía, o `expediente_externo` sin unidad tramitadora, o `directa` con código de gestor → rechazado.

**TF-SOL-02 — Formularios de solicitud separados**: un `TipoFicha` con `uso = solicitud` no aparece en el selector de fichas de valoración, y uno de `valoracion` no aparece en el selector de la prestación.

**TF-SOL-03 — Snapshot de configuración**: crear una solicitud de `$pSad`, después cambiar sus documentos exigidos → la solicitud conserva los de su creación.

## Grupo B — Ciclo de la solicitud

**TF-SOL-04 — Solicitar según la vía**: «Solicitar prestación» con `$pDirecta` registra una prestación de la persona activa; con `$pPlaza` abre la prescripción; con `$pSad` crea una solicitud `borrador`; con la derivación, el modal de derivación.

**TF-SOL-05 — Solo prestaciones del centro**: el selector solo ofrece las prestaciones efectivas del centro del contexto; solicitar una que el centro no ofrece, por el servicio → rechazado.

**TF-SOL-06 — Borrador y bandeja**: `$tsNorte` rellena el formulario, vincula el documento de identidad de la carpeta y pasa a bandeja → `en_bandeja`, `unidad_tramitadora_id = $uoTramitacionSad`, evento `a_bandeja`.

**TF-SOL-07 — Unidad «centro de solicitud»**: una solicitud de `$pInterna` desde `$cssNorte` cae en la bandeja de `$uoCssNorte`.

**TF-SOL-08 — Desde un objetivo**: solicitar desde un objetivo de `$pisoAna` guarda plan y objetivo; el objetivo muestra la solicitud.

**TF-SOL-09 — Personas afectadas**: añadir a un miembro activo de la unidad de convivencia → permitido; a una persona que no es miembro → rechazado.

**TF-SOL-10 — Documento subido para la solicitud**: subir un informe médico desde la solicitud → queda en la carpeta de `$ana` y vinculado a la solicitud.

**TF-SOL-11 — Tomar y soltar**: `$tramitadora1` toma → `en_preparacion`, responsable ella; la suelta con nota → `en_bandeja`, sin responsable; eventos.

**TF-SOL-12 — No se toma una ya tomada** **[negativo]**: con la solicitud tomada por `$tramitadora1`, `$tramitadora2` intenta tomarla o enviarla → rechazado (la supervisión puede reasignarla).

**TF-SOL-13 — Devolver al TS**: `$tramitadora1` la devuelve con nota → `borrador`, alerta a `$tsNorte` con la nota. Sin nota → rechazado.

**TF-SOL-14 — Sugerencia no es asignación**: con `$tramitadora2` sugerida, la solicitud sigue en la bandeja para las dos; la sugerencia se ve.

**TF-SOL-15 — Anular antes del envío**: `$tsNorte` anula su borrador con motivo → `anulada`. Anular una `enviada` → rechazado.

## Grupo C — Envío

**TF-SOL-16 — Faltan documentos obligatorios**: enviar sin documento de identidad → pide justificación; con justificación → se envía y la justificación queda en el evento.

**TF-SOL-17 — Envío con simulador**: `$tramitadora1` envía → `enviada`, `numero_expediente` del simulador, `fecha_envio` hoy, evento con origen `usuario`.

**TF-SOL-18 — Envío con adaptador manual**: con `GestorManual`, enviar exige número de expediente y fecha de presentación; sin ellos → rechazado; con ellos → `enviada`.

**TF-SOL-19 — Tramitación interna**: enviar una de `$pInterna` → `enviada` sin llamar al adaptador (comprobado con un doble que falla si se le llama).

**TF-SOL-20 — Fallo del gestor**: el simulador lanza error en `iniciarSolicitud` → la solicitud sigue `en_preparacion`, el usuario ve un mensaje y la causa queda en el log.

## Grupo D — Actualizaciones

**TF-SOL-21 — Cuatro orígenes**: actualizar el estado por aviso simulado, por `expedientes:consultar`, por «Consultar ahora» y por registro manual → cuatro eventos con su origen y el estado normalizado actualizado.

**TF-SOL-22 — Idempotencia** **[negativo]**: el mismo aviso dos veces → un solo evento.

**TF-SOL-23 — Mapeo de estados**: un estado externo `TRAMITE_02` se guarda tal cual y se normaliza a `en_tramite` según la configuración del adaptador.

**TF-SOL-24 — Incidencia**: un requerimiento de documentación con plazo → evento `incidencia`, alertas a `$tsNorte` y a la responsable; la solicitud aparece en «Enviadas con incidencia abierta».

**TF-SOL-25 — Atender incidencia**: `$tramitadora1` vincula un documento y la marca atendida → evento `incidencia_atendida`; con simulador se llama a `aportarDocumentacion`; sale de «con incidencia abierta».

**TF-SOL-26 — VIDA no escribe al ciudadano**: ninguna incidencia ni resolución genera comunicaciones al ciudadano (sin `Notification` ni correo a la persona).

**TF-SOL-27 — Consulta automática solo con adaptador que la admite**: con `GestorManual`, `expedientes:consultar` no hace nada y la interfaz no muestra «Consultar ahora».

**TF-SOL-28 — Un fallo no para la consulta automática**: con tres solicitudes enviadas y el simulador fallando en la segunda, la primera y la tercera se actualizan.

## Grupo E — Resolución y prestaciones de la persona

**TF-SOL-29 — Resolución favorable**: resolución con una línea concedida de `$pSad` (intensidad 20 h/mes) → `resuelta`, `PrestacionPersona` `concedida` con fecha de concesión, condiciones y plan; apunte automático con la fuente; alerta a `$tsNorte`.

**TF-SOL-30 — Resolución parcial y distinta de lo pedido**: dos líneas: una concedida de otra prestación de menor intensidad y una denegada con motivo → una `PrestacionPersona` de la concedida; la denegada visible en la solicitud.

**TF-SOL-31 — Resolución desfavorable**: ninguna `PrestacionPersona`; alerta al TS.

**TF-SOL-32 — Concedida no es activa** **[negativo]**: tras la concesión, la prestación no cuenta como activa en la ficha hasta registrar el inicio. Registrar inicio con fecha → `activa`, evento.

**TF-SOL-33 — Suspender, reanudar, finalizar**: transiciones con motivo; desde `finalizada` no se vuelve; historial completo.

**TF-SOL-34 — Prestación directa**: `$tsNorte` registra `$pDirecta` → `activa` desde hoy, sin concesión, origen nulo.

**TF-SOL-35 — Plaza asignada**: asignar plaza a una prescripción de `$ana` → `PrestacionPersona` `activa` con origen la prescripción.

**TF-SOL-36 — Cerrar sin resolución**: estado externo `desistida` → `cerrada_sin_resolucion` con motivo; sin prestaciones; alerta al TS.

**TF-SOL-37 — Bloque «Prestaciones» de la ficha**: muestra activas, concedidas pendientes de inicio con los días transcurridos, suspendidas y solicitudes en curso; `statPrestaciones()` devuelve el resumen.

## Grupo F — Permisos y privacidad

**TF-SOL-38 — Tramitación no ve la Historia Social** **[negativo]**: `$tramitadora1`, con una solicitud de `$ana` en su bandeja, intenta abrir la historia, los apuntes o el motivo profesional (rutas y acciones) → denegado. Ve identificación, contacto, parámetros, documentos vinculados a la solicitud e historial.

**TF-SOL-39 — Bandeja por unidad**: `$tramitadoraOtra` no ve ni puede tomar solicitudes de `$uoTramitacionSad`; `$tramitadoraCentro` solo ve las de `$uoCssNorte`.

**TF-SOL-40 — Persona protegida en tramitación**: una solicitud de `$marta` en la bandeja de `$uoTramitacionSad`: `$tramitadora1` la ve con identidad para tramitarla, cada acceso se audita como `acceso_restringido`, y no ve su Historia Social.

**TF-SOL-41 — Quién crea solicitudes**: `consulta_basica` y `tramitacion` no pueden crear solicitudes (acción y servicio) → denegado.

**TF-SOL-42 — Registro manual con permiso**: un usuario sin `solicitud.registrar_manual` intenta registrar estado o resolución → denegado.

**TF-SOL-43 — `consulta_basica` no ve prestaciones**: la ficha de `$ana` para `consulta_basica` no muestra el bloque «Prestaciones» ni las solicitudes.

## Grupo G — Retirada de `SolicitudServicio`

**TF-SOL-44 — Sin `SolicitudServicio`**: la tabla y el modelo no existen; `Servicio` sigue existiendo y su UO puede ser unidad tramitadora de una prestación.

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Catálogo | TF-SOL-01..03 | |
| B — Ciclo | TF-SOL-04..15 | |
| C — Envío | TF-SOL-16..20 | |
| D — Actualizaciones | TF-SOL-21..28 | |
| E — Resolución y prestaciones de la persona | TF-SOL-29..37 | |
| F — Permisos y privacidad | TF-SOL-38..43 | |
| G — Retirada de `SolicitudServicio` | TF-SOL-44 | |
