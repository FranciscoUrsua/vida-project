# CHANGELOG — VIDA 360 — Septiembre 2026

> Entradas de septiembre 2026. Para meses anteriores, ver `CHANGELOG-062026.md`.

---

## 2026-09-25 — Documentos: estado real del módulo y custodia v2, fase 2a (tipos documentales, modelo, almacenamiento cifrado)

### Módulos afectados
`Modules/Documentos` (enums, modelos, contratos, servicios, comandos, migraciones, seeders, rutas, config, tests y fixtures), `app/Filament/Resources/TipoDocumentalResource.php` (nuevo) y sus páginas, `DocumentoResource.php` y `Pages/ViewDocumento.php`, `InformeResource/Pages/ViewInforme.php`, `config/filesystems.php`, `.env.example`, `phpunit.xml`, `docs/modulo-documentos.md`, `docs/documentacion-proyecto.md` §9, `CLAUDE.md`

### Documentación corregida (punto 1)
- `docs/modulo-documentos.md` daba por implementados las variables auxiliares (TF-DOC-22 a 25, `ParametroInforme`), `ConfiguracionTipografiaResource` y los cuatro componentes Livewire operativos. No existen en el código ni en el historial de git. Se marcan como ⏳ y se corrige el estado (24 tests reales, no 25).
- Los tests del 2026-09-24 TF-DOC-26, 27 y 29 (número de página y logo) chocaban con la numeración de la custodia v2: pasan a **TF-DOC-79, 80 y 81**.

### Añadido (custodia v2, pasos 1 a 3 de `documentos-custodia-implementacion.md`)
- **Paso 0:** 24 tests del módulo en verde; binarios presentes (`gs`, `qpdf`, `soffice`, `pdfinfo`, ClamAV, `imagick`) salvo `img2pdf` (innecesario); `clamd` parado; 0 filas en `documentos`, `informes` y `piso_firmados`.
- **Tipos documentales:** tabla `tipos_documentales`, modelo `TipoDocumental` (`Auditable`, `Versionable`; código, familia y origen inmutables con documentos; no se borra con documentos), `TipoDocumentalResource` («Informes y Plantillas», orden 5, solo `adm_sistema`) y `TiposDocumentalesSeeder` (desde `catalogos_sistema` `documento.tipo` + `informe_profesional`), llamado desde `DocumentosSeeder`.
- **Modelo:** `documentos` reconstruida como documento lógico; `documento_versiones` (índice parcial: una vigente), `documento_vinculos` (morph, índice parcial de vínculo activo único), `documento_retenciones`, `actas_eliminacion` (inmutable). Modelos `Documento`, `DocumentoVersion` (`nombre_original` con cast `encrypted`), `DocumentoVinculo`, `DocumentoRetencion`, `ActaEliminacion`. Enums `FamiliaDocumental`, `OrigenEni`, `PoliticaVersiones`, `EstadoDocumento`, `EstadoVersion`, `CanalCaptura`, `MotivoRetencion`.
- **Almacenamiento y cifrado:** contratos `AlmacenDocumentos` y `ProveedorClavesMaestras`; `AlmacenFlysystem` (disco `documentos`, ruta `xx/uuid`), `ProveedorClavesLocal` (`DOCUMENTOS_CLAVE_MAESTRA`), `CifradorDocumentos` (AES-256-GCM por versión). Comandos `documentos:verificar-integridad` y `documentos:limpiar-huerfanos`.
- **Servicios:** `IngestaDocumentoService` (tubería mínima, solo PDF), `CicloVidaDocumentoService` (`altaDocumento`, `desvincular`), `LecturaDocumentoService`, `RetencionService`; excepciones `IngestaRechazadaException`, `ConfiguracionDocumentosException`, `IntegridadDocumentoException`.
- **Tests:** `TiposDocumentalesTest` (TF-DOC-26 a 31), `VinculosDocumentoTest` (32 a 38), `AlmacenamientoCifradoTest` (39 a 45), trait `DocumentosTestSetup`, fixtures y `generar-fixtures.sh`. `Modules/Documentos`: 44 passed. `FilamentPanelAccessTest`: 16 passed. `Modules/Intervencion`: 261 passed, 1 incomplete y 1 fallo ya conocido (`AccesosExpedienteTest`).
- **Comprobación en negativo** (quitando la protección, el test falla): TF-DOC-29 sin `impedirCambiosInmutables()`, TF-DOC-37 sin `validarMetadatos()`, TF-DOC-39 guardando el contenido en claro.
- **Acceso al disco:** `grep` de `Storage::` en `app`, `Modules` y `routes`: solo `AlmacenFlysystem` usa el disco de documentos (el resto es el disco `public` del logo).

