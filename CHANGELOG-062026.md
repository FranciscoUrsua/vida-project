# CHANGELOG — VIDA 360 — Junio 2026

> Entradas de junio 2026. Para meses anteriores, ver `CHANGELOG-052026.md`.

---

## feat(intervencion): PlanPage — UI completa del Plan de Intervención — 2026-06-19

### Área afectada
`Modules/Intervencion/app/Http/Livewire/PlanPage.php`, `Modules/Intervencion/resources/views/livewire/plan-page.blade.php`, `Modules/Intervencion/app/Models/PlanFichaDiagnostico.php`, `Modules/Intervencion/database/migrations/2026_06_16_000015_create_plan_fichas_diagnostico_table.php`, `Modules/Intervencion/routes/web.php`, `vida/resources/css/app-operativo.css`, `Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php`, `Modules/Intervencion/resources/views/livewire/ciudadano-page.blade.php`, `Modules/Intervencion/tests/Feature/Livewire/PlanPageTest.php`

### Cambios

#### Componente Livewire `PlanPage`
- Página dedicada para el Plan de Intervención Social con 7 secciones: datos de la persona, diagnóstico social, objetivos, compromisos del Ayuntamiento, compromisos del ciudadano, participantes y firmas.
- Drawer lateral para seleccionar fichas de valoración del historial.
- Modal de motivo obligatorio para cambios en planes ya firmados (estado activo).
- Acciones: guardarDiagnostico, guardarSeguimiento, marcarFirmaProfesional, marcarFirmaCiudadano, guardarFechaFirma, activarPlan, generarPdf.
- `unset($this->plan)` reemplazado por `$this->plan->fresh()` — Livewire lanza `PropertyNotFoundException` al unsetear una propiedad pública regular.

#### Modelo `PlanFichaDiagnostico` y migración
- Tabla `plan_fichas_diagnostico`: pivote entre `planes_intervencion` y `fichas`, con campo `orden`.
- Relación `fichasDiagnostico()` añadida a `PlanDeIntervencion`.

#### Rutas
- `GET /intervencion/plan/crear` → `PlanPage` (nombre: `plan.crear`)
- `GET /intervencion/plan/{plan}` → `PlanPage` (nombre: `plan.show`)

#### CSS
- 250+ líneas de tokens VIDA para la UI del plan en `app-operativo.css`.

#### Enlace desde `CiudadanoPage`
- Computed `planActivo()` añadido.
- Botón "Ver Plan" / "Crear Plan" en la vista de CiudadanoPage.

#### Tests
- 13 tests TF-PP-01 a TF-PP-13 — todos en verde.

### Correcciones detectadas
- `ValoracionFactory`: usaba `fake()->numberBetween()` como `ciudadano_id` sin crear la fila en `ciudadanos`, causando FK violation en `audits`. Corregido a `Ciudadano::factory()->create()`.
- `VersionadoPlanTest` (TF-INT-B05/B06/B07): usaba campos `firma_ciudadano`/`firma_profesional` (string, eliminados). Migrado a `ciudadano_firmado`/`profesional_firmado` (booleanos).
- Vista `plan-page.blade.php`: comparaciones de `$this->plan->estado` (enum) migradas a `.value`/`.label()`.

### Decisiones de implementación
- `unset($this->plan)` no es válido en Livewire para propiedades públicas — usar `fresh()`.
- Los campos `estado` de `PlanObjetivo` y `PlanActuacionAyuntamiento` son strings, no enums; solo `PlanDeIntervencion::estado` es enum.

---

## feat(intervencion): Ficha — schema_snapshot, Versionable y pre-relleno de nueva valoración — 2026-06-18

### Área afectada
`Modules/Intervencion/app/Models/Ficha.php`, `Modules/Intervencion/app/Models/TipoFicha.php`, `Modules/Intervencion/app/Http/Livewire/RegistrarValoracionPage.php`, `Modules/Intervencion/database/migrations/2026_06_18_000001_add_schema_snapshot_and_profesional_to_fichas.php`, `Modules/Intervencion/tests/Feature/FichaVersionadoTest.php`, `Modules/Intervencion/tests/Feature/TipoFichaTest.php`, `docs/modulo-intervencion.md`, `CLAUDE.md`

### Cambios

#### Migración (Paso 1)
- `fichas` table: columnas `schema_snapshot` (jsonb, nullable) y `profesional_id` (FK a `users`, nullOnDelete).

#### Modelo `Ficha` (Paso 2)
- Añadido trait `Versionable`: snapshot automático antes de cada `updating` en tabla `versiones`.
- `schema_snapshot` y `profesional_id` añadidos a `$fillable`.
- Cast `'schema_snapshot' => 'array'`.
- Nuevo scope `historialPara(int $historiaId, int $tipoFichaId)`: ordena fichas de más reciente a más antigua.
- Nuevo método estático `prerellenarDesde(Ficha $fichaAnterior, TipoFicha $tipoFicha)`: copia valores de campos que siguen en el schema actual, descarta los retirados, deja null los nuevos.

#### Inversión restricción TipoFicha (Paso 3)
- `TipoFicha::validarSchema()`: **eliminar un campo con fichas asociadas ahora está permitido** (las fichas existentes conservan su `schema_snapshot`). Cambiar el tipo de un campo existente sigue siendo prohibido.
- PHPDoc de clase y método actualizados para reflejar la nueva política.
- Test H08 invertido: `h08_eliminar_campo_de_ficha_con_datos_asociados_esta_permitido` verifica que no lanza excepción. 10/10 tests siguen en verde.

#### RegistrarValoracionPage (Paso 4)
- `guardar()` ahora persiste `schema_snapshot` (copia del schema del TipoFicha en el momento de guardado) y `profesional_id` (usuario autenticado).

#### Backfill (Paso 5)
- 2 fichas preexistentes actualizadas con `updateQuietly(['schema_snapshot' => ..., 'profesional_id' => null])`.

#### Tests TF-INT-I01..I12 (Paso 6)
- `FichaVersionadoTest.php`: 12 tests nuevos, todos en verde (26 assertions):
  - I01: crear ficha guarda schema_snapshot igual al schema del TipoFicha.
  - I02: schema_snapshot no muta al modificar el TipoFicha posterior.
  - I03: corrección genera versión Versionable con datos anteriores.
  - I04: versión Versionable incluye schema_snapshot.
  - I05: nueva valoración crea Ficha nueva, no modifica la anterior.
  - I06: nueva valoración usa schema actual del TipoFicha.
  - I07: pre-relleno copia campos comunes de la ficha anterior.
  - I08: pre-relleno descarta campos retirados del schema actual.
  - I09: pre-relleno deja null los campos nuevos.
  - I10: cambiar tipo de campo con fichas lanza ValidationException.
  - I11: eliminar campo con fichas está permitido (inversión de H08 antigua).
  - I12: historialPara ordena de más reciente a más antigua.

#### Documentación
- `docs/modulo-intervencion.md` §4 reescrito: §4.1 Atributos, §4.2 Sistema configurable, §4.3 Frontera, §4.4 Filosofía, §4.5 tabla completa de Ficha (incluye schema_snapshot/profesional_id), §4.6 Versionado (distinción 2 actos), §4.7 Visualización histórica, §4.x Tests I01-I12 documentados.
- `CLAUDE.md` sección 6: añadida fila para `ficha-schema-snapshot.md`.

### Decisiones de implementación
- Con `schema_snapshot`, cada ficha es autocontenida: interpretable aunque el TipoFicha evolucione. Esto invierte la restricción que impedía eliminar campos del tipo.
- Dos "actos" bien diferenciados: corrección (update sobre ficha incompleta → version Versionable) vs. nueva valoración (create de ficha nueva → sin version).
- `prerellenarDesde` es método puro sin persistencia: el caller decide si usar los valores.

---

## feat(ciudadania): Relaciones entre ciudadanos + UC solo lectura en FichaCiudadanoPage — 2026-06-17

### Área afectada
`Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php`, `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php`, `Modules/Ciudadania/tests/Feature/Livewire/RelacionesCiudadanoTest.php`

### Cambios

#### FichaCiudadanoPage — PHP
- Propiedades nuevas: `modalRelacionAbierto`, `relacionId`, `relacionFechaInicio`, `relacionFechaFin`.
- Computeds nuevos: `puedeEditarRelaciones()` (roles intervencion/tramitacion), `ucVigente()` real (reemplaza stub), `ucMiembros()` enriquecido con tipo de relación.
- Métodos nuevos: `abrirModalNuevaRelacion()`, `abrirModalEditarRelacion(int)`, `cerrarModalRelacion()`.
- `guardarRelacion()`: crea o edita (solo observaciones) con `abort(403)` si no autorizado.
- `cerrarRelacion()`: idem con `abort(403)`.
- Eager loads de ciudadanos relacionados y convivientes con `withoutGlobalScope(AmbitoUoScope::class)`.

