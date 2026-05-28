# CHANGELOG — VIDA 360

---

## Escalas — Bugfix drag-and-drop Builder — 2026-05-28

### Módulos afectados
`app/Filament/Resources/TipoEscalaResource` (único fichero modificado)

### Cambios realizados

- Bugfix: `TipoEscalaResource` — TypeError al expandir bloque Builder tras drag-and-drop.
  Causa: estado post-drag contenía valores no-array (índices enteros). Corrección: guardias `is_array()`
  en `afterStateHydrated` y `dehydrateStateUsing`.
- `afterStateHydrated` ahora acepta `mixed $state` y re-normaliza si Filament entrega estado plano
  post-drag; si ya está en formato Builder no transforma.
- `dehydrateStateUsing` ahora acepta `mixed $state` y descarta con `is_array($block)` cualquier valor
  no-array antes de acceder a `['type']`; también descarta bloques con `$block['data']` no-array.
- 18 tests de EscalaTest siguen pasando.

---

## Escalas — UX diseñador de secciones — 2026-05-27

### Módulos afectados
`app/Filament/Resources/TipoEscalaResource` (único fichero modificado)

### Cambios realizados

- Pestaña «Estructura» refactorizada: `Repeater` exterior de tres niveles reemplazado por `Builder` nativo de Filament con un único tipo de bloque `seccion`.
- Transformación `afterStateHydrated` / `dehydrateStateUsing` para convertir entre la estructura del modelo (`['secciones' => [...]]`) y la estructura interna del `Builder` (`[['type' => 'seccion', 'data' => [...]], ...]`).
- Generación automática de IDs de sección (`sec_N`) e ítem (`item_N_M`) en `dehydrateStateUsing` si aún no tienen ID.
- Condición de inmutabilidad corregida: `->disabledOn('edit')` reemplazado por closure `fn (?TipoEscala $record) => $record !== null && $record->pases()->exists()` en `TextInput::make('texto')` del ítem y `Repeater::make('opciones')`.
- `Placeholder` de aviso añadido al inicio del bloque cuando existen pases, explicando la restricción de edición.
- Labels dinámicos: sección muestra su `titulo` en el header colapsado; ítem muestra el `texto` de la pregunta.
- Bloques colapsados por defecto (`->collapsed()`), drag-and-drop y clonable en el `Builder`.
- `Repeater` interior de ítems: `->collapsible()`, `->cloneable()`, `->itemLabel()`.
- Imports añadidos: `Filament\Forms\Components\Builder`, `Filament\Forms\Components\Placeholder`.

### Decisiones de implementación
- El campo `id` y `orden` ya no se exponen como inputs visibles al administrador; se generan/calculan automáticamente en `dehydrateStateUsing`.
- 18 tests existentes siguen pasando sin cambios.

---

## Módulo Escalas fase 1 — 2026-05-26

### Módulos afectados
`Modules/Escalas` (nuevo), `app/Models/HistoriaSocial`

### Cambios realizados

**Módulo nuevo:** `Modules/Escalas`
- Migraciones: `tipo_escalas` y `pases_escala` (FK a `tipo_escalas`, `historias_sociales`, `users`, `fichas`, `entrevistas`)
- Enum `EstadoPase` (`borrador` / `completado`)
- Modelo `TipoEscala`:
  - Casts `array` para `schema`, `rangos_interpretacion`, `contextos`
  - Validación de schema al guardar (secciones, ítems, opciones mínimas) en `booted()`
  - Validación de rangos al guardar (sin huecos ni solapamientos)
  - Inmutabilidad de `codigo` y opciones de ítems existentes con pases asociados
  - Scope `scopeAplicables`
- Modelo `PaseEscala`:
  - Inmutabilidad de `score_total`, `scores_seccion` e `interpretacion_codigo` en estado `completado`
  - Métodos `calcularScores()`, `asignarInterpretacion()`, `completar()`
- Factories: `TipoEscalaFactory` y `PaseEscalaFactory`
- `EscalaSeeder`: Barthel, Pfeiffer SPMSQ, Lawton-Brody AIVD (idempotente vía `updateOrCreate`)
- `TipoEscalaResource` en Filament: tabla con filtros, formulario en 3 pestañas (datos, estructura, rangos)
- 18 tests funcionales pasando (TF-ESC-A01…C04)

**Modelo existente modificado:** `App\Models\HistoriaSocial`
- Añadida relación `pasesEscala(): HasMany`

