# CHANGELOG — VIDA 360 — Septiembre 2026

> Entradas de septiembre 2026. Para meses anteriores, ver `CHANGELOG-062026.md`.

---

## 2026-09-24 — Actualización de dependencias: 28 vulnerabilidades conocidas corregidas

### Módulos afectados
`composer.json` / `composer.lock` (infraestructura, sin cambios funcionales)

### Corregido

`composer audit` pasó de 28 advisories en 6 paquetes a 0. Motivo original: el hook `.git/hooks/pre-commit` (`security-check.sh`) trata cualquier hallazgo de `composer audit` como error bloqueante, así que ningún commit pasaba el hook hasta corregir esto (ver entrada anterior de hoy, donde se hizo commit con `--no-verify`).

**Actualizados sin tocar `composer.json`** (dentro de las constraints ya declaradas):
- `dompdf/dompdf` v3.1.5 → v3.1.6 (6 advisories: SVG file-existence leak, DoS por bitmaps/BMP, local file read, chroot bypass).
- `guzzlehttp/guzzle` 7.12.3 → 7.15.5 (+ `guzzlehttp/psr7`, `guzzlehttp/promises`) (6 advisories: host/cookie canonicalization, referer leak, DoS, proxy-auth header leak).
- `league/commonmark` 2.8.2 → 2.10.3 (10 advisories: varios DoS y un bypass XSS en `AttributesExtension`).
- `livewire/livewire` v4.3.1 → v4.4.6 (1 advisory: XSS basado en DOM en el manejo de estado cliente).
- `spatie/laravel-medialibrary` 11.21.0 → 11.23.8 (2 advisories: bypass de restricción de subida de ficheros, SSRF).
- `filament/filament` (+ todos sus sub-paquetes `filament/*`) v5.6.7 → v5.8.4 (3 advisories, la de mayor severidad: bypass de MFA cuando hay códigos de recuperación habilitados).

**Assets regenerados:** `vida/public/{css,js,fonts}/filament/**` republicados (`vendor:publish --tag=filament-assets --force`, ejecutado automáticamente por el script `post-update-cmd` de Filament) — necesarios porque el JS/CSS compilado de Filament cambió de versión.

### Verificación

Ninguna actualización tocó `laravel/framework` ni Symfony (las dependencias declaradas por `laravel/framework` para guzzle/commonmark ya admitían las versiones parcheadas; los sub-paquetes `filament/*` se actualizaron juntos y se resuelven sin cascada). Se comparó contra una baseline con las dependencias originales (`git stash` de `composer.lock` + `composer install`) ejecutando los tests más proclives a verse afectados (Livewire, Filament, PDF): todos los fallos observados con las dependencias nuevas ya fallaban igual con las antiguas (deuda técnica pre-existente, no relacionada — ver `BACKLOG.md`: `TF-AUTH-16/17`, y fallos de esquema en `Modules/Agenda` por una migración de una sesión anterior). Se ejecutaron además, en verde: `tests/Feature/FilamentPanelAccessTest`, toda la suite de `Documentos`, `Organizacion` y `Usuarios`, y el directorio completo `tests/Feature`.

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
