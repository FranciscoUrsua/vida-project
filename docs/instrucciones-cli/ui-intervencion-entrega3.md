# Instrucciones para Claude CLI — UI Intervención · Entrega 3
## `docs/instrucciones-cli/ui-intervencion-entrega3.md`

> Implementación del interfaz operativo del rol `intervencion` — Tercera entrega.
> Cubre la pantalla de trabajo con el ciudadano: Historia Social, unidad de
> convivencia y todas las herramientas de registro (entrevista, anotación,
> valoración, escala, derivación, gestión/coordinación, informes).
>
> **Diseño de referencia:** `docs/front/ui-intervencion.md` (sección 7)
> **Módulos afectados:** `Modules/Intervencion`, `Modules/Documentos`, `Modules/Escalas`
> **Prerequisito:** Entrega 2 completada y sus 23 tests pasando.

---

## Contexto

Esta es la pantalla de trabajo principal del TSR. Es la más compleja del proyecto
porque concentra la mayor variedad de comportamientos: timeline interactivo,
navegación entre miembros de la UC, siete herramientas con formularios distintos
y tres que abren pantalla completa.

La pantalla funciona sobre modelos ya implementados en fase 1:
`HistoriaSocial`, `Apunte`, `PlanIntervencion`, `Entrevista`, `Valoracion`,
`Derivacion`, `UnidadConvivencia`. Consultar `docs/modulo-intervencion.md`
para la estructura exacta de cada modelo.

---

## Paso 1 — Revisar el estado antes de empezar

```bash
# Confirmar que las entregas anteriores están completas
php artisan test --filter="AgendaPage|MisCasosPage|BuscarCiudadanoPage|BuzonPage" 2>&1 | tail -3

# Modelos disponibles en el módulo de Intervención
ls Modules/Intervencion/app/Models/

# Tests del módulo completo
php artisan test --filter=Intervencion 2>&1 | tail -5
```

---

## Paso 2 — Ruta

Añadir en `Modules/Intervencion/routes/web.php`, dentro del grupo existente:

```php
Route::get('/ciudadano/{historia}', \Modules\Intervencion\Http\Livewire\CiudadanoPage::class)
    ->name('ciudadano.show')
    ->middleware('can:view,historia');
```

El gate `can:view,historia` usa la `HistoriaSocialPolicy` ya implementada en
fase 1. Si el usuario no tiene permiso, Laravel devuelve 403 automáticamente.

---

## Paso 3 — Componente principal: CiudadanoPage

Crear `Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php`.

### 3.1 Propiedades y estado

```php
public HistoriaSocial $historia;        // inyectado por route model binding
public string $filtroHS = 'todos';      // 'todos' | 'plan' | 'entrevista'
public bool $ucExpandida = false;       // unidad de convivencia colapsada/expandida
public array $apuntesExpandidos = [];   // [apunte_id => bool]
public ?string $herramientaActiva = null; // null | 'entrevista' | 'anotacion' | ...
```

### 3.2 Propiedad computada: ciudadano

```php
public function getCiudadanoProperty(): Ciudadano
{
    return $this->historia->ciudadano;
}
```

### 3.3 Propiedad computada: apuntesHS

```php
public function getApuntesHSProperty(): Collection
```

Obtiene los apuntes de la Historia Social ordenados por `created_at DESC`.
Aplica el filtro `$filtroHS`:
- `todos`: todos los tipos.
- `plan`: solo apuntes de tipo `plan_intervencion`.
- `entrevista`: solo apuntes de tipo `entrevista`.

Solo apuntes visibles para el usuario: tipo `profesionales` o
(`privada` AND `autor_id = Auth::id()`).

### 3.4 Propiedad computada: pisoActivo

```php
public function getPisoActivoProperty(): ?PlanIntervencion
```

El plan de tipo `general_asp` en estado `activo` más reciente de la Historia Social.

### 3.5 Métodos de UI

```php
public function toggleUC(): void
public function toggleApunte(int $apunteId): void
public function setFiltroHS(string $filtro): void
public function seleccionarHerramienta(string $herramienta): void
public function cancelarHerramienta(): void
```

`setFiltroHS` y `seleccionarHerramienta` no producen recarga de página completa:
solo actualizan estado del componente.

---

## Paso 4 — Vista Blade: ciudadano-page

La vista implementa el layout de dos columnas descrito en
`docs/front/ui-intervencion.md`, sección 7.

### 4.1 Barra superior (breadcrumb)

```
← Mis casos  ·  [Nombre del ciudadano]  [badge estado HS]
                                          [Ficha social]  [⋯]
```

