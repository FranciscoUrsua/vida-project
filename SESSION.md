# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-18

---

## Tarea completada

Modelo completo del Plan de Intervención Social (PISO):

- 4 migraciones nuevas: `tipos_plan`, expansión de `planes_intervencion` (tipo_plan_id, diagnostico_social, periodicidad_seguimiento), tablas de contenido (objetivos, actuaciones, participantes, historial de cambios), FK documento_firmado en firmas_plan.
- Seeder `TipoPlanSeeder`: 5 tipos del sistema (asp_general, esp_familia_infancia, esp_violencia_genero, esp_mayores, esp_inclusion), idempotente, no eliminables.
- 7 modelos nuevos: `TipoPlan`, `ObjetivoCatalogo`, `PlanObjetivo`, `PlanActuacionAyuntamiento`, `PlanActuacionCiudadano`, `PlanParticipante`, `PlanCambio`.
- `PlanDeIntervencion` actualizado con relaciones y método `registrarCambio()`.
- `TipoPlanResource` en `app/Filament/Resources/` con página `GestionarObjetivos`.
- `PlanPdfService` con vista Blade para generar PDF del plan con dompdf.
- `CiudadanoPage.php` añadido `generarPdfPlan()`.
- `PrestacionFactory` creada y añadido `HasFactory` a `Prestacion`.
- 17 tests TF-PLAN-01 a TF-PLAN-17 — todos en verde.

---

## Estado exacto del proyecto

- **Módulo Intervención**: modelo completo del plan implementado. Faltan las UIs de creación/edición del plan dentro de CiudadanoPage.
- **dompdf**: verificar si está instalado. PlanPdfService lo requiere. Si no: `composer require barryvdh/laravel-dompdf`.
- **TipoPlanResource**: visible en Filament bajo grupo "Catálogos" (navigationSort: 10).
- **Resto de módulos**: sin cambios en esta sesión.

---

## Siguiente paso concreto recomendado

1. Verificar que dompdf está instalado: `composer show barryvdh/laravel-dompdf` (si falla, instalar).
2. Ejecutar `php artisan db:seed --class=Modules\\Intervencion\\Database\\Seeders\\TipoPlanSeeder` en el entorno de desarrollo para cargar los 5 tipos del sistema.
3. Iniciar la UI del plan en `CiudadanoPage`: sección de diagnóstico social, objetivos y actuaciones — ver BACKLOG entrada "UI del Plan de Intervención en CiudadanoPage" (2026-06-18).

---

## Contexto para retomar sin fricción

- `TipoPlan` (modelo) usa FQCN `\Modules\Intervencion\Models\TipoPlan::class` en las relaciones de `PlanDeIntervencion` para evitar colisión con el enum `Modules\Intervencion\Enums\TipoPlan`.
- `PlanActuacionAyuntamiento::booted()` lanza `LogicException` si `prestacion_id` es null al guardar.
- `TipoPlan::booted()` lanza `LogicException` si se intenta eliminar un tipo con `eliminable = false`.
- El `slug` de `TipoPlan` es inmutable una vez creado (campo deshabilitado en el formulario Filament al editar).
- `GestionarObjetivos` usa `protected string $view` (no static) porque `Filament\Pages\Page::$view` es no-estático.