#### FichaCiudadanoPage — Blade
- Panel «Relaciones» con lista vigentes, badge de tipo, enlace a ficha, botón «Añadir» condicional, historial colapsable.
- Modal de relación (creación y edición) con buscador de ciudadano en tiempo real.
- Panel «Convivientes» solo lectura reemplaza el stub anterior.

#### Tests
- `RelacionesCiudadanoTest.php`: 24 tests TF-LW-REL-01..20 y TF-LW-UC-01..04, todos en verde.

### Decisiones de implementación
- `consulta_basica` excluido de editar relaciones: tienen implicaciones legales que requieren perfil tramitación mínimo.
- Editar una relación solo permite modificar observaciones: tipo y ciudadanos son inmutables (cerrar y crear nueva si cambia el vínculo).
- `AmbitoUoScope` bypaseado en todos los eager loads de ciudadanos relacionados/convivientes: pueden pertenecer a cualquier UO.

---

## feat(ciudadania+intervencion): CiudadanoRelacion, topbar operativo y reconstrucción BD — 2026-06-17

### Área afectada
`Modules/Ciudadania/app/Models/CiudadanoRelacion.php`, `Modules/Ciudadania/database/migrations/`, `Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php`, `Modules/Intervencion/tests/Feature/Livewire/RelacionesUiTest.php`, `resources/views/layouts/operativo.blade.php`, `Modules/Intervencion/resources/views/livewire/sidebar.blade.php`, `resources/css/app-operativo.css`, `database/seeders/DatabaseSeeder.php`

### Cambios

#### Modelo CiudadanoRelacion
- Modelo completo en `Modules/Ciudadania/app/Models/CiudadanoRelacion.php` con hooks `booted()` (created/updated/deleted), relaciones Eloquent `ciudadano()` y `ciudadanoRelacionado()` (→ `App\Models\Ciudadano`), scope `activas()`.
- Tres métodos privados de reciprocidad: `crearReciprocaSiProcede()`, `sincronizarFechaFinReciproca()`, `eliminarReciproca()`. Guard estático `$sincronizandoReciproca` para prevenir recursión infinita.
- Migración `2026_06_16_000004_create_ciudadano_relaciones_table.php`.

#### CiudadanoPage — representante con cross-UO
- `CiudadanoPage.php`: computed `representante()` usa `Ciudadano::withoutGlobalScope(AmbitoUoScope::class)` para no filtrar al representante si pertenece a una UO distinta al profesional conectado.

#### Tests TF-LW-REL-01..12
- `RelacionesUiTest.php`: 12 tests Livewire de UI de relaciones, todos en verde.

#### Topbar pantalla operativa
- `operativo.blade.php`: topbar a ancho completo — logo izquierda (196px), título de sección centrado (match sobre `routeIs()`), menú usuario derecha.
- `sidebar.blade.php`: eliminada zona logo (movida al topbar).
- `app-operativo.css`: `op-topbar` desde `left: 0`, sidebar desde `top: 56px`, clases `.topbar__*` nuevas, `html { font-size: 18px }` para escalar tipografía, tokens de contraste sobrescritos en `.op-layout`, tamaños de texto aumentados en todos los BEM del operativo, nav items en `ink-700`.

#### DatabaseSeeder
- Añadido `TipoRelacionSeeder` de Ciudadanía al flujo principal (paso 5).

#### Infraestructura / base de datos
- Creada BD `vida_agents` en PostgreSQL para aislar Codex.
- `~/.bashrc`: alias `codex="DB_DATABASE=vida_agents codex"`.
- Admin `admin@vida.local` / `Admin!Vida360` recreado con roles `adm_sistema` + `adm_usuarios`.
- `php artisan db:seed` ejecutado — datos base reconstruidos tras vaciado accidental.

### Decisiones de implementación
- La BD `vida` fue vaciada accidentalmente por Codex (CLI de OpenAI) que ejecutó `migrate:fresh` sin `--env`. Solución adoptada: alias shell que inyecta `DB_DATABASE=vida_agents` antes de cada invocación de Codex.
- `CiudadanoRelacion` usa `App\Models\Ciudadano` (no `Modules\Ciudadania\Models\Ciudadano`) porque la entidad principal reside en `app/Models/`.
- El guard `$sincronizandoReciproca` es estático para que sea compartido por todas las instancias en la misma request (requisito para que funcione en callbacks de Eloquent).

---

## feat(intervencion): TipoFichaResource — Fichas de Valoración en Filament — 2026-06-15

### Área afectada
`Modules/Intervencion/app/Models/TipoFicha.php`, `app/Filament/Resources/TipoFichaResource.php`, `app/Filament/Resources/TipoEscalaResource.php`, `Modules/Intervencion/database/seeders/`, `Modules/Intervencion/database/factories/TipoFichaFactory.php`, `Modules/Intervencion/tests/Feature/TipoFichaTest.php`, `CLAUDE.md`

### Cambios

#### Modelo TipoFicha (Paso 1)
- `const TIPOS_CAMPO`: contrato estable de tipos válidos (`texto`, `numero`, `select`, `booleano`, `fecha`, `escala`).
- `fichas(): HasMany`: relación con fichas cumplimentadas (`tipo_ficha_id`).
- `tieneFichasAsociadas()`: indica si hay instancias de datos reales → activa guardia de inmutabilidad.
- `booted()` + `saving` event: llama a `validarSchema()` antes de cada persistencia.
- `validarSchema()`: valida estructura del schema (`campos` key, atributos obligatorios, tipos válidos, opciones select ≥2, tipo_escala_id en bloques escala, ids únicos). Si hay fichas asociadas, impide eliminar o cambiar tipo de campos existentes.
- `TipoFichaFactory` actualizado al nuevo formato canónico de schema.

#### TipoFichaResource Filament (Paso 2)
- Grupo «Informes y Plantillas», sort 3, icon `heroicon-o-clipboard-document-list`.
- Tabla: columnas nombre, descripción (límite 80), num_campos (calculado), activo (toggle), updated_at.
- Filtro ternario por estado activo/inactivo.
- `DeleteAction` solo visible si `! $record->tieneFichasAsociadas()`.
- Formulario con dos pestañas: «Datos generales» y «Campos de la ficha».
- Pestaña 2: Builder con 6 bloques tipados (texto, numero, select, booleano, fecha, escala). `afterStateHydrated` convierte `{'campos': [...]}` → formato Builder. `dehydrateStateUsing` hace la conversión inversa + genera IDs automáticos desde etiqueta con Str::slug.
- Placeholder de advertencia de inmutabilidad cuando `tieneFichasAsociadas()`.
- Páginas: `ListTipoFichas`, `CreateTipoFicha`, `EditTipoFicha` con manejo de `ValidationException`.

#### TipoEscalaResource (Paso 3)
- `$navigationSort` cambiado de 5 a 4 (TipoFicha ocupa el sort 3).

#### Seeders (Paso 4)
- `IntervencionFichaSeeder`: 3 fichas con schema canónico — «Situación económica» (5 campos), «Situación de vivienda» (6 campos), «Valoración social libre» (1 campo). Usa `updateOrCreate` para idempotencia.
- `IntervencionSeeder` refactorizado: delega fichas a `IntervencionFichaSeeder` + crea tipo de valoración ASP inicial + 3 registros pivot. Los tests TF-INT-G02 siguen pasando (3 fichas, 1 valoración, 3 pivot).

#### Tests TF-INT-H01 a H10 (Paso 5)
- `TipoFichaTest.php`: 10 tests nuevos, todos en verde.
- H01: schema válido se guarda. H02: sin clave `campos` → ValidationException. H03: tipo inválido → exc. H04: select sin opciones → exc. H05: select < 2 opciones → exc. H06: escala sin tipo_escala_id → exc. H07: ids duplicados → exc. H08: eliminar campo con datos → exc. H09: cambiar tipo con datos → exc. H10: añadir campo con datos → OK.

### Decisiones de implementación
- No se añadió `'schema' => 'array'` a `$casts` porque el modelo ya tiene mutador/accessor custom que gestionan la serialización JSON (añadir el cast provocaría doble codificación).
- Los tests H08-H10 usan `Ficha::factory()->create(['tipo_ficha_id' => $tipoFicha->id])` con `ValoracionFactory` que crea una `HistoriaSocial` con `ciudadano_id` aleatorio (sin FK real a `ciudadanos`).
- CLAUDE.md actualizado con la nueva entrada en la tabla 6.

