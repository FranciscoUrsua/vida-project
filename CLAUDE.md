# CLAUDE.md — VIDA 360

Instrucciones permanentes para Claude CLI en el proyecto VIDA 360.
Este fichero se aplica a todas las sesiones sin necesidad de repetirlo en cada prompt.

---

## 1. Antes de tocar cualquier fichero

1. `git pull origin master` — siempre como primer paso, sin excepciones.
1 bis. Revisar la suite nocturna: `gh run list --workflow=nightly-tests.yml --limit 1`.
   Si la última ejecución falló, leer los fallos (`gh run view <id> --log-failed`), corregir
   los que vengan del desarrollo del día anterior antes de empezar la tarea y llevar al
   `BACKLOG.md` los que requieran decisión. Ver «Tests».
2. Leer `SESSION.md` — resume el estado actual y el siguiente paso recomendado.
3. Leer `docs/principios-vida360.md` — referencia de decisiones arquitectónicas y restricciones de dominio.
4. Leer `docs/decisiones-tecnicas.md` — decisiones técnicas que ya se han tomado, para no contradecirlas ni replantearlas inútilmente.
5. Leer `docs/documentacion-proyecto.md` — arquitectura general, modelos y convenciones.
6. Leer `docs/design-system/SKILL.md` - desing-system, antes de cualquier tarea de UI, Blade o Filament.
7. Si la tarea afecta a un módulo específico, leer `docs/modulo-{nombre}.md` antes de escribir código.
8. Si existe un fichero en `docs/instrucciones-cli/` para la tarea, leerlo íntegramente antes de actuar.

---

## 2. Convenciones de código

### General
- Estándar PSR-12 en todo el código PHP.
- Nombres de variables, métodos y clases en inglés. Comentarios y PHPDoc en español.
- No usar `bcrypt()` manual; el cast `hashed` del modelo lo gestiona.
- No usar `factory()` en seeders de producción; usar `Model::create()` con datos explícitos.

### PHPDoc y comentarios

**Cobertura obligatoria — sin excepciones:**

- **Todas las clases** (modelos, enums, Livewire, Filament, servicios, observers, traits, seeders):
  una línea de descripción que explique la *responsabilidad* de la clase, no su nombre.
  Añadir `@property` / `@property-read` para campos de modelos y propiedades Livewire relevantes.
  Añadir `@throws` si el constructor o `booted()` lanzan excepciones.

- **Todos los métodos públicos y protegidos**:
  una línea de descripción del *para qué* (no del cómo).
  `@param TipoExacto $nombre` por cada parámetro; omitir solo si el tipo nativo PHP es inequívoco.
  `@return TipoExacto` siempre, aunque el tipo esté declarado en la firma — usar `@return void` si aplica.

- **Métodos privados**: docblock cuando el nombre solo no basta para entender el contrato o la lógica no es trivial.

**Casos que generan carencias frecuentes — reglas concretas:**

| Tipo | Requisito mínimo |
|---|---|
| Enum (clase) | Docblock explicando qué representa el conjunto de valores |
| Enum (métodos `etiqueta()`, `label()`, etc.) | `@return string` + descripción de una línea |
| Livewire `#[Computed]` | `@return TipoExacto` obligatorio aunque el tipo esté declarado |
| Scope Eloquent | `@param Builder<self> $query` + `@return Builder<self>` |
| Relación Eloquent | `@return HasMany<Modelo>`, `@return BelongsTo<Modelo, self>`, etc. con genérico completo |
| Filament Page (List/Create/Edit/View) | Al menos: `/** Página de listado/creación/edición de {entidad}. */` |
| Observer (cada método) | `@param NombreModelo $model` + `@return void` |
| Closure en `booted()` | Comentario inline o extraer a método privado con docblock propio |

**Restricciones de dominio críticas** (p. ej. `ColectivoProtegido`): documentar la razón de la restricción
en el PHPDoc de clase y en el método que la implementa.

**Comentario inline**: obligatorio en lógica no evidente, workarounds o invariantes ocultas.
No documentar lo que el nombre ya dice.

