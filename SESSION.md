# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-23

---

## Tarea completada

`Módulo Intervención` — Modal "Añadir objetivo" con catálogo:

- `PlanPage`: nueva propiedad `modoObjetivo` ('catalogo' | 'libre') y array `objetivosCatalogoSeleccionados`.
- `PlanPage`: computed `objetivosCatalogo()` — carga generales activos del catálogo filtrados por `tipo_plan_id` del plan, con específicos e indicadores. Solo se ejecuta cuando el modal está abierto.
- `PlanPage`: método `guardarObjetivosDesdeCatalogo()` — instancia los objetivos seleccionados en el plan, crea sus específicos y hereda los indicadores del catálogo (`instanciarIndicador()` vía `setRelation`).
- `plan-page.blade.php`: modal rediseñado con dos tabs (botones toggle). Tab "Del catálogo" muestra generales con checkbox y sub-lista de específicos; deshabilita los ya añadidos al plan. Tab "Objetivo libre" mantiene el textarea original.
- Sin regresiones: 4 fallos pre-existentes de PlanPageTest siguen igual; 8 tests ObjetivosIndicadores en verde; 7 tests CierrePlan en verde.

---

## Estado exacto del proyecto

- **Modal de objetivos**: cuando hay objetivos en el catálogo para el tipo de plan, se presenta la lista con checkboxes. Los ya añadidos aparecen deshabilitados con badge "ya añadido". Si el catálogo está vacío para ese tipo de plan, se indica y se puede usar el modo libre.
- **Fallos pre-existentes en PlanPageTest**: 4 tests (guardar diagnóstico activo pide motivo, motivo vacío no confirma, eliminar ficha activo pide motivo, cancelar cambio no persiste) siguen fallando — deuda técnica pendiente.
- **Resto del módulo**: sin cambios.

---

## Siguiente paso concreto recomendado

1. Investigar y corregir los 4 tests pre-existentes de `PlanPageTest` (relacionados con el modal de motivo en plan activo).
2. Propuesta automática de objetivos al añadir ficha al diagnóstico (scope `deArea($tipoFichaId)` en `ObjetivoCatalogo` es el punto de entrada).
3. Verificar que `dompdf` está instalado: `composer show barryvdh/laravel-dompdf`.

---

## Contexto para retomar sin fricción

- `guardarObjetivosDesdeCatalogo()` usa `$planObjetivo->setRelation('objetivoCatalogo', $objCatalogo)` antes de `instanciarIndicador()` para evitar N+1 y garantizar que la relación está cargada en el modelo recién creado.
- El computed `objetivosCatalogo` se invalida explícitamente con `unset($this->objetivosCatalogo)` en `abrirModalObjetivo()` para forzar recarga fresca cada vez que se abre.
- `$this->plan->tipo_plan_id` es el FK al modelo `TipoPlan`; `ObjetivoCatalogo` tiene el mismo FK. Sin `tipo_plan_id` en el plan, el catálogo devuelve colección vacía y el modal sugiere modo libre.