### Cambiado
- Eliminados `ServicioAlmacenamiento` y el enum `OrigenDocumento`. Adaptados: `ServicioGeneracionPDF::generarFinal()` (el PDF firmado entra por `CicloVidaDocumentoService` con tipo `informe_profesional`, canal `generado`, vinculado al ciudadano del informe), la ruta `documentos.ver` (descifra en memoria, nombre genérico `{codigo}-{fecha}.pdf`), `DocumentoResource`, `ViewDocumento`, `ViewInforme`, y los tests TF-DOC-01 a 05, 13, 15, 17 a 19 (misma intención; TF-DOC-02 rechaza un ZIP porque los DOCX se admitirán convertidos en la fase 2b).
- Pint quitó los `@return` de los docblocks; se restauraron por `CLAUDE.md`.

### Decisiones de implementación no previstas en las instrucciones
- **Sin migración de datos:** con 0 documentos en la única BD con datos, la migración que reconstruye `documentos` se detiene si encuentra filas en vez de migrarlas. Recrea las tres FK que apuntan a `documentos` (incluida `firmas_plan.documento_firmado_id`, que las instrucciones no mencionaban).
- **Fase 2a de la ingesta:** solo admite PDF; aplica ya los límites de páginas y tamaño (sin recompresión). Antivirus, conversión y saneado, en 2b.
- `altaDocumento` exige un tipo activo y al menos un vínculo.
- `vinculables`: `intervencion` = `PlanDeIntervencion`, `valoracion` = `Valoracion` (módulo Intervención).
- `TiposDocumentalesSeeder` usa `firstOrCreate` y solo sincroniza el nombre, en lugar de `updateOrCreate`, para no pisar lo configurado en Filament al volver a ejecutarlo.
- Clave maestra en formato `base64:` de 32 bytes, distinta de `APP_KEY`; `id_clave_maestra` = `local-` + 16 caracteres del SHA-256 de la clave. El proveedor se resuelve sin singleton para fallar en el primer uso si falta.
- Formato del objeto cifrado: nonce (12) · tag (16) · texto cifrado. La clave de datos cifrada sigue el mismo formato en base64.
- `limpiar-huerfanos` trata como huérfanos también los objetos de versiones purgadas o destruidas.
- El visor de Filament ya no muestra el nombre original del fichero (puede contener datos personales). El filtro de supervisión pasa de «subido por» a «dado de alta por».
- `ServicioGeneracionPDF` resuelve el alta de documentos al firmar, no en el constructor, para que la vista previa de borradores no dependa de la clave maestra.

### Pendiente en el servidor de pruebas
Crear `/srv/vida/documentos` y configurar `DOCUMENTOS_RUTA` y `DOCUMENTOS_CLAVE_MAESTRA` (comandos en BACKLOG). Hasta entonces la custodia falla con mensaje claro en el primer uso; el resto de la aplicación no se ve afectado.

---

## 2026-09-25 — Usuarios: alta rápida de profesional, historial de roles de solo lectura y nivel de supervisión explícito

### Módulos afectados
`app/Filament/Resources/UsuarioResource.php`, `ProfesionalResource.php`, `UsuarioRolResource.php` (+ páginas `CreateUsuarioRol`/`EditUsuarioRol` eliminadas), `Modules/Usuarios/app/Models/UsuarioRol.php`, `Modules/Supervision/app/Http/Livewire/AprobacionesPage.php`, `InicioPage.php`, `Modules/Supervision/app/Services/SupervisionSidebarDataService.php`, `database/seeders/ConfiguracionRolesSeeder.php` (nuevo), `DatabaseSeeder.php`, tests, `docs/modulo-usuarios-permisos.md` §2.8 y §4.8

### Añadido
- **Alta rápida de profesional** desde el selector «Profesional vinculado» del formulario de usuarios (botón «+»). Abre un modal con los mismos campos que la ficha de profesional (`ProfesionalResource::camposFormulario()`, extraído de `form()`). Al guardar, el profesional queda seleccionado y se pre-rellenan los roles sugeridos de su cargo. Solo se muestra a quien puede crear profesionales.
- `ConfiguracionRolesSeeder`: nivel de supervisión explícito para todos los roles (aprobación previa para `adm_sistema` y `supervision`, alerta supervisada para el resto). No sobrescribe niveles existentes. Se llama desde `DatabaseSeeder` tras `RolesSeeder`. **Ejecutado en la BD compartida**: se añadieron 6 filas; la de `intervencion` ya existía y no se tocó.
- Scope `UsuarioRol::resolublesPor(User $supervisor)`: solicitudes pendientes de usuarios del subárbol de UO del supervisor, sin las suyas. Sustituye a la misma consulta, que estaba copiada en Aprobaciones, Inicio y la barra lateral de Supervisión.

