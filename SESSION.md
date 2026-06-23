# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-23

---

## Tarea completada

`Módulo Intervención` — Indicadores de objetivos, valoración y cierre del plan:

- 5 migraciones: `tipo_ficha_id` en catálogo y plan, tablas `indicadores_catalogo` y `plan_objetivo_indicadores`, actualización del constraint `motivo_cierre`.
- 2 modelos nuevos: `IndicadorCatalogo`, `PlanObjetivoIndicador`.
- Modelos actualizados: `ObjetivoCatalogo`, `PlanObjetivo`, enum `MotivoCierre` (6 casos reales).
- `GestionarObjetivos` (Filament): columnas área + indicador, formulario crea indicador junto al objetivo.
- `PlanPage`: modal de cierre funcional con 6 motivos, valoración de indicadores con radio buttons, aviso especial para motivos que requieren constancia en historial.
- `docs/modulo-intervencion.md` actualizado con modelo completo de objetivos, motivos de cierre y modelo de beneficiarios.
- 8 tests TF-OI-01 a TF-OI-08 (indicadores) — todos en verde.
- 7 tests TF-CP-01 a TF-CP-07 (cierre del plan) — todos en verde.
- Sin nuevas regresiones (4 fallos pre-existentes en PlanPageTest no son de esta sesión).

---

## Estado exacto del proyecto

- **Indicadores de objetivos**: modelo completo implementado. `IndicadorCatalogo` tiene unique constraint (uno por objetivo del catálogo). `PlanObjetivoIndicador` permite valoración en escala de 3 tipos.
- **GestionarObjetivos**: al crear un objetivo en el backoffice se crea automáticamente su indicador. La tabla muestra área temática y tipo de valoración.
- **PlanPage**: los objetivos generales y específicos muestran su indicador con radio buttons. Seleccionar un valor lo guarda inmediatamente (sin modal de motivo). El modal de cierre funciona con los 6 motivos reales.
- **MotivoCierre**: enum actualizado a 6 valores. La migración limpió valores obsoletos del enum anterior en la BD de desarrollo.
- **Fallos pre-existentes en PlanPageTest**: 4 tests (guardar diagnóstico activo pide motivo, motivo vacío no confirma, eliminar ficha activo pide motivo, cancelar cambio no persiste) fallan antes y después de esta sesión. Son deuda técnica pendiente.

---

## Siguiente paso concreto recomendado

1. Investigar y corregir los 4 tests pre-existentes de `PlanPageTest` que fallan (relacionados con el modal de motivo en plan activo — posible regresión de sesión anterior).
2. UI del plan: formulario para crear objetivos ex-novo con indicador (actualmente el modal solo crea objetivos sin indicador). Ver `BACKLOG.md` o sección 9 del módulo.
3. Propuesta automática de objetivos al añadir ficha al diagnóstico (drawer de fichas en `PlanPage`).
4. Verificar que `dompdf` está instalado: `composer show barryvdh/laravel-dompdf`.

---

## Contexto para retomar sin fricción

- `PlanObjetivo::instanciarIndicador()` requiere que la relación `objetivoCatalogo->indicador` esté cargada. Usar `with('objetivoCatalogo.indicador')` al crear objetivos desde el plan.
- `MotivoCierre::requiereConstanciaHistorial()` devuelve true para `negativa_firma` e `imposibilidad_localizacion` — la vista ya muestra el aviso correspondiente.
- El scope `deArea($tipoFichaId)` en `ObjetivoCatalogo` es el punto de entrada para la propuesta automática de objetivos al añadir una ficha.
- `confirmarCierrePlan()` hace `$this->plan->fresh()` (no unset) para mantener `historia_id` disponible en la vista post-cierre.
- Tests Livewire en `CierrePlanTest` requieren `Gate::before(fn () => true)` — usando el mismo patrón que `PlanPageTest`.
