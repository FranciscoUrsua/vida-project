# Instrucciones CLI — UI Supervisión (Módulo Supervision)

> **Referencia funcional:** `docs/front/ui-supervision.md`
> **Tests funcionales:** `docs/instrucciones-cli/supervision-ui-tests.md` (este documento incluye ambos)
> **Módulo Laravel:** `Modules/Supervision`
> **Rol protegido:** `supervision`

---

## 0. Antes de empezar: obligaciones de arquitectura frontend

**Lee esto antes de escribir una sola línea de Blade o CSS.**

La superficie operativa de VIDA usa Bootstrap 5.3 como capa de primitives, con tokens VIDA aplicados como variables Bootstrap. La arquitectura tiene cuatro capas estrictas:

1. **Tokens VIDA** — variables CSS definidas en `resources/css/vida/` y expresadas como variables Bootstrap en `_bootstrap-variables.scss`.
2. **Bootstrap estándar** — usar directamente `btn`, `form-control`, `form-select`, `table`, `modal`, `alert`, `badge`, `d-flex`, `gap-*`, `mt-*`, etc. **Sin reinventarlos.**
3. **Componentes `op-*`** — piezas de producto reutilizables que Bootstrap no modela: `op-page`, `op-section`, `op-toolbar`, `op-chip`, `op-empty`, `op-filter-row`. Reutilizar los existentes; si falta uno genuinamente nuevo, añadirlo en `_op-components.scss`.
4. **Clases específicas de pantalla** — solo para necesidades estructurales que no cubre ninguna capa anterior, y siempre en el fichero SCSS de la pantalla, **nunca en `app-operativo.css`**.

**Reglas de oro — violaciones que generan deuda técnica:**

- **No escribir en `app-operativo.css`.** Este fichero es deuda heredada en proceso de remediación. Todo CSS nuevo va en `_op-components.scss` (si es reutilizable) o en el SCSS propio de la pantalla.
- **No crear clases `xxx-btn`, `xxx-input`, `xxx-modal`, `xxx-table`** si Bootstrap ya lo resuelve. Usar directamente las clases Bootstrap con modificadores de tokens VIDA.
- **No usar estilos inline estructurales** en Blade. Solo se admiten para valores dinámicos inevitables (por ejemplo, `style="width: {{ $pct }}%"` en una barra de progreso calculada).
- **No cargar Bootstrap, iconos ni fuentes por CDN.** Todo está en el build local vía Vite.
- **Iconos: solo Heroicons** via `blade-ui-kit/blade-heroicons`. Sintaxis: `<x-heroicon-o-nombre class="icon-16" aria-hidden="true"/>`. No usar Bootstrap Icons, Tabler ni ningún otro sistema. Tamaños disponibles: `icon-12`, `icon-13`, `icon-14`, `icon-16`, `icon-20`.
- **No usar `Tailwind` en la superficie operativa Livewire.** Bootstrap es la primitiva aquí, no Tailwind (Tailwind se usa en Filament).

**Antes de añadir cualquier clase CSS nueva**, pregúntate en orden:
1. ¿Lo hace Bootstrap con sus clases estándar?
2. ¿Existe ya un componente `op-*`?
3. ¿Es realmente una necesidad estructural nueva que justifica una clase propia?

Solo si las tres respuestas son «no», crea la clase, en el SCSS de la pantalla o en `_op-components.scss` según corresponda.

---

## 1. Estructura del módulo

Crear el módulo `Supervision` siguiendo la estructura estándar nwidart v12:

```
Modules/Supervision/
  app/
    Http/Livewire/
      Sidebar.php
      InicioPage.php
      CuadrantePage.php
      ActividadesPage.php
      PlazasPage.php          ← solo si centro tiene plazas
      EquipoPage.php
      AuditoriaPage.php
      AprobacionesPage.php
      ConfiguracionCentroPage.php
    Services/
      SupervisionSidebarDataService.php
      IndicadoresCentroService.php
  resources/views/livewire/
    sidebar.blade.php
    inicio-page.blade.php
    cuadrante-page.blade.php
    actividades-page.blade.php
    plazas-page.blade.php
    equipo-page.blade.php
    auditoria-page.blade.php
    aprobaciones-page.blade.php
    configuracion-centro-page.blade.php
  routes/web.php
  module.json
  SupervisionServiceProvider.php
  tests/Feature/Livewire/
```

