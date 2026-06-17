# SESSION — VIDA 360

_Actualizado: 2026-06-17_

## Tarea completada

Modelo `CiudadanoRelacion` completo + 12 tests en verde. Rediseño topbar operativo (logo en cabecera, tipografía mejorada). Base de datos reconstruida tras vaciado accidental por Codex. Aislamiento de Codex en `vida_agents`.

## Estado actual

### Cambios aplicados en esta sesión (2026-06-17)

**UI — Topbar pantalla operativa**
- `resources/views/layouts/operativo.blade.php`: topbar a ancho completo con zona logo izquierda (196px, border-right), título de sección centrado (match sobre `request()->routeIs()`), menú usuario derecha.
- `Modules/Intervencion/resources/views/livewire/sidebar.blade.php`: eliminado `.op-sidebar-logo` (movido al topbar).
- `resources/css/app-operativo.css`:
  - `.op-topbar` ahora empieza en `left: 0`.
  - `.op-sidebar` empieza en `top: 56px`, `height: calc(100vh - 56px)`.
  - Clases nuevas: `.topbar__logo`, `.topbar__logo-img`, `.topbar__logo-text`, `.topbar__section`, `.topbar__section-app`, `.topbar__section-sep`, `.topbar__section-name`.
  - `html { font-size: 18px }` en contexto operativo para escalar rem automáticamente.
  - Tokens de contraste sobrescritos en `.op-layout`: `--color-ink-400`, `--color-ink-500` más oscuros.
  - Tamaños de texto aumentados en todos los componentes BEM del operativo.
  - Color de nav items: `ink-600` → `ink-700`.

**Módulo Ciudadania — CiudadanoRelacion**
- `Modules/Ciudadania/app/Models/CiudadanoRelacion.php`: modelo completo con hooks `booted()` (created/updated/deleted), relaciones Eloquent, scope `activas()`, y tres métodos privados de reciprocidad (`crearReciprocaSiProcede`, `sincronizarFechaFinReciproca`, `eliminarReciproca`) con guard `$sincronizandoReciproca` para evitar recursión infinita.
- `Modules/Ciudadania/database/migrations/2026_06_16_000004_create_ciudadano_relaciones_table.php`: tabla con FKs a `ciudadanos`, slug de tipo, fechas y observaciones.

**Módulo Intervencion — corrección CiudadanoPage**
- `CiudadanoPage.php`: computed `representante()` ahora usa `Ciudadano::withoutGlobalScope(AmbitoUoScope::class)` para no filtrar al ciudadano representante si pertenece a otra UO.

**Tests**
- `Modules/Intervencion/tests/Feature/Livewire/RelacionesUiTest.php`: 12 tests TF-LW-REL-01..12, todos en verde.

**Seeders**
- `database/seeders/DatabaseSeeder.php`: añadido `TipoRelacionSeeder` (paso 5 de Ciudadania) al flujo principal.

**Infraestructura**
- `vida_agents` BD creada en PostgreSQL para aislar Codex del resto.
- `~/.bashrc`: alias `codex="DB_DATABASE=vida_agents codex"`.
- Admin recreado: `admin@vida.local` / `Admin!Vida360`, roles `adm_sistema` + `adm_usuarios`.
- `php artisan db:seed` ejecutado — datos base reconstruidos.

## Siguiente paso recomendado

1. **Cargar mundo demo desde el backoffice** — hay varios mundos en `database/seeders/worlds/`. Desde el panel de administración (Demo Worlds) seleccionar el mundo deseado y pulsar «Cargar». Contraseñas de usuarios demo: `demo1234`.
2. **Modal gestión de relaciones en FichaCiudadanoPage** — la lógica PHP ya existe; falta revisar/implementar el blade del modal (crear/editar/cerrar relaciones entre ciudadanos).
3. **Widget UC con tipo de relación** — mostrar el tipo de relación del miembro de la UC respecto al titular.

## Contexto técnico para retomar

### AmbitoUoScope en Ciudadano
El scope global `AmbitoUoScope` filtra ciudadanos por UO del profesional conectado. Al buscar el ciudadano representante de un expediente hay que bypasearlo con `Ciudadano::withoutGlobalScope(AmbitoUoScope::class)` porque el representante puede pertenecer a otra UO.

### Reciprocidad en CiudadanoRelacion
- `$sincronizandoReciproca` static previene recursión al crear/sincronizar el inverso.
- `crearReciprocaSiProcede()` consulta `TipoRelacion::where('slug', ...)->tipoRecíproco()`.
- Para tipos simétricos `tipoRecíproco()` devuelve `$this`; comprueba existencia antes de crear duplicado.

### Aislamiento Codex
- Codex usa `vida_agents` DB vía alias shell. Nunca tocará `vida` ni `vida_testing`.
- Si Codex necesita tablas de Eloquent, hay que migrar `vida_agents` manualmente: `DB_DATABASE=vida_agents php artisan migrate`.
