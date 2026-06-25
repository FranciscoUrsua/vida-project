# Instrucciones CLI — UI agenda supervisor

**Tarea:** Implementar la interfaz operativa Livewire para el supervisor de centro del módulo Agenda.
**Documento de referencia UI:** `docs/ui-agenda-supervisor.md`
**Tests a implementar:** `docs/instrucciones-cli/tf-agenda-supervisor.md` (TF-AGS-01 a TF-AGS-42)
**Módulo:** `Modules\Agenda`

---

## Antes de empezar

1. `git pull origin master`
2. Leer `SESSION.md`
3. Leer `docs/principios-vida360.md` — especialmente 4.12 (Filament/Livewire) y 4.18 (design system)
4. Leer `docs/documentacion-proyecto.md`
5. Leer `docs/design-system/SKILL.md`
6. Leer `docs/modulo-agenda.md` íntegramente — entidades, servicios y pruebas funcionales ya implementados
7. Leer `docs/ui-agenda-supervisor.md` — especificación de UI que este fichero implementa

El dominio (modelos, migraciones, servicios) ya está implementado y con tests pasando. Esta tarea es exclusivamente de interfaz.

---

## Scope de la tarea

### Lo que hay que crear

**Livewire (componentes operativos):**

```
Modules/Agenda/app/Livewire/Supervisor/
├── CuadranteSupervisorPage.php       + cuadrante-supervisor-page.blade.php
├── AusenciasSupervisorPage.php       + ausencias-supervisor-page.blade.php
├── ExcepcionesSupervisorPage.php     + excepciones-supervisor-page.blade.php
├── EventosSupervisorPage.php         + eventos-supervisor-page.blade.php
└── Partials/
    └── ReasignacionPanel.php         + reasignacion-panel.blade.php
```

**Rutas Livewire** (en `Modules/Agenda/routes/web.php`):

```php
Route::middleware(['auth', 'rol:supervision'])->prefix('agenda/supervisor')->group(function () {
    Route::get('/cuadrante', CuadranteSupervisorPage::class)->name('agenda.supervisor.cuadrante');
    Route::get('/ausencias', AusenciasSupervisorPage::class)->name('agenda.supervisor.ausencias');
    Route::get('/excepciones', ExcepcionesSupervisorPage::class)->name('agenda.supervisor.excepciones');
    Route::get('/eventos', EventosSupervisorPage::class)->name('agenda.supervisor.eventos');
});
```

**Filament — cambios menores:**

`HorarioCentroResource`, `TipoSlotResource` y `PerfilHorarioProfesionalResource` ya existen. Verificar que están en el grupo *Agenda — Configuración* y que tienen los permisos correctos (solo `supervision` y `adm_sistema`). No hay que crear estos resources, solo revisar su agrupación y permisos.

**Tests:**

```
Modules/Agenda/tests/Feature/Supervisor/
├── CuadranteSupervisorTest.php       (TF-AGS-01 a TF-AGS-07)
├── AusenciasSupervisorTest.php       (TF-AGS-10 a TF-AGS-18)
├── ExcepcionesSupervisorTest.php     (TF-AGS-20 a TF-AGS-25)
├── EventosSupervisorTest.php         (TF-AGS-30 a TF-AGS-33)
└── AccesoSupervisorTest.php          (TF-AGS-40 a TF-AGS-42)
```

### Lo que NO hay que tocar

- Modelos, migraciones, enums: ya implementados
- `GestionAusenciaService`, `DisponibilidadService`, `CuadranteGeneratorService`: ya implementados
- Tests de dominio (PF-01 a PF-10): ya implementados y pasando
- La vista de agenda del profesional (individual): ya implementada

---

## Orden de implementación

Seguir este orden estrictamente. No pasar al siguiente paso hasta que los tests del paso actual pasen.

### Paso 1 — Trait de helpers y fixtures de test

Crear `Modules/Agenda/tests/Feature/Supervisor/AgendaSupervisorTestHelpers.php` con el trait que define los actores reutilizados (ver sección "Actores reutilizados" en `tf-agenda-supervisor.md`).

Los fixtures deben crear:
- Un centro en modo `estandar` con `HorarioCentro` vigente (L–V, 9:00–15:00, atención 9:30–14:00, buffers de 15 min)
- Un `CuadranteMes` en estado `borrador` para el mes actual con `LineaCuadrante` para los tres profesionales
- Un usuario supervisor con rol `supervision` adscrito al centro

