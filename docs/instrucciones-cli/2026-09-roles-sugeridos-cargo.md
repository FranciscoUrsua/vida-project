# Instrucciones CLI — Roles sugeridos por cargo

> Fichero: `docs/instrucciones-cli/2026-09-roles-sugeridos-cargo.md`
> Módulos afectados: `Modules/Usuarios`, `app/Filament/Resources/` (cargos y usuarios)
> Tests: `TF-USU-RS-01` a `TF-USU-RS-06`
> **Sustituye** a `2026-09-habilitaciones-profesionales.md`, que queda descartado y **no debe ejecutarse**.

---

## Contexto

Se analizó un modelo de habilitación profesional por titulación y colegiación y **se ha descartado**.
VIDA no restringe actos profesionales por titulación: quién hace qué lo determinan la responsabilidad
profesional y los protocolos del centro, y la auditoría permite al supervisor detectar desviaciones.
Ver `docs/principios-vida360.md` §3.15 y `docs/modulo-usuarios-permisos.md` §1.6.

De aquel diseño solo sobrevive una comodidad de gestión: **sugerir roles a partir del cargo** al dar de
alta un usuario. Los roles se siguen asignando individualmente; la sugerencia **no tiene ningún efecto
sobre los permisos**.

Leer antes de empezar:
- `docs/modulo-usuarios-permisos.md` §1.2, §2.8 y §2.9 (ya actualizados con este diseño).
- `docs/principios-vida360.md` §3.3.

---

## Fase 0 — Reconocimiento (sin modificar ficheros)

Comprobar e informar:

1. Nombres exactos de los cargos existentes en `cargos`.
2. Cómo es hoy el formulario de alta y edición de usuario en Filament: cómo se elige el profesional y
   cómo se asignan roles (vía `UsuarioRol` con historial, aprobación previa y alertas).
3. Que ningún punto del código deduce roles o permisos del cargo. Si se encuentra alguno, informar y
   no tocarlo.
4. Que **no** existe ningún resto de implementación de habilitaciones (`habilitaciones_profesionales`,
   `HabilitacionService`, `habilitacion_snapshot`). Si existe, parar y consultar.

Si algo no coincide con estas instrucciones, parar y consultar.

---

## Fase 1 — Modelo

```
cargo_roles_sugeridos
- id
- cargo_id     FK cargos
- rol          varchar  — nombre del rol Spatie
- created_at, updated_at
unique (cargo_id, rol)
```

- Relación `Cargo::rolesSugeridos()`.
- Trait `Auditable`. No hace falta `Versionable`.
- Validar que `rol` existe en el catálogo de roles de Spatie.

---

## Fase 2 — Comportamiento

1. **Recurso de cargos en Filament:** selector múltiple «Roles sugeridos», con la nota «Se proponen al
   dar de alta a un usuario con este cargo. No otorgan permisos por sí mismos».
2. **Alta de usuario en Filament:** al elegir el profesional (y por tanto su cargo), el selector de
   roles se pre-rellena con los roles sugeridos. `adm_usuarios` puede quitar o añadir roles antes de
   guardar. Si el profesional no tiene cargo o el cargo no tiene sugerencias, el selector queda vacío.
3. **Los roles pre-rellenados siguen el flujo normal de asignación**, sin atajos: historial en
   `UsuarioRol`, aprobación previa para `supervision` y `adm_sistema`, alerta supervisada para el resto.
4. **Cambio de cargo de un usuario existente:** **no** se tocan sus roles. En la ficha del usuario en
   Filament se muestra el aviso «El cargo ha cambiado. Roles sugeridos para el nuevo cargo: …
   Revisa si procede ajustarlos». El aviso desaparece cuando alguien edita los roles del usuario o lo
   descarta.
5. **Ningún código fuera del formulario de alta y de ese aviso lee `cargo_roles_sugeridos`.**

---

## Fase 3 — Sugerencias iniciales

Seeder idempotente que **no sobrescribe** sugerencias ya configuradas:

| Cargo | Roles sugeridos |
|---|---|
| Directora de centro | `supervision`, `intervencion` |
| Trabajadora social | `intervencion` |
| Psicóloga | `intervencion` |
| Auxiliar de servicios sociales | `intervencion` |
| Administrativa | `tramitacion` |
| Abogada | *(sin sugerencia)* |

- Abogada no tiene sugerencia a propósito: en el CIAM trabaja con `intervencion` y en el SOJ con
  `consulta_profesional`.
- Si los cargos existen con otros nombres (Fase 0, punto 1), usar los existentes. Si alguno no existe,
  omitirlo e informar. **No crear cargos.**

---

## Tests funcionales

Añadir a los tests del módulo Usuarios. PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`),
Dado/Cuando/Entonces.

- **TF-USU-RS-01** — Al dar de alta en Filament un usuario cuyo profesional tiene el cargo «Directora
  de centro», el selector de roles aparece pre-rellenado con `supervision` e `intervencion`.
- **TF-USU-RS-02** — Si `adm_usuarios` quita un rol sugerido antes de guardar, el usuario se crea sin
  ese rol.
- **TF-USU-RS-03** — Un `supervision` pre-rellenado genera la solicitud de aprobación previa y no es
  efectivo hasta que se aprueba.
- **TF-USU-RS-04** — Cambiar el cargo de un usuario existente no modifica sus roles y muestra el aviso
  con los roles sugeridos del nuevo cargo.
- **TF-USU-RS-05** — Añadir o quitar roles sugeridos de un cargo no altera los roles de los usuarios
  que ya lo tienen.
- **TF-USU-RS-06** — Negativo: no se puede guardar como sugerido un rol que no existe.

---

## Qué NO hacer

- **No** implementar nada del modelo de habilitación profesional descartado.
- **No** asignar ni retirar roles automáticamente por cargo.
- **No** crear roles, perfiles ni cargos nuevos.

---

## Criterio de finalización

1. La Fase 0 está documentada en la respuesta de CLI.
2. Los tests `TF-USU-RS-01` a `TF-USU-RS-06` están en verde y `php artisan test Modules/Usuarios/tests/`
   pasa sin regresiones.
3. En `docs/modulo-usuarios-permisos.md` (ya actualizado con el diseño): quitar la marca «pendiente de
   implementación» de `cargo_roles_sugeridos`, ajustar nombres si la implementación difiere y añadir
   las referencias de código en §4.8. **No reescribir el resto del documento.**
4. Este fichero está en la tabla de `CLAUDE.md`, y `2026-09-habilitaciones-profesionales.md` **no**
   figura en ella.
5. Hay entrada en el CHANGELOG del mes y `SESSION.md` está actualizado.