---

## Mejoras pantalla intervención — 2026-06-15

### Área afectada
`Modules/Intervencion/`, `app/Models/UnidadOrganizativa.php`, `app/Filament/Resources/UnidadOrganizativaResource.php`, `app/Filament/Resources/ConfiguracionOrganizacionResource/`, `Modules/Organizacion/app/Models/Configuracion.php`, `resources/css/app-operativo.css`

### Cambios

#### Cambio 1 — Logotipo configurable en el sidebar
- `Configuracion` model: métodos estáticos `logoUrl()` y `nombreAplicacion()` leen de `ConfiguracionService` (claves `logo_path` y `nombre_aplicacion` en `organizacion_configuracion`).
- `Sidebar.php`: computed `branding()`.
- `sidebar.blade.php`: tres niveles de fallback — logo img → nombre texto → "VIDA360" + icono por defecto.
- `ListConfiguracion` (Filament): Header Action «Identidad visual» con `FileUpload` y `TextInput`.
- `app-operativo.css`: `.op-sidebar-logo-img`, `.op-sidebar-logo-text`.

#### Cambio 2 — Nombre del Plan de Intervención configurable por UO
- Migración `2026_06_15_100001`: añade `plan_nombre_completo`, `plan_nombre_corto` a `unidades_organizativas`.
- `UnidadOrganizativa`: `$fillable`, accessors con fallback «Plan de intervención» / «Plan».
- `UnidadOrganizativaResource`: sección «Plan de intervención» con dos `TextInput`.
- `CiudadanoPage.php`: computeds `planNombreCorto()` y `planNombreCompleto()`.
- `ciudadano-page.blade.php`: literales "PISO" reemplazados por `$this->planNombreCorto`.

#### Cambio 3 — Sin avatar de iniciales
- No existía; no se ha añadido ninguno.

#### Cambio 4 — Nombre de la UO en lugar del ID
- Migración: añade `nombre_corto` (string 40, nullable) a `unidades_organizativas`.
- `UnidadOrganizativaResource`: campo `nombre_corto` en sección «Identificación».
- `CiudadanoPage.php`: computed `uoNombre()` (nombre_corto → nombre → null).
- `ciudadano-page.blade.php`: badge UO muestra nombre; fallback `UO #ID`.

#### Cambio 5 — Más datos del ciudadano en la cabecera
- `CiudadanoPage.php`: computeds `ciudadanoDocumento()`, `ciudadanoTelefono()`, `ciudadanoEmail()`.
- `ciudadano-page.blade.php`: línea de contacto con clase `.hs-ciudadano-contacto`.
- `app-operativo.css`: clase `.hs-ciudadano-contacto` con separador `·` via `::before`.

#### Cambio 6 — Reorganización del layout (4 cuadrantes)
- `ciudadano-page.blade.php`: banda plan a ancho completo + grid 2×2 debajo.
  - Sup-izq (blanco): datos ciudadano + UC. Sup-der (blanco): toolbox.
  - Inf-izq (paper): filtros + timeline + accesos. Inf-der (paper): trabajo + stats.
- `app-operativo.css`: `.ciudadano-layout`, `.ciudadano-header-left/right`, `.ciudadano-body-left/right`.

#### Cambio 7 — Estadísticas de contexto en el pie del panel derecho
- `CiudadanoPage.php`: computeds `statApuntes()`, `statPrestaciones()` (null, TODO), `statUltimoContacto()`.
- `ciudadano-page.blade.php`: `.hs-stats-bar` en el pie de la zona inferior derecha.
- `app-operativo.css`: `.hs-stats-bar`, `.hs-stat`, `.hs-stat__val`, `.hs-stat__label`.

### Decisiones de implementación
- El branding se almacena en el key-value store existente (`organizacion_configuracion`) en lugar de añadir columnas a la tabla, para respetar la arquitectura establecida. No se ha creado migración de branding.
- `statPrestaciones` devuelve `null` mientras no exista integración con el módulo Prestaciones.

---

## UI Tweaks — 2026-06-15

### Área afectada
`Modules/Intervencion/resources/views/livewire/`, `resources/views/layouts/operativo.blade.php`, `vite.config.js`

### Cambios

#### Agenda — leyenda de colores (ajuste 2026-06-09)
- Añadida leyenda compacta con los 4 tipos de cita (Entrevista, Seguimiento, Urgencia, Evento) al pie del área de contenido de `agenda-page.blade.php`. Visible en las tres vistas (día, semana, mes). Usa los mismos tokens `$estiloCita` ya definidos en el componente.

#### Pantalla del ciudadano — proporciones de columna (ajuste 2026-06-14)
- Columna izquierda de `ciudadano-page.blade.php`: de `width: 280px` fijo a `flex: 0 0 33.333%` (ratio 1/3+2/3 en lugar de ~1/4+3/4).

#### Pantalla del ciudadano — reorganización de la cabecera (ajuste 2026-06-14)
- Eliminada la segunda banda horizontal de breadcrumb ("← Mis casos · Nombre · [Abierta]").
- La columna izquierda comienza ahora con la cabecera estructurada del ciudadano: fila nav (← Mis casos + [Ficha completa]), nombre completo en texto grande (sin avatar), fila HS+UO+badge Estado HS, fecha de nacimiento con edad calculada, teléfono y domicilio.
- Avatar con iniciales eliminado de la pantalla del ciudadano.
- TODO documentados: centroActivo() (pendiente de implementación), DNI (requiere CiudadanoIdentificador::activo()), menú ⋯.

#### Altura del div principal (ajuste 2026-06-14)
- `agenda-page.blade.php` y `ciudadano-page.blade.php`: `height: 100vh` → `height: calc(100vh - 56px)` para descontar el topbar fijo de 56px y eliminar el scroll vertical innecesario.

#### Error de preload de CSS (ajuste 2026-06-14)
- `vite.config.js`: añadido `build.modulePreload.polyfill: false` para suprimir el aviso de consola "resource preloaded but not used" que aparecía al navegar con Livewire (Vite genera `<link rel="modulepreload">` para CSS por defecto, pero Livewire navega sin recargar la página).

#### Toolbox — iconos Lucide desaparecen al seleccionar herramienta (ajuste 2026-06-14)
- `operativo.blade.php`: añadido listener `livewire:updated` que relanza `lucide.createIcons()` tras cada re-render de Livewire, solucionando que los iconos `<i data-lucide="...">` queden sin convertir cuando Livewire actualiza el DOM al cambiar `$herramientaActiva`.

---

## Widget de últimos accesos al expediente — 2026-06-14

### Área afectada
`app/Queries/`, `Modules/Ciudadania/`, `Modules/Intervencion/`, `resources/css/`, `lang/es/`, `tests/Feature/Auditoria/`, `Modules/Intervencion/tests/Feature/Livewire/`

### Cambios

#### Query object compartido
- `app/Queries/AccesosExpedienteQuery.php` — nueva clase que encapsula la lógica de filtrado por visibilidad (adm_sistema, TSR responsable, supervisor UO, resto). Evita duplicación entre FichaCiudadanoPage y CiudadanoPage.

#### FichaCiudadanoPage (Ciudadania)
- Refactorizado `actividadReciente()` para usar `AccesosExpedienteQuery`
- Añadidas propiedades computadas `puedeVerAccesos` y `puedeVerTodosLosAccesos`
- El panel ahora solo se renderiza para roles `intervencion`, `supervision` y `adm_sistema`
- Blade actualizado: resaltado visual por nivel de anomalía con clases CSS BEM (`acceso-fila--propio`, `acceso-fila--sospechoso`, `acceso-fila--anomalo`) en lugar de estilos inline

#### CiudadanoPage (Intervencion)
- Añadidas propiedades computadas `accesosRecientes` (máx. 5) y `puedeVerTodosLosAccesos`
- Blade: nuevo panel `accesos-panel` al final de la columna izquierda con resaltado de anomalías

#### CSS y traducciones
- `resources/css/app-operativo.css` — nuevas clases BEM del panel de accesos
- `lang/es/auditoria.php` — traducciones de acciones de auditoría