**Autocomprobación al terminar cada tarea**: revisar todos los ficheros nuevos o modificados y
confirmar que cada clase y cada método público/protegido tienen su docblock antes de cerrar.

### Modelos Eloquent
- Soft deletes en todas las entidades sensibles. No hay hard deletes en producción.
- Enums PHP solo cuando el código toma decisiones basándose en el valor (principio 3.10).
- Campos clasificatorios sin lógica de negocio → `catalogos_sistema`, nunca enum.
- Los valores de `catalogos_sistema` nunca se referencian en `match`/`if`/`switch`.
- Versionado polimórfico mediante trait `Versionable` en todas las entidades no auxiliares (principio 3.5).

### Arquitectura de módulos
- Módulos nwidart v12: código en `Modules/NombreModulo/app/`; providers en `bootstrap/providers.php`.
- Filament Resources centralizados en `app/Filament/Resources/` — decisión arquitectónica deliberada, no moverlos.
- Filament para configuración y backoffice. Livewire para operación diaria de profesionales (principio 3.12).
- Toda integración con sistemas externos mediante adaptador con mock activo por defecto (principio 3.6).

### Frontend y UI
- **Bootstrap 5.3 es el único sistema de estilos** de las superficies operativa y pública (Blade/Livewire): botones, formularios, tablas, modales, alerts, grid y spacing. Ver principio 4.18 y `docs/instrucciones-cli/2026-09-bootstrap-unico.md`, que hay que leer antes de tocar Blade o SCSS.
- Tokens: solo variables Sass de `_bootstrap-overrides.scss` y `_vida-sass-tokens.scss` (o `--bs-*`). **Nunca `var(--color-*)` ni colores literales** fuera de Filament: esas variables no existen en los bundles operativo y público.
- Sin Tailwind fuera de Filament.
- Bootstrap se instala localmente vía npm + Vite. No usar Bootstrap por CDN en la aplicación principal.
- Arquitectura en cuatro capas: (1) tokens VIDA como variables Bootstrap, (2) primitives Bootstrap estándar, (3) componentes compartidos VIDA del catálogo (`op-page`, `op-empty`, `op-avatar`…), (4) clases específicas de pantalla solo para necesidades estructurales genuinas.
- **Catálogo cerrado:** toda clase propia (no Bootstrap) está en `config/ui-catalogo.php` con su motivo. Una clase nueva va al catálogo en el mismo commit o no existe.
- **Toda tarea que toque Blade o SCSS termina con `php artisan ui:auditar` en verde** (tras `npm run build`). El CI lo ejecuta y bloquea el despliegue si falla.
- No construir nombres de clase concatenando (`"badge-{{ $tipo }}"`) ni escribir tablas de clases (`match`, arrays) en las vistas. El color de un estado o tipo sale de `App\Support\Ui\Tono` (`$estado->tono()->clasesSuave()`, o `Tonos::…()` del módulo si el valor no tiene enum). Toda clase decidida en PHP sale de una `FuenteClasesCss`; `ui:auditar` rechaza cualquier otra interpolación en un atributo de clase.
- No crear clases tipo `xxx-btn`, `xxx-input`, `xxx-modal` si Bootstrap ya lo resuelve.
- Overrides de Bootstrap centralizados en `_bootstrap-overrides.scss`.
- Evitar estilos inline estructurales en Blade. Solo se admiten para valores dinámicos inevitables.
- Filament usa su tema VIDA y componentes nativos. Los overrides sobre clases internas `.fi-*` deben estar centralizados en el tema y ser excepcionales.
- Iconos: **Heroicons** en todo el UI via `blade-ui-kit/blade-heroicons` — `<x-heroicon-o-nombre />` (outline) o `<x-heroicon-s-nombre />` (solid). Nombres dinámicos: `<x-dynamic-component :component="'heroicon-o-' . $nombre" />`. No usar Bootstrap Icons, Tabler Icons ni CDNs de iconos.

