# Autenticación — VIDA 360
## Documento de referencia · `docs/front/ui-autenticacion.md`

> Cubre la pantalla de login, la identidad visual del usuario dentro de la aplicación, el primer acceso y el onboarding mínimo. Para la implementación técnica del modelo `User` y el sistema de roles, ver `docs/modulo-usuarios-permisos.md`.

---

## 1. Login

### Comportamiento general

La autenticación se implementa con Laravel estándar. El identificador de acceso es el **correo electrónico** (`users.email`). No existe un campo de nombre de usuario separado.

La pantalla de login es la primera pantalla que ve cualquier usuario que no tiene sesión activa. No hay pantalla de bienvenida previa.

### Layout

Dos columnas:

**Columna izquierda** — identidad visual de la aplicación: logo y nombre de VIDA 360, descripción breve, píldoras con los módulos principales, nota de acceso restringido. Esta columna no tiene campos interactivos.

**Columna derecha** — formulario de autenticación con dos secciones separadas por un divisor:
- Campo de correo electrónico + campo de contraseña + botón "Entrar".
- Enlace "¿Olvidaste tu contraseña?" bajo el botón.
- Enlace de contacto con soporte al pie.

**Badge de entorno** en la esquina superior derecha del panel de formulario: muestra el entorno activo (`Producción` / `Staging` / `Demo`). Se configura mediante variable de entorno `APP_ENV_LABEL`.

### Campos y validación

El formulario valida en servidor. Los errores se muestran bajo el campo correspondiente con el texto estándar de Laravel. No es necesario documentar los mensajes de error exactos — se usan los mensajes por defecto del framework traducidos al español.

### Funcionalidad "he olvidado mi contraseña"

Flujo estándar de Laravel (envío de enlace por correo, formulario de restablecimiento). No requiere documentación adicional salvo que se desvíe del comportamiento por defecto.

---

## 2. Identidad del usuario dentro de la aplicación

### Principio

VIDA es una aplicación sin anonimato: todos los usuarios son personas identificadas con nombre y cargo. En consecuencia, **la aplicación muestra siempre el nombre real del profesional**, nunca el correo electrónico como identificador visual primario dentro de la interfaz.

El correo (`users.email`) es el identificador de autenticación. El nombre visible en la UI procede del campo `users.name` o, cuando está disponible, del perfil `Profesional` asociado.

### Nombre visible

| Contexto | Qué se muestra | Fuente |
|---|---|---|
| Avatar / topbar | Iniciales (2 caracteres) | `users.name` |
| Tooltip sobre avatar | Nombre completo + rol activo | `users.name` + rol Spatie activo |
| Log de accesos (ficha ciudadano) | "Tú (Nombre Apellido)" para el propio usuario; "Nombre Apellido · UTS" para otros | `users.name` |
| Auditoría y trazabilidad | Nombre completo | `users.name` |
| Cabecera de ciudadano (campo TSR) | Nombre abreviado (Inicial. Apellido) | `users.name` |
| Firma de apuntes | Nombre completo + cargo profesional | `Profesional.nombre_completo` + `Profesional.cargo` |

El campo `users.name` debe contener el nombre completo ("María Ruiz García"), no solo el nombre de pila. El backoffice de usuarios debe validar y orientar este formato en el alta.

### Avatar

El avatar es un círculo con las dos primeras iniciales del nombre completo, con color de fondo asignado de forma determinista según el ID del usuario (para que el mismo usuario siempre tenga el mismo color y sea reconocible visualmente). No se usan fotos de perfil en esta versión.

---

## 3. Primer acceso y onboarding

### Cuándo se activa

Solo en el primer login exitoso de un usuario después de que su cuenta ha sido creada. No se muestra en accesos posteriores.

### Contenido

Una pantalla única (no un carrusel ni un wizard) que muestra:
- Nombre del profesional y bienvenida personalizada.
- Centro de adscripción y rol activo — confirmación de que la cuenta está configurada correctamente.
- Enlace opcional a un tour rápido (si existe).
- Botón "Empezar" que lleva a la pantalla de inicio normal del rol.

### Lo que no hace el onboarding

No explica la aplicación en detalle, no enseña a usar las pantallas, no muestra capturas. Si la interfaz necesita explicación en el primer acceso, el problema está en el diseño de la interfaz, no en el onboarding.

---

## 4. Ayuda en contexto

El apoyo al usuario durante el uso normal no es un manual ni un centro de ayuda. Es información contextual integrada en la propia pantalla, presente donde hace falta y ausente donde no.

Patrones en uso:

**Nota de acceso** — línea discreta con icono de candado que aparece en cada vista donde el rol tiene restricciones. Informa de qué puede y qué no puede ver el usuario en ese contexto. No es un error, es información permanente. Ver `ui-guia-general.md` sección 8.4.

**Nota de irreversibilidad** — aparece antes de acciones que no se pueden deshacer (registrar un apunte en la historia, publicar una actividad). Texto conciso: "Esta acción no se puede deshacer."

**Texto de ayuda en campos** — para campos cuyo propósito no sea evidente (p.ej. `contexto_alta` en el registro de ciudadanos), una línea de texto en color terciario bajo el campo explica para qué sirve.

Lo que no se usa: tooltips emergentes, iconos de interrogación, modales de ayuda, enlaces a documentación externa desde la UI operacional.

---

## 5. Nota sobre documentación y formación de usuarios

Una aplicación bien diseñada para profesionales con formación técnica no debería requerir formación específica para tareas de uso diario. El nivel de apoyo que necesitará un usuario medio de VIDA es:

- **Onboarding** (ver sección 3): una pantalla, una sola vez.
- **Ayuda en contexto** (ver sección 4): integrada en las pantallas, no en un manual externo.
- **Guía rápida por rol**: un documento de una página imprimible por cada perfil principal (TSR, supervisión, mostrador). Describe qué puede hacer el rol, qué no puede hacer, y cómo ejecutar las tres o cuatro tareas más frecuentes. Se genera cuando las pantallas estén implementadas.
- **Acompañamiento de un compañero**: la curva de aprendizaje real ocurre en los primeros días de uso con alguien experimentado al lado. Ningún documento sustituye esto.

Lo que no se producirá en esta versión: manual de usuario completo, centro de ayuda en línea, vídeos formativos.

---

*Primera versión: mayo 2026.*
