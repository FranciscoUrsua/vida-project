# SESSION — VIDA 360

_Actualizado: 2026-05-23_

## Tarea completada

Seeders de todos los módulos revisados y corregidos: nuevo CentroSeeder completo,
tres seeders huérfanos conectados al DatabaseSeeder, e IntervencionSeeder corregido
para ser idempotente.

## Estado actual

- **Seeders:** todos los módulos tienen seeder y están registrados en DatabaseSeeder.
  - `CentroSeeder` — nuevo: 7 tipos de espacio, 6 tipos de actividad, 6 segmentos de
    población, 3 centros de ejemplo (Albergue San Isidro, Albergue Vallecas, Centro de
    Día Retiro), 1 red (Red de Albergues Municipales con los dos albergues).
  - `DatabaseSeeder` — añadidos en orden: CentroSeeder, CatalogosSistemaSeeder,
    PrestacionesSeeder, AgendaSeeder (que ya dependía de centros), DocumentosSeeder,
    IntervencionSeeder.
  - `IntervencionSeeder` — corregido: `create()` → `firstOrCreate()` en todos los modelos.
  - Suite completa idempotente: `db:seed` puede ejecutarse múltiples veces sin duplicados.
- **Anonimización:** implementada al 100% (ver sesión 2026-05-22).
- **Suite completa:** 332 tests pasan ✅ — 0 fallos — 5 incompletos.

## Tests incompletos actuales

| Test | Clase | Motivo |
|---|---|---|
| 3.5, 3.6, 3.8 | KAnonimatoTest | Requieren modelo Extraccion + job asíncrono |
| 6.6 | PerfilesTest | Requiere modelo Extraccion + relación con PerfilAnonimizacion |
| TF-USU-31 | UnidadOrganizativaTest | Policy jerárquica de adscripción de usuarios a UO |

## Siguiente paso recomendado

**TF-USU-31** sigue siendo el desbloqueador más simple: implementar la validación jerárquica
en `UsuarioUoPolicy` sin infraestructura nueva.

Alternativa: **módulo Ciudadanía** — el modelo `Ciudadano` es un stub; implementarlo completo
(alta, motor de matching, unidades de convivencia) desbloquea los tests de k-anonimato
pendientes y es el paso natural para activar los módulos de Intervención y Agenda.

## Contexto relevante para retomar

- Los centros de ejemplo del seeder usan como UO el "Departamento de Atención Primaria"
  (los albergues) y las UOs "CSS Arganzuela" / "CSS Retiro" (el Centro de Día). Si se
  resetea la BD, `db:seed` recrea todo en el orden correcto.
- Los perfiles de anonimización usan `apellido1`/`apellido2` (no `apellidos` del JSON del spec).
- El alias seudonimizado se computa como `CIU-{HMAC-SHA256(id, APP_PSEUDONYM_KEY)[0..7]}`.