"← Mis casos" → `route('intervencion.casos.index')`.
El badge de estado usa los colores semánticos:
- `abierta` → morado (`#EEEDFE` / `#3C3489`)
- `en_seguimiento` → verde teal
- `cerrada` → gris

### 4.2 Columna izquierda (280px fija)

**Cabecera del ciudadano**

Avatar con iniciales (componente `<x-avatar>`), nombre completo, edad (calculada
de `fecha_nacimiento`), NIF, NI-HSU-CM (solo si `$historia->id` existe —
siempre en esta pantalla), domicilio, teléfono, fecha de apertura de la HS.

**Unidad de convivencia** (colapsable con `wire:click="toggleUC"`)

Estado cerrado: avatares apilados de los miembros (máximo 3 visibles, resto como "+N").
Estado abierto: lista de miembros con nombre, rol en la UC y edad.

Clic en un miembro: navegar a la HS de ese ciudadano si existe y el usuario tiene
acceso. Si es nivel 2 o 3, aplicar la misma lógica que en `BuscarCiudadanoPage`.

**Historia Social**

Filtros en fila: Todos / PISO / Entrevistas (usando `wire:click="setFiltroHS(...)"`,
el activo con fondo `#EEEDFE`).

Timeline: cada entrada colapsada muestra punto de color, título truncado, tipo y
fecha. Al hacer clic (`wire:click="toggleApunte($apunte->id)"`) se expande para
mostrar el cuerpo completo y el autor.

**Código de color de puntos:**

| Tipo de apunte | Color |
|---|---|
| `plan_intervencion` | `#854F0B` |
| `entrevista` | `#534AB7` |
| `valoracion` | `#1D9E75` |
| `escala` | `#7F77DD` |
| `derivacion` | `#0F6E56` |
| `anotacion` | `#888780` |
| `comunicacion_interna` | `#888780` |

### 4.3 Columna derecha

**Banda PISO activo** (fija en la parte superior del área de trabajo):
Muestra nombre del plan, estado, versión y fecha de próxima revisión.
Botón "Ver PISO" → pendiente de implementación (`href="#"`, `// TODO: Entrega 4`).

**Área de herramientas:**

Cuando `$herramientaActiva === null`: cuadrícula de 4 columnas con las 7 herramientas.
Cuando hay herramienta activa: cuadrícula compacta de 1 fila + formulario abajo.

---

## Paso 5 — Las siete herramientas

Implementar cada herramienta como un sub-componente Livewire separado o como
condicionales dentro de `CiudadanoPage`. Usar la aproximación más simple que
permita tests aislados.

### 5.1 Herramienta: Entrevista

Sub-componente `RegistrarEntrevista` o formulario dentro de `CiudadanoPage`.

**Campos:**
- `tipo`: select (seguimiento / inicial / urgencia / informativa)
- `modalidad`: select (presencial / telefónica / videollamada / domicilio)
- `notas`: textarea libre
- `generar_valoracion`: checkbox — si marcado, al guardar navegar a
  `RegistrarValoracion` con la entrevista ya vinculada
- `programar_seguimiento`: checkbox — si marcado, mostrar un campo de fecha
  (no conectado al módulo Agenda todavía; guardar en
  `SeguimientoPlan.fecha_siguiente_seguimiento` como intención del profesional)

**Acción principal:** "Guardar entrevista"

```php
public function guardarEntrevista(array $datos): void
{
    // Crear Entrevista vinculada a $this->historia
    // Crear Apunte de tipo 'entrevista' en la Historia Social
    // Si genera_valoracion: redirigir a herramienta valoracion con entrevista_id
    // Si programar_seguimiento: actualizar fecha_siguiente_seguimiento
    $this->herramientaActiva = null;
    // El timeline se refresca automáticamente por la propiedad computada
}
```

### 5.2 Herramienta: Anotación

**Campos:**
- `contenido`: textarea
- `visibilidad`: radio (profesionales / privada)

**Acción:** "Guardar anotación"

```php
public function guardarAnotacion(array $datos): void
{
    // Crear Apunte de tipo 'anotacion' con visibilidad según $datos['visibilidad']
    // visibilidad 'privada': autor_id = Auth::id(), solo visible para él
    $this->herramientaActiva = null;
}
```

Las anotaciones privadas solo las ve su autor. Verificar que la consulta
`getApuntesHSProperty` aplica correctamente este filtro.

### 5.3 Herramienta: Derivación

**Campos:**
- `servicio_receptor_id`: select del catálogo de servicios/prestaciones del sistema
- `urgencia`: select (ordinaria / preferente / urgente)
- `motivo`: textarea
- `adjuntar_informe`: checkbox (por ahora solo guarda la intención en el apunte;
  la generación real del informe es responsabilidad del módulo Documentos)