Registrar el provider en `bootstrap/providers.php`.

---

## 2. Rutas

```php
// Modules/Supervision/routes/web.php
Route::middleware(['auth', 'role:supervision'])->prefix('supervision')->name('supervision.')->group(function () {
    Route::redirect('/', '/supervision/inicio');
    Route::get('/inicio',          InicioPage::class)->name('inicio');
    Route::get('/cuadrante',       CuadrantePage::class)->name('cuadrante');
    Route::get('/actividades',     ActividadesPage::class)->name('actividades');
    Route::get('/actividades/{id}',ActividadesPage::class)->name('actividades.detalle');
    Route::get('/plazas',          PlazasPage::class)->name('plazas');
    Route::get('/equipo',          EquipoPage::class)->name('equipo');
    Route::get('/equipo/{profesional}', EquipoPage::class)->name('equipo.profesional');
    Route::get('/auditoria',       AuditoriaPage::class)->name('auditoria');
    Route::get('/aprobaciones',    AprobacionesPage::class)->name('aprobaciones');
    Route::get('/configuracion',   ConfiguracionCentroPage::class)->name('configuracion');
});
```

---

## 3. Sidebar

### 3.1 `SupervisionSidebarDataService`

```php
public function aprobacionesPendientes(int $usuarioId): int
// Cuenta registros en usuario_rol con estado = pendiente_aprobacion
// cuya UO destino está bajo el árbol de UOs del supervisor
// + accesos_protegidos con estado = pendiente y ciudadano de colectivo protegido
// de la UO del supervisor (solo si tiene_colectivos_protegidos = true)
```

### 3.2 `Sidebar.php`

Computed properties:
- `aprobacionesPendientes(): int` — llama a `SupervisionSidebarDataService`
- `tieneColectivosProtegidos(): bool` — lee `tiene_colectivos_protegidos` de la config del centro
- `tienePlazas(): bool` — lee `tiene_plazas` de la config del centro
- `branding(): array` — igual que en el módulo Intervencion (logo + nombre)

Polling: `#[Poll('300s')]`

### 3.3 `sidebar.blade.php`

Estructura idéntica al sidebar de Intervencion. Ítems de navegación:

```
Inicio          → supervision.inicio       (sin badge)
Cuadrante       → supervision.cuadrante    (sin badge)
Actividades     → supervision.actividades  (sin badge)
Plazas          → supervision.plazas       [solo si $tienePlazas]
Mi equipo       → supervision.equipo       (sin badge)
Accesos         → supervision.auditoria    (sin badge)
Aprobaciones    → supervision.aprobaciones (badge si $aprobacionesPendientes > 0)
Configuración   → supervision.configuracion (sin badge)
```

El badge de Aprobaciones usa `<span class="badge bg-danger rounded-pill">`. Si el count es 0, no renderizar el badge (no ocultar con `d-none`, directamente no incluirlo en el DOM con `@if`).

---

## 4. Tests funcionales

> **Convención:** PHPUnit con atributo `#[Test]`. Base de datos `vida_testing` (PostgreSQL). Ubicación: `Modules/Supervision/tests/Feature/Livewire/`. Patrón: Dado / Cuando / Entonces. Siempre incluir caso negativo para restricciones de seguridad.

### Grupo A — Acceso y protección de rutas (TF-SUP-A)

**TF-SUP-A01 — Usuario sin rol supervision es redirigido**
- Dado un usuario autenticado con rol `intervencion`.
- Cuando accede a `/supervision/inicio`.
- Entonces recibe 403 o redirección a la página de error de rol.

**TF-SUP-A02 — Usuario con rol supervision accede a inicio**
- Dado un usuario con rol `supervision`.
- Cuando accede a `/supervision/inicio`.
- Entonces recibe 200 y el componente `InicioPage` se monta.

**TF-SUP-A03 — Ruta raíz redirige a inicio**
- Dado un usuario con rol `supervision`.
- Cuando accede a `/supervision`.
- Entonces es redirigido a `/supervision/inicio`.

**TF-SUP-A04 — Usuario no autenticado es redirigido al login**
- Dado ningún usuario autenticado.
- Cuando accede a `/supervision/inicio`.
- Entonces es redirigido a la ruta de login.

---

### Grupo B — Sidebar (TF-SUP-B)

