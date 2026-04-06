# CHANGELOG — VIDA 360

Registro de cambios agrupado por módulo y área funcional, en orden cronológico descendente.

---

## [Sin versión] — 2026-03-25

### Documentación general
- Generación de `documentacion-proyecto.md` completa con arquitectura, decisiones y convenciones.
- PHPDoc completos añadidos a modelos de Centro, Mensajes y Prestaciones.

### Infraestructura / Arquitectura
- Corrección de incoherencias estructurales entre módulos y app raíz (namespaces, rutas de autoload, providers).

---

## Módulo Mensajes — 2026-03-24

### Nuevas funcionalidades
- Implementación completa de mensajería interna entre profesionales.
- Sistema de alertas del sistema.
- Resource de Filament para gestión de mensajes y alertas desde el backoffice.

---

## Módulo Prestaciones — 2026-03-18

### Nuevas funcionalidades
- Migraciones, modelos y relaciones para gestión de prestaciones sociales.
- Resource de Filament con formulario reactivo (campos dependientes con `Get`).
- Tests de integración para el módulo.

### Correcciones
- Namespace incorrecto de `Get` en `PrestacionResource` para Filament v5 (`Filament\Schemas\Components\Utilities\Get`).

---

## Módulo Centro — 2026-03-17

### Nuevas funcionalidades
- Migraciones para centros, tipos de centro, colecciones de plazas, actividades y espacios.
- Modelos Eloquent con relaciones (Centro, Red, TipoEspacio, TipoActividad, SegmentoPoblacion).
- Resources de Filament: `CentroResource` (con RelationManager `ColeccionesPlazas`), `RedResource`, `TipoEspacioResource`, `TipoActividadResource`, `SegmentoPoblacionResource`.
- Grupos de navegación Filament: `Centros` y `Catálogos de centros`.

---

## Módulo Usuarios / Organización — 2026-03-11 a 2026-03-13

### Nuevas funcionalidades
- Creación de módulos `Organizacion` y `Usuarios` con `nwidart/laravel-modules` v12.
- Entidad `Profesional` con datos técnicos del trabajador social; relación con `User` mediante `profesional_id` nullable.
- Entidades de organización: `UnidadOrganizativa` (árbol autorreferencial con CTE recursiva), `Cargo`, `Titulacion`, `TipoRelacionProfesional`.
- Tabla `usuario_uo` (adscripción UO) y `usuario_rol` (historial de roles con flujo de aprobación: `pendiente_aprobacion → activo → inactivo`).
- Traits: `TieneUO`, `TieneRoles`.
- Policies para `HistoriaSocial` y `Apunte`, registradas en `UsuariosServiceProvider`.
- `RolResource` en Filament para supervisión de roles.
- Resources adicionales de Filament para modelos sin backoffice previo.

### Refactorizaciones
- Renombrado tabla `usuario_uo_rol → usuario_uo`.
- Corrección de autoload PSR-4 para seeders, migrations y factories de módulos (mapeos específicos por módulo).
- Corrección de inconsistencias de namespaces en módulos.

### Correcciones
- Namespace de `EditAction`, `DeleteAction`, `CreateAction` corregido a `Filament\Actions` (Filament v5).
- Seeder de usuario admin: uso de `User::create()` en lugar de `factory()` (sin faker en prod), inclusión de `email_verified_at`, eliminación de `bcrypt()` manual (el cast `hashed` del modelo lo gestiona).

---

## Panel de administración Filament — 2026-03-10 a 2026-03-11

### Nuevas funcionalidades
- Instalación del panel Filament v5 con `AdminPanelProvider`.
- 8 recursos CRUD iniciales para gestión de `UnidadOrganizativa` y `Usuario`.
- Grupos de navegación: `Organización`, `Profesionales`.

---

## Módulo Ciudadanos (SocialUsers) — 2025-11-13 a 2025-11-14

### Nuevas funcionalidades
- Modelo `Ciudadano` / `SocialUser` con cifrado AES-256 transparente en todos los campos PII (nombres, DNI, dirección, contacto, coordenadas) mediante cast `'encrypted'` de Laravel.
- Flag `requiere_permiso_especial` para colectivos protegidos (menores, víctimas de violencia de género); comprobación en controladores via `can()`.
- Trait `ValidatesIdentification`: validación de NIF/NIE/pasaporte español.
- Servicio `DomicilioValidatorService`: integración con callejero municipal de Madrid para validación de domicilios.
- Versionado automático de documento de identidad mediante trait `Versionable` (snapshot JSON en tabla `versiones`).

### Auditoría
- Sistema de auditoría propio (`AuditService` + trait `Auditable`) en sustitución de `owen-it/laravel-auditing`.
- Trazabilidad RGPD completa: operaciones CRUD registradas en tabla `audits` con valores anteriores/nuevos, usuario actuante, IP y user agent.
- Cifrado de `old_values` en tabla `audits`.

### Refactorizaciones
- Modelos `Audit` y `Version` movidos a `App\Common`.
- Eliminación de tabla `calles_numeros` (reemplazada por servicio externo).

---

## Módulo Centro (versión inicial) — 2025-11-13 a 2025-12-09

### Nuevas funcionalidades
- Modelos `Centro`, `Director`, `TipoCentro`, `CentroProfesional` con seeders de datos iniciales.
- Controladores API: centros, directores.
- Campo `formatted_address` en tabla `centros` generado por `DomicilioValidatorService`.
- Relación polimórfica de versionado aplicada a `Centro`.

---

## Módulo Profesionales (versión inicial) — 2025-11-13

### Nuevas funcionalidades
- Modelo `Profesional` con validación de documento de identidad.
- Seeders de profesionales de prueba.

---

## Módulo Organización / Estructura territorial — 2025-11-13

### Nuevas funcionalidades
- Modelo `Distrito` con controlador.
- Lógica de estandarización de países y comunidades autónomas españolas.
- Migraciones refactorizadas con numeración de orden de dependencia.

---

## Módulo Historia Social / Intervención — 2025-11-04 a 2025-11-05

### Nuevas funcionalidades (borrador)
- Módulo `Intervencion` con modelo `Historia` y ruta API.
- Borradores de `Fichas` y `Valoraciones`.

---

## Infraestructura y configuración inicial — 2025-10-22 a 2025-12-05

### Setup del proyecto
- Scaffolding inicial Laravel (renombrado carpeta `backend/ → vida/`).
- Configuración de CI/CD (GitHub Actions → rama `master`).
- Bootstrap 5 + VIDA Logo + pantalla de login/bienvenida.
- Prototipo inicial de dashboard.
- Header y footer Blade (`layouts/app.blade.php`, partials).
- Documentación: `README.md`, `LICENSE.md`, `NOTICE.md`, `CLAUDE.md`, `principios-vida360.md`.
- Principios técnicos de desarrollo y convenciones de abstracción.

---

## Notas sobre convenciones del proyecto

- **Keep-Alive**: soft deletes en todas las entidades sensibles; no hay hard deletes.
- **Filament Resources centralizados** en `app/Filament/Resources/` (decisión arquitectónica deliberada).
- **Tests**: usan PostgreSQL (`vida_testing`), no SQLite.
- **Módulos nwidart v12**: código en `Modules/NombreModulo/app/`; providers en `bootstrap/providers.php`.