**Acción:** "Crear derivación"

```php
public function crearDerivacion(array $datos): void
{
    // Crear Derivacion vinculada a la Historia Social
    // Crear Apunte de tipo 'derivacion'
    $this->herramientaActiva = null;
}
```

### 5.4 Herramienta: Gestión / coordinación

**Campos:**
- `tipo_gestion`: select (coordinación con otro servicio / trámite administrativo /
  mesa de trabajo / contacto con familia / otro)
- `recurso_interlocutor`: input texto libre
- `descripcion`: textarea

**Acción:** "Guardar gestión"

```php
public function guardarGestion(array $datos): void
{
    // Crear Apunte de tipo 'gestion_coordinacion'
    // Si 'gestion_coordinacion' no existe en el enum de Apunte,
    // usar 'anotacion' con un campo adicional o crear el tipo.
    // Consultar docs/modulo-intervencion.md para los tipos de apunte disponibles.
    $this->herramientaActiva = null;
}
```

### 5.5 Herramienta: Valoración (pantalla completa)

Al seleccionar Valoración, mostrar el formulario de selección de tipo:
- `tipo_ficha_id`: select de `TipoFicha::activos()->where('tipo', 'valoracion')`

Y un aviso visual: "La ficha se abrirá en pantalla completa".

**Acción:** "Abrir en pantalla completa"

Navegar a:
```
route('intervencion.valoracion.nueva', [
    'historia' => $this->historia->id,
    'tipo_ficha' => $tipoFichaId,
    'entrevista' => $entrevistaId ?? null,  // si viene de herramienta entrevista
])
```

Esta ruta se implementa como una nueva `FullPageComponent` de Livewire:
`Modules/Intervencion/app/Http/Livewire/RegistrarValoracionPage.php`.

La pantalla completa usa el mismo `layouts.operativo` pero con la historia
colapsada al mínimo (solo nombre y badge de estado en la barra superior;
sin sidebar de UC ni timeline).

El formulario carga el `schema` JSON del `TipoFicha` seleccionado y renderiza
los campos dinámicamente. Usar `@foreach ($tipoFicha->schema['campos'] as $campo)`.
Para los tipos de campo del schema, consultar `docs/modulo-intervencion.md`.

**Acción:** "Guardar valoración"

```php
public function guardarValoracion(array $datos): void
{
    // Crear Valoracion con los datos del schema
    // Crear Apunte de tipo 'valoracion' en la Historia Social
    // Redirigir de vuelta a la pantalla del ciudadano
}
```

### 5.6 Herramienta: Escala (pantalla completa)

Al seleccionar Escala, mostrar:
- `tipo_escala_id`: select de `TipoEscala::activos()`
  (módulo Escalas — si el modelo no existe, usar un array hardcodeado con
  comentario `// TODO: conectar con módulo Escalas`)
- Última aplicación de ese tipo para este ciudadano (si existe).
- Aviso: "La escala se abrirá en pantalla completa".

**Acción:** "Abrir en pantalla completa"

Navegar a `route('intervencion.escala.nueva', ['historia' => ..., 'tipo_escala' => ...])`.

La pantalla completa sigue el mismo patrón que la valoración. El formulario
de la escala muestra las secciones y preguntas del `schema` del `TipoEscala`,
con los controles adecuados (radio, select o número según el tipo de pregunta).

**Acción:** "Guardar escala"

```php
public function guardarEscala(array $datos): void
{
    // Crear PaseEscala con las respuestas y la puntuación total calculada
    // Crear Apunte de tipo 'escala' en la Historia Social con el resultado
    // Redirigir de vuelta a la pantalla del ciudadano
}
```

La puntuación total: suma de `respuesta * peso` de cada pregunta según el
`schema`. Si el `schema` de `TipoEscala` no define pesos, asumir `peso = 1`.

### 5.7 Herramienta: Informes (pantalla completa)

Al seleccionar Informes, mostrar:
- `plantilla_id`: select de `PlantillaInforme` (módulo Documentos) visibles
  para la UO del profesional.

**Acción:** "Abrir en pantalla completa"

Navegar a:
```
route('intervencion.informe.nuevo', [
    'historia' => $this->historia->id,
    'plantilla' => $plantillaId,
])
```

La implementación de esta ruta delega en el módulo Documentos.
Si el módulo Documentos no tiene este endpoint, crear un
stub que muestre "Módulo de informes en construcción" y añadir
`// TODO: conectar con módulo Documentos cuando implemente la vista de edición`.