**TF-SUP-B01 — Sidebar muestra 7 ítems en centro sin plazas ni colectivos protegidos**
- Dado un supervisor en un centro con `tiene_plazas = false`.
- Cuando se renderiza el sidebar.
- Entonces el ítem «Plazas» no está presente en el DOM.
- Y están presentes: Inicio, Cuadrante, Actividades, Mi equipo, Accesos, Aprobaciones, Configuración.

**TF-SUP-B02 — Sidebar muestra ítem Plazas en centro con plazas**
- Dado un supervisor en un centro con `tiene_plazas = true`.
- Cuando se renderiza el sidebar.
- Entonces el ítem «Plazas» está presente con enlace a `supervision.plazas`.

**TF-SUP-B03 — Badge de aprobaciones muestra count correcto**
- Dado 3 solicitudes en `usuario_rol` con `estado = pendiente_aprobacion` en el ámbito del supervisor.
- Cuando se renderiza el sidebar.
- Entonces el badge del ítem Aprobaciones muestra «3».

**TF-SUP-B04 — Badge de aprobaciones no aparece con cero pendientes**
- Dado ninguna solicitud pendiente en el ámbito del supervisor.
- Cuando se renderiza el sidebar.
- Entonces el DOM no contiene ningún badge en el ítem Aprobaciones.

---

### Grupo C — Dashboard / Inicio (TF-SUP-C)

**TF-SUP-C01 — InicioPage se monta sin errores**
- Dado un supervisor con UO adscrita.
- Cuando se monta `InicioPage`.
- Entonces el componente renderiza sin excepciones y contiene los 4 KPIs.

**TF-SUP-C02 — Indicador ratio personas/profesional se calcula correctamente**
- Dado 10 historias sociales activas y 2 profesionales con agenda activa en la UO.
- Cuando se llama a `IndicadoresCentroService::ratioCarga($uoId)`.
- Entonces devuelve `5.0`.

**TF-SUP-C03 — KPI ratio se resalta cuando supera umbral**
- Dado un umbral configurado de 4 y un ratio calculado de 6.
- Cuando se renderiza el KPI de ratio.
- Entonces el elemento tiene clase CSS de advertencia (por ejemplo `text-warning` o `op-chip--warning`).

**TF-SUP-C04 — Zona de aprobaciones no se renderiza si no hay pendientes**
- Dado 0 solicitudes pendientes.
- Cuando se monta `InicioPage`.
- Entonces el bloque de aprobaciones no está presente en el DOM.

**TF-SUP-C05 — Zona de aprobaciones muestra hasta 5 ítems**
- Dado 8 solicitudes pendientes.
- Cuando se monta `InicioPage`.
- Entonces el bloque muestra exactamente 5 ítems y un enlace «Ver todas».

---

### Grupo D — Mi equipo: profesionales (TF-SUP-D)

**TF-SUP-D01 — EquipoPage lista los profesionales de la UO**
- Dado 3 profesionales adscritos a la UO del supervisor y 1 profesional de otra UO.
- Cuando se monta `EquipoPage`.
- Entonces la tabla muestra exactamente 3 filas.

**TF-SUP-D02 — Alta de profesional crea registro sin usuario_id**
- Dado los datos válidos de un nuevo profesional (nombre, cargo, fecha_incorporacion).
- Cuando se llama a `crearProfesional()` en `EquipoPage`.
- Entonces existe un registro en `profesionales` con `usuario_id = null` y la UO del supervisor.

**TF-SUP-D03 — Alta de profesional muestra aviso de cuenta pendiente**
- Dado que se acaba de crear un profesional sin usuario_id.
- Cuando se renderiza la confirmación.
- Entonces el DOM contiene el texto sobre vincular la cuenta de usuario.

**TF-SUP-D04 — Baja de profesional con casos activos requiere confirmación explícita**
- Dado un profesional con 2 planes de intervención activos.
- Cuando se inicia el flujo de baja.
- Entonces el sistema muestra un aviso indicando el número de casos activos antes de permitir confirmar.

**TF-SUP-D05 — Baja de profesional es soft delete**
- Dado un profesional sin casos activos.
- Cuando se confirma la baja con fecha.
- Entonces el registro en `profesionales` tiene `deleted_at` no nulo.
- Y el registro sigue siendo recuperable (la baja es lógica, no física).

**TF-SUP-D06 — Supervisor no puede gestionar profesionales de UO fuera de su ámbito**
- Dado un supervisor adscrito a `$uo_hija`.
- Cuando intenta llamar a `crearProfesional()` asignando la UO a `$uo_paralela`.
- Entonces la operación es rechazada con error de autorización.

