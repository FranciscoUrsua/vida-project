# CHANGELOG — VIDA 360 — Septiembre 2026

> Entradas de septiembre 2026. Para meses anteriores, ver `CHANGELOG-062026.md`.

---

## 2026-09-24 — Pequeños cambios en Filament: tema, pie de informe, logo de organización, borrado de usuario

### Módulos afectados
`app/Providers/Filament`, `Modules/Documentos`, `Modules/Organizacion`, `Modules/Usuarios`, `app/Filament/Resources`

### Añadido

**Filament — tema:**
- `AdminPanelProvider::panel()` — `->darkMode(false)`. Elimina el selector claro/oscuro (menú de usuario y layout base de Filament); el panel queda fijo en modo claro. Motivo: inconsistencias de UI en modo oscuro (`docs/design-system/SKILL.md` ya indicaba no producir variantes oscuras salvo petición explícita).

**Documentos — número de página en el pie de informe:**
- `EstiloInforme::MARCADOR_NUMERO_PAGINA` (`'{{ numero_pagina }}'`) — nueva constante.
- `EstiloInformeResource` — botón «Insertar número de página» (`hintAction`) en el campo del pie, añade el marcador al texto.
- `ServicioGeneracionPDF::generarBorrador()` — detecta el marcador, lo retira del HTML y dibuja el número real por página con `Canvas::page_text()` de dompdf (única vía sin habilitar evaluación de PHP embebido en el HTML, que sería una vulnerabilidad ya que el pie lo edita libremente el supervisor).
- Tests: `test_tf_doc_26_...` y `test_tf_doc_27_...` en `DocumentosTest.php`.

**Documentos — logo único de organización:**
- `Modules\Organizacion\Models\Configuracion::logoPathAbsoluto()` — resuelve a ruta de fichero absoluta (no URL) el logotipo ya configurable en Sistema → Configuración → «Identidad visual» (clave `logo_path`), para que dompdf pueda incrustarlo.
- `ServicioGeneracionPDF::generarBorrador()` — sobreescribe siempre `estilo['logo_cabecera']` con ese logo global, ignorando el valor por UO de `EstiloInforme`.
- `EstiloInformeResource` — se retira el campo de ruta manual al logotipo (`logo_cabecera`) y la columna del listado; se añade un aviso que enlaza a dónde gestionarlo.
- `ListConfiguracion` («Identidad visual») — texto actualizado: el logo ahora se usa también en la cabecera de los informes PDF, no solo en el sidebar.
- Módulo `Organizacion` dado de alta en el test runner (no lo estaba): `composer.json` (`autoload-dev` PSR-4) y `phpunit.xml` (testsuite `Feature`).
- Tests: `Modules/Organizacion/tests/Feature/ConfiguracionTest.php` (nuevo, 3 tests) + `test_tf_doc_29_...` en `DocumentosTest.php`.

**Usuarios — borrado de usuario es soft delete:**
- Bug reportado: borrar un usuario desde Filament lanzaba `QueryException` (FK `usuario_uo.usuario_id` con `onDelete('restrict')`).
- `User` — trait `SoftDeletes`. Migración `add_deleted_at_to_users_table`. `UsuarioResource::DeleteAction` ya hacía lo correcto en cuanto el modelo tuvo `SoftDeletes` (sin cambios en la action).
- Efecto colateral corregido en la misma sesión: el índice único de `email` bloqueaba para siempre el email de un usuario borrado. Migración `make_users_email_unique_index_exclude_soft_deleted` (índice único parcial de PostgreSQL, `WHERE deleted_at IS NULL`) + `UsuarioResource` con `modifyRuleUsing` en la validación `unique()` del email.
- Tests: `Modules/Usuarios/tests/Feature/UsuarioSoftDeleteTest.php` (nuevo, 5 tests).

### Decisiones de implementación

- Ver `docs/decisiones-tecnicas.md` Sección 12 (logo único de organización) y Sección 13 (soft delete en `users`).
- El campo `logo_cabecera` de `EstiloInforme` (columna y resolución jerárquica en `ResolverEstiloInforme`) no se elimina del esquema ni del código — solo deja de exponerse en el formulario. Revisar si en el futuro se necesita volver a un logo por UO.
- No se añade cascada de soft delete desde `User` hacia `usuario_uo`/`usuario_rol`/roles de Spatie: esas filas se conservan (es el comportamiento deseado, mantiene el historial).
- No se añaden `TrashedFilter`/`RestoreAction`/`ForceDeleteAction` en `UsuarioResource`: ningún otro resource del proyecto los tiene (patrón existente).

### Pendiente (ver `BACKLOG.md`)

- Bug pre-existente detectado durante la verificación de esta sesión (no introducido por ella, no corregido por estar fuera de alcance): `User::booted()` sobreescribe `name` con el email de forma incondicional, lo que rompe TF-AUTH-16 y TF-AUTH-17 (`tests/Feature/Auth/AutenticacionTest.php`). Confirmado reproducible en `master` antes de esta sesión.
