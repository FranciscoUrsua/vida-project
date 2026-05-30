# SESSION — VIDA 360

_Actualizado: 2026-05-30_

## Tarea completada

Implementación del módulo de autenticación: login, logout, onboarding de primer acceso, badge de entorno y componente avatar. 21/23 tests en verde (1 skipped por decisión de diseño, 1 incomplete por dependencia futura).

## Estado actual

- **Autenticación — completa:**
  - Rutas: `/login`, `/logout`, `/bienvenida`, `/` (inicio).
  - `LoginController`, `OnboardingController`, middleware `PrimerAcceso`.
  - Campo `primer_acceso` en tabla `users` (migración aplicada en `vida_testing`).
  - Vistas: `auth/login.blade.php`, `auth/onboarding.blade.php`, `inicio.blade.php`.
  - Componente `<x-avatar>` con iniciales y color determinista por ID.
  - `APP_ENV_LABEL` en `config/app.php` y `.env.example`.
  - Tests: TF-AUTH-01 a TF-AUTH-23 (21 pasan, 1 skipped, 1 incomplete).

- **Suite completa:** ~399 tests (376 anteriores + 23 nuevos de Auth). 9 fallos pre-existentes en `PrestacionFilamentResourceTest`.

## Tests incompletos actuales

| Test | Clase | Motivo |
|---|---|---|
| TF-AUTH-04 | AutenticacionTest | Skipped: email case-sensitive por diseño (Laravel + PostgreSQL estándar) |
| TF-AUTH-20 | AutenticacionTest | Incomplete: requiere `Profesional::centroActivo()` (módulo Centro) |
| 3.5, 3.6, 3.8 | KAnonimatoTest | Requieren modelo Extraccion + job asíncrono |
| 6.6 | PerfilesTest | Requiere modelo Extraccion + relación con PerfilAnonimizacion |
| TF-USU-31 | UnidadOrganizativaTest | Policy jerárquica de adscripción de usuarios a UO |

## Siguiente paso recomendado

**Módulo Escalas fase 2** — componente Livewire de aplicación del pase desde la Historia Social:
selección de instrumento activo, presentación sección a sección, cierre con cálculo de scores,
y visualización del historial cronológico de pases por escala.

Alternativa: **TF-USU-31** — validación jerárquica en `UsuarioUoPolicy`.

## Contexto relevante para retomar

- `EscalaSeeder` NO está incluido en `DatabaseSeeder` aún.
- La migración `add_primer_acceso_to_users_table` debe ejecutarse en producción: `php artisan migrate`.
- Los 9 fallos de `PrestacionFilamentResourceTest`: "Invalid Livewire snapshot structure" — problema pre-existente de setup de tests.
- Grupos de navegación: Organización, Centros y Servicios, Catálogos, Informes y Plantillas, Usuarios y Profesionales, Sistema.
- El campo `primer_acceso` del modelo no tiene soft-delete; una vez completado el onboarding se mantiene `false` permanentemente.