#### Tests
- `Modules/Intervencion/tests/Feature/Livewire/AccesosExpedienteTest.php` — 11 tests (TF-AUD-INT-01 a 11): visibilidad por rol/UO, clases CSS de anomalía, límite de 5 accesos, ausencia de IP/user_agent en HTML
- `tests/Feature/Auditoria/PanelAccesosRecentesTest.php` — actualizado: supervisor ahora requiere adscripción a UO (más restrictivo, correcto); TF-AUD-17 actualizado a clase CSS `acceso-fila--propio`

### Decisiones de implementación
- `uoSubtreeIds()` devuelve `array`, no Collection, por lo que se usa `in_array()` en vez de `->contains()`
- `profesional_responsable_id` en PlanDeIntervencion es FK a `User.id` (no a `profesional_id`), adaptado respecto al pseudocódigo de las instrucciones
- El supervisor de la historia ahora requiere pertenecer al árbol de UOs (más seguro que el comportamiento anterior que permitía a cualquier `supervision` ver todos los accesos)

---

## Módulo Auditoría — implementación completa — 2026-06-14

### Área afectada
`app/`, `Modules/Intervencion/`, `Modules/Ciudadania/`, `Modules/Escalas/`, `Modules/Documentos/`, `database/migrations/`, `tests/Feature/Auditoria/`

### Cambios

#### Infraestructura
- `database/migrations/2026_06_14_100001_create_audits_table.php` — tabla `audits` con FK a users y ciudadanos, índices en `(ciudadano_id, created_at)`, `(user_id, created_at)`, `auditable_type/id` y `created_at`
- `app/Enums/AccionAuditEnum.php` — enum `ver|crear|editar|eliminar|exportar|imprimir|acceso_restringido` con métodos `etiqueta()` y `color()`
- `app/Models/Audit.php` — modelo inmutable; `update()` y `delete()` por instancia lanzan `LogicException`; `const UPDATED_AT = null`
- `app/Traits/Auditable.php` — trait con `bootAuditable()`, `audits()`, `camposAuditables()` y `getCiudadanoId()` por defecto null
- `app/Observers/AuditObserver.php` — registra automáticamente `crear/editar/eliminar`; serializa BackedEnum y Carbon; omite si no hay usuario autenticado
- `app/Services/AuditService.php` — punto único de escritura; resuelve `ciudadano_id` (explícito > modelo > null); enriquece contexto con `canal` y `ruta`; usa `withoutEvents()` para evitar recursión
- `app/Http/Middleware/AuditarAccesoCiudadano.php` — red de seguridad de segunda línea; registra acceso en rutas con `{ciudadano}`
- `app/Console/Commands/AuditPurgeCommand.php` — `audit:purge` scheduled a las 03:00; retención configurable vía `CatalogoSistema`
- `bootstrap/app.php` — alias `audit.ciudadano` registrado
- `routes/console.php` — `audit:purge` programado diariamente a las 03:00

#### Trait Auditable añadido a modelos de core ciudadano
- `app/Models/Ciudadano.php` — `getCiudadanoId()` → `$this->id`
- `app/Models/HistoriaSocial.php` — `getCiudadanoId()` → `$this->ciudadano_id`
- `Modules/Intervencion/app/Models/Apunte.php` — `getCiudadanoId()` resuelve sin AmbitoUoScope (dos saltos)
- `Modules/Intervencion/app/Models/PlanDeIntervencion.php` — ídem, sin scope
- `Modules/Intervencion/app/Models/Valoracion.php` — ídem
- `Modules/Intervencion/app/Models/Entrevista.php` — ídem
- `Modules/Escalas/app/Models/PaseEscala.php` — `getCiudadanoId()` → `$this->historia?->ciudadano_id`
- `Modules/Documentos/app/Models/Informe.php` — `getCiudadanoId()` → `$this->ciudadano_id`
- `Modules/Ciudadania/app/Models/CiudadanoIdentificador.php` — `getCiudadanoId()` → `$this->ciudadano_id`

#### Filament — AuditResource (grupo Sistema, sort 6)
- `app/Filament/Resources/AuditResource.php` — solo lectura; scope de UO para supervisores; filtro de fechas obligatorio (máx 90 días); `canCreate/Edit/Delete()` → false; `canAccess()` verifica `supervision|adm_sistema`
- `app/Filament/Resources/AuditResource/Pages/ListAudits.php` — listado estándar sin header actions
- `app/Filament/Resources/AuditResource/Pages/ViewAudit.php` — detalle con API Filament v5 (`Filament\Schemas\Schema`, `Filament\Schemas\Components\Section`)