**TF-SUP-D07 — Ficha de profesional muestra pestañas Resumen, Perfil horario y Suplencias**
- Dado un profesional de la UO del supervisor.
- Cuando se monta `EquipoPage` con `{profesional}` en la ruta.
- Entonces el DOM contiene los tres elementos de navegación de pestañas.

---

### Grupo E — Aprobaciones (TF-SUP-E)

**TF-SUP-E01 — AprobacionesPage lista solicitudes pendientes del ámbito**
- Dado 2 solicitudes `usuario_rol` con `estado = pendiente_aprobacion` en la UO del supervisor y 1 en una UO fuera del ámbito.
- Cuando se monta `AprobacionesPage`.
- Entonces la lista muestra exactamente 2 solicitudes.

**TF-SUP-E02 — Aprobar asignación de rol activa el registro**
- Dado una solicitud `usuario_rol` con `estado = pendiente_aprobacion`.
- Cuando se llama a `aprobarSolicitud($solicitudId)`.
- Entonces el registro pasa a `estado = activo`.
- Y `$profesional->fresh()->hasRole($rol)` devuelve `true`.

**TF-SUP-E03 — Denegar asignación de rol deja el registro como denegado**
- Dado una solicitud `usuario_rol` con `estado = pendiente_aprobacion`.
- Cuando se llama a `denegarSolicitud($solicitudId, $motivo)` con motivo no vacío.
- Entonces el registro pasa a `estado = denegado`.
- Y `$profesional->fresh()->hasRole($rol)` devuelve `false`.

**TF-SUP-E04 — Denegar sin motivo es rechazado**
- Dado una solicitud pendiente.
- Cuando se llama a `denegarSolicitud($solicitudId, '')` con motivo vacío.
- Entonces se lanza un error de validación y el registro no cambia de estado.

**TF-SUP-E05 — Pestaña «Accesos a expedientes» no aparece en centro sin colectivos protegidos**
- Dado un centro con `tiene_colectivos_protegidos = false`.
- Cuando se monta `AprobacionesPage`.
- Entonces el DOM no contiene la pestaña «Accesos a expedientes».

**TF-SUP-E06 — Supervisor no puede aprobar solicitudes fuera de su ámbito de UO**
- Dado una solicitud `usuario_rol` cuya UO destino está fuera del subárbol del supervisor.
- Cuando se llama a `aprobarSolicitud($solicitudId)`.
- Entonces la operación es rechazada con error de autorización.
- Y el registro sigue con `estado = pendiente_aprobacion`.

---

### Grupo F — Auditoría de accesos (TF-SUP-F)

**TF-SUP-F01 — AuditoriaPage solo muestra accesos de profesionales externos al centro**
- Dado un acceso de un profesional de la misma UO (responsable del ciudadano) y uno de otra UO.
- Cuando se monta `AuditoriaPage`.
- Entonces la tabla contiene solo el acceso del profesional externo.

**TF-SUP-F02 — Accesos a ciudadanos de colectivos protegidos aparecen destacados**
- Dado un acceso a un ciudadano de colectivo protegido sin autorización.
- Cuando se renderiza la tabla.
- Entonces la fila tiene un marcador visual de estado `sin autorización` (clase CSS o badge visible).

**TF-SUP-F03 — Acceso autorizado a colectivo protegido muestra enlace a la autorización**
- Dado un acceso a un ciudadano protegido con `accesos_protegidos.estado = aprobado`.
- Cuando se renderiza la fila.
- Entonces existe un enlace a la autorización (por ejemplo `accesos_protegidos.id`).

**TF-SUP-F04 — Filtro por rango de fechas restringe resultados**
- Dado accesos en las últimas 48 h y accesos de hace 60 días.
- Cuando se filtra con rango «últimos 30 días» (por defecto).
- Entonces solo aparecen los accesos de las últimas 48 h.

**TF-SUP-F05 — Columna colectivo protegido no aparece en centros sin colectivos**
- Dado un centro con `tiene_colectivos_protegidos = false`.
- Cuando se renderiza `AuditoriaPage`.
- Entonces el DOM no contiene la columna «Colectivo protegido».