### Cambiado
- **`UsuarioRolResource` es de solo lectura** (menú «Historial de roles»). Se quitan el alta, la edición y el borrado: permitían crear una asignación `activa` sin aprobación ni alerta, también sobre uno mismo. El listado muestra además el estado `denegado`.
- **Nadie resuelve su propia solicitud de rol** en Supervisión → Aprobaciones. No aparece en su bandeja ni en los contadores, y aprobarla o denegarla directamente devuelve 403.
- Pint reformateó líneas preexistentes de `InicioPage.php` (alineación de arrays e imports).

### Tests
- `BackofficeRolesSupervisionTest` (5): alta rápida (crea, selecciona y pre-rellena roles; valida obligatorios), historial de solo lectura, seeder de configuración (niveles de 2.8; no sobrescribe; idempotente).
- `SupervisionTest::supervisor_no_puede_resolver_su_propia_solicitud_de_rol` (TF-SUP-E07).
- `Modules/Usuarios/tests/`: 63 passed y 1 incomplete (ya existía). `Modules/Supervision/tests`: 34 passed y los mismos 3 fallos que ya había en master. `FilamentPanelAccessTest` y `SinRolTest`: 26 passed.

### Decisiones
- Historial de solo lectura en lugar de pasar su formulario por `AsignacionRolesService`: ya existen un camino para asignar (formulario de usuarios) y otro para aprobar (Supervisión), y el historial no debe reescribirse (principio 4.2).
- Alta del profesional en un modal, no en un enlace a otra pestaña: así el profesional queda seleccionado sin recargar y se aplican los roles sugeridos.
- El caso «quien solicita el rol para otro lo aprueba él mismo» queda en el BACKLOG como decisión pendiente.

---

## 2026-09-25 — Usuarios: seeders de cargos y roles sugeridos alineados con la BD compartida

### Módulos afectados
`database/seeders/CargosSeeder.php`, `database/seeders/RolesSugeridosCargoSeeder.php`, `app/Filament/Resources/CargoResource.php`, `Modules/Usuarios/app/Models/Cargo.php`, `Modules/Usuarios/tests/Feature/CatalogoCargosSeederTest.php` (nuevo), `RolesSugeridosCargoTest.php`, `docs/seeders.md`, `docs/modulo-usuarios-permisos.md` §2.9

### Cambiado
- El desarrollador rellenó a mano los slugs y las sugerencias de roles de los cargos en la BD compartida y eliminó 4 cargos sin uso (Técnico/a de Integración Social, Mediador/a Social, Técnico/a de Acogida, Ordenanza). Se comprobó que no quedan profesionales sin cargo ni con un cargo inexistente.
- `CargosSeeder`: 9 cargos con los slugs de la BD (`ts`, `psicologo`, `educadorsocial`, `terapeutaocupacional`, `auxss`, `abogado`, `coordinador`, `administrativo`, `auxadmin`) y sus descripciones actuales. Antes habría duplicado 6 cargos y recreado los 4 eliminados.
- `RolesSugeridosCargoSeeder`: sugerencias para los 9 cargos, iguales a las de la BD.
- `CargoResource`: el texto de ayuda del slug ya no pide guiones (ejemplo: `ts`, `coordinador`).
- Tests: `CatalogoCargosSeederTest` (2): una instalación nueva queda con los 9 cargos, todos con sugerencia, y sin duplicados al repetir la carga; el seeder actualiza por slug sin duplicar. `Modules/Usuarios/tests/`: 58 passed y 1 incomplete (ya existía).

### Decisiones
- La BD compartida es la referencia: los seeders copian lo configurado por el desarrollador.
- **Abogado/a pasa a tener sugerencia** (`consulta_profesional`, `intervencion`). Antes no tenía ninguna a propósito (en el CIAM usa `intervencion` y en el SOJ `consulta_profesional`). Ahora se pre-marcan los dos y `adm_usuarios` desmarca el que no corresponda. Documentado en §2.9.
- `RolesSugeridosCargoSeeder` sigue buscando los cargos por nombre, no por slug.

---

## 2026-09-25 — Usuarios: nadie puede borrar ni editar su propio usuario desde Filament

### Módulos afectados
`app/Filament/Resources/UsuarioResource.php`, `Modules/Usuarios/tests/Feature/UsuarioAutogestionTest.php`