#### Panel de accesos recientes en FichaCiudadanoPage
- `Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php` — `actividadReciente()` query la tabla `audits`; supervisores y TSR (profesional_responsable_id) ven todos; el resto solo los propios
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php` — sección "Accesos recientes al expediente" con texto natural, tratamiento visual diferenciado para accesos propios

#### Tests (29 tests, 74 assertions)
- `tests/Feature/Auditoria/AuditServiceTest.php` — 6 tests TF-AUD-01 a TF-AUD-06
- `tests/Feature/Auditoria/AuditObserverTest.php` — 5 tests TF-AUD-07 a TF-AUD-11
- `tests/Feature/Auditoria/PanelAccesosRecentesTest.php` — 6 tests TF-AUD-12 a TF-AUD-17
- `tests/Feature/Auditoria/AuditResourceTest.php` — 6 tests TF-AUD-18 a TF-AUD-23
- `tests/Feature/Auditoria/AuditPurgeCommandTest.php` — 3 tests TF-AUD-24 a TF-AUD-26
- `tests/Feature/Auditoria/AuditAccesoRestringidoTest.php` — 3 tests TF-AUD-27 a TF-AUD-29

### Decisiones de implementación
- `getCiudadanoId()` en modelos con scopes de UO (Apunte, Plan, Valoracion, Entrevista) usa `withoutGlobalScopes()` para la resolución de ciudadano, ya que es una operación interna del sistema de auditoría, no un acceso de datos de usuario.
- `AuditService::contextoBase()` usa `request()->path()` en lugar de `request()->hasSession()` porque en el entorno de test con PHPUnit, la sesión no está iniciada aunque el request sea HTTP real.
- El check de TSR en `actividadReciente()` también usa `PlanDeIntervencion::withoutGlobalScopes()` por la misma razón.
- La tabla `audits` no tiene `updated_at` (`const UPDATED_AT = null`); la purga usa el query builder directamente para evitar la `LogicException` del método `delete()` de instancia.

---

## Fix CI/CD: permisos, set -e y git reset --hard — 2026-06-12

### Área afectada
`.github/workflows/ci.yml`

### Cambios
- Añadido `set -e` al script de deploy para abortar ante cualquier error en lugar de continuar silenciosamente.
- `chown/chmod` sobre `storage/` y `bootstrap/cache/` movidos **antes** de los comandos `artisan`, para que `jupiter` pueda escribir archivos incluso si `www-data` los generó en requests previas.
- Añadido `php artisan optimize:clear` antes de `config:cache / route:cache / view:cache` para eliminar archivos corruptos o inaccesibles de deploys anteriores.
- Reemplazado `git pull origin master` por `git fetch origin && git reset --hard origin/master` para evitar fallos por cambios locales en el servidor (artefactos de npm build, etc.).

### Causa raíz del bug
`view:cache` corría como usuario `jupiter` pero los archivos compilados en `storage/framework/views/` podían ser propiedad de `www-data` (generados on-the-fly durante requests). Sin permisos de escritura, `view:cache` fallaba silenciosamente — sin `set -e`, el script reportaba éxito y el servidor seguía sirviendo compiled views desactualizadas.

---

## Navegación: mapa completo entre pantallas UI — 2026-06-11

### Módulos afectados
`Modules/Intervencion/`, `Modules/Ciudadania/`

### Referencia funcional
`docs/front/ui-intervencion.md` §8 — mapa de navegación

### Cambios

**`AgendaPage` — bifurcación de enlace por rol**
- Campo `ciudadano_id` añadido a la fixture `citasFixture()` junto a `historia_id`. Se obtiene de `HistoriaSocial::whereNotNull('ciudadano_id')` cuando el usuario tiene `profesional_id`.
- Vista `agenda-page.blade.php`: el nombre del ciudadano en cada cita bifurca según rol:
  - `historia_id` + rol `intervencion` → `intervencion.ciudadano.show` (pantalla de intervención)
  - `ciudadano_id` no nulo (cualquier otro rol) → `ciudadania.ciudadano.ficha`
  - Sin `ciudadano_id` (evento o cita sin datos) → `<div>` no clicable

**`MisCasosPage` — columna nombre separada del clic de fila**
- Columna "Ciudadano/a": `<a wire:navigate @click.stop href="ciudadania.ciudadano.ficha">` — independiente del clic de fila.
- Columna "Historia Social": `<a wire:navigate @click.stop href="intervencion.ciudadano.show">` — mantiene comportamiento previo pero con `@click.stop`.
- Clic en el resto de la fila mantiene la navegación a `intervencion.ciudadano.show`.

**`FichaCiudadanoPage` — ajustes de widgets**
- Widgets condicionales verificados: banner HS dentro de `@if($historiaSocial)`, prestaciones dentro de `@if($prestaciones->isNotEmpty())`.
- Enlace "Ir a HS" en el banner: `<a>` clicable para rol `intervencion`, `<span>` con `opacity:.4` para el resto.
- Botón "Ver ficha" en bloque UC: comentario TODO explícito — la tabla `unidades_convivencia` aún no existe.
- Widget "Permisos del rol activo" **eliminado** de la vista.

**Tests — `NavegacionTest`**
- TF-LW-NAV-16: Agenda tramitacion → enlaza a `ciudadania.ciudadano.ficha`
- TF-LW-NAV-17: Agenda intervencion → enlaza a `intervencion.ciudadano.show`
- TF-LW-NAV-18: MisCasosPage columna nombre → `ciudadania.ciudadano.ficha`
- TF-LW-NAV-19: MisCasosPage columna HS → `intervencion.ciudadano.show`
- TF-LW-NAV-20: FichaCiudadanoPage sin historia → banner HS no renderiza
- TF-LW-NAV-21: FichaCiudadanoPage intervencion → banner HS clicable
- TF-LW-NAV-22: FichaCiudadanoPage tramitacion → banner HS no clicable
- TF-LW-NAV-23: FichaCiudadanoPage sin prestaciones → widget no renderiza
- TF-LW-NAV-24: FichaCiudadanoPage → widget permisos no existe

**Decisiones de implementación**
- TF-LW-NAV-15 ya existía del ciclo anterior (enlace alta en BuscarCiudadanoPage). Los tests nuevos se numeraron TF-LW-NAV-16..24 para mantener continuidad sin renumerar; total 24 tests (1 incomplete TF-LW-NAV-03).
- Fixture `citasFixture()` depende de `Auth::user()->profesional_id` para obtener `historia_id` real. Los tests TF-LW-NAV-16/17 usan fechaAncla fija ('2026-06-12') y crean el entorno Cargo + TipoRelacionProfesional + Profesional + Ciudadano + HistoriaSocial para ejercer la bifurcación.

---

## Ficha del ciudadano — Módulo Ciudadanía — 2026-06-10

### Módulos afectados
`Modules/Ciudadania/` (ampliado), `app/Models/Ciudadano.php`, `database/migrations/`

### Cambios

**Migración**
- `create_ciudadano_prestaciones_resumen_table` — tabla de agregación de prestaciones sin historia social: `ciudadano_id FK`, `modulo_origen`, `origen_id`, `tipo`, `descripcion`, `estado`, `fecha_inicio/fin`, índices compuestos. Desacopla la ficha de los módulos origen (Centros, Teleasistencia...).

**Modelo `CiudadanoPrestacionResumen`**
- Scopes `activas()` y `recientes(int $limit = 4)` — activos primero, por fecha descendente, limitado.

**`Ciudadano` model**
- Relación `prestacionesResumen(): HasMany<CiudadanoPrestacionResumen>`.

**Componente `FichaCiudadanoPage` (Livewire)**
- Accede al ciudadano con `withoutGlobalScope(AmbitoUoScope::class)` — la ficha es accesible aunque el ciudadano no tenga historia social en ninguna UO.
- Edición de Capa 1 (`activarEdicion`, `cancelarEdicion`, `guardar`) con normalización via `NormalizadorCiudadano`.
- Modal de añadir documento (`abrirModalDocumento`, `guardarDocumento`) — cierra el documento activo anterior sin eliminarlo (principio 4.2).
- Computed: `ciudadano`, `puedeEditar`, `historiaSocial`, `puedeVerHistoria`, `documentos`, `ucVigente` (stub), `prestaciones`, `actividadReciente` (stub).
- `supervision`: acceso de solo lectura; sin botón editar, sin modal documento.

**Vista `ficha-ciudadano-page.blade.php`**
- Dos columnas: principal (identificación, documentos, UC) + lateral (banner HS, prestaciones, actividad, permisos del rol).
- Enlace "Ir a HS" como `<a>` para `intervencion`, como `<span>` no navegable para otros roles.
- Primera demanda inmutable, entrecomillada, sin lápiz de edición.
- Badges de nivel de identificación y estado de prestaciones con colores del design system.

**Rutas**
- `ciudadania.ciudadano.ficha` ahora apunta a `FichaCiudadanoPage` (ya no es stub 501).

**Tests (TF-LW-FIC-01 a TF-LW-FIC-16)**
- 16 tests, todos en verde.

**Decisiones de implementación**
- `actividadReciente()` devuelve colección vacía directamente (sin try/catch de query): en PostgreSQL una query a tabla inexistente dentro de una transacción la aborta aunque se capture la excepción PHP.
- `ciudadanoId` almacenado como `int` en el componente (no como modelo Eloquent) para evitar que la rehidratación de Livewire pase por AmbitoUoScope.

---

## Alta de ciudadano — Módulo Ciudadanía — 2026-06-09

### Módulos afectados
`Modules/Ciudadania/` (nuevo), `app/Models/Ciudadano.php`, `database/migrations/`, `composer.json`, `bootstrap/providers.php`, `phpunit.xml`, `Modules/Intervencion/resources/views/livewire/buscar-ciudadano-page.blade.php`

### Cambios

**Módulo Ciudadanía (nuevo)**
- `module.json`, `CiudadaniaServiceProvider` — estructura estándar nwidart v12.
- `FuenteIdentidadInterface` — contrato del servicio de consulta al padrón.
- `MockFuenteIdentidad` — adaptador mock activo por defecto (principio 3.6).
- `NormalizadorCiudadano` — normalización de NIF/NIE/pasaporte, nombre (Title Case + abreviaturas), teléfono (+34 prefix) y email.
- `ResultadoMatching` (readonly DTO) + `MotorMatching` — detección de duplicados con Jaro-Winkler; 3 pasos: documento exacto → fecha+apellidos → teléfono/email.
- `CiudadanoIdentificador` model con auto-hash en boot (SHA-256 del valor en minúsculas).
- `AltaCiudadano` Livewire (4 fases: busqueda → padron → formulario → confirmacion).
- Vista `alta-ciudadano.blade.php` con design system tokens.
- Rutas `ciudadania.buscar`, `ciudadania.alta`, stubs `ciudadania.ciudadano.ficha` y `ciudadania.ciudadano.nueva-cita`.
- 19 tests TF-LW-ALT-01 a TF-LW-ALT-19, todos en verde.

**Migraciones**
- `add_primera_demanda_to_ciudadanos_table` — campo `text nullable` + hashes `telefono_hash`, `email_hash`, `fecha_nacimiento_hash`.
- `nullable_fecha_nacimiento_ciudadanos` — `fecha_nacimiento` pasa a nullable (campo opcional en el alta).
- `create_ciudadano_identificadores_table` — tabla de documentos de identidad con `valor_hash` indexado.

**Ficheros modificados**
- `Ciudadano` model: `primera_demanda`, `telefono_hash`, `email_hash` en `$fillable`.
- `buscar-ciudadano-page.blade.php`: botón alta habilitado con `wire:navigate` a `ciudadania.alta`.

### Decisiones de implementación

1. `primera_demanda` en `ciudadanos` (Capa 1), no como apunte. Dato del momento del alta, no un acto profesional.
2. Motor de matching sin IA: Jaro-Winkler implementado directamente. Determinista y auditable.
3. VVG: la consulta al padrón no se lanza en ningún caso (ni para ignorar la respuesta). La condición se evalúa antes de cualquier llamada HTTP. Documentado con comentario de seguridad.
4. PSH y VVG: validación de rol en servidor además de en vista (seleccionarExcepcionPadron rechaza silenciosamente si el rol no lo permite).
5. Geocodificación transparente: AltaCiudadano pasa `origen_direccion = OrigenDireccion::Profesional`; DireccionObserver lanza la normalización automáticamente.
6. confirmarAlta() usa `withoutGlobalScope(AmbitoUoScope::class)` para guardar `primera_demanda`: el ciudadano recién creado no tiene historia social y AmbitoUoScope lo filtraría.

---

## Rediseño visual UI operativa — Design System Tokens — 2026-06-08

### Módulos afectados
`resources/css/`, `resources/views/layouts/`, `resources/views/errors/`, `Modules/Intervencion/resources/views/`, `vite.config.js`

### Cambios

- Creado `resources/css/vida/colors_and_type.css` (copia del fichero fuente de verdad de `docs/design-system/`) para importación vía Vite.
- Creado `resources/css/app-operativo.css` con tokens del design system y definiciones de las clases `.op-*` (sidebar, nav, layout).
- Añadido `resources/css/app-operativo.css` al array `input` de `vite.config.js`.
- Reescrito `resources/views/layouts/operativo.blade.php`: eliminado bloque `<style>` inline completo (con paleta morada `#534AB7`), eliminada Tabler Icons CDN, carga `app-operativo.css` vía Vite, inicializa Lucide CDN con `stroke-width: 1.75`.
- Actualizado `resources/views/errors/sin-rol.blade.php`: colores morados reemplazados por tokens, emoji `🔒` sustituido por icono Lucide `lock`, carga `app-operativo.css` y Lucide CDN.
- Reemplazados iconos Tabler (`ti ti-heart-handshake`) y Bootstrap Icons (`bi bi-*`) en `sidebar.blade.php` por equivalentes Lucide.
- Eliminados todos los colores morados hardcodeados (`#534AB7`, `#EEEDFE`, `#3C3489`, `#F8F7FF`, `#F9F8FF`, etc.) de `mis-casos-page.blade.php`, `agenda-page.blade.php`, `ciudadano-page.blade.php`, `buscar-ciudadano-page.blade.php`, `registrar-escala-page.blade.php`, `registrar-valoracion-page.blade.php`.
- Arrays PHP `$semaforo`, `$estiloCita`, `$coloresTipo`, `$badgeEstado`, `$herramientas` actualizados con tokens CSS (`var(--color-*)`).
- Añadido array `$coloresTipoSoft` en `agenda-page.blade.php` para chips del mes (evita `{{ $color }}22` con CSS vars).
- Iconos de herramientas en `ciudadano-page.blade.php` migrados de Bootstrap Icons a Lucide con `data-lucide=""`.
- `buscar-ciudadano-page.blade.php`: colores de nivel de acceso (protegido → `var(--color-protected)`, warning → `var(--color-warning)`, success → `var(--color-success)`).

