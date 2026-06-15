# SESSION — VIDA 360

_Actualizado: 2026-06-15_

## Tarea completada
Implementación de los 7 cambios de `docs/instrucciones-cli/instrucciones-cli-mejoras-intervencion.md` en `CiudadanoPage` y sus dependencias.

## Estado actual

### Cambios aplicados

**Cambio 1 — Logotipo configurable en el sidebar**
- `Configuracion` (Organizacion): métodos estáticos `logoUrl()` y `nombreAplicacion()` que leen de `ConfiguracionService`.
- `Sidebar.php`: propiedad computada `branding()` con logo URL y nombre.
- `sidebar.blade.php`: tres niveles de fallback — logo img → nombre texto → "VIDA360" + icono.
- `ListConfiguracion` (Filament): Header Action «Identidad visual» con `FileUpload` (disk public, dir branding) y `TextInput` para `nombre_aplicacion`.
- `app-operativo.css`: clases `.op-sidebar-logo-img` y `.op-sidebar-logo-text`.

**Cambio 2 — Nombre del Plan de Intervención configurable por UO**
- Migración `2026_06_15_100001_add_plan_intervencion_nombre_to_unidades_organizativas` ejecutada.
- Campos añadidos a `unidades_organizativas`: `plan_nombre_completo`, `plan_nombre_corto`.
- `UnidadOrganizativa` model: accessors con fallback `getPlanNombreCompletoAttribute()` y `getPlanNombreCortoAttribute()`.
- `UnidadOrganizativaResource`: sección «Plan de intervención» con dos `TextInput`.
- `CiudadanoPage.php`: computed `planNombreCorto()` y `planNombreCompleto()` desde la UO de la Historia Social.
- `ciudadano-page.blade.php`: todas las ocurrencias de "PISO" reemplazadas por `$this->planNombreCorto`.

**Cambio 3 — Sin avatar de iniciales**
- No había avatar; no se ha añadido ninguno.

**Cambio 4 — Nombre de la UO en lugar del ID**
- `UnidadOrganizativa`: campo `nombre_corto` (string 40, nullable) en migración y `$fillable`.
- `UnidadOrganizativaResource`: campo `nombre_corto` en sección «Identificación».
- `CiudadanoPage.php`: computed `uoNombre()` que resuelve `nombre_corto ?? nombre ?? null`.
- `ciudadano-page.blade.php`: badge muestra nombre de UO; fallback al `UO #ID` si es null.

**Cambio 5 — Más datos del ciudadano en la cabecera**
- `CiudadanoPage.php`: computeds `ciudadanoDocumento()`, `ciudadanoTelefono()`, `ciudadanoEmail()`.
- `ciudadano-page.blade.php`: línea de contacto con separador `·` (clase `.hs-ciudadano-contacto`).
- `app-operativo.css`: clase `.hs-ciudadano-contacto` con pseudo-elemento `::before` para el separador.

**Cambio 6 — Reorganización del layout**
- `ciudadano-page.blade.php`: layout 4 cuadrantes (banda plan + grid 2×2).
- Zona sup-izq (blanco): datos ciudadano + UC; zona sup-der (blanco): toolbox.
- Zona inf-izq (paper): filtros + timeline + accesos; zona inf-der (paper): trabajo + stats.
- `app-operativo.css`: clases `.ciudadano-layout`, `.ciudadano-header-left/right`, `.ciudadano-body-left/right`.

**Cambio 7 — Estadísticas de contexto**
- `CiudadanoPage.php`: computeds `statApuntes()`, `statPrestaciones()` (null, TODO), `statUltimoContacto()`.
- `ciudadano-page.blade.php`: `.hs-stats-bar` en el pie de la zona inferior derecha.
- `app-operativo.css`: clases `.hs-stats-bar`, `.hs-stat`, `.hs-stat__val`, `.hs-stat__label`.

### TODOs documentados en código
- `CiudadanoPage::statPrestaciones()`: integrar con módulo Prestaciones cuando esté disponible.
- `ciudadano-page.blade.php`: route PISO show (Entrega 4).
- `ciudadano-page.blade.php`: menú ⋯ con acciones adicionales del expediente.
- `ciudadano-page.blade.php`: modal "Ver historial completo" de accesos.
- `ciudadano-page.blade.php`: unidades_convivencia (tabla pendiente).

### Estado de los tests TF-LW-CIU-*
Los tests de `CiudadanoPageTest` estaban ya en rojo antes de esta sesión (ciudadano_id = 9001 hardcodeado viola FK). Esta sesión no los ha roto más; sin embargo no los ha corregido (no era objetivo de estas instrucciones).

## Siguiente paso recomendado

1. **Corregir CiudadanoPageTest** — Prioridad: los tests fallan por FK. Corregir antes del próximo merge a main.
2. **Integrar `statPrestaciones`** con el módulo Prestaciones una vez disponible.
3. **Modal "Ver historial completo"** de accesos (TODO en ambas vistas).
4. **Flujo de autorización de colectivos protegidos** — AccesoProtegido (Módulo Ciudadanía) pendiente.

## Contexto técnico para retomar
- El branding (logo/nombre) se almacena en `organizacion_configuracion` como key-value via `ConfiguracionService`. Claves: `logo_path` y `nombre_aplicacion`.
- El plan de intervención ahora lee de `unidades_organizativas.plan_nombre_corto` / `plan_nombre_completo`. Valores por defecto: «Plan» / «Plan de intervención».
- La UO se muestra como `nombre_corto ?? nombre`. Rellenar `nombre_corto` en el backoffice para cada UO.