### Corregido
- Bug reportado: `admin@vida.local` se borró a sí mismo desde la tabla de usuarios y perdió el acceso. El usuario lo recreó desde tinker; la fila borrada (id 1) sigue en la BD como soft delete.
- **Causa 1:** la `DeleteAction` de la tabla tenía un `->authorize()` propio que solo comprobaba `adm_sistema` y se saltaba `canDelete()`.
- **Causa 2:** en Filament 5, las acciones y páginas no usan `canEdit()`/`canDelete()`, sino `getEditAuthorizationResponse()`/`getDeleteAuthorizationResponse()`. Sobrescribir solo `can*()` no protegía los botones.
- Arreglo: `UsuarioResource` sobrescribe ambas `get*AuthorizationResponse()`. Deniegan si el registro es el usuario autenticado y, si no, delegan en `canEdit()`/`canDelete()`, que también excluyen al propio usuario. Se quitó el `->authorize()` de la acción de borrado de la tabla. Las acciones de borrar y editar desaparecen de la propia fila, la página de edición de uno mismo devuelve 403 y el botón «Borrar» de la cabecera de edición queda cubierto.
- Tests: `UsuarioAutogestionTest` (5). Antes del arreglo fallaban 4. `Modules/Usuarios/tests/` y `FilamentPanelAccessTest`: 72 passed y 1 incomplete (ya existía).

### Decisiones
- También se bloquea **editar** el propio usuario, no solo borrarlo: permitiría darse o quitarse roles sin supervisión. El cambio de contraseña propio sigue disponible fuera de `/admin` (onboarding y avatar).

---

## 2026-09-25 — Usuarios: roles sugeridos por cargo y asignación supervisada de roles desde el backoffice

### Módulos afectados
`Modules/Usuarios` (modelos, servicios, migraciones, tests), `app/Filament/Resources/CargoResource.php`, `app/Filament/Resources/UsuarioResource.php` y sus páginas, `app/Models/User.php`, `database/seeders/`

Instrucciones: `docs/instrucciones-cli/2026-09-roles-sugeridos-cargo.md`.

### Añadido
- Migración `create_cargo_roles_sugeridos_table` (`cargo_id`, `rol`, unique `(cargo_id, rol)`) y modelo `CargoRolSugerido` (Auditable). Guardar un rol que no existe en Spatie lanza `InvalidArgumentException`. Relación `Cargo::rolesSugeridos()` y `Cargo::sincronizarRolesSugeridos()`, que crea y borra fila a fila para que cada cambio quede auditado.
- Migración `add_cargo_roles_revisado_id_to_users_table`: el cargo para el que se revisaron por última vez los roles del usuario. Se rellena con el cargo actual, así que ningún usuario existente muestra el aviso al migrar.
- `RolesSugeridosService`, el único punto de lectura de las sugerencias: pre-relleno del alta, aviso de cambio de cargo y marcado como revisado.
- `AsignacionRolesService`: asigna y retira roles por `usuario_rol`. Aprobación previa → `pendiente_aprobacion`, sin rol efectivo. Alerta supervisada → rol activo más una alerta `rol_uo` para `supervision` de la UO del usuario. `ConfiguracionRol::nivelPara()` da el nivel de cada rol, con el valor por defecto de 2.8 si falta configuración.
- `CargoResource`: selector múltiple «Roles sugeridos» con la nota «Se proponen al dar de alta a un usuario con este cargo. No otorgan permisos por sí mismos». Valida que los roles existan.
- `UsuarioResource`:
  - En el alta, elegir el profesional sustituye la selección de roles por las sugerencias de su cargo (vacía si no tiene).
  - En la edición, un aviso «El cargo ha cambiado» con los roles sugeridos del nuevo cargo y la acción de cabecera «Descartar aviso de cargo». El aviso desaparece también al guardar un cambio de roles.
- `RolesSugeridosCargoSeeder` (idempotente, no sobrescribe, no crea cargos), registrado en `DatabaseSeeder` después de `CargosSeeder`.
- Tests: `Modules/Usuarios/tests/Feature/RolesSugeridosCargoTest.php`, con 11 tests (TF-USU-RS-01 a 06 más las variantes negativas y el seeder). Se comprobó que RS-03b y RS-06 fallan si se quita la restricción que protegen. `Modules/Usuarios/tests/`: 51 passed y 1 incomplete (ya existía).

### Cambiado
- **El formulario de usuarios ya no escribe los roles directamente en Spatie.** Antes usaba `CheckboxList::relationship('roles')`, que escribía en `model_has_roles` sin historial, sin aprobación previa y sin alerta. Ahora los roles se guardan en `CreateUsuario::afterCreate()` y `EditUsuario::afterSave()` por `AsignacionRolesService`. Al editar, el selector muestra los roles efectivos y los pendientes (marcados como «Pendiente de aprobación»).
- El alta ya no marca `consulta_basica` por defecto: el selector parte vacío hasta elegir el profesional. Sigue siendo obligatorio marcar al menos un rol. `consulta_basica`, que `User::booted()` añade al crear, se retira si no está marcado.

### Aplicado en la BD compartida local/staging
- Migraciones `2026_09_25_100001` y `2026_09_25_100002` aplicadas. `RolesSugeridosCargoSeeder` ejecutado: Coordinador/a de Centro → supervision e intervencion; Trabajador/a Social, Psicólogo/a y Auxiliar de Servicios Sociales → intervencion; Administrativo/a → tramitacion.