### Decisiones de implementación

- Bootstrap Icons CDN se mantiene en el layout operativo temporalmente: puede existir uso residual en otros componentes no cubiertos en esta sesión.
- Los valores `#fff` y `#1D160E` se mantienen donde corresponden (blanco puro sobre primary, ink-900 como texto base) — no son colores incorrectos.
- Los chips de cita del calendario mensual usan `$coloresTipoSoft` (array separado) porque CSS vars no admiten concatenación de alfa hexadecimal (`var(--color-X)22`).

---

## Fix CSS Filament: clases Tailwind en modales/SlideOvers con Livewire — 2026-06-08

### Módulos afectados
`app/Providers/Filament/AdminPanelProvider.php`, `resources/css/filament/admin/theme.css`, `vite.config.js`

### Cambios

- `resources/css/filament/admin/theme.css`: añadidos `@import "tailwindcss"` completo (no solo `utilities`) y directivas `@source` para escanear vistas Livewire. El `@import url(...)` de Google Fonts se mueve al inicio para cumplir spec CSS.
- `vite.config.js`: añadido `resources/css/filament/admin/theme.css` al array `input` de Vite para que el fichero se compile como entry point y aparezca en el manifest.
- `app/Providers/Filament/AdminPanelProvider.php`: el `renderHook` de `HEAD_END` usa `Vite::asset('resources/css/filament/admin/theme.css')` para cargar el hash correcto del build, con fallback al fichero estático `public/css/filament-vida.css`.
- `public/build/`: reconstruido. `theme-DB8bu6J7.css` (49 kB) ahora incluye todas las utilidades Tailwind necesarias.

### Decisiones de implementación

- Filament no carga `app.css` del Vite de la aplicación. Su panel carga únicamente los CSS del vendor y el registrado vía `renderHook`. Por ello, cualquier componente Livewire embebido en modales/SlideOvers Filament necesita que sus clases Tailwind estén compiladas en `theme.css`.
- `@import "tailwindcss"` completo necesario (no `tailwindcss/utilities` solo): `gap-4`, `rounded-lg` etc. requieren `--spacing` de `@layer theme` para generarse.
- Los tokens del `:root` (sin `@layer`) tienen mayor precedencia que `@layer theme` de Tailwind, por lo que los colores y radios del design system no quedan sobreescritos.

---

## Selector de prestaciones en CentroResource — SlideOver Livewire — 2026-06-08

### Módulos afectados
`Centro / Prestaciones`

### Cambios

- `app/Filament/Resources/CentroResource.php`: eliminada la sección `Prestaciones` con `CheckboxList` del formulario. También eliminado el import de `Modules\Prestaciones\Models\Prestacion` (ya no necesario en este fichero).
- `app/Filament/Resources/CentroResource/Pages/EditCentro.php`: añadido `getHeaderActions()` con un `Action::make('gestionarPrestaciones')` que abre un SlideOver de ancho `4xl`. El modal usa `modalSubmitAction(false)` porque el guardado lo gestiona el componente Livewire.
- `resources/views/livewire/centros/selector-prestaciones-centro-modal.blade.php`: vista puente entre el modal Filament y el componente Livewire (requerida porque `modalContent()` recibe una `View`, no un componente directamente).
- `app/Livewire/Centros/SelectorPrestacionesCentro.php`: componente Livewire nuevo. Carga prestaciones activas, las agrupa por objetivo general usando etiquetas de `catalogos_sistema`, gestiona la selección y persiste en `centro_prestacion` via `sync()`.
- `resources/views/livewire/centros/selector-prestaciones-centro.blade.php`: vista Blade del componente. Layout en dos columnas (catálogo 2/3 + seleccionadas 1/3) con búsqueda por texto, filtros por segmento y modal de detalle de prestación.

### Decisiones de implementación

- El agrupamiento por objetivo general usa `CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general')` para obtener etiquetas legibles. El campo `objetivo_general` en `Prestacion` es una clave de `catalogos_sistema`, no un nombre directo.
- El filtro por segmento de población se deja como TODO: la relación `Prestacion` ↔ `SegmentoPoblacion` no existe en el modelo actual (`poblacion_destinataria` es un array JSONB de claves de catálogo, no una FK). Ver BACKLOG.
- Livewire 4 — auto-discovery activo. No se requiere registro manual del componente.

---

## Corrección DemoWorldsPage — Actions y diseño — 2026-06-05

### Módulos afectados
`app/Filament/Pages/DemoWorldsPage.php`, `resources/views/filament/pages/demo-worlds-page.blade.php`

### Cambios

- `DemoWorldsPage` implementa ahora `HasActions` y usa el trait `InteractsWithActions`
  (de `Filament\Actions\Concerns` y `Filament\Actions\Contracts`). Sin esto, Livewire
  no conocía las Actions y los botones no tenían handler.
- Blade reescrito: usa `wire:click="mountAction('reset_X')"` para disparar las Actions
  registradas. Añadido `<x-filament-actions::modals />` (punto de montaje de modales,
  sin el cual los modales de confirmación no se renderizan).
- Diseño: sustituidas clases CSS manuales por componentes Filament nativos
  (`x-filament::section`, `x-filament::badge`, `x-filament::button`).
- Botón visible usa `color="gray"` — el rojo se reserva para el modal de confirmación,
  donde la irreversibilidad se comunica con el texto, no con un botón rojo en cada tarjeta.

### Decisiones de implementación

- `getActions()` (método heredado de `InteractsWithHeaderActions` en `Page`) es el punto
  correcto para registrar Actions de página en Filament 5. Las Actions se cachean
  automáticamente al booting del componente Livewire via `cacheInteractsWithHeaderActions()`.
- El `color('danger')` se mantiene internamente en la Action para que el botón de
  confirmación del modal sea rojo, pero el botón visible en la tarjeta es `gray`.

---

## Sistema de world-building para entornos de demo — 2026-06-03

### Módulos afectados
`database/seeders/Demo/`, `app/Console/Commands/`, `app/Filament/Pages/`, `tests/Feature/Demo/`, `database/seeders/worlds/`

### Ficheros creados