**TF-SUP-F06 — Supervisor no ve accesos de ciudadanos de UOs fuera de su ámbito**
- Dado un ciudadano asignado a una UO fuera del subárbol del supervisor, con un acceso externo a ese ciudadano.
- Cuando se monta `AuditoriaPage`.
- Entonces ese acceso no aparece en la tabla.

---

### Grupo G — Configuración del centro (TF-SUP-G)

**TF-SUP-G01 — ConfiguracionCentroPage muestra los valores actuales**
- Dado un centro con `nombre_corto = 'CSS Retiro'` en `unidades_organizativas`.
- Cuando se monta `ConfiguracionCentroPage`.
- Entonces el campo de nombre corto muestra «CSS Retiro».

**TF-SUP-G02 — Guardar nombre corto persiste el cambio**
- Dado un valor nuevo para `nombre_corto`.
- Cuando se guarda el formulario.
- Entonces `UnidadOrganizativa::find($uoId)->nombre_corto` devuelve el nuevo valor.

**TF-SUP-G03 — Cambiar modo de agenda muestra advertencia**
- Dado el modo actual `basico`.
- Cuando se selecciona `avanzado` en el selector.
- Entonces el DOM muestra el texto de advertencia sobre el impacto en todos los profesionales.

**TF-SUP-G04 — Supervisor no puede guardar configuración de una UO fuera de su ámbito**
- Dado un supervisor adscrito a `$uo_hija`.
- Cuando intenta guardar parámetros referenciando `$uo_paralela`.
- Entonces la operación es rechazada.

---

## 5. Checklist de implementación por pantalla

Para cada componente Livewire, al terminarlo:

- [ ] El componente tiene docblock de clase con descripción de propósito.
- [ ] Todos los `#[Computed]` tienen `@return TipoExacto`.
- [ ] Todas las rutas están protegidas por `role:supervision`.
- [ ] Las operaciones de escritura verifican que el objeto pertenece al ámbito de UO del supervisor (`$supervisor->puedeGestionar($uo)`).
- [ ] Ninguna vista Blade usa estilos inline estructurales.
- [ ] Ninguna vista Blade usa clases de Tailwind (`flex`, `grid`, `text-sm`, etc.) — solo Bootstrap y `op-*`.
- [ ] Ninguna vista usa iconos que no sean Heroicons via `<x-heroicon-*>`.
- [ ] No se ha escrito nada en `app-operativo.css`.
- [ ] Si se añadió CSS nuevo, está en `_op-components.scss` (si es reutilizable) o en el SCSS de la pantalla (si es específico), con un comentario que justifica por qué Bootstrap no era suficiente.
- [ ] Los tests del grupo correspondiente pasan (ejecutar solo los del módulo: `php artisan test --filter=Supervision`).
- [ ] La suite completa no tiene regresiones antes del commit final.

---

## 6. Fixtures y helpers de test

Definir en un trait o `setUp()` compartido:

```php
// $supervisor — usuario con rol supervision, adscrito a $uo_hija
// $uo_raiz    — UO sin parent_id
// $uo_hija    — UO con parent_id = $uo_raiz->id (ámbito del supervisor)
// $uo_paralela — UO con parent_id = $uo_raiz->id (fuera del ámbito)
// $centroConPlazas  — centro con tiene_plazas = true
// $centroSinPlazas  — centro con tiene_plazas = false
// $centroConProtegidos — centro con tiene_colectivos_protegidos = true
```

---

## 7. Notas de implementación

- El módulo `Supervision` **no reimplementa** lógica de agenda. `CuadrantePage` consume los servicios de `Modules/Agenda` para leer y escribir. Si un servicio necesario no existe en el módulo Agenda, crearlo allí y consumirlo desde aquí.
- `AuditoriaPage` extiende `app/Queries/AccesosExpedienteQuery` añadiendo el filtro de «profesional externo al centro» (su UO no es la UO del ciudadano). No duplicar la query base.
- La condición `tiene_plazas` y `tiene_colectivos_protegidos` se lee siempre de la configuración del centro (tabla `organizacion_configuracion` o columna en `centros`), nunca hardcodeada.
- Las alertas generadas desde `AprobacionesPage` al aprobar/denegar usan el sistema de Mensajes existente (`Alerta::create()` con `tipo = aviso`). No implementar un canal de notificaciones propio.
- La baja de un profesional es **siempre soft delete**. No implementar hard delete. El campo `deleted_at` en `profesionales` debe existir; si no existe, añadir la migración.
ENDDOC