### Decisiones de implementación no previstas en las instrucciones
- **Nombres de cargo:** «Directora de centro» → `Coordinador/a de Centro` (el que usa el mundo CIAM para la dirección), «Administrativa» → `Administrativo/a` y «Abogada» → `Abogado/a`. No se creó ningún cargo.
- **Flujo supervisado en el formulario:** la Fase 0 detectó que el alta escribía directamente en Spatie. Por decisión del desarrollador, el formulario se reencaminó por `usuario_rol` con supervisión, lo que afecta a todas las altas y ediciones, no solo a las que tienen sugerencias.
- **Nivel por defecto sin configuración:** si un rol no tiene fila en `configuracion_roles`, `adm_sistema` y `supervision` requieren aprobación previa y el resto va con alerta supervisada. Sin este respaldo, `supervision` se habría activado sin aprobación en la BD compartida, que solo tiene configurado `intervencion`.
- **Estado del aviso:** columna `users.cargo_roles_revisado_id` en lugar de una tabla aparte.
- **Destino de la alerta:** `supervision` de la UO de la primera adscripción vigente del usuario, con la raíz como respaldo (la tabla `alertas` exige UO para destinatarios `rol_uo`). Pendiente de confirmar frente a «UO superior» (BACKLOG).
- **`profesionales.cargo_id` es obligatorio,** así que el caso «profesional sin cargo» de la Fase 2 no puede darse. Solo se prueba el de cargo sin sugerencias.

---

## 2026-09-24 — Auth: la supervisión operativa tiene prioridad sobre /admin para supervision + adm_usuarios

### Módulos afectados
`app/Models/User.php`, `app/Http/Controllers/Auth/LoginController.php`, `routes/web.php`

### Corregido
- Bug reportado: la directora del CIAM (`dir.ciam@vida.local`, roles `intervencion` + `supervision` + `adm_usuarios`) entraba tras el login en el panel Filament (`/admin`) en lugar de en la supervisión operativa (Livewire). Causa: `LoginController::destino()` y la ruta `/` comprobaban `adm_usuarios` antes que `supervision`.
- Nuevo `User::destinoInicial()`, punto único con el orden de prioridad `adm_sistema` → `/admin`, `supervision` → `supervision.inicio`, `adm_usuarios` → `/admin`, `intervencion` → agenda, y sin roles → `sin-rol`. Lo usan el login y `/`, que antes duplicaban la lógica.
- La directora conserva el acceso a `/admin` (`canAccessPanel` no cambia), pero no entra en él por defecto.
- Tests: `tests/Feature/Auth/DestinoTrasLoginTest.php` (3 tests: supervision + adm_usuarios → supervisión; solo adm_usuarios → /admin; adm_sistema + supervision → /admin). El primero fallaba antes de la corrección. `tests/Feature/Auth/` y `FilamentPanelAccessTest` sin regresiones; siguen los 2 fallos conocidos de TF-AUTH-16/17 (BACKLOG).

---

## 2026-09-24 — Mundo demo «Prueba CIAM» en modo aditivo + plan especializado con entrada directa

### Módulos afectados
`database/seeders/Demo/`, `database/seeders/worlds/demo_ciam.yaml`, `app/Console/Commands/`, `app/Models/`, `app/Filament/Pages/DemoWorldsPage.php`, `app/Filament/Resources/TipoPlanResource.php`, `Modules/Intervencion` (tipos de plan, `PlanDeIntervencion`)

Instrucciones: `docs/instrucciones-cli/2026-09-demo-ciam-aditivo.md` (incluye las iteraciones de la sesión).

### Añadido

**Modo aditivo de mundos demo:**
- Comando `demo:load --world=X [--dry-run]` (`DemoLoadCommand`). Solo acepta mundos con `modo: aditivo`. Nunca hace TRUNCATE, DELETE ni forceDelete. Corre en una única transacción; `--dry-run` hace la carga completa y rollback. Se niega en `production`. Al final verifica invariantes solo sobre los planes del mundo y muestra un resumen por entidad (creado / ya existente / referenciado).
- `DemoWorldLoader`: claves raíz `modo` (`reset` por defecto | `aditivo`), `etiqueta` (obligatoria en aditivo, `[A-Z0-9_]+`), `tipo_plan` y sección `existentes` (centros, tipos_plan, cargos, salas, tipos_actividad) con `buscar_por` y `crear_si_no_existe`. Los mundos existentes (reset) no cambian: se validan igual que antes.
- `DemoReferenciaResolver`: cada referencia debe encontrar exactamente un registro. Cero sin `crear_si_no_existe`, o más de uno, hacen fallar la carga. Las entidades referenciadas nunca se modifican. Centros y tipos de plan solo pueden referenciarse, no crearse.
- `DemoRegistrador` + migración `create_demo_world_registros_table` + modelo `DemoWorldRegistro` (`::de($etiqueta)`, scope `deTipo()`). Es el registro de todo lo que crea un mundo aditivo y la base de la idempotencia (etiqueta, clave, tipo). La tabla no se trunca en `demo:reset`.
- `DemoMundoAditivoBuilder` + `DemoContextoAditivo`: profesionales con varios roles, actividades y sesiones, y ciudadanas con claves estables `usuaria_NNN`.
- Escenarios `EscenarioCiam` (base) y `CiamPiaActiva`, `CiamPiaCerrada`, `CiamParticipanteActividad` y `CiamInformacion` en `database/seeders/Demo/Scenarios/`.
- `DemoWorldsPage`: los mundos aditivos muestran las etiquetas «Aditivo» y su etiqueta. Tienen la acción «Cargar (aditivo)», cuyo modal muestra el dry-run, y no tienen reset.