**Infraestructura:**
- `Modules/Escalas/app/Providers/EscalasServiceProvider` registrado en `bootstrap/providers.php`
- PSR-4 añadido en `composer.json` para `Modules\Escalas\`
- Tests añadidos a `phpunit.xml`
- `module.json` creado (requerido por `nwidart/laravel-modules`)

### Decisiones de implementación
- `fuente` definida como `text` (no `varchar(200)`) porque las citas bibliográficas completas superan ese límite
- La comparación de estado en el observer usa `getOriginal('estado') === EstadoPase::Completado` (enum casteado) en lugar del valor string raw, porque Laravel 12 aplica casts en `getOriginal()`
- `TipoEscalaResource` en grupo 'Catálogos' (el grupo 'Configuración' de las instrucciones no existe en el proyecto; se usó el grupo más próximo)
- `TipoFichaResource` referenciado en las instrucciones no existe aún; sort 90 provisional

---

## Acceso del rol `supervision` al backoffice Filament — 2026-05-25

### Módulos afectados
`app/Filament/Resources/CentroResource`, `RedResource`, `ProfesionalResource`,
`PlantillaInformeResource`, `EstiloInformeResource`, `InformeResource`, `DocumentoResource`

### Cambios realizados

- Añadida `canViewAny()` (solo lectura) para el rol `supervision` en los 7 resources indicados,
  sobreescribiendo el trait `AutorizaGestion` donde era necesario.
- **Catálogos / Centros**: supervision hereda el `modifyQueryUsing` ya existente que filtra por
  `unidad_organizativa_id` del subtree de UO del usuario.
- **Catálogos / Redes**: nuevo `modifyQueryUsing` que muestra solo las redes que contienen al
  menos un centro perteneciente al subtree de UO del usuario.
- **Organización / Profesionales**: nuevo `modifyQueryUsing` que muestra los profesionales cuya
  cuenta de acceso (`usuario`) tiene una adscripción **vigente** en alguna UO del subtree.
- **Informes y plantillas / Plantillas e Informes**: supervision hereda el `modifyQueryUsing`
  existente (filtra por `unidad_organizativa_id` de la plantilla). Para Informes, nuevo
  `modifyQueryUsing` que filtra por `plantilla.unidad_organizativa_id`.
- **Informes y plantillas / Estilos**: supervision hereda el `modifyQueryUsing` existente.
- **Informes y plantillas / Documentos**: nuevo `modifyQueryUsing` que filtra por adscripciones
  del usuario que subió el documento.
- Acción `anular` de `InformeResource` restringida explícitamente a `adm_sistema`/`adm_usuarios`
  mediante `->authorize()`.
- Los permisos de escritura (crear, editar, eliminar) permanecen exclusivos de `adm_sistema`
  y `adm_usuarios` en todos los resources.

### Decisiones de implementación

- El scoping de Profesionales va por adscripciones **vigentes** (fecha_fin nula o futura),
  no por el historial completo. Profesionales sin cuenta de sistema no son visibles para
  supervision (son irrelevantes para su función supervisora).
- El scoping de Documentos va por el uploader (historial completo, sin filtro de vigencia)
  para no perder documentos de profesionales que han cambiado de UO.
- Redes: se filtra por centros del subtree porque las redes no tienen `unidad_organizativa_id`
  propio.

---

## Seguridad en profundidad para datos sensibles de ciudadanos — 2026-05-25

### Módulos afectados
`app/Models/Scopes/`, `app/Models/HistoriaSocial`, `app/Models/Apunte`, `app/Models/Ciudadano`,
`Modules/Intervencion/Models/PlanDeIntervencion`, `Modules/Intervencion/Models/Apunte`,
`Modules/Usuarios/Policies/`, `Modules/Intervencion/Policies/`, `app/Policies/`,
`Modules/Intervencion/Services/`, `app/Services/`, `database/seeders/`,
`tests/Feature/AutorizacionDatosTest.php`

### Cambios realizados

**Fase 1 — Policies completas para modelos sensibles**

- **`Modules/Usuarios/Policies/HistoriaSocialPolicy`** — reescrita con los tres pasos estándar:
  1. permiso atómico (`historia.leer/crear/editar/eliminar`), 2. ámbito de UO subtree (`uoSubtreeIds`),
  3. colectivo protegido con AccesoProtegido. Añadidos `viewAny`, `delete`. supervision bloqueada en escritura.
- **`Modules/Usuarios/Policies/ApuntePolicy`** — reescrita con regla absoluta de privacidad (precedencia total),
  permisos atómicos (`apunte.leer/crear/editar/eliminar`) y comprobación de Historia Social para ámbito de UO.
- **`app/Policies/CiudadanoPolicy`** (nueva) — tres pasos: permiso atómico, UO vía Historia Social activa,
  colectivo protegido con AccesoProtegido. supervision bloqueada en escritura.
- **`Modules/Intervencion/Policies/PlanDeIntervencionPolicy`** (nueva) — tres pasos: permiso atómico,
  UO vía Historia Social del plan, colectivo protegido. supervision bloqueada en escritura.
- **`Modules/Usuarios/Providers/UsuariosServiceProvider`** — registra `CiudadanoPolicy`.
- **`Modules/Intervencion/Providers/IntervencionServiceProvider`** — registra `PlanDeIntervencionPolicy`.

**Fase 2 — Global Scope de ámbito de UO**

- **`app/Models/Scopes/AmbitoUoScope`** (nuevo) — scope reutilizable con tres estrategias:
  - directa (FK a UO), vía Historia Social (FK a historias_sociales), ciudadano (PK propia)
  - Sin usuario autenticado: no filtra. adm_sistema: no filtra. Desactivable con `withoutGlobalScope`.
- **`app/Models/HistoriaSocial`** — aplica `AmbitoUoScope` en `booted()`.
- **`app/Models/Apunte`** — aplica `AmbitoUoScope` en `booted()` (estrategia via Historia Social).
- **`app/Models/Ciudadano`** — aplica `AmbitoUoScope` en `booted()` (estrategia ciudadano).
- **`Modules/Intervencion/Models/PlanDeIntervencion`** — aplica `AmbitoUoScope` en `booted()`.
- **`Modules/Intervencion/Models/Apunte`** — aplica scope inline vía subquery de dos niveles
  (plan → historia → UO) más cláusula OR para privados del propio usuario.

**Fase 3 — Servicios de dominio**

- **`Modules/Intervencion/Services/HistoriaSocialService`** (nuevo) — `crear/actualizar/eliminar`.
- **`Modules/Intervencion/Services/ApunteService`** (nuevo) — `crear/actualizar/eliminar`.
- **`Modules/Intervencion/Services/PlanDeIntervencionService`** (nuevo) — `crear/actualizar/eliminar`.
- **`app/Services/CiudadanoService`** (nuevo) — `crear/actualizar/eliminar`.

**Fase 4 — Filament Resources**

- No se eliminaron métodos `canXxx` de ningún Resource porque ninguno de los Resources existentes
  gestiona datos sensibles de ciudadanos (HistoriaSocial, Apunte, Ciudadano, PlanDeIntervencion).
  Esos modelos se gestionan vía Livewire para operación diaria, no Filament.
  Los Resources de backoffice (CentroResource, UsuarioResource, etc.) mantienen sus métodos correctamente.

**Fase 5 — Tests**

- **`tests/Feature/AutorizacionDatosTest.php`** (nuevo) — 18 tests en PostgreSQL que cubren:
  GlobalScope (4 tests), Policies positivos (4 tests), Policies negativos (7 tests), Servicios (2 tests).
  Resultado: 18/18 PASS.

**Seeders**

- **`database/seeders/PermisosSeeder`** — añadidos nuevos permisos: `ciudadano.leer/eliminar`,
  `historia.crear/eliminar`, `apunte.leer/editar/eliminar`, `plan.leer/eliminar`.
  Los nuevos permisos son idempotentes (`firstOrCreate`).
- **`database/seeders/RolesSeeder`** — todos los roles actualizados con los nuevos permisos.
  `adm_sistema` y `supervision` actualizados para reflejar que supervision tiene solo lectura.

**Factories**

- **`database/factories/HistoriaSocialFactory`** (nuevo).
- **`database/factories/UnidadOrganizativaFactory`** (nuevo).
- **`database/factories/AccesoProtegidoFactory`** (nuevo).

### Decisiones de implementación

- Los permisos `historia.crear` y `historia.abrir` coexisten como alias; se mantienen ambos
  para compatibilidad con el código existente.
- El `AmbitoUoScope` no implementa un segundo filtro de colectivos protegidos al nivel de query
  porque esa lógica es delegada a la Policy (evitar duplicación y complejidad de subqueries).
- La restricción de Nivel 2 (consulta libre fuera de UO) se aplica mediante la Policy, no el scope;
  el scope solo limita el browse automático (listados).
- `Modules\Intervencion\Models\Apunte` usa un scope inline en lugar del AmbitoUoScope genérico
  porque requiere join de dos niveles (plan → historia).
- Los Resources de Filament con `canXxx` propios son todos de backoffice y se mantienen intactos.

### Recursos Filament pendientes de migrar al servicio de dominio

Los siguientes Resources acceden a modelos sensibles (indirectamente) y en el futuro deberían
usar los servicios de dominio cuando se implemente la interfaz Livewire:
- Ningún Resource Filament accede actualmente a HistoriaSocial, Apunte, Ciudadano ni PlanDeIntervencion.
- `app/Services/HistoriaSocialService` (stub existente) debe fusionarse con `Modules/Intervencion/Services/HistoriaSocialService` cuando se consolide el módulo Intervencion.

---

## Filament — scoping por UO en UsuarioResource y PlantillaInformeResource — 2026-05-25

### Módulos afectados
`Modules/Usuarios/app/Traits/TieneUO`, `app/Filament/Resources/UsuarioResource`,
`app/Filament/Resources/PlantillaInformeResource`, `app/Filament/Resources/LogAlertasResource`

### Cambios realizados

- **`TieneUO::uoSubtreeIds()`** — nuevo método helper que devuelve los IDs de todas las UOs
  del usuario (propias + descendientes). Centraliza la lógica que estaba inline en LogAlertasResource.

- **`LogAlertasResource`** — refactorizado para usar `$user->uoSubtreeIds()` (sin cambio de comportamiento).

- **`UsuarioResource`**:
  - `modifyQueryUsing`: adm_usuarios solo ve usuarios con adscripción vigente en su subtree de UO.
  - `canEdit()`: adm_usuarios solo puede editar usuarios adscritos a su subtree (protege URL directa).
  - Select de UO en el repeater de adscripciones: opciones filtradas al subtree del usuario que edita.
  - `DeleteAction` ya tenía `->authorize()` de sesión anterior (solo adm_sistema puede borrar).

- **`PlantillaInformeResource`**:
  - `modifyQueryUsing`: adm_usuarios solo ve plantillas cuya UO pertenece a su subtree.
  - `canEdit()`: adm_usuarios solo puede editar plantillas de su subtree (protege URL directa).
  - `canDelete()`: mismo criterio.
  - `DeleteAction`: `->authorize()` con comprobación de subtree.
  - Select de UO en el formulario: opciones filtradas al subtree del usuario.

### Decisiones de implementación

- adm_sistema mantiene visibilidad y gestión total en ambos recursos (sin filtro).
- adm_usuarios ve y gestiona solo el ámbito de sus UOs. Si no tiene UOs activas, la query
  devuelve cero resultados (whereRaw('1 = 0')).
- El scoping del formulario de adscripciones impide adscribir usuarios a UOs fuera del ámbito,
  pero no modifica adscripciones históricas ya existentes fuera del subtree.

---

## Filament — auditoría y cierre de brechas de seguridad en widgets y acciones de tabla — 2026-05-25

### Módulos afectados
`app/Filament/Widgets/*`, `app/Filament/Resources/UsuarioResource.php`, `app/Filament/Resources/ProfesionalResource.php`

### Cambios realizados

- **4 Dashboard widgets** — añadido `canView()` con restricción de rol:
  - `EstadoSistemaWidget`: visible solo para `adm_sistema` y `adm_usuarios`.
  - `RolesPendientesWidget`: visible solo para `adm_sistema` y `adm_usuarios`.
  - `AlertasSistemaWidget`: visible solo para `adm_sistema` y `supervision`.
  - `ActividadCatalogosWidget`: visible solo para `adm_sistema` y `adm_usuarios`.

- **`RolesPendientesWidget`** — añadido `->authorize()` a la acción "Aprobar".
  Sin este guard, un usuario `supervision` podría invocar la acción vía petición directa aunque
  el widget estuviera oculto. Ahora la autorización se verifica también en servidor.

- **`UsuarioResource` + `ProfesionalResource`** — añadido `->authorize()` al `DeleteAction` de tabla.
  `canDelete()` ya devolvía `false` para `adm_usuarios`, pero la tabla mostraba el botón y no
  bloqueaba la ejecución de la acción Livewire. La brecha cerraba solo al navegar a la página
  de edición (EditRecord::authorizeAccess). Ahora el borrado queda bloqueado tanto en UI como
  en servidor para cualquier rol distinto de `adm_sistema`.

### Decisiones de implementación

- `->authorize(Closure)` en Filament v5 Actions bloquea tanto la visibilidad del botón como
  la ejecución de la acción en servidor (InteractsWithActions::callMountedAction verifica
  `isAuthorized()` antes de ejecutar). Es la barrera correcta para acciones de tabla inline.
- Los 16 tests de `FilamentPanelAccessTest` siguen pasando sin cambios.

---

## Filament — autorización del panel — 2026-05-24

### Módulos afectados
`app/Models/User`, `app/Filament/Concerns`, `app/Filament/Resources/*`, `tests/Feature`

### Cambios realizados

- **`app/Models/User.php`** — implementa `FilamentUser`.
  Añadido `canAccessPanel(Panel $panel): bool` restringiendo el acceso al panel a los roles
  `adm_sistema`, `supervision` y `adm_usuarios`. El resto de roles (intervencion, tramitacion,
  consulta_profesional, consulta_basica) y los usuarios sin rol quedan fuera del panel.

- **`app/Filament/Concerns/AutorizaGestion.php`** — creado.
  Trait reutilizable que sobreescribe los cuatro métodos de autorización de Filament Resource
  (`canViewAny`, `canCreate`, `canEdit`, `canDelete`) restringiéndolos a `adm_sistema` y
  `adm_usuarios`.

- **27 Resources estándar** — añadido `use AutorizaGestion;`.
  CargoResource, CentroResource, ColectivoProtegidoResource, ConfiguracionHorarioLaboralResource,
  ConfiguracionOrganizacionResource, ConfiguracionRolResource, CuadranteMesResource,
  DistritoResource, DocumentoResource, EstiloInformeResource, ExcepcionProfesionalResource,
  HorarioCentroResource, InformeResource, PerfilHorarioProfesionalResource,
  PlantillaInformeResource, PrestacionResource, RedResource, SegmentoPoblacionResource,
  ServicioEmergenciaResource, TipoActividadResource, TipoEspacioResource,
  TipoRelacionProfesionalResource, TipoSlotResource, TitulacionResource,
  UnidadOrganizativaResource, UsuarioRolResource, ZonaResource.
  Los métodos `canCreate(): bool { return false; }` de DocumentoResource e InformeResource
  han sido eliminados (ya no son necesarios; el trait los sustituye con la lógica de roles).

- **`RolResource`** — autorización manual: `adm_sistema` exclusivo en los 4 métodos can*.

- **`UsuarioResource`** — autorización manual: `adm_sistema + adm_usuarios` para
  canViewAny/canCreate/canEdit; `adm_sistema` solo para canDelete.

- **`ProfesionalResource`** — mismo esquema que UsuarioResource.

- **`LogAlertasResource`** — autorización manual: `adm_sistema + supervision` para canViewAny;
  canCreate/canEdit/canDelete → false (recurso de solo lectura).

- **`tests/Feature/FilamentPanelAccessTest.php`** — creado.
  14 tests PHPUnit (atributo `#[Test]`, RefreshDatabase, PostgreSQL vida_testing).
  Grupos: A (acceso al panel), B (RolResource), C (UsuarioResource), D (LogAlertasResource).
  Todos pasan ✅.

### Decisiones de implementación

- `canAccessPanel` devuelve 403 (no redirect) para usuarios autenticados sin permiso de panel.
  El redirect a login solo se produce para usuarios no autenticados (comportamiento del
  middleware `Authenticate` de Filament). Los tests verifican `assertForbidden()` en esos casos.
- La autorización vía `can*()` en Resources es más directa que registrar Policies; no se han
  creado Policies de Filament para no añadir indirección innecesaria.

---

## Backoffice Filament — restyling con design system — 2026-05-24

### Módulos afectados
`app/Filament`, `resources/css/filament`, `vite.config.js`, `app/Providers/Filament`

### Cambios realizados

- **`resources/css/filament/admin/theme.css`** — creado.
  Aplica los tokens del design system al panel Filament mediante variables CSS:
  paleta primaria Azul Retiro (#2A5B8A con escala completa), fondos en papel cálido (#FAF7F1),
  tipografía Source Sans 3 (texto) + JetBrains Mono (códigos/IDs), sidebar blanco con borde
  cálido, topbar, tabla con cabecera arena, cards, badges, botones primarios, inputs y
  focus ring de accesibilidad obligatorio (2px #2A5B8A).

- **`vite.config.js`** — `resources/css/filament/admin/theme.css` añadido al input de Vite.

- **`app/Providers/Filament/AdminPanelProvider.php`** — actualizado.
  Añadido `->viteTheme('resources/css/filament/admin/theme.css')`. Color base cambiado de
  Amber a Blue (las variables CSS del tema toman precedencia). Widgets y pages por defecto
  de Filament eliminados; el panel usa ahora el Dashboard y widgets custom por auto-discovery.

- **`app/Filament/Pages/Dashboard.php`** — creado.
  Sustituye el dashboard por defecto de Filament. Título "Panel principal", 4 columnas,
  lista explícita de los 4 widgets custom. Icono y etiqueta de navegación propios.

- **`app/Filament/Widgets/EstadoSistemaWidget.php`** — creado.
  StatsOverview con 4 contadores: prestaciones activas, centros/redes, profesionales activos,
  roles pendientes de aprobación. Usa `Prestacion::activas()`, `Profesional::activos()`,
  `UsuarioRol::pendientes()` (scopes existentes en los modelos).

- **`app/Filament/Widgets/RolesPendientesWidget.php`** — creado.
  Tabla de `UsuarioRol::pendientes()` con acción de aprobación inline.
  Adaptado: el spec usaba `HistorialRolUsuario` (no existe); se usa el modelo correcto
  `UsuarioRol` con la misma interfaz.

- **`app/Filament/Widgets/AlertasSistemaWidget.php`** — creado.
  Tabla de `Alerta` filtrada por origen backoffice (`sistema` + `UsuarioRol::class`)
  usando `->pendientes()` (scope con enum `EstadoAlerta::Pendiente`). Límite 10 alertas.

- **`app/Filament/Widgets/ActividadCatalogosWidget.php`** — creado (stub).
  El spec requería `App\Models\Audit` que no existe en el proyecto. El widget devuelve
  tabla vacía con mensaje informativo. Marcado con `// TODO:`.

- **31 Filament Resources** — grupos de navegación reorganizados en exactamente 4 grupos:
  - `Catálogos` (10): Prestacion, Centro, Red, TipoSlot, ColectivoProtegido,
    SegmentoPoblacion, TipoEspacio, TipoActividad, Distrito, Zona.
  - `Organización` (10): UnidadOrganizativa, Profesional, Usuario, UsuarioRol,
    ConfiguracionRol, ServicioEmergencia, Rol, Cargo, Titulacion, TipoRelacionProfesional.
  - `Informes y plantillas` (4): PlantillaInforme, EstiloInforme, Informe, Documento.
  - `Sistema` (7): ConfiguracionOrganizacion, ConfiguracionHorarioLaboral, HorarioCentro,
    CuadranteMes, PerfilHorarioProfesional, ExcepcionProfesional, LogAlertas.

### Decisiones de implementación

1. **`HistorialRolUsuario` no existe:** el spec del widget usaba este nombre; el modelo real
   es `UsuarioRol`. Se adapta el widget sin crear un alias.
2. **`App\Models\Audit` no existe:** no hay paquete de auditoría instalado. `ActividadCatalogosWidget`
   queda como stub funcional. Pendiente en BACKLOG.
3. **`$navigationIcon` en Filament v5** requiere `string|\BackedEnum|null` (no `?string`).
   Corregido en `Dashboard.php` al detectar el error fatal en tiempo de arranque.
4. **`getColumns()` en Filament v5** devuelve `int|array` (no `int|string|array`). Corregido.
5. **Resources no mencionados en el spec** (Cargo, Titulacion, TipoRelacion, Distrito, Zona,
   HorarioCentro, CuadranteMes, PerfilHorario, ExcepcionProfesional, Informe, Documento,
   LogAlertas) asignados a los grupos más afines para completar la reorganización a 4 grupos.

---

## Seeders — revisión y completado — 2026-05-23

### Módulo afectado
`Modules/Centro`, `Modules/Intervencion`, `database/seeders`

### Cambios realizados

- **`Modules/Centro/database/seeders/CentroSeeder.php`** — creado desde cero.
  Seeder idempotente que siembra los catálogos base del módulo Centro y datos de ejemplo:
  - 7 tipos de espacio físico (dormitorio individual, compartido, adaptado, sala común,
    despacho profesional, sala de actividades, módulo familiar).
  - 6 tipos de actividad (taller de empleo, grupo terapéutico, deportiva, formación,
    cultural/ocio, acompañamiento social).
  - 6 segmentos de población (personas sin hogar, personas mayores, menores y familia,
    discapacidad, VVG, atención primaria general).
  - 3 centros de ejemplo: Albergue Municipal San Isidro (municipal directo, personas sin
    hogar), Albergue Municipal Vallecas (municipal concertado, personas sin hogar), Centro
    de Día Retiro (municipal directo, personas mayores).
  - 1 red: Red de Albergues Municipales, que agrupa los dos albergues.

- **`database/seeders/DatabaseSeeder.php`** — actualizado.
  Tres seeders de módulo que existían pero no estaban registrados se incorporan en el orden
  correcto de dependencias: `CentroSeeder` (paso 6), `CatalogosSistemaSeeder` y
  `PrestacionesSeeder` (paso 7), `AgendaSeeder` movido al paso 8 (requiere centros previos),
  `DocumentosSeeder` (paso 9), `IntervencionSeeder` (paso 10).

- **`Modules/Intervencion/database/seeders/IntervencionSeeder.php`** — corregido.
  Todos los `Model::create()` reemplazados por `firstOrCreate()` para garantizar idempotencia.
  La ejecución repetida ya no genera registros duplicados.

### Decisiones de implementación

- El `AgendaSeeder` dependía en silencio de que existieran centros en la BD (fallaba con
  `warn` si no había ninguno). Al colocar `CentroSeeder` antes, la dependencia queda resuelta.
- Los dos albergues se adscriben al "Departamento de Atención Primaria" (UO creada por
  `UoSeeder`) porque no existe una UO propia para recursos de acogida en la estructura mínima.
  Se puede ajustar cuando se amplíe la jerarquía organizativa.

---

## Anonimización y seudonimización — 2026-05-22

### Descripción

Implementación completa de la capa de anonimización transversal de VIDA 360.
Incluye los cuatro perfiles predefinidos, tres técnicas de transformación, validador
de k-anonimato, servicio de revelación de identidad con trazabilidad completa y
42 tests funcionales organizados en 6 grupos.

### Configuración

- **`.env.example`** — añadida clave `APP_PSEUDONYM_KEY` (independiente de `APP_KEY`).
- **`config/app.php`** — añadida entrada `pseudonym_key` que lee `APP_PSEUDONYM_KEY`.
- **`phpunit.xml`** — añadida `APP_PSEUDONYM_KEY` con valor de test fijo para determinismo.

### Excepciones añadidas (`app/Exceptions/Anonimizacion/`)

- `PerfilAnonimizacionNotFoundException` — perfil no existe.
- `PerfilAnonimizacionInactivoException` — perfil existe pero está inactivo.
- `PerfilSistemaNoEliminableException` — intento de eliminar un perfil de sistema.
- `PerfilConExtraccionesException` — intento de eliminar perfil con extracciones asociadas.
- `KAnonimatoValidacionException` — validación final de k-anonimato fallida.

### Migraciones añadidas

- `2026_05_22_100001_add_campos_anonimizacion_to_ciudadanos_table` — añade `documento_identidad` (encrypted), `es_vvg`, `es_psh`, `colectivo_extra_protegido`, `colectivo_principal`, `zona_intervencion`, `pernocta_lat`, `pernocta_lng`.
- `2026_05_22_100002_create_perfiles_anonimizacion_table` — tabla de perfiles con versionado.
- `2026_05_22_100003_create_perfil_anonimizacion_versiones_table` — snapshots inmutables de perfiles.
- `2026_05_22_100004_create_revelaciones_identidad_table` — auditoría de revelaciones de identidad.

### Modelos añadidos (`app/Models/Api/` y `app/Models/`)

- **`PerfilAnonimizacion`** — perfil con versionado automático en `updating`, scopes `activos()` y `deSistema()`, restricción de eliminación en `delete()`.
- **`PerfilAnonimizacionVersion`** — snapshot inmutable (`UPDATED_AT = null`).
- **`RevelacionIdentidad`** — registro de auditoría inmutable de revelaciones.

### Modelos actualizados

- **`App\Models\Ciudadano`** — `$fillable` y `$casts` ampliados con los nuevos campos de anonimización.

### Seeder

- **`PerfilesAnonimizacionSeeder`** — crea los 4 perfiles predefinidos del sistema con `es_sistema = true`. Añadido al `DatabaseSeeder`.

### Servicios añadidos (`app/Services/Api/`)

- **`AnonimizadorService`** — servicio principal. Acepta `Collection` de modelos Eloquent o arrays, aplica el perfil campo a campo. Sin dependencias de módulos funcionales. Técnicas implementadas: `suprimir`, `seudonimizar`, `generalizar` (anio/decada/calle_sin_numero/distrito_proxy), `mantener`.
- **`RevelacionIdentidadService`** — reversión de alias con validación de permiso `ciudadano.revelar_identidad`, búsqueda O(n) en vuelo y registro en auditoría.
- **`ValidadorKAnonimato`** — aplica la cascada de 4 pasos sobre el conjunto completo, con preprocesado de casos especiales (VVG, PSH, colectivo_extra_protegido). Lanza `KAnonimatoValidacionException` si la validación final falla.

### Factories añadidas/actualizadas

- **`PerfilAnonimizacionFactory`** — estados `supervisionInterna()`, `analiticaInterna()`, `datosAbiertos()`, `investigacionExterna()`, `inactivo()`.
- **`CiudadanoFactory`** — estados `psh()`, `vvg()`, `conDireccionNormalizada()`, `sinDireccionNormalizada()`.

### Tests añadidos — 42 tests, todos pasan ✅ (+ 4 marcados incomplete)

- **`SeudonimizacionTest`** (10 tests) — Grupo 1: alias HMAC, opacidad, determinismo, supresión de identificadores, trazabilidad y auditoría de revelación.
- **`GeneralizacionTest`** (7 tests) — Grupo 2: precisiones anio/decada/calle_sin_numero/distrito_proxy, supresión por falta de normalización, irreversibilidad.
- **`KAnonimatoTest`** (8 tests — 3 incomplete) — Grupo 3: cascada de 4 pasos, datasets fijos deterministas. Marcado `@group slow`.
- **`AnonimizadorServiceTest`** (7 tests) — Grupo 4: contrato del servicio, manejo de excepciones, transparencia, verificación de no-dependencia de módulos.
- **`CasosEspecialesDominioTest`** (5 tests) — Grupo 5: PSH, VVG, colectivo_extra_protegido, múltiples colectivos.
- **`PerfilesTest`** (9 tests — 1 incomplete) — Grupo 6: versionado automático, inmutabilidad de historial, restricciones de eliminación.

### Decisiones de implementación tomadas

1. **`apellido1`/`apellido2` en perfiles en lugar de `apellidos`:** el JSON de ejemplo del spec usa `apellidos` como campo único, pero el modelo Ciudadano tiene dos campos separados. Los perfiles predefinidos usan `apellido1` y `apellido2`. Desviación documentada intencionadamente.

2. **El alias se computa desde `$registro['id']`:** para cualquier campo seudonimizado, el alias HMAC se genera siempre a partir del `id` del registro. Si `campo = 'id'`, se renombra a `alias_ciudadano` y se elimina `id`. Para otros campos, se reemplaza el valor con el alias.

3. **`analitica_interna` no suprime `id`:** el campo `id` permanece en el resultado de Nivel 2 (no hay entrada en el perfil para él). La "irreversibilidad" se garantiza por la ausencia de `alias_ciudadano`, no por la supresión del id.

4. **Timing del criterio `--exclude-group=slow < 10s`:** no se cumple porque `RefreshDatabase` ejecuta todas las migraciones en el primer test de la suite (~14s de overhead de BD). La lógica de los tests en sí completa en ~2s. Se requeriría una BD pre-migrada o cambiar a `DatabaseTransactions` para cumplir el criterio literalmente.

5. **`investigacion_externa` usa K=10:** la decisión de K=5 para este perfil está en evaluación pendiente (docs/anonimizacion.md § 8). Se usa K=10 como valor conservador hasta que se documente la decisión formal.

### Estado de la suite

**332 tests pasan ✅** — 0 fallos — 5 incompletos (4 k-anonimato/jobs + 1 extracciones + TF-USU-31 previo).

---

## Geocodificación y modelo canónico de dirección — 2026-05-21

### Descripción

Implementación completa del sistema de geocodificación con adaptador mock activo por defecto.
Aplicado al modelo `Ciudadano` y `Centro`. El sistema es extensible a cualquier entidad futura
con dirección postal mediante el trait `TieneDireccion`.

### Enums añadidos

- **`App\Enums\OrigenDireccion`** — `profesional | padron | geocodificacion`
- **`App\Enums\TipoNumeracion`** — `numero | sin_numero | km`

### Trait añadido

- **`App\Traits\TieneDireccion`** — inyecta casts, método `direccionFormateada()` y scope
  `scopeSinNormalizar()` a los modelos con dirección postal.

### Servicio de geocodificación

- **`GeocodificadorInterface`** — contrato uniforme para todos los adaptadores.
- **`ResultadoGeocodificacion`** — DTO inmutable con todos los campos estructurados.
- **`GeocodificadorService`** — fachada que lee el proveedor activo de `configuracion_sistema`
  y delega en el adaptador correspondiente. Fallback al mock si el proveedor no está disponible.
- **`MockGeocodificador`** — adaptador de desarrollo con parser de texto libre en 5 pasos
  (tipo vía, número, nombre vía, complementos, código postal) y coordenadas aleatorias
  dentro del bbox del municipio de Madrid.

### Observer y job

- **`DireccionObserver`** — geocodifica en los eventos `creating`/`updating` cuando
  `origen_direccion = profesional`. Las direcciones del padrón no pasan por el geocoder.
  Encola `NormalizarDireccionJob` en cola `low` si el geocoder falla o el guardado es sin red.
- **`NormalizarDireccionJob`** — job de reintento asíncrono para entidades cuya normalización
  quedó pendiente.

### Provider y autoload

- **`GeocodificacionServiceProvider`** — registra el binding y conecta el observer a
  `Ciudadano` y `Centro`.
- Registrado en `bootstrap/providers.php`.
- `app/helpers.php` con `configuracion_sistema()` añadido al autoload de `composer.json`.

### Migraciones

- `2026_05_21_110001_add_direccion_canonica_to_ciudadanos_table.php` — renombra `domicilio`
  a `direccion_texto`, elimina `latitud`/`longitud` genéricas, añade 14 campos canónicos.
- `2026_05_21_110002_add_direccion_canonica_to_centros_table.php` — añade los mismos 14
  campos canónicos a la tabla `centros`.

### Modelos actualizados

- **`App\Models\Ciudadano`** — añadido `TieneDireccion`, actualizado `$fillable` con los
  campos canónicos.
- **`Modules\Centro\Models\Centro`** — añadido `TieneDireccion`, actualizado `$fillable`.

### Tests añadidos — 18 tests, todos pasan ✅

- **`DireccionObserverTest`** (5 tests) — integración observer + mock geocoder.
- **`MockGeocodificadorParserTest`** (11 tests) — parser unitario del mock (sin BD).
- Corrección de bug: flag `/u` en regex del parser para reconocer `sin número` con UTF-8.
- Corrección de bug: observer inicializa `direccion_normalizada = false` en `creating`
  cuando no geocodifica, para que el modelo en memoria refleje el default de BD.

### Decisiones de implementación

- Los campos de dirección se almacenan en la tabla de la entidad (no hay tabla centralizada).
- El proveedor activo se lee de `configuracion_sistema('geocoder.proveedor', 'mock')`.
- `NormalizarDireccionJob` en cola `low` — no bloquea el flujo del profesional.

### Estado de la suite

290 tests pasan ✅ — 0 fallos — 1 incompleto (TF-USU-31 en módulo Usuarios).

---

## Módulo Centro — Entidad Servicio (Fase 2) — 2026-05-21

### Limpieza previa

- **Eliminado** `Modules/Centro/app/Models/Ciudadano.php` — artefacto temporal que
  solo extendía `App\Models\Ciudadano` sin añadir nada. Creaba acoplamiento innecesario
  entre módulos.
- **Corregidos** 5 archivos que lo referenciaban para usar `App\Models\Ciudadano` directamente:
  `InscripcionCentro.php`, `Prescripcion.php`, `Cita.php`, `CitaFactory.php`, `Informe.php`.

### Migraciones añadidas

- `2026_05_21_100001_create_servicios_table.php` — entidad principal con FK obligatoria a UO.
- `2026_05_21_100002_create_servicio_prestacion_table.php` — pivot N:M servicio ↔ prestación.
- `2026_05_21_100003_create_responsables_servicio_table.php` — historial de responsables (fecha_inicio/fecha_fin).
- `2026_05_21_100004_create_profesional_servicio_table.php` — pivot profesionales asignados al servicio.
- `2026_05_21_100005_create_solicitudes_servicio_table.php` — solicitudes de tramitación con FK real a `planes_intervencion`.

### Modelos añadidos

- **`Modules/Centro/app/Models/Servicio.php`** — `Versionable`, `SoftDeletes`. Validación en
  `booted()` que garantiza `unidad_organizativa_id` obligatorio. Relaciones: `prestaciones()`,
  `responsables()`, `profesionales()`, `solicitudes()`. Métodos de dominio: `responsableActivo()`,
  `nombrarResponsable()`.
- **`Modules/Centro/app/Models/ResponsableServicio.php`** — Validación en `booted()` que impide
  `profesional_id = null` (no existe la figura de responsable externo). Accesor `cargo_nombre`
  que delega en `$this->servicio->cargo_nombre`.
- **`Modules/Centro/app/Models/SolicitudServicio.php`** — Hook `saving` que registra automáticamente
  `fecha_resolucion = today()` al transicionar a estado `resuelta`.

### Tests añadidos — 14 tests, todos pasan ✅

- **`ServicioTest`** (5 tests) — §11.8 del documento funcional.
- **`ResponsableServicioTest`** (4 tests) — §11.9 del documento funcional.
- **`SolicitudServicioTest`** (5 tests) — §11.10 del documento funcional.

### Decisiones de implementación

- `plan_intervencion_id` en `solicitudes_servicio` tiene FK real a `planes_intervencion`
  con `nullOnDelete()` — el módulo Intervención ya está implementado, a diferencia de
  `prescripciones` donde la FK fue declarada sin constraint.
- `profesional_servicio` usa `id` auto-increment como PK (no compuesta) para permitir
  historial de asignaciones de un mismo profesional al mismo servicio en períodos distintos.

### Estado de la suite

272 tests pasan ✅ — 0 fallos — 1 incompleto (TF-USU-31 en módulo Usuarios).

---

## Módulo Agenda — Validación solapamiento perfiles horarios (PF-02.3) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Models/PerfilHorarioProfesional.php`** — añadido `booted()` con evento `saving`:
  - Lanza `LogicException` si se intenta persistir un perfil con `activo = true` cuando ya existe otro perfil activo para la misma combinación `(usuario_id, centro_id)`.
  - Los perfiles inactivos no aplican la restricción y no bloquean la creación de un activo.
  - Se usa `when($perfil->exists, ...)` para distinguir entre `creating` (sin id) y `updating` (excluye self).

### Tests añadidos (2 tests pasan ahora ✅)

- **PF-02.3** — Segundo perfil activo mismo profesional+centro lanza `LogicException`.
- **PF-02.3 negativo** — Perfil inactivo en la misma combo no impide crear un activo.

### Estado de la suite

259 tests pasan ✅ — 0 fallos — 1 incompleto (TF-USU-31 en módulo Usuarios).

---

## Módulo Agenda — Slots, disponibilidad, eventos, itinerantes (PF-04, PF-08, PF-09) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Jobs/SlotExpirationJob.php`** — implementado `handle()`:
  - `bloqueado_urgencia` + fecha pasada → `expirado`.
  - `disponible` + fecha pasada → `no_ocupado`.
  - `reservado` + fecha pasada + sin cita activa (no-show ciudadano) → `no_ocupado`.

- **`Modules/Agenda/app/Services/DisponibilidadService.php`** — implementado `obtenerSlots()`:
  - Filtra por `usuario_id`, `centro_id`, `tipo_slot_id` y rango de fechas.
  - `incluirUrgencias = false` (defecto): solo slots `disponible`. Ideal para canal externo.
  - `incluirUrgencias = true`: incluye también `bloqueado_urgencia`. Para canal interno y supervisores.

- **`Modules/Agenda/app/Models/EventoAgenda.php`** — métodos nuevos:
  - `agregarProfesionales(array $usuarioIds)`: convoca a los profesionales al evento, bloquea sus slots `disponible` en la franja del evento (`→ bloqueado_evento`), y devuelve mapa de citas confirmadas afectadas (conflictos). Los slots `reservado` no se tocan.
  - `detectarConflictoEspacio()`: devuelve `true` si el `espacio_id` del evento está ocupado por otro evento simultáneo. El sistema avisa pero no bloquea la creación.

### Tests desbloqueados (11 tests pasan ahora ✅)

- **PF-04.2** — Urgencias no visibles en consulta externa: `DisponibilidadService` filtra `bloqueado_urgencia` por defecto.
- **PF-04.3** — Evento bloquea slots disponibles de la franja: `agregarProfesionales` marca `bloqueado_evento`.
- **PF-04.4** — Job expira slots de urgencia no consumidos: `bloqueado_urgencia` → `expirado`.
- **PF-04.5** — Job marca disponibles expirados: `disponible` → `no_ocupado`.
- **PF-08.1** — Evento sin conflicto: 2 slots en franja → `bloqueado_evento`; slot fuera de franja intacto.
- **PF-08.2** — Evento sobre cita confirmada: slot `reservado` no se bloquea; devuelve cita en conflictos.
- **PF-08.3** — Conflicto de espacio: ambos eventos creados sin excepción; `detectarConflictoEspacio()` detecta solapamiento.
- **PF-08.4** — Modo básico: evento sin espacio bloquea slots igual que modo estándar.
- **PF-09.1** — Itinerante sin disponibilidad en centro incorrecto: filtro `centro_id` devuelve vacío.
- **PF-09.2** — Excepción de un centro no afecta al otro: `ExcepcionProfesionalObserver` filtra por `centro_id`.
- (PF-04.1 ya pasaba; PF-04-PF-09 completos salvo PF-02.3 y TF-USU-31)

### Decisiones de implementación

- `agregarProfesionales()` usa `syncWithoutDetaching` para ser idempotente si se llama varias veces con el mismo profesional.
- El tipo hint de `DisponibilidadService` usa `Carbon\Carbon` (clase base) en lugar de `Illuminate\Support\Carbon` (extensión) para evitar errores de tipo en contextos donde se pasa la instancia base.
- `detectarConflictoEspacio()` usa overlap estándar: `hora_inicio < otro.hora_fin AND hora_fin > otro.hora_inicio`.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 2 incompletos (PF-02.3, TF-USU-31).

---

## Módulo Agenda — No-show del profesional (PF-07) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Services/GestionAusenciaService.php`** — servicio nuevo:
  - `procesarAusencia(int $usuarioId, int $centroId, Carbon $fecha)`: cancela las citas confirmadas del profesional en la fecha dada (motivo: 'Ausencia del profesional') y devuelve candidatos de reasignación. En modo básico devuelve slots `disponible` de otros profesionales; en modo estándar/avanzado devuelve slots `bloqueado_urgencia`.
  - `reasignar(Cita $cita, Slot $slotDestino, int $supervisorId, string $motivo)`: crea el registro `ReasignacionCita`, actualiza la cita con el nuevo profesional/slot/horario y marca el slot destino como `reservado`.

- **`Modules/Agenda/app/Observers/ExcepcionProfesionalObserver.php`** — Observer nuevo:
  - `created`: cuando `afecta_disponibilidad = true`, anula las `LineaCuadrante` del profesional en el rango de la excepción, cancela las citas confirmadas vinculadas a esos slots (motivo: 'Excepción del profesional'), y anula los slots en estado `disponible` o `bloqueado_urgencia`. Los slots `reservado` (con cita activa) no se anulan para preservar la trazabilidad.

- **`Modules/Agenda/app/Providers/AgendaServiceProvider.php`** — registra `ExcepcionProfesional::observe(ExcepcionProfesionalObserver::class)` en `boot()`.

### Tests desbloqueados (5 tests pasan ahora ✅)

- **PF-07.1** — Ausencia sobrevenida: `procesarAusencia()` cancela 2 citas confirmadas y devuelve slots urgencia de otros profesionales como candidatos.
- **PF-07.2** — Reasignación a slot de urgencia: `reasignar()` crea `ReasignacionCita`, actualiza la cita con el nuevo profesional/slot y marca el slot destino como `reservado`.
- **PF-07.3** — Ausencia sin candidatos disponibles: `procesarAusencia()` cancela las citas y devuelve colección vacía de candidatos.
- **PF-07.4** — Modo básico: `procesarAusencia()` devuelve slots `disponible` (no urgencia) como candidatos de reasignación.
- **PF-07.5** — Excepción programada con observer: `ExcepcionProfesionalObserver` anula líneas y slots afectados, cancela citas confirmadas, respeta slots `reservado` y líneas fuera del rango.

### Decisiones de implementación

- Los slots en estado `reservado` no se anulan por `ExcepcionProfesionalObserver` (sí sus citas se cancelan): preserva la trazabilidad del estado anterior de la cita.
- `reasignar()` no modifica el slot original; el profesional estuvo ausente y el slot refleja ese estado.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 12 incompletos.

---

## Módulo Agenda — No-show del ciudadano (PF-06) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Models/Cita.php`** — método añadido:
  - `noShowCiudadano()`: transiciona la cita a `no_show_ciudadano` sin tocar el slot. El slot permanece `reservado`; el `SlotExpirationJob` lo transitará a `no_ocupado` al final del día.

### Tests desbloqueados (3 tests pasan ahora ✅)

- **PF-06.1** — No-show registrado después de que la franja ha pasado: cita → `no_show_ciudadano`, slot permanece `reservado`.
- **PF-06.2** — Cancelación anticipada del ciudadano (con margen): reutiliza `cancelar()` con slot futuro → cita `cancelada`, slot `disponible`.
- **PF-06.3** — No-show en el momento (franja en curso): idéntico a PF-06.1; slot permanece `reservado` hasta que el job lo expire.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 17 incompletos (eran 20 antes de esta sesión).

---

## Módulo Agenda — Ciclo de vida de Cita — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Observers/CitaObserver.php`** — Observer nuevo:
  - `creating`: lanza `LogicException` si se intenta reservar un slot `bloqueado_urgencia` desde canal `api_externa`.
  - `created`: actualiza el slot asociado a estado `reservado` tras crear la cita.

- **`Modules/Agenda/app/Models/Cita.php`** — métodos y relación añadidos:
  - `completar()`: transiciona a `completada` y registra `completada_en = now()`.
  - `cancelar(User $canceladoPor, string $motivo)`: cancela la cita y ajusta el slot según si su hora_inicio ya ha pasado (`no_ocupado`) o no (`disponible`). Usa `Slot::findOrFail` para garantizar estado fresco.
  - `apuntes()`: `MorphMany` hacia `Apunte` (Intervención) via `apuntable`, para detectar apuntes vinculados antes de una cancelación retroactiva.

- **`Modules/Agenda/app/Providers/AgendaServiceProvider.php`** — registra `Cita::observe(CitaObserver::class)` en `boot()`.

### Tests desbloqueados (7 tests pasan ahora ✅)

- **PF-05.1** — Reserva estándar: `CitaObserver::created` transiciona el slot a `reservado`.
- **PF-05.2** — Bloqueo canal externo en urgencia: `CitaObserver::creating` lanza `LogicException`.
- **PF-05.3** — Completar cita: `completar()` → `completada` + `completada_en`.
- **PF-05.4** — Cancelación anticipada por profesional: slot futuro → `disponible`.
- **PF-05.5** — Cancelación retroactiva por supervisor: slot pasado → `no_ocupado`, `cancelado_por_id` registrado.
- **PF-05.6** — Cancelación retroactiva bloqueada (apuntes previos): `cancelar()` lanza `LogicException` si existen apuntes vinculados.
- **PF-05.7** — No-show ciudadano en cita completada: verificación de que `no_show_ciudadano` no se puede aplicar a cita ya completada.
- **PF-05.8** — Apuntes vinculados: relación polimórfica `Cita::apuntes()` carga apuntes de Intervención.

### Decisiones de implementación

- `cancelar()` resuelve el estado del slot consultando DB (`findOrFail`) en lugar de `$this->slot` (relation cache podría estar desactualizado).
- La relación `apuntes()` es `morphMany` para ser coherente con el resto del sistema de anotaciones de Intervención.

---

## Módulo Agenda — CuadranteGeneratorService (PF-03, PF-10) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Services/CuadranteGeneratorService.php`** — servicio nuevo:
  - `generarBorrador(CuadranteMes $cuadrante)`: crea `LineaCuadrante` por profesional por día laborable del mes. Incorpora excepciones vigentes como líneas `anulada = true` en el momento de la generación.
  - `generarYPublicarAutomaticamente(Centro $centro, int $anyo, int $mes)`: crea `CuadranteMes` con `generado_automaticamente = true`, llama a `generarBorrador`, publica y materializa slots.

### Tests desbloqueados (4 tests pasan ahora ✅)

- **PF-03.1** — Generación de borrador: las líneas se crean correctamente para los días laborables de cada perfil.
- **PF-03.4** — Publicación automática: el cuadrante se publica y los slots se materializan.
- **PF-03.5** — Excepción antes de generar: líneas afectadas se crean como `anulada = true`.
- **PF-10.1** — Integridad de casos límite: generación en mes con festivos y excepciones simultáneas.

### Decisiones de implementación

- Las claves de `horario_habitual` JSON se comparan como strings (cast `'array'` de PHP convierte las claves de objeto a string) — se usa `(string)$dia->isoWeekday()` para el lookup.
- `generarBorrador` usa `LineaCuadrante::insert()` (bulk) para evitar disparar observers en cada línea.