**Infraestructura de world-building:**
- `database/seeders/Demo/DemoWorldLoader.php` — Cargador y validador de mundos YAML (con 12 validaciones semánticas)
- `database/seeders/Demo/DemoWorldBuilder.php` — Constructor de UOs y usuarios profesionales
- `database/seeders/Demo/DemoScenarioBuilder.php` — Constructor de escenarios de ciudadanos

**Escenarios de trayectoria:**
- `database/seeders/Demo/Scenarios/TrayectoriaActiva.php` — Historia abierta, plan activo, 2-4 seguimientos
- `database/seeders/Demo/Scenarios/TrayectoriaCerrada.php` — Historia cerrada, plan cerrado (objetivos_cumplidos), 3-6 seguimientos
- `database/seeders/Demo/Scenarios/TrayectoriaNueva.php` — Caso reciente, sin plan ni seguimientos
- `database/seeders/Demo/Scenarios/TrayectoriaUrgente.php` — Sin SIA, entrevista urgencia, plan activo, 1-3 seguimientos
- `database/seeders/Demo/Scenarios/TrayectoriaCompleja.php` — Plan ASP + plan especializado, solo UOs 'especializada'

**Verificador de invariantes:**
- `database/seeders/Demo/DemoInvariantChecker.php` — 3 invariantes de dominio (planes sin historia, planes esp sin plan_asp_id, historias cerradas con planes activos)

**Comandos Artisan:**
- `app/Console/Commands/DemoResetCommand.php` — `demo:reset --world=X` con TRUNCATE CASCADE y transacción
- `app/Console/Commands/DemoValidateCommand.php` — `demo:validate X` solo valida sin tocar BD

**Página Filament:**
- `app/Filament/Pages/DemoWorldsPage.php` — Grupo 'Sistema', solo visible en no-producción, con actions de reset con confirmación
- `resources/views/filament/pages/demo-worlds-page.blade.php` — Vista con grid de mundos y stats

**Mundos YAML:**
- `database/seeders/worlds/ci_minimo.yaml` — 2 centros, 5 profesionales, 5 ciudadanos (1 por escenario)
- `database/seeders/worlds/demo_formacion.yaml` — 2 centros ASP, 40 ciudadanos
- `database/seeders/worlds/pruebas_permisos.yaml` — 1 centro, 10 ciudadanos, 1 por cada rol
- `database/seeders/worlds/pruebas_agenda.yaml` — 3 centros ASP, 75 ciudadanos activos
- `database/seeders/worlds/demo_comercial.yaml` — 5 centros (3 ASP + 2 esp.), ~145 ciudadanos

**Tests:**
- `tests/Feature/Demo/DemoWorldLoaderTest.php` — TF-DEMO-01 a TF-DEMO-12 (7 activos, 5 markTestIncomplete)

### Decisiones de implementación

- `HistoriaSocial::withoutGlobalScopes()->create(...)` en todos los escenarios (AmbitoUoScope filtraría en contexto sin usuario autenticado)
- `Ciudadano::withoutGlobalScopes()->create(...)` por la misma razón
- Citas omitidas en todos los escenarios: requieren slot_id y maquinaria de agenda compleja (ver BACKLOG)
- `seguimientos_plan.entrevista_id` es NOT NULL → cada seguimiento crea su propia Entrevista auxiliar de tipo 'seguimiento'
- `PlanDeIntervencion` tiene guard de firma en `saving()` pero solo aplica cuando `$plan->exists` y se actualiza estado → crear directamente con estado 'activo' no dispara el guard
- Directorio `Demo/` (capital D) para cumplir PSR-4 (namespace `Database\Seeders\Demo`)
- La página Filament usa `getResetAction($worldId)` devolviendo `Action` en lugar de `getHeaderActions()` para poder generar un action por cada mundo dinámicamente

---

## Corrección de 17 tests fallidos — 2026-06-03

### Módulos afectados
`tests/Feature/Auth`, `Modules/Prestaciones/tests`, `Modules/Intervencion/tests`, `Modules/Intervencion/app/Providers`

### Bloque 1 — AutenticacionTest (7 tests corregidos)

- `setUp()` siembra `PermisosSeeder` + `RolesSeeder` y asigna rol `consulta_basica` a `$this->usuario`.
- `tf_auth_11`: `$usuarioB` recibe `assignRole('consulta_basica')` tras crearse.
- `tf_auth_21`: `$nuevo` recibe `assignRole('consulta_basica')` antes de completar onboarding.
- **Causa raíz:** sin rol, `destino()` en `LoginController` y la ruta `/` redirigen a `sin-rol` en lugar de `inicio`.

### Bloque 2 — PrestacionFilamentResourceTest (9 tests corregidos)

- Añadido `setUp()` que siembra `PermisosSeeder` + `RolesSeeder`.
- Añadido helper privado `crearAdmin()` que crea usuario con rol `adm_sistema`.
- Los 9 tests sustituyen `User::factory()->create()` por `$this->crearAdmin()`.
- **Causa raíz:** sin rol `adm_sistema`, `canAccessPanel()` devuelve false y el componente Livewire/Filament no monta → null → errores de método sobre null.

### Bloque 3 — CiudadanoPageTest (1 test corregido)

- `IntervencionServiceProvider::boot()`: añadido `Route::bind('historia', ...)` que resuelve `HistoriaSocial` usando `withoutGlobalScopes()`.
- **Causa raíz:** `SubstituteBindings` (en el middleware `web`) resuelve el modelo antes de que los middlewares de rol y policy tengan oportunidad de ejecutarse. El `AmbitoUoScope` filtraba el registro haciéndolo invisible para el usuario sin acceso → 404 en lugar de 403.

### Decisiones de implementación

- `consulta_basica` es el rol mínimo que conduce a `route('inicio')` sin disparar redirecciones a `/admin` ni a `/intervencion/agenda`. Tests de Auth usan siempre este rol.
- El binding de `historia` sin scope sigue el principio "binding encuentra, policy decide" — el modelo siempre se resuelve y la policy emite el 403 correspondiente. El middleware `role:intervencion` también puede emitir 403 antes que la policy cuando el usuario no tiene ese rol.

---

## Tooling de calidad de código — 2026-06-03

### Módulos afectados
Proyecto global (tooling, no lógica de negocio)

### Añadido

- `nunomaduro/larastan` v3.10 + `phpstan/phpstan` v2.2 instalados como dev.
- `phpstan.neon`: configuración nivel 6, paths `app/` y `Modules/`, excluye migraciones y seeders.
- `phpstan-baseline.neon`: baseline con 772 errores heredados (a reducir progresivamente).
- `rector/rector` v2.4 + `driftingly/rector-laravel` v2.5 instalados como dev.
- `rector.php`: PHP 8.3, sets CODE_QUALITY / DEAD_CODE / EARLY_RETURN / TYPE_DECLARATION / LARAVEL_120, excluye Filament y migraciones.
- `pint.json`: preset laravel con reglas adicionales (ordered_imports, no_unused_imports, phpdoc_align…).
- `.github/workflows/quality.yml`: CI que ejecuta Pint y PHPStan en cada push/PR a master.
- Scripts en `composer.json`: `analyse`, `analyse-ci`, `format`, `format-check`, `rector`, `rector-dry`.
- `.gitignore` de `vida/` actualizado: excluye `.phpstan-cache/` y `.rector-cache/`.

### Cambios de código

- Primera ejecución de Pint: ~240 ficheros reformateados (imports ordenados, espaciado binario, phpdoc, etc.).
- `CreateTipoEscala::handleRecordCreation()` y `EditTipoEscala::handleRecordUpdate()`: añadido `throw new \LogicException('unreachable')` tras `$this->halt()` para satisfacer PHPStan (error `return.missing` no ignorable).

### Decisiones de implementación

- Se instaló `driftingly/rector-laravel` adicional porque `rector/rector` no incluye los sets de Laravel.
- El paquete `nunomaduro/larastan` está marcado como abandonado upstream; el sucesor es `larastan/larastan`. Se mantiene el actual hasta que haya una sesión de actualización de dependencias planificada.
- `checkMissingIterableValueType` eliminado de `phpstan.neon`: parámetro inválido en PHPStan v2.

---

## UI Intervención — Entrega 3: Pantalla del ciudadano y herramientas — 2026-06-01

### Módulos afectados
`Modules/Intervencion`, `Modules/Escalas`, `app/Models/HistoriaSocial`, `app/Models/Scopes`

### Añadido