### Paso 2 — CuadranteSupervisorPage + tests TF-AGS-01 a 07

El componente recibe el centro del supervisor autenticado mediante `Auth::user()->centroActivo()` (método ya existente en el modelo `User`).

**Propiedades Livewire:**
```php
#[Computed]
public function cuadrante(): ?CuadranteMes // cuadrante del mes actual

#[Computed]
public function profesionales(): Collection // profesionales activos del centro

public string $vistaActiva = 'semana'; // 'semana' | 'mes'
```

**Acciones:**
- `publicar()`: llama a `CuadrantePublicadorService::publicar($cuadrante)` — crear este servicio si no existe, extrayendo la lógica de publicación del `CuadranteMes`. Emite flash de éxito o error.
- `regenerar()`: llama a `CuadranteGeneratorService` para regenerar el borrador. Solo disponible en modo `estandar`/`avanzado`.

**Vista:** grid con columna fija de profesional y columnas dinámicas por días laborables de la semana/mes seleccionado. Usar `op-section` y `op-toolbar` del design system. Las franjas se renderizan como chips con las clases de color del design system VIDA.

### Paso 3 — AusenciasSupervisorPage + ReasignacionPanel + tests TF-AGS-10 a 18

**AusenciasSupervisorPage — propiedades:**
```php
#[Computed]
public function ausenciasHoy(): Collection // ExcepcionProfesional que comienzan hoy o ya están activas

#[Computed]
public function citasPendientes(): Collection // Citas canceladas por ausencia hoy sin ReasignacionCita

#[Computed]
public function noshowsCiudadanos(): Collection // Citas no_show_ciudadano de hoy

public int $citaSeleccionadaId = 0;
public bool $panelAbierto = false;
```

**Acciones:**
- `abrirReasignacion(int $citaId)`: establece `$citaSeleccionadaId` y `$panelAbierto = true`
- `descartar(int $citaId)`: actualiza el `motivo_cancelacion` de la cita con el texto de descarte por supervisor. No crea `ReasignacionCita`.
- `liberarSlot(int $citaId)`: libera el slot de un no-show de ciudadano (pone el slot en `disponible`)

**ReasignacionPanel — componente hijo:**
```php
// Recibe: $citaId (int)
// Emite: 'citaReasignada' con $citaId cuando se confirma

#[Computed]
public function cita(): Cita

#[Computed]
public function slotsDisponiblesHoy(): Collection
// Llama a DisponibilidadService::obtenerSlots() con incluirUrgencias: true
// Filtrado por fecha = hoy, tipo_slot = el de la cita, excluyendo al profesional original
// Ordenado: primero bloqueado_urgencia, luego disponible; dentro de cada grupo por hora
```

**Acción `confirmarReasignacion(int $slotId)`:** llama a `GestionAusenciaService::reasignar($cita, $slot, Auth::id(), 'Reasignación por supervisor')`. Emite `citaReasignada`.

El panel es un div posicionado como sidebar derecho en la vista. En Livewire 4 se implementa como componente hijo con `wire:model` para la visibilidad. No usar `position: fixed` (ver restricción del design system). Implementar como offcanvas de Bootstrap 5 (`offcanvas-end`).

### Paso 4 — ExcepcionesSupervisorPage + tests TF-AGS-20 a 25

**Propiedades:**
```php
public array $form = [
    'usuario_id' => '',
    'tipo' => '',
    'fecha_inicio' => '',
    'fecha_fin' => '',
    'notas' => '',
];

#[Computed]
public function excepcionesActivas(): Collection
// ExcepcionProfesional del centro donde fecha_fin is null OR fecha_fin >= hoy
// Ordenadas por fecha_inicio ASC
```

**Validación del formulario:**
```php
protected array $rules = [
    'form.usuario_id' => 'required|exists:users,id',
    'form.tipo' => 'required|in:baja_medica,vacaciones,reduccion_jornada,permiso_retribuido,cambio_puntual',
    'form.fecha_inicio' => 'required|date',
    'form.fecha_fin' => 'nullable|date|after_or_equal:form.fecha_inicio',
    'form.notas' => 'nullable|string|max:500',
];
```

