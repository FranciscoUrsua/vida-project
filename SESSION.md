# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-09-24

---

## Tarea completada

Mundo demo **«Prueba CIAM»** en modo aditivo (`demo:load`, etiqueta `TEST_CIAM`) y regla de dominio de **plan especializado con entrada directa** (`tipos_plan.admite_entrada_directa`). El mundo está implementado, testeado (TF-DEMO-CIAM-01 a 16) y **ya cargado en staging**.

Corrección posterior: quien tiene `supervision` + `adm_usuarios` (la directora del CIAM) entra tras el login en la supervisión operativa y no en `/admin`. La prioridad entre roles está centralizada en `User::destinoInicial()`.

Detalle en `CHANGELOG-092026.md` (entrada «Mundo demo "Prueba CIAM" en modo aditivo») y en `docs/instrucciones-cli/2026-09-demo-ciam-aditivo.md`, que incluye las decisiones de la sesión.

---

## Estado exacto del proyecto

- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.** Hoy rechaza `demo_ciam`, pero con cualquier otro mundo trunca staging.
- En esa BD compartida:
  - Ya están aplicadas las migraciones `create_demo_world_registros_table` y `add_admite_entrada_directa_to_tipos_plan_table`; `pia` tiene `admite_entrada_directa = true`.
  - El mundo `demo_ciam` ya está cargado: 980 registros TEST_CIAM. Una segunda carga crea 0.
- Cuentas CIAM (`*.ciam@vida.local`, contraseña = usuario sin dominio + `987`, p. ej. `dir.ciam@vida.local / dir987`, `ts2.ciam@vida.local / ts2987`). Sus roles coinciden exactamente con la tabla 5.2 de las instrucciones.
- **Código de staging** (`/var/www/vida-project/vida`): desplegado a `origin/master` en esta sesión (ver commit de hoy).
- **Tests:**
  - `tests/Feature/Demo/`: 20 passed y 5 incomplete (ya existían).
  - `Modules/Intervencion/tests/`: 261 passed y 1 fallo pre-existente (`AccesosExpedienteTest::acceso_de_otra_uo_con_accion_ver_tiene_clase_sospechoso`, que falla igual en master).

### Comandos para staging (referencia; la carga ya está hecha)

```bash
cd /var/www/vida-project/vida
php artisan migrate --force                           # ya aplicado
php artisan demo:load --world=demo_ciam --dry-run     # simula y hace rollback
php artisan demo:load --world=demo_ciam               # carga real (idempotente: repetirla crea 0)
```

Consultar lo creado: `DemoWorldRegistro::de('TEST_CIAM')`. No existe comando de purga: la retirada está pendiente de diseño (BACKLOG).

---

## Siguiente paso concreto recomendado

1. **Separar la BD de desarrollo local de la de staging** (p. ej. `vida_dev`) antes de volver a usar `demo:reset` o migraciones experimentales en local.
2. **Flujo de creación de planes especializados en la UI:** `PlanPage::crearNuevoPlan()` guarda siempre `tipo = general_asp`, así que un PIA creado desde la interfaz se trata como PISO. Derivar `tipo` del `ambito` del tipo de plan y diseñar la entrada directa (BACKLOG).
3. Decidir si hace falta asociar tipos de plan a centros/UO/servicios (punto 1.4 omitido, BACKLOG).
4. Pendientes de sesiones anteriores, sin cambios: bug de `User::booted()` (`name` = email), la suite de `Modules/Agenda` rota (`tipos_slot.horario_centro_id`) y `AccesosExpedienteTest`.

---

## Contexto para retomar sin fricción

- **Idempotencia de mundos aditivos:** cada entidad se crea a través de `DemoRegistrador::obtenerOCrear(clave, Modelo, fn)`. Las decisiones del azar que determinan *qué* se crea deben salir de `DemoContextoAditivo::decidir()`, no de `mt_rand`: en la segunda carga no se ejecutan los cierres de creación y la secuencia aleatoria se desplazaría. Las fechas y los textos sí pueden usar `mt_rand` o Faker.
- **No reordenar los `escenarios` de `demo_ciam.yaml`:** las claves `usuaria_NNN` dependen de ese orden.
- `User::booted()` auto-asigna `consulta_basica` a los usuarios creados con `profesional_id` y sin roles. Por eso los builders de demo crean primero el usuario y vinculan el profesional después.
- La regla «plan especializado ⇒ `plan_asp_id`» vive ahora en el modelo (`PlanDeIntervencion::verificarOrigenPlanEspecializado()`). Antes solo existía en el invariante INV-02 de demo.