- `TipoApunte` enum extendido con: `Valoracion`, `Escala`, `GestionCoordinacion`, `PlanIntervencion`.
- `HistoriaSocial::ciudadano()` relationship añadida.
- Rutas `/intervencion/ciudadano/{historia}`, `/ciudadano/{historia}/valoracion` y `/ciudadano/{historia}/escala`.
- `CiudadanoPage`: pantalla principal de trabajo con timeline de HS, UC colapsable,
  7 herramientas (4 inline + 3 a pantalla completa), banda PISO activo.
- Herramientas inline: entrevista, anotación, derivación, gestión/coordinación.
- Herramientas pantalla completa: `RegistrarValoracionPage` y `RegistrarEscalaPage`.
- `calcularScoreEscala()`: suma `valor × peso` de cada ítem respondido.
- 23 nuevos tests TF-LW-CIU-01..23, todos en verde.

### Decisiones de implementación

- `crearDerivacion()` crea solo el Apunte (tipo `derivacion`); la tabla `derivaciones` no existe.
  TODO: añadir modelo y tabla Derivacion cuando esté disponible.
- `HistoriaSocial::ciudadano()` usa `withoutGlobalScope` en las consultas de CiudadanoPage
  para no romper el ámbito UO de la Historia.
- `pisoActivo` y `apuntesHS` usan `withoutGlobalScopes()` porque el acceso ya fue verificado
  por la policy en el middleware de la ruta.
- El botón "Ver PISO" está deshabilitado con TODO: Entrega 4.

---

## UI Intervención — Entrega 2: Mis casos, Buscar ciudadano y Buzón — 2026-06-01

### Módulos afectados
`Modules/Intervencion`, `Modules/Mensajes`, `app/Models/CatalogoSistema`

### Añadido

- `CatalogoSistema::valor(clave, defecto)`: método estático para leer parámetros de configuración por clave.
- Rutas `/intervencion/casos`, `/intervencion/mensajes` y `/intervencion/buscar` en `Modules/Intervencion/routes/web.php`.
- `MisCasosPage`: tabla paginada de planes activos asignados al profesional con filtros de seguimiento
  y derivación especializada. Semáforo de colores por estado. Cabecera PISO configurable.
- `BuscarCiudadanoPage`: búsqueda de ciudadanos con tres niveles de acceso (propio, otra UO, protegido).
  Modal de solicitud de acceso para colectivos protegidos. `AccesoProtegido::create()` + Alerta al supervisor.
- `BuzonPage` (Mensajes): bandeja unificada en tres pestañas (Alertas / Avisos / Mensajes).
  Reconocimiento de alertas, respuesta a hilos, contador de no leídos.
- Vistas Blade para `mis-casos-page`, `buscar-ciudadano-page` y `buzon-page`.
- 23 nuevos tests: TF-LW-CAS-01..07, TF-LW-BUS-01..10, TF-LW-BUZ-01..06. Todos en verde.

### Decisiones de implementación

- Búsqueda por nombre en ciudadanos cifrados: carga ≤ 500 registros y filtra en PHP.
  TODO: reemplazar por índice hash determinista cuando esté disponible.
- Búsqueda por `doc`/`hsu`: retorna vacío con TODO, tabla `ciudadano_identificadores` no existe.
- Registro de acceso nivel 2: usa `\Log::info()` con TODO, tabla `audits` no existe.
- `MisCasosPage` usa `DB::table()` directamente para evitar interferencia de `AmbitoUoScope` en el query.
- `reconocerAlerta()` actualiza `estado = EstadoAlerta::Reconocida` (no `reconocida_en`, que no existe).

---

## UI Intervención — Entrega 1: layout operativo y pantalla Agenda — 2026-06-01

### Módulos afectados
`Modules/Intervencion`, `resources/views/layouts/`, `bootstrap/app.php`

### Añadido

- `bootstrap/app.php`: alias de middleware `role`, `permission` y `role_or_permission`
  de Spatie laravel-permission registrados en la aplicación.
- `Modules/Intervencion/routes/web.php`: rutas protegidas `auth + role:intervencion`
  (`/intervencion` → redirect, `/intervencion/agenda` → AgendaPage).
- `IntervencionServiceProvider`: carga de rutas, vistas y registro de componentes Livewire.
- `resources/views/layouts/operativo.blade.php`: layout base con sidebar de 196px,
  área principal flexible y Livewire wired.
- `Modules/Intervencion/app/Services/IntervencionSidebarDataService`: contadores de
  alertas directas, mensajes no leídos y casos activos para los badges del sidebar.
- `Modules/Intervencion/app/Http/Livewire/Sidebar`: componente sidebar con polling 300s,
  4 ítems de navegación, badges y avatar del profesional.
- `Modules/Intervencion/app/Http/Livewire/AgendaPage`: pantalla completa con vistas día,
  semana y mes; navegación de fechas; 4 KPIs; fixture de citas para desarrollo.
- Vistas Blade para `sidebar` y `agenda-page` bajo `intervencion::livewire.*`.
- `Modules/Intervencion/tests/Feature/Livewire/AgendaPageTest`: 14 tests funcionales
  (TF-LW-AGE-01 a TF-LW-AGE-14), todos en verde.

### Decisiones de implementación

- El sidebar usa `DestinatarioType::Usuario` para contar solo alertas directas;
  las alertas por rol+UO se tratan en la bandeja completa (Entrega 2).
- Los KPIs de citas, seguimientos y mensajes devuelven 0 con comentario `// TODO:`
  hasta que los módulos Agenda y Mensajes exporten los métodos necesarios.
- La fixture de citas es determinista (basada en `crc32($fecha)`) para que los tests
  sean predecibles sin necesitar el módulo Agenda completo.
- La adscripción UO en tests se crea con `UsuarioUo::create()` porque el BelongsToMany
  de `TieneUO` no declara `withPivot()` para las columnas adicionales del pivot.

---

## 2026-06-18 — Módulo Intervención: modelo completo del Plan de Intervención

### Migraciones
- `2026_06_16_000010_create_tipos_plan_table` — tabla `tipos_plan`
- `2026_06_16_000011_expand_planes_intervencion_table` — campos `tipo_plan_id`, `diagnostico_social`, `periodicidad_seguimiento`
- `2026_06_16_000012_create_plan_content_tables` — tablas `objetivos_catalogo`, `plan_objetivos`, `plan_actuaciones_ayuntamiento`, `plan_actuaciones_ciudadano`, `plan_participantes`, `plan_cambios`
- `2026_06_16_000013_add_documento_to_firmas_plan` — FK `documento_firmado_id` en `firmas_plan`

### Modelos (nuevos)
- `TipoPlan` — catálogo de tipos de plan; los de sistema son no eliminables (LogicException en delete)
- `ObjetivoCatalogo` — objetivos generales/específicos del catálogo por tipo de plan
- `PlanObjetivo` — objetivos seleccionados en un plan concreto (con self-referential)
- `PlanActuacionAyuntamiento` — compromisos del Ayuntamiento; requiere `prestacion_id` (LogicException si null)
- `PlanActuacionCiudadano` — compromisos del ciudadano (texto libre, prestación opcional)
- `PlanParticipante` — profesionales participantes con `estaActivo()` basado en fecha_fin
- `PlanCambio` — historial de cambios con snapshot del estado previo, `$timestamps = false`

### Modelos (actualizados)
- `PlanDeIntervencion` — añadidos `tipo_plan_id`, `unidad_convivencia_id`, `diagnostico_social`, `periodicidad_seguimiento` + nuevas relaciones + `registrarCambio()`

### Filament
- `TipoPlanResource` en `app/Filament/Resources/` — grupo Catálogos, con página `GestionarObjetivos`
- Slug inmutable al editar; tipos no eliminables se excluyen de DeleteAction

### Servicios
- `PlanPdfService` — genera PDF del plan con dompdf
- Vista `Modules/Intervencion/resources/views/pdf/plan.blade.php`
- Método `generarPdfPlan(int $planId)` en `CiudadanoPage`

### Factories y seeders
- `TipoPlanSeeder` — 5 tipos del sistema, idempotente
- `TipoPlanFactory` — con estados `asp()`, `especializado()`, `noEliminable()`
- `PrestacionFactory` — nueva; añadido `HasFactory` a modelo `Prestacion`

### Tests
- `PlanContenidoTest` — 17 tests TF-PLAN-01 a TF-PLAN-17, todos en verde

### Decisiones de implementación
- `GestionarObjetivos::$view` declarado como `protected string` (no static) para respetar la herencia de `Filament\Pages\Page`
- FQCN en `PlanDeIntervencion::tipoPlan()` para evitar colisión de import con el enum `TipoPlan`
- `PlanCambio` usa `$timestamps = false` con `created_at` explícito como campo datetime

---