---

## Paso 6 — Tests

### CiudadanoPage — core

```
TF-LW-CIU-01 — La ruta aplica la policy: usuario sin acceso recibe 403
TF-LW-CIU-02 — El componente monta con la historia inyectada correctamente
TF-LW-CIU-03 — getApuntesHSProperty devuelve solo apuntes visibles para el usuario
TF-LW-CIU-04 — Apunte privado de otro profesional no aparece en el timeline
TF-LW-CIU-05 — filtroHS 'plan' devuelve solo apuntes de tipo plan_intervencion
TF-LW-CIU-06 — filtroHS 'entrevista' devuelve solo apuntes de tipo entrevista
TF-LW-CIU-07 — toggleApunte alterna el estado expandido del apunte
TF-LW-CIU-08 — toggleUC alterna la visibilidad de la unidad de convivencia
TF-LW-CIU-09 — pisoActivo devuelve el plan general_asp activo más reciente
TF-LW-CIU-10 — pisoActivo devuelve null si no hay plan activo
```

### Herramientas inline

```
TF-LW-CIU-11 — guardarEntrevista crea Entrevista y Apunte de tipo entrevista
TF-LW-CIU-12 — guardarEntrevista con programar_seguimiento actualiza fecha_siguiente_seguimiento
TF-LW-CIU-13 — guardarAnotacion con visibilidad privada crea apunte con autor correcto
TF-LW-CIU-14 — Apunte privado creado no es visible para otro profesional en la misma HS
TF-LW-CIU-15 — crearDerivacion crea Derivacion y Apunte de tipo derivacion
TF-LW-CIU-16 — guardarGestion crea Apunte con los campos correctos
TF-LW-CIU-17 — cancelarHerramienta limpia herramientaActiva
TF-LW-CIU-18 — Después de guardar cualquier herramienta, herramientaActiva es null
```

### Herramientas a pantalla completa

```
TF-LW-CIU-19 — guardarValoracion crea Valoracion y Apunte de tipo valoracion
TF-LW-CIU-20 — guardarValoracion vincula la Valoracion a la entrevista si se pasa entrevista_id
TF-LW-CIU-21 — guardarEscala crea PaseEscala con puntuacion_total calculada correctamente
TF-LW-CIU-22 — guardarEscala crea Apunte de tipo escala con la puntuación en el resumen
TF-LW-CIU-23 — La ruta de valoracion nueva requiere un tipo_ficha_id válido
```

Ejecutar al terminar:

```bash
php artisan test --filter=CiudadanoPage
php artisan test --filter=Intervencion
php artisan test 2>&1 | tail -5
```

---

## Lo que NO hay que hacer

- No implementar el flujo de firma con AutoFirma. La herramienta de Informes
  puede mostrar el stub "en construcción" si el módulo Documentos no está listo.
- No implementar la "Ver PISO" — navegar al editor del PISO es Entrega 4.
- No implementar el módulo de Escalas completo: solo las consultas de lectura
  necesarias para el selector. Si no existe `TipoEscala`, usar fixture hardcodeada.
- No añadir lógica de permisos no documentada. Si la policy ya existente es
  suficiente, usarla tal cual.
- No usar el término "apunte" en la interfaz (labels, tooltips, botones).
  Verbos siempre: "Guardar entrevista", "Guardar anotación", "Crear derivación",
  "Guardar gestión", "Guardar valoración", "Guardar escala".
- No sobreescribir tests existentes del módulo de Intervención (35 pasando
  en fase 1). Si un test existente falla por cambios de esta entrega, investigar
  antes de modificarlo.

---

## Checklist de finalización

- [ ] `php artisan test --filter=CiudadanoPage` pasa los 23 tests
- [ ] `php artisan test --filter=Intervencion` pasa los 35 tests previos + 23 nuevos
- [ ] `php artisan test` no introduce fallos nuevos
- [ ] La ruta `/intervencion/ciudadano/{historia}` aplica la policy
- [ ] El timeline filtra correctamente por tipo y visibilidad
- [ ] Los apuntes privados de otros profesionales no son visibles
- [ ] Las 4 herramientas inline guardan correctamente y recargan el timeline
- [ ] Las 3 herramientas a pantalla completa navegan a su propia ruta
- [ ] El botón "Guardar" de cada herramienta usa el verbo correcto (no "guardar apunte")
- [ ] La banda PISO activo muestra el plan correcto (o nada si no hay plan activo)
- [ ] La unidad de convivencia se colapsa/expande sin recargar la página
- [ ] Entrada añadida en `CHANGELOG.md`
