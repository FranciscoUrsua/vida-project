# SESSION — VIDA 360

_Actualizado: 2026-06-01_

## Tarea completada

Implementación de la Entrega 2 del interfaz operativo de Intervención: pantallas Mis casos,
Buscar ciudadano y Buzón de alertas/mensajes. 23/23 tests nuevos en verde,
69 tests del módulo Intervención pasan.

## Estado actual

- **UI Intervención Entrega 2 — completa:**
  - Rutas `/intervencion/casos`, `/intervencion/mensajes`, `/intervencion/buscar`.
  - `MisCasosPage`: tabla paginada de planes activos con semáforo de seguimiento,
    filtros por estado, derivación especializada y cabecera PISO configurable.
  - `BuscarCiudadanoPage`: búsqueda por alias/nombre con tres niveles de acceso
    (propio/otra UO/protegido). Modal de solicitud + Alerta al supervisor.
  - `BuzonPage` (Mensajes): tres pestañas (Alertas/Avisos/Mensajes),
    reconocimiento de alertas, respuesta a hilos.
  - `CatalogoSistema::valor(clave, defecto)` añadido al modelo.
  - Tests TF-LW-CAS-01..07, TF-LW-BUS-01..10, TF-LW-BUZ-01..06 en verde.

- **UI Intervención Entrega 1 — completa** (sin cambios):
  - Layout `operativo.blade.php`, sidebar con poll, AgendaPage con vistas día/semana/mes.
  - Tests TF-LW-AGE-01..14 en verde.

- **Autenticación — completa** (sin cambios).

- **Suite completa:** 9 fallos pre-existentes en `PrestacionFilamentResourceTest`.
  Los tests de la entrega 2 pasan limpiamente en ejecución aislada.

## Limitaciones conocidas (pendientes Entrega 3 o backlog)

| Componente | Limitación | TODO |
|---|---|---|
| `BuscarCiudadanoPage` | Búsqueda por nombre sobre datos cifrados carga ≤500 registros y filtra en PHP | Índice hash determinista |
| `BuscarCiudadanoPage` | Búsqueda por doc/hsu devuelve vacío | Tabla `ciudadano_identificadores` |
| `BuscarCiudadanoPage` | Acceso nivel 2 se loguea con `\Log::info()` | Tabla `audits` |
| `AgendaPage` | 4 KPIs devuelven 0 | Conectar con módulos Agenda y Mensajes |
| Sidebar | `centroActivo()` no implementado en Profesional | Módulo Centro |
| `BuscarCiudadanoPage` | Enlace "Ir a Historia Social" apunta a `#` | Entrega 3 |

## Siguiente paso recomendado

**UI Intervención — Entrega 3**: pantalla del ciudadano y herramientas de trabajo
(Historia Social, Plan de Intervención, apuntes, escalas).
Instrucciones en `docs/instrucciones-cli/ui-intervencion-entrega3.md`.

## Contexto relevante para retomar

- `reconocerAlerta()` actualiza `estado = EstadoAlerta::Reconocida` (no `reconocida_en` — no existe).
- `MisCasosPage` usa `DB::table()` para evitar `AmbitoUoScope` en el query principal.
- `BuzonPage` vive en `Modules/Mensajes/app/Http/Livewire/` y se registra en `MensajesServiceProvider`.
- Los componentes Livewire de Entrega 2 se registran en `IntervencionServiceProvider::boot()`.
- Los 9 fallos de `PrestacionFilamentResourceTest`: "Invalid Livewire snapshot structure" — pre-existente.
