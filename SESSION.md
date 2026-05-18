# SESSION — VIDA 360

_Actualizado: 2026-05-18_

## Tarea completada

Tests funcionales del módulo Intervención implementados: 35/35 tests pasan (Grupos A–G, TF-INT-A01 a G03).

## Estado actual

- **Módulo Intervención — tests funcionales:** 35 tests, 35 pasan ✅, 0 pendientes, 0 fallos.
- **Módulo Documentos — tests funcionales:** 20 tests, 20 pasan ✅ (sesión anterior).
- **Módulo Centros — tests funcionales:** 31 tests, 31 pasan ✅ (sesión anterior).
- **Suite completa:** 142 tests pasan, 0 fallos, 30 incompletos (pendientes de Agenda).
- `docs/modulo-intervencion.md` actualizado con tabla de resultados y marcadores ✅ por test.

## Siguiente paso recomendado

Implementar **`CuadranteGeneratorService`** del módulo Agenda — desbloquea 4 tests pendientes (PF-03.1, PF-03.4, PF-03.5, PF-10.1).

Lógica a implementar:
1. `generarBorrador(CuadranteMes $cuadrante)` — crea `LineaCuadrante` por profesional y día laborable del mes, aplicando `PerfilHorarioProfesional` vigente e incorporando `ExcepcionProfesional` ya conocidas como líneas `anulada = true`.
2. `generarYPublicarAutomaticamente(Centro $centro, int $anyo, int $mes)` — para modo `basico`: crea el cuadrante, genera líneas y llama a `SlotMaterializadorService::materializar()` en un único paso.

## Contexto relevante

- El módulo Intervención usa la tabla `plan_apuntes` (no `apuntes`) para los apuntes de planes, evitando conflicto con el stub `App\Models\Apunte` que usa la tabla `apuntes`.
- La policy de `Modules\Intervencion\Models\Apunte` se registra en `IntervencionServiceProvider`; la policy del stub `App\Models\Apunte` sigue en `UsuariosServiceProvider`.
- `TipoFicha::setSchemaAttribute()` valida JSON a nivel de mutador (no de evento `saving`) porque el cast 'array' transformaría el string antes de que el evento pudiese inspeccionarlo.
- `PlanDeIntervencion` guard de firma solo aplica a updates (`$plan->exists = true`); la creación directa con `estado = activo` está permitida para fixtures de test y seeders.
- `SeguimientoPlan::solicitarCitaSiguiente()` es un stub; se implementará con la integración del módulo Agenda.