### Tests
- Base de datos de test: PostgreSQL (`vida_testing`). No usar SQLite.
- Tests escritos antes o en paralelo a la implementación, nunca después.
- Cada test describe comportamiento observable desde fuera, no detalles de implementación.
- Incluir casos que deben fallar, no solo happy path.
- Para tests críticos de seguridad o dominio: verificar también en negativo (el test debe fallar
  si se elimina la restricción que protege).
- **Durante el día, solo los ficheros de test de lo que se ha tocado**: los tests nuevos y
  los ficheros que ejercitan el código modificado (ruta del fichero o `--filter`), con
  `--parallel` si son varios ficheros. No lanzar módulos enteros ni la suite completa: un
  módulo son de 10 a 30 minutos y rompe el flujo de trabajo.
- **La suite completa corre cada madrugada** en GitHub Actions (`.github/workflows/nightly-tests.yml`,
  3:00 hora de Madrid, sobre el último commit de `master`, en paralelo). No bloquea el despliegue:
  estamos en desarrollo y se acepta que algo quede roto hasta la revisión de la mañana
  (paso 1 bis de «Antes de tocar cualquier fichero»). Se puede lanzar a mano con
  `gh workflow run nightly-tests.yml` cuando una sesión haya tocado código transversal.
- **Nunca lanzar dos ejecuciones de tests a la vez** contra la BD de test local: comparten
  `vida_testing` (y `vida_testing_test_N` en paralelo) y se rompen entre sí.
- **Nunca ejecutar `php artisan migrate:fresh`** salvo que el usuario lo pida
  explícitamente en el prompt. Este comando destruye toda la base de datos de
  desarrollo. Para verificar seeders o datos demo usar `php artisan demo:reset`.

---

## 3. Restricciones de dominio críticas

Estas restricciones nunca pueden relajarse sin decisión explícita documentada:

- **Colectivos protegidos:** no son accesibles fuera de la Unidad Orgánica responsable salvo aprobación explícita de un responsable de la UO.
- **Colectivos protegidos:** no tienen baja lógica. `ColectivoProtegido::delete()` lanza `LogicException`.
  Ver principio 3.11 y `docs/instrucciones-cli/organizacion-colectivos-tests.md`.
- **Anotaciones privadas:** solo accesibles por su autor. Sin excepciones de rol ni jerarquía.
- **Consulta al padrón para VVG:** nunca se lanza. Ver principio 4.1.
- **La IA asiste, nunca decide:** ningún componente de IA ejecuta acciones con consecuencias
  sobre personas sin validación explícita de un profesional. Ver principio 3.9.
- **El pasado es inmutable:** los cambios de estado generan nuevos registros, no sobrescriben. Ver principio 4.2.
- **Seguridad en datos personales:** los datos personales de ciudadanos se guardan cifrados en la BBDD, sin excepción.

---

## 4. Al finalizar cada sesión

1. Añadir entrada a `CHANGELOG.md` con:
   - Fecha
   - Módulo o área afectada
   - Lista de cambios realizados (migraciones, modelos, recursos, tests)
   - Decisiones de implementación tomadas que no estaban en las instrucciones
2. Si durante la sesión han surgido decisiones postergadas, integraciones pendientes,
   o ideas no implementadas, añadirlas a `BACKLOG.md` con fecha y módulo afectado.
   No esperar a que lo haga el desarrollador: si algo ha quedado pendiente, va al BACKLOG.
3. Actualizar `SESSION.md` con:
   - Qué tarea se acaba de completar (una línea)
   - En qué punto exacto está el proyecto
   - Cuál es el siguiente paso concreto recomendado
   - Cualquier contexto relevante para retomar sin fricción
   SESSION.md se actualiza al terminar cada tarea significativa (implementar un módulo,
   añadir tests, aplicar un conjunto de cambios), no al final del día de trabajo.
   Sobreescribir el contenido anterior: SESSION.md refleja solo el estado actual,
   el historial está en CHANGELOG.md.
4. `git add -A`
5. `git commit -m "<tipo>(<módulo>): <descripción concisa>"` — formato conventional commits.
   Ejemplos de tipo: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
6. `git push origin master`

---