**Plan especializado con entrada directa:**
- Migración `add_admite_entrada_directa_to_tipos_plan_table`: boolean con default `false`. Marca `true` solo en `pia`, solo en ese campo y sin tocar `updated_at`.
- `PlanDeIntervencion::verificarOrigenPlanEspecializado()` (hook `saving`): un plan `especializado` sin `plan_asp_id` lanza `DomainException` salvo que su tipo tenga `admite_entrada_directa`. En actualizaciones solo actúa si cambian `tipo`, `plan_asp_id` o `tipo_plan_id`.
- `DemoInvariantChecker`: INV-02 ignora los tipos con entrada directa; `check(?array $planIds)` permite limitar la comprobación a unos planes.
- `TipoPlan` (fillable, cast y PHPDoc), `TipoPlanFactory::entradaDirecta()`, y toggle «Admite entrada directa (sin plan ASP previo)» en `TipoPlanResource`. `TipoPlanSeeder` no escribe el campo, así que no lo revierte.

**Mundo `demo_ciam.yaml` → «Prueba CIAM»** (aditivo, etiqueta `TEST_CIAM`):
- Referencia el centro CIAM Puente de Vallecas, el tipo `pia`, 6 cargos existentes y las salas Girasol y Polivalente. Crea 10 profesionales (todas `F`), 100 ciudadanas (30 PIA activo, 20 PIA cerrado, 35 participantes en actividades y 15 atenciones informativas) y 4 actividades con 13 sesiones. El CSS Entrevías del mundo anterior desaparece.

**Tests** (16, todos en verde):
- `tests/Feature/Demo/DemoAditivoTest.php`: TF-DEMO-CIAM-01 a 11, 15 y 16.
- `Modules/Intervencion/tests/Feature/PlanEntradaDirectaTest.php`: TF-DEMO-CIAM-12 a 14.
- Comprobado en negativo: al quitar la guarda del modelo falla TF-13; al quitar la excepción de INV-02 falla TF-14; al quitar el rechazo en `demo:reset` falla TF-02.
- Regresión:
  - `tests/Feature/Demo/`: 20 passed y 5 incomplete (ya existían).
  - `Modules/Intervencion/tests/`: 261 passed y 1 fallo pre-existente, `AccesosExpedienteTest::acceso_de_otra_uo_con_accion_ver_tiene_clase_sospechoso`. Se ha reproducido en master sin los cambios de esta sesión.

### Modificado
- `DemoResetCommand`: rechaza los mundos aditivos antes de truncar nada, con un mensaje que remite a `demo:load`. Es la única excepción al comportamiento de `demo:reset`.
- `DemoValidateCommand`: muestra el modo, la etiqueta y el recuento de `existentes`.

### Carga en staging (autorizada por el desarrollador en la sesión)
- La BD de desarrollo local **es la misma** que la de staging (`vida@127.0.0.1`), así que se aplicaron allí las 2 migraciones y `demo:load --world=demo_ciam`.
- La carga creó 980 registros. Una segunda ejecución crea 0. El centro CIAM (id 13) queda idéntico, campo a campo.
- `dir.ciam@vida.local / dir987` y `ts2.ciam@vida.local / ts2987` validan credenciales. ts2 tiene 10 historias asignadas, 6 de ellas vigentes.