**Acción `guardar()`:** crea `ExcepcionProfesional`. Si `afecta_disponibilidad = true` y hay cuadrante publicado, el Observer del modelo (ya implementado) se encarga de anular líneas y slots. Redispatch del computed `excepcionesActivas`.

**Acción `eliminar(int $excepcionId)`:** soft delete con confirmación `wire:confirm`.

El selector de profesional debe filtrar por `centro_id = $centro->id` y `activo = true`.

### Paso 5 — EventosSupervisorPage + tests TF-AGS-30 a 33

**Propiedades:**
```php
public bool $mostrarFormulario = false;
public array $form = [...]; // campos del EventoAgenda

#[Computed]
public function eventosProximos(): Collection
// EventoAgenda del centro con inicio >= hoy, ordenados por inicio ASC
```

**Acción `crear()`:** crea `EventoAgenda` y convoca a los profesionales seleccionados (relación `BelongsToMany`). El Observer de `EventoAgenda` (ya implementado) bloquea los slots afectados y emite aviso si hay conflicto de espacio.

**Acción `eliminar(int $eventoId)`:** elimina el evento. El Observer libera los slots bloqueados.

### Paso 6 — Tests de acceso TF-AGS-40 a 42

Tests de política de acceso. Verificar que la middleware `rol:supervision` rechaza usuarios sin ese rol con 403.

### Paso 7 — Revisión Filament

Verificar que `HorarioCentroResource`, `TipoSlotResource` y `PerfilHorarioProfesionalResource`:
1. Están en el grupo *Agenda — Configuración* en el panel Filament
2. Solo son accesibles por usuarios con rol `supervision` o `adm_sistema`
3. Tienen la navegación correcta en el sidebar de Filament

Si alguno de los tres resources no existe todavía, crearlo ahora con los campos definidos en `docs/modulo-agenda.md` secciones 2.1, 2.2 y 2.3.

---

## Restricciones de implementación

**Design system:**
- Usar clases Bootstrap 5 como primitive layer (botones `btn btn-primary`, formularios `form-control`, modales como offcanvas)
- Usar componentes `op-*` para piezas de producto (topbar → `op-toolbar`, secciones → `op-section`)
- No crear clases `xxx-btn` ni `xxx-input` si Bootstrap lo resuelve
- Los colores de franja del cuadrante deben usar variables CSS del design system VIDA, no colores hardcodeados. Consultar `docs/design-system/SKILL.md` para los nombres exactos de las variables

**Livewire:**
- `#[Computed]` con `@return TipoExacto` obligatorio (PHPStan nivel 6)
- No usar `$this->emit()` — Livewire 4 usa `$this->dispatch()`
- El panel de reasignación como `offcanvas-end` de Bootstrap, no con `position: fixed`
- Polling para el badge de ausencias: `wire:poll.30s` sobre la propiedad del contador

**PHPDoc:**
- Todas las clases y métodos públicos/protegidos con docblock (ver sección PHPDoc en CLAUDE.md)
- Especialmente los `#[Computed]`: `@return Collection<int, Cita>` etc.

**Tests:**
- PHPUnit con `#[Test]`, no Pest
- Base de datos PostgreSQL `vida_testing`
- Usar `Livewire::test(CuadranteSupervisorPage::class)` para tests de componentes
- Patrón Dado/Cuando/Entonces en comentarios de cada test

---

## Al terminar

1. Ejecutar suite completa: `php artisan test Modules/Agenda/tests/Feature/Supervisor/`
2. Verificar PHPStan: `./vendor/bin/phpstan analyse Modules/Agenda/app/Livewire/Supervisor/`
3. Añadir entrada en `CHANGELOG.md`:
   - Módulo: Agenda
   - Cambios: lista de componentes creados, tests añadidos, cambios en Filament
   - Decisiones tomadas que no estaban en las instrucciones
4. Añadir a `BACKLOG.md` cualquier cosa que haya quedado pendiente
5. Actualizar `SESSION.md` con el estado resultante y el siguiente paso recomendado
6. Actualizar la tabla de ficheros disponibles en la sección 6 de `CLAUDE.md` añadiendo este fichero: `agenda-supervisor-ui.md`
7. Commit y push:
   ```
   git add -A
   git commit -m "feat(agenda): UI operativa del supervisor — cuadrante, ausencias, excepciones, eventos"
   git push origin master
   ```