## 5. Mapa de documentación

### Raíz del proyecto
| Documento | Cuándo leerlo |
|---|---|
| `SESSION.md` | Siempre, como segundo paso tras el pull |
| `CHANGELOG.md` | Para entender el estado actual antes de una sesión de continuación |
| `BACKLOG.md` | Cuando se quiera consultar deuda técnica o ideas pendientes |

### docs/
| Documento | Cuándo leerlo |
|---|---|
| `docs/principios-vida360.md` | Siempre, antes de cualquier sesión |
| `docs/documentacion-proyecto.md` | Siempre, antes de cualquier sesión |
| `docs/decisiones-tecnicas.md` | Antes de tomar decisiones de arquitectura o elegir herramientas |
| `docs/modulo-{nombre}.md` | Antes de tocar ese módulo |
| `docs/roles-consulta-basica-tramitacion.md` | Antes de tocar permisos, ficha del ciudadano o citas para roles administrativos |
| `docs/estado-proyecto-2026-11-10.md` | Foto del estado del proyecto a noviembre 2026 |
| `docs/instrucciones-cli/{fichero}.md` | Cuando se indique explícitamente en el prompt |

---

## 6. Estructura de instrucciones CLI

Las instrucciones detalladas de cada tarea están en `docs/instrucciones-cli/`.
Cuando el prompt diga *"ejecuta las instrucciones de X"*, leer ese fichero antes de actuar.

Ficheros disponibles:

| Fichero | Contenido |
|---|---|
| `organizacion-colectivos-tests.md` | Inmutabilidad de colectivos protegidos + 13 tests funcionales |
| `usuarios-tests.md` | 18 tests funcionales (TF-USU-16/17 pendientes de revisión Intervención) |
| `prestaciones-tests.md` | 13 tests funcionales (TF-PRE-13 parcial, pendiente Intervención) |
| `autenticacion-tests.md` | 23 tests funcionales de login, logout y onboarding (TF-AUTH-01 a TF-AUTH-23) |
| `autenticacion-implementacion.md` | Instrucciones paso a paso para implementar login, onboarding y componente avatar |
| `demo-worlds-cli.md` | Sistema de world-building para entornos de demo: infraestructura, escenarios, mundos YAML y página Filament |
| `tipo-ficha-implementacion.md` | TipoFichaResource: creador de fichas de valoración + 10 tests (TF-INT-H01 a H10) |
| `ficha-schema-snapshot.md` | Ficha con schema_snapshot + Versionable + pre-relleno: migración, modelo, inversión restricción TipoFicha, 12 tests (TF-INT-I01 a I12) |
| `2026-09-demo-ciam-aditivo.md` | Modo aditivo de mundos demo (`demo:load`, referencias `existentes`, etiquetado `demo_world_registros`), plan especializado con entrada directa (`admite_entrada_directa`) y mundo «Prueba CIAM»; 16 tests (TF-DEMO-CIAM-01 a 16) |
| `2026-09-roles-sugeridos-cargo.md` | Roles sugeridos por cargo (`cargo_roles_sugeridos`): pre-relleno del alta de usuario, aviso al cambiar de cargo, asignación de roles por `usuario_rol` con supervisión y seeder inicial; 6 tests (TF-USU-RS-01 a 06) |
| `documentos-custodia-implementacion.md` | Custodia v2 de documentos: tipos documentales, documento con versiones cifradas, vínculos n:M, tubería de entrada, ciclo de vida, retenciones, destrucción con acta, acceso y auditoría. Implementada el 2026-09-25 (pasos 1 a 9) |
| `documentos-custodia-tests.md` | 53 tests de la custodia v2 (TF-DOC-26 a 78); hechos todos |
| `instrucciones-cli-mensajes.md` | Mensajería y alertas: bandeja unificada, toasts de alertas, panel de redacción flotante, avisos de supervisor, sin adjuntos. El módulo ya existía: ver en SESSION el análisis de lo que falta |
| `2026-09-bootstrap-unico.md` | Bootstrap como único sistema de estilos (operativo y público): tokens únicos, comando `ui:auditar`, catálogo cerrado de clases. Completado el 2026-09-28 |
| `2026-09-asignacion-implementacion.md` | Asignación de centro (por domicilio con barrios y secciones censales, o por elección) y del profesional de referencia (sorteo con corrección de desvío, libre elección, quien abre), reparto por salida, actividad de los casos y bandeja del supervisor. Implementada el 2026-09-30 |
| `2026-09-asignacion-tests.md` | 34 tests de la asignación (TF-ASG-01 a 34); hechos todos |
| `2026-09-citas-implementacion.md` | Citas: tipos de cita, solicitudes y bandeja de citación, búsqueda de huecos, citación, reprogramación y cancelación, historial inmutable (`cita_eventos`), cierre implícito por apunte o registro de atención, acompañantes, canal externo (mock Cita Previa), permisos `citas.*` e interfaz. Implementada el 2026-09-30 (el paso 10, demo, pospuesto al BACKLOG) |
| `2026-09-citas-tests.md` | 43 tests de citas (TF-CIT-01 a 43) y revisión de PF-05.1, PF-05.5 y PF-06.2; hechos todos |
| `instrucciones-cli-acceso-auditoria.md` | Acceso y auditoría de lectura de expedientes: `AccesoExpediente`, policies que delegan en `CiudadanoPolicy`, buscadores y bandeja sin datos de protegidos; 12 tests (TF-ACC-01 a 12). Hecho el 2026-10-02 |
| `informe-calidad-vida360.md` | Informe cualitativo de calidad (2026-10-01): hallazgos priorizados y propuesta de prohibiciones para `CLAUDE.md`. El punto 1 está cerrado |
| `2026-10-tipo-centro-implementacion.md` | Tipo de Centro como entidad: denominaciones con vigencia, herencia tipo → centro (`ConfiguracionEfectivaCentro`), horario y cierres de calendario, catálogo por tipo, evolución y bajas; con la addenda, ámbito por dos jerarquías, centro de trabajo, protegidos y bajas. Implementada el 2026-11-13 (rama A, pasos 1 a 10) |
| `2026-10-tipo-centro-tests.md` | 60 tests de Tipo de Centro (TF-TCE-01 a 60, con la addenda); hechos todos |
| `2026-11-tipo-centro-addenda-d1.md` | Addenda D1, D2, D4 y D5 de Tipo de Centro (11 nov 2026): centro y UO 1:1, ámbito por las jerarquías orgánica y funcional, centro de trabajo y barra superior, migración «el tipo manda», personas protegidas solo para el centro, con la salvedad del rol `intervencion` de UO superior (RN-17 a 19) y bajas de profesionales (RN-20 a 23). Incorporada a los tres documentos de Tipo de Centro el 2026-11-12 |
| `2026-11-puesta-al-dia-documental.md` | Alineación de la documentación con lo implementado y con las decisiones de septiembre a noviembre. Hecho el 2026-11-10 |
| `2026_11_estabilizacion_y_privacidad.md` | Fases 0 y 1 del plan de cierre: subida de documentos, tests de Supervisión, CI de calidad y formato de documentos (bloque A; A4 aplazado a la 1.0) y huecos de privacidad: personas relacionadas protegidas, `VerFichaPage`, edición por policy y búsqueda por documento (bloque B). Hecho el 2026-11-12 |
| `2026-11-fase3-circuito-intervencion.md` | Fase 3 del plan de cierre: varios tipos de plan y plan principal (`es_principal`), centro del plan, acceso del equipo del plan y personas protegidas (RN-17 ampliada), derivación a atención especializada (`derivaciones`, bandeja del destino), evaluación de objetivos en el seguimiento con historial, avisos de plazas al TSR vigente y cambio de referencia; 43 tests (TF-CIR-01 a 43). Hecha el 2026-11-14 |
| `2026-11-fase3bis-solicitudes-expedientes.md` | Fase 3 bis: vía de acceso por prestación, solicitud de prestación con bandeja de tramitación por unidad, envío al gestor de expedientes (adaptadores manual y simulado), actualizaciones por las cuatro vías, incidencias, resolución, prestaciones de la persona (concedida/activa) y retirada de `SolicitudServicio`; 44 tests (TF-SOL-01 a 44). Hecha el 2026-11-15 |
| `2026-11-fase4-consulta-basica.md` | Fase 4: atención administrativa. Ficha por permiso para `consulta_basica` y `tramitacion` (estado de la Historia Social con referencia, sin prestaciones ni banner de protegido; F4-1: protegidas solo desde su centro), historial de citas en la ficha (§6 bis), alta y relaciones, documentos propios del día con retirada lógica (`documento.ver_carpeta`, `documento.gestionar_propios`), citas y avisos para `tramitacion`, inicio y menú; 30 tests (TF-CBA-01 a 30). Hecha el 2026-11-15 |
| `2026-11-fase5-informes.md` | Fase 5: informes desde la interfaz operativa. Historial (caja de herramientas de la Historia Social y plan), asistente en cuatro pasos (plantilla, redacción con secciones automáticas y texto libre, vista previa, firma), firma por subida del PDF firmado fuera de VIDA validada con `pdfsig` (F5-1, F5-2; AutoFirma integrada preparada e inactiva), anulación, PISO firmado escaneado con sustitución, `InformePolicy`/`PisoFirmadoPolicy` y variables auxiliares (paso 6); 28 tests (TF-INF-01 a 28) y TF-DOC-22 a 25. Hecha el 2026-11-16 |
| `2026-11-fase6-actividades.md` | Fase 6: actividades grupales con inscripción. Inscripción sesión a sesión (`inscripciones_sesion`, vías `libre` y `prescripcion`), aforo por vía con bloqueo por sesión y rechazo con aforo completo (F6-3), inscripción en el centro (F6-4), prestación por persona y actividad (F6-10), prescripción a sesión desde el plan (F6-7), baja de actividades, cancelación de sesiones con aviso y nada borrado (F6-5), inscritos con RN-17, bloque «Actividades» de la ficha e `InscripcionSesionPolicy`; 26 tests (TF-ACT-01 a 26). Hecha el 2026-11-17 |
| `2026-11-correcciones-staging-ronda1.md` | Correcciones de la ronda 1 de pruebas en staging: R1 (RN-15 en toda actuación y modo lectura sin centro), R2 (supervisión sola, ficha en lectura), R3 (desactivar desde administración) y R4 (superficie operativa única: menú por permisos, `DestinoInicial`, preferencia de página de inicio); errores propios, buscador por policies, documentos con varios roles; 31 tests (TF-STG-01 a 31). Hecha el 2026-11-23/24 |
| `2026-11-callejero-madrid.md` | Domicilio con el callejero oficial de Madrid: importación del callejero abierto, adaptador `callejero` (geocodificador local, sin terceros), autocompletado del domicilio, normalización de las direcciones existentes y asignación de centro por la sección censal oficial; 12 tests (TF-CAL-01 a 12). Hecha el 2026-11-25 (pasos 0 a 5; fuente: conjunto 200075; adaptador activo en staging; autocompletado en alta y ficha; TF-CAL-01 a 15) |
| `2026-11-encargo-profesional.md` | Encargo profesional (modalidad de derivación sin plan especializado) y superficie de `consulta_profesional`: «Mis derivaciones», ficha en lectura y buscador limitados a las personas de sus encargos, permisos `derivacion.ver_asignadas`, `derivacion.registrar_resultado` y `ciudadano.leer_por_encargo`; 40 tests (TF-ENC-01 a 40). Hecha el 2026-11-25 (bloques A a F) |
| `2026-11-encargo-actuaciones.md` | Actuaciones del profesional de encargo: área del encargo con su caja de herramientas (`AreaEncargo`: fichas, escalas, plantillas), anotaciones, fichas de valoración, escalas e informes desde el encargo vinculados por `derivacion_id`, ámbito de lectura limitado a lo propio, cierre de sus citas por la actuación vinculada; 36 tests (TF-EAC-01 a 36). Pendiente de revisión |