### Decisiones de implementación no previstas en las instrucciones
- **Cargos:** se reutilizan los del catálogo con nombre neutro. La directora usa «Coordinador/a de Centro». No se crea ningún cargo (decisión del desarrollador).
- **Tipos de actividad:** se crea `taller-empoderamiento`; la sesión informativa usa `charla` (equivalente existente); `formacion` y `taller-empleo` ya existían.
- **Punto 1.4 omitido:** no existe ningún mecanismo que asocie tipo de plan con centro, UO o servicio. `pia` ya está disponible en todos los centros. Anotado en BACKLOG.
- **Roles «ya aprobados»:** los mundos de reset asignan los roles solo en Spatie. Aquí se crea además un `UsuarioRol` en estado `activo` (el observer sincroniza Spatie), para que resistan `usuarios:reconciliar-roles`.
- **`consulta_basica` automático:** `User::booted()` asigna `consulta_basica` a todo usuario creado con `profesional_id` y sin roles. El builder crea primero el usuario y vincula el profesional después, igual que `DemoWorldBuilder`. En la primera carga en staging los 10 usuarios recibieron ese rol extra (solo en Spatie). Se retiró únicamente de esos 10 usuarios TEST_CIAM, con la aprobación del desarrollador. TF-15 siembra ahora el rol para detectarlo.
- **Atención informativa:** `SiaContacto` con `clasificacion = informacion_general` e `informacion_prestada`, sin historia social (principio 2.1).
- **Participación en actividades:** `InscripcionCentro` más una `Prescripcion` por sesión (`tipo_destino = sesion_actividad`). Las sesiones pasadas quedan `finalizada` y las futuras `activa`. No existe otra entidad de inscripción en actividad.
- **Sin documentos de identidad:** los mundos existentes no generan ninguno (decisión del desarrollador).
- **Determinismo:** las decisiones estructurales del azar (nº de seguimientos, participación, actividades) salen de `crc32(etiqueta:clave:decisión)`. Con `mt_rand` la segunda carga desplazaba la secuencia aleatoria y creaba registros nuevos.
- Clave raíz nueva `tipo_plan` en el YAML. Las ciudadanas reciben las claves `usuaria_NNN` según el orden de los escenarios, así que no hay que reordenarlos tras la primera carga.
- Pint aplicado a los ficheros tocados, como hace la CI; elimina los `@return void` redundantes.

---

## 2026-09-24 — Actualización de dependencias: 28 vulnerabilidades conocidas corregidas

### Módulos afectados
`composer.json` / `composer.lock` (infraestructura, sin cambios funcionales)

### Corregido

`composer audit` pasó de 28 advisories en 6 paquetes a 0. Motivo original: el hook `.git/hooks/pre-commit` (`security-check.sh`) trata cualquier hallazgo de `composer audit` como error bloqueante, así que ningún commit pasaba el hook hasta corregir esto (ver entrada anterior de hoy, donde se hizo commit con `--no-verify`).

**Actualizados sin tocar `composer.json`** (dentro de las constraints ya declaradas):
- `dompdf/dompdf` v3.1.5 → v3.1.6 (6 advisories: SVG file-existence leak, DoS por bitmaps/BMP, local file read, chroot bypass).
- `guzzlehttp/guzzle` 7.12.3 → 7.15.5 (+ `guzzlehttp/psr7`, `guzzlehttp/promises`) (6 advisories: host/cookie canonicalization, referer leak, DoS, proxy-auth header leak).
- `league/commonmark` 2.8.2 → 2.10.3 (10 advisories: varios DoS y un bypass XSS en `AttributesExtension`).
- `livewire/livewire` v4.3.1 → v4.4.6 (1 advisory: XSS basado en DOM en el manejo de estado cliente).
- `spatie/laravel-medialibrary` 11.21.0 → 11.23.8 (2 advisories: bypass de restricción de subida de ficheros, SSRF).
- `filament/filament` (+ todos sus sub-paquetes `filament/*`) v5.6.7 → v5.8.4 (3 advisories, la de mayor severidad: bypass de MFA cuando hay códigos de recuperación habilitados).

**Assets regenerados:** `vida/public/{css,js,fonts}/filament/**` republicados (`vendor:publish --tag=filament-assets --force`, ejecutado automáticamente por el script `post-update-cmd` de Filament) — necesarios porque el JS/CSS compilado de Filament cambió de versión.

### Verificación

Ninguna actualización tocó `laravel/framework` ni Symfony (las dependencias declaradas por `laravel/framework` para guzzle/commonmark ya admitían las versiones parcheadas; los sub-paquetes `filament/*` se actualizaron juntos y se resuelven sin cascada). Se comparó contra una baseline con las dependencias originales (`git stash` de `composer.lock` + `composer install`) ejecutando los tests más proclives a verse afectados (Livewire, Filament, PDF): todos los fallos observados con las dependencias nuevas ya fallaban igual con las antiguas (deuda técnica pre-existente, no relacionada — ver `BACKLOG.md`: `TF-AUTH-16/17`, y fallos de esquema en `Modules/Agenda` por una migración de una sesión anterior). Se ejecutaron además, en verde: `tests/Feature/FilamentPanelAccessTest`, toda la suite de `Documentos`, `Organizacion` y `Usuarios`, y el directorio completo `tests/Feature`.

---

