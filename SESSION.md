# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-19

---

## Tarea completada

`Módulo Atención` — RegistroAtencion fase 1:

- Módulo `Modules/Atencion/` creado con ServiceProvider, migración, modelo, policy y factory.
- Tabla `registros_atencion` creada y migrada.
- Permisos `atencion.crear`, `atencion.leer`, `atencion.leer_ajeno` añadidos al PermisosSeeder.
- Permisos asignados en RolesSeeder: `intervencion` (los 3), `consulta_basica` (crear + leer), `supervision` y `tramitacion` (leer + leer_ajeno para supervision).
- Relaciones `registrosAtencion()` y `ultimaAtencion()` añadidas a `App\Models\Ciudadano`.
- `FichaCiudadanoPage` actualizado: modal de nueva atención, `abrirHistoriaSocial()`, historial de atenciones.
- Vista `ficha-ciudadano-page.blade.php` actualizada con botones acción, historial y modal.
- CSS de atenciones y modales añadido a `app-operativo.css`.
- 8 tests TF-AT-01 a TF-AT-08 (modelo) — todos en verde.
- 11 tests TF-LW-AT-01 a TF-LW-AT-11 (Livewire) — todos en verde.
- Sin regresiones en Ciudadania (99 tests en verde).

---

## Estado exacto del proyecto

- **Módulo Atención (fase 1)**: completado. Tipos `informacion` y `contacto` operativos. Tipo `actividad` definido en modelo pero sin UI ni generación automática (pendiente módulo Centro).
- **FichaCiudadanoPage**: muestra historial de atenciones, botones "Nueva atención", "Abrir historia social" / "Ver historia social" según rol y estado.
- **abrirHistoriaSocial()**: crea la HistoriaSocial con la UO activa del usuario y redirige a `intervencion.ciudadano.show`. Nota: si el user no tiene UO activa, `unidad_organizativa_id` será null (campo NOT NULL en la tabla → fallará). Pendiente manejar este caso edge.
- **dompdf**: verificar si está instalado. PlanPdfService lo requiere. `composer show barryvdh/laravel-dompdf`.
- **PlanPage**: UI completa del Plan de Intervención Social (implementado en sesión anterior). Objetivos y actuaciones en modo listado solo; botones "Añadir" sin formularios.

---

## Siguiente paso concreto recomendado

1. Ejecutar `php artisan db:seed --class=PermisosSeeder` y `php artisan db:seed --class=RolesSeeder` en producción (ya hecho en dev).
2. Implementar formularios de creación de objetivos y actuaciones dentro de `PlanPage`.
3. Conectar `cita_generada_id` en el formulario de atención cuando el módulo Agenda exponga una API de creación de citas.
4. Revisar el edge case de `abrirHistoriaSocial()` cuando el usuario no tiene UO activa (actualmente falla silenciosamente con FK constraint violation).

---

## Contexto para retomar sin fricción

- `RegistroAtencion::booted()` valida que tipo `informacion` tenga `profesional_id` y tipo `actividad` tenga `origen_tipo`/`origen_id`.
- `FichaCiudadanoPage` usa `#[Computed]` para `historialAtenciones()` → invalidar con `unset($this->historialAtenciones)` tras guardar.
- Ruta `intervencion.ciudadano.show` espera un parámetro `historia` (ID de HistoriaSocial, no ciudadano).
- Tests de Livewire en Ciudadania requieren `$this->seed(PermisosSeeder::class)` y `$this->seed(RolesSeeder::class)` en `setUp()`.
- `HistoriaSocial` tiene `AmbitoUoScope` — en tests usar `withoutGlobalScopes()` para consultarla directamente.