## 2026-09-24 — Pequeños cambios en Filament: tema, pie de informe, logo de organización, borrado de usuario

### Módulos afectados
`app/Providers/Filament`, `Modules/Documentos`, `Modules/Organizacion`, `Modules/Usuarios`, `app/Filament/Resources`

### Añadido

**Filament — tema:**
- `AdminPanelProvider::panel()` — `->darkMode(false)`. Elimina el selector claro/oscuro (menú de usuario y layout base de Filament); el panel queda fijo en modo claro. Motivo: inconsistencias de UI en modo oscuro (`docs/design-system/SKILL.md` ya indicaba no producir variantes oscuras salvo petición explícita).

**Documentos — número de página en el pie de informe:**
- `EstiloInforme::MARCADOR_NUMERO_PAGINA` (`'{{ numero_pagina }}'`) — nueva constante.
- `EstiloInformeResource` — botón «Insertar número de página» (`hintAction`) en el campo del pie, añade el marcador al texto.
- `ServicioGeneracionPDF::generarBorrador()` — detecta el marcador, lo retira del HTML y dibuja el número real por página con `Canvas::page_text()` de dompdf (única vía sin habilitar evaluación de PHP embebido en el HTML, que sería una vulnerabilidad ya que el pie lo edita libremente el supervisor).
- Tests: `test_tf_doc_26_...` y `test_tf_doc_27_...` en `DocumentosTest.php`.

**Documentos — logo único de organización:**
- `Modules\Organizacion\Models\Configuracion::logoPathAbsoluto()` — resuelve a ruta de fichero absoluta (no URL) el logotipo ya configurable en Sistema → Configuración → «Identidad visual» (clave `logo_path`), para que dompdf pueda incrustarlo.
- `ServicioGeneracionPDF::generarBorrador()` — sobreescribe siempre `estilo['logo_cabecera']` con ese logo global, ignorando el valor por UO de `EstiloInforme`.
- `EstiloInformeResource` — se retira el campo de ruta manual al logotipo (`logo_cabecera`) y la columna del listado; se añade un aviso que enlaza a dónde gestionarlo.
- `ListConfiguracion` («Identidad visual») — texto actualizado: el logo ahora se usa también en la cabecera de los informes PDF, no solo en el sidebar.
- Módulo `Organizacion` dado de alta en el test runner (no lo estaba): `composer.json` (`autoload-dev` PSR-4) y `phpunit.xml` (testsuite `Feature`).
- Tests: `Modules/Organizacion/tests/Feature/ConfiguracionTest.php` (nuevo, 3 tests) + `test_tf_doc_29_...` en `DocumentosTest.php`.

**Usuarios — borrado de usuario es soft delete:**
- Bug reportado: borrar un usuario desde Filament lanzaba `QueryException` (FK `usuario_uo.usuario_id` con `onDelete('restrict')`).
- `User` — trait `SoftDeletes`. Migración `add_deleted_at_to_users_table`. `UsuarioResource::DeleteAction` ya hacía lo correcto en cuanto el modelo tuvo `SoftDeletes` (sin cambios en la action).
- Efecto colateral corregido en la misma sesión: el índice único de `email` bloqueaba para siempre el email de un usuario borrado. Migración `make_users_email_unique_index_exclude_soft_deleted` (índice único parcial de PostgreSQL, `WHERE deleted_at IS NULL`) + `UsuarioResource` con `modifyRuleUsing` en la validación `unique()` del email.
- Tests: `Modules/Usuarios/tests/Feature/UsuarioSoftDeleteTest.php` (nuevo, 5 tests).

### Decisiones de implementación

- Ver `docs/decisiones-tecnicas.md` Sección 12 (logo único de organización) y Sección 13 (soft delete en `users`).
- El campo `logo_cabecera` de `EstiloInforme` (columna y resolución jerárquica en `ResolverEstiloInforme`) no se elimina del esquema ni del código — solo deja de exponerse en el formulario. Revisar si en el futuro se necesita volver a un logo por UO.
- No se añade cascada de soft delete desde `User` hacia `usuario_uo`/`usuario_rol`/roles de Spatie: esas filas se conservan (es el comportamiento deseado, mantiene el historial).
- No se añaden `TrashedFilter`/`RestoreAction`/`ForceDeleteAction` en `UsuarioResource`: ningún otro resource del proyecto los tiene (patrón existente).

### Pendiente (ver `BACKLOG.md`)

- Bug pre-existente detectado durante la verificación de esta sesión (no introducido por ella, no corregido por estar fuera de alcance): `User::booted()` sobreescribe `name` con el email de forma incondicional, lo que rompe TF-AUTH-16 y TF-AUTH-17 (`tests/Feature/Auth/AutenticacionTest.php`). Confirmado reproducible en `master` antes de esta sesión.
