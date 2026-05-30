# UI Operacional — VIDA 360
## Guía de diseño de interfaces · Primera versión

> Este documento recoge las decisiones de diseño del interfaz operacional de VIDA 360 (la parte que usan los profesionales en su trabajo diario, distinta del backoffice de configuración en `/admin`). Debe leerse junto a `docs/modulo-usuarios-permisos.md` y `docs/modulo-ciudadania.md`.

---

## 1. Principios generales

**El interfaz habla el lenguaje del profesional, no del modelo de datos.** Un trabajador social no trabaja con "apuntes de tipo entrevista" — trabaja con entrevistas, escalas, derivaciones. La UI nombra las cosas como las nombran quienes las usan.

**Cada rol tiene su pantalla de inicio natural.** El TSR llega a su lista de casos. El supervisor llega al resumen operativo del centro. El administrativo llega a la agenda del día. No existe una pantalla de inicio genérica.

**El contexto del ciudadano es siempre visible.** Al trabajar con un expediente, los datos de identificación del ciudadano y la historia reciente nunca desaparecen de la pantalla. Las herramientas de trabajo conviven con el contexto, no lo sustituyen.

**Las acciones destructivas o irreversibles son explícitas.** Registrar un apunte en la historia social (pasado inmutable) es un acto deliberado distinguido del guardado de borrador. La UI deja claro cuándo algo es definitivo.

**La privacidad es visible, no solo técnica.** Los profesionales ven explícitamente a qué tienen y no tienen acceso. El log de accesos al expediente es parte de la pantalla normal, no una funcionalidad de auditoría enterrada.

---

## 2. Acceso y autenticación

**URL:** `dominio.ext` (raíz). El backoffice ocupa `/admin`. No existe subfolder `/vida` ni ningún otro prefijo — la aplicación operacional es la aplicación principal.

**Pantalla de login:** layout de dos columnas. La columna izquierda muestra la identidad visual de la aplicación (logo, nombre, descripción breve, píldoras con los módulos principales) y el aviso de acceso restringido. La columna derecha contiene el formulario de autenticación.

**Orden de autenticación:**
1. Botón prominente de acceso con cuenta corporativa (SSO / directorio LDAP municipal) — flujo normal para la mayoría de usuarios.
2. Separador con credenciales locales (email + contraseña) — fallback para entornos externos al Ayuntamiento o cuando el directorio no está disponible.
3. Enlace de recuperación de contraseña y contacto con soporte.

**Badge de entorno** en la esquina del panel de login (`Producción` / `Staging` / `Demo`) — permite distinguir instancias cuando hay varias abiertas simultáneamente.

**Primera sesión:** si es el primer acceso del profesional, tras el login se muestra un onboarding mínimo de una sola pantalla: centro asignado, rol activo, y enlace opcional a un tour rápido. No es una pantalla de bienvenida — es una confirmación de contexto.

---

## 3. Estructura de navegación

### 3.1 Layout general

Todas las pantallas operacionales siguen el mismo esqueleto:

```
┌─────────────────────────────────────────────────────┐
│  Topbar (42px) — breadcrumb, contexto, avatar       │
├──────┬──────────────────────────────────────────────┤
│      │                                              │
│ Nav  │           Área de contenido                 │
│ (44) │                                             │
│      │                                              │
└──────┴──────────────────────────────────────────────┘
```

**Sidenav izquierdo colapsado (44px):** iconos de módulo sin etiqueta. Tooltip al pasar el cursor. Los módulos disponibles varían según el rol — los iconos simplemente no aparecen si el rol no tiene acceso. No se muestran candados ni elementos desactivados para funcionalidades fuera del alcance del rol.

**Topbar (42px):** breadcrumb de navegación a la izquierda, contexto del centro y avatar del profesional a la derecha. En pantallas de ciudadano, el nombre del ciudadano activo aparece en el breadcrumb.

### 3.2 Módulos por rol en el sidenav

| Icono | Módulo | Roles que lo ven |
|---|---|---|
| `ti-layout-dashboard` | Inicio / resumen | Todos |
| `ti-folder-open` | Casos / expediente | `intervencion`, `supervision` (con rol intervención) |
| `ti-building-community` | Centro / supervisión | `supervision` |
| `ti-calendar` | Agenda | `intervencion`, `tramitacion`, `consulta_basica` |
| `ti-clipboard-list` | Tramitación | `tramitacion` |
| `ti-door-enter` | Recepción / SIA | `consulta_basica` |
| `ti-users-group` | Actividades | `supervision`, `intervencion` |
| `ti-bell` | Alertas | Todos |
| `ti-settings` | Ajustes | Todos (siempre al fondo) |

---

## 4. Vista del ciudadano (rol intervención — TSR)

Es la pantalla de trabajo principal del TSR. Se organiza en dos columnas.

### 4.1 Columna izquierda: contexto del ciudadano

La columna izquierda (≈280px fija) es de solo lectura y proporciona contexto permanente mientras se trabaja. Se divide en tres franjas verticales:

**Franja 1 — Identidad (siempre visible):**
- Avatar con iniciales, nombre completo, edad, estado del documento de identidad y UTS.
- Badges de estado: "Historia abierta", "Plan activo", "Revisión pendiente", etc.
- Datos de contacto mínimos en píldoras compactas: teléfono, email, TSR asignado.

**Franja 2 — Log de accesos y Unidad de Convivencia (siempre visible, sin necesidad de clic):**

Dividida en dos subcolumnas separadas por un divisor vertical.

*Log de últimos accesos:*
El log se muestra automáticamente al abrir la ficha del ciudadano, sin ninguna acción por parte del TSR. Muestra los N accesos más recientes con tres niveles de señal visual:

| Tipo de acceso | Tratamiento visual | Significado |
|---|---|---|
| Acceso propio | Sin fondo, color neutro | Ruido esperado, no requiere atención |
| Acceso de otro profesional de la misma UTS | Fondo gris suave | Habitual en sustituciones y trabajo en equipo |
| Acceso desde otro centro / UO | Fondo ámbar | Infrecuente, merece reconocimiento |
| Modificación desde otro centro / UO | Fondo rojo tenue | No debería ocurrir; requiere verificación |

Cada entrada muestra: punto de color (codifica origen), nombre del profesional, centro/UTS si no es propio, **tipo de acceso** (lectura o modificación con icono), y fecha/hora. En versión de panel estrecho, el tipo se integra en la segunda línea de texto de la entrada.

Una modificación desde fuera de la UO es técnicamente posible en circunstancias excepcionales pero es la señal de mayor prioridad. Es el único uso del color rojo en la pantalla del TSR.

*Unidad de Convivencia:*
- Estado colapsado: avatares apilados + número de miembros. Un clic despliega.
- Estado expandido: lista de miembros con nombre, rol en la UC (titular, cónyuge, hijo…), edad, y flecha de navegación.

Al hacer clic en un miembro de la UC, la columna izquierda actualiza su contenido al ciudadano seleccionado. Aparece un breadcrumb de retorno en la topbar: "← Volver a [nombre original]". No se abren pestañas nuevas — la navegación en UC es contextual y reversible.

**Franja 3 — Historia social (scroll):**
Timeline cronológico inverso de apuntes en formato colapsado: badge de tipo (color semántico), título del apunte, y fecha. Al hacer clic en un apunte se expande mostrando el contenido. Colores por tipo de apunte:

| Tipo | Color del dot |
|---|---|
| Entrevista | Azul |
| Escala / valoración | Morado |
| Plan de intervención | Ámbar |
| Valoración / diagnóstico | Verde teal |
| Apertura / cierre | Gris |

**Distinción entre pasado y planificado en la historia:**
Los apuntes ya registrados tienen el dot relleno y tipografía normal. Los elementos planificados (citas, revisiones programadas) tienen el dot con contorno (sin relleno) y tipografía en color secundario. Un no-show registrado usa el dot ámbar con icono de persona saliente.

### 4.2 Columna derecha: herramientas de trabajo

Ocupa el resto del ancho. En estado neutro muestra la cuadrícula de herramientas disponibles. Al seleccionar una herramienta, el formulario aparece debajo de la cuadrícula (la cuadrícula permanece como referencia visual).

**Herramientas disponibles:**

| Herramienta | Genera en la historia | Notas de UI |
|---|---|---|
| Entrevista | Apunte tipo entrevista | Subtipo (inicial / seguimiento / urgencia / informativa) + modalidad + toggles para valoración y plan |
| Escala / valoración | Apunte tipo escala | Selector de escala con contexto de última aplicación. Campos progresivos. |
| Anotación | Apunte tipo anotación | Campo único + visibilidad (profesionales / privada) |
| Derivación | Apunte tipo derivación | Selector de servicio receptor + urgencia + toggle de notificación |
| Gestión / coordinación | Apunte tipo gestión | Tipo de gestión + recurso + descripción |
| Informe social | Documento firmable | Abre vista de pantalla completa con wizard |

**Herramientas complejas (pantalla completa):** las herramientas que generan documentos extensos (Informe social, Escala compleja con muchos ítems) se abren en vista de pantalla completa. La historia de la columna izquierda permanece colapsada para dar contexto mínimo sin competir con la herramienta. Prima la usabilidad de la herramienta sobre el contexto.

**Borradores y registro definitivo:** todas las herramientas guardan automáticamente un borrador. El botón "Registrar en historia" es el acto irreversible que publica el apunte (pasado inmutable). El borrador no es un apunte — es un estado previo al registro. El indicador de autoguardado aparece en la topbar.

**Visibilidad de apuntes:** campo explícito en cada formulario. Tres valores: `privada` (solo el autor), `profesionales` (cualquier profesional con acceso a la historia), `ciudadano` (cuando la carpeta ciudadana esté activa).

---

## 5. Vista de supervisión del centro

Pantalla de inicio para el rol `supervision`. Organizada en tres pestañas dentro de la misma pantalla.

### 5.1 Resumen operativo (pestaña por defecto)

- **Métricas del día:** citas totales, profesionales activos/ausentes, casos activos, ciudadanos en lista de espera.
- **Alertas accionables:** ausencias no cubiertas, desequilibrios de carga, listas de espera que superan el límite del protocolo. Cada alerta tiene un botón de acción directa. Las alertas se pueden descartar individualmente una vez gestionadas.
- **Tabla de profesionales:** carga semanal (barra visual con tres estados: normal / alta / sobrecarga), citas del día, estado (disponible / carga alta / sobrecarga / ausente). Acceso directo a la agenda de cada profesional.

### 5.2 Agenda del centro (segunda pestaña)

Vista de columnas paralelas — una por profesional del centro. Cada columna muestra los slots del día con el nombre del ciudadano (nivel consulta básica), tipo de cita y estado.

**Estados de slots:**
| Estado | Color | Icono |
|---|---|---|
| Realizada | Verde relleno | `ti-check` |
| Planificada (pendiente) | Contorno gris | `ti-clock` |
| No-show registrado | Ámbar | `ti-user-off` |
| Sin cubrir / urgente | Rojo | `ti-alert-triangle` |
| Bloqueado (reunión, etc.) | Gris sólido | — |

Al hacer clic en un slot con ciudadano, se despliega un panel con nombre completo, teléfono y tipo de cita. **Nota explícita:** "Datos de contacto únicamente · Sin acceso a ficha social ni historia." Esto corresponde al nivel de acceso `consulta_basica`.

El supervisor puede reasignar citas directamente desde la agenda (arrastrando o desde el panel de detalle del slot).

### 5.3 Actividades del centro (tercera pestaña)

Lista de actividades activas y próximas con su estado de inscripción (barra de progreso visual) y profesional responsable. Acceso al wizard de creación de nueva actividad.

**Acceso a casos (rol intervención opcional):** si el supervisor tiene también el rol `intervencion`, aparece el icono "Casos" en el sidenav. Si no, ese icono no existe en su navegación — no se bloquea ni se muestra con candado, simplemente no está.

---

## 6. Vista del mostrador (roles tramitación y consulta básica)

Ambos roles comparten el mismo layout de tres columnas. La diferencia está en qué pestañas y acciones están disponibles en la columna central.

### 6.1 Layout de tres columnas

**Columna izquierda (≈240px):** buscador de ciudadanos + lista de atendidos hoy. El botón "Nuevo ciudadano" aparece para ambos roles (ambos pueden registrar ciudadanos con datos mínimos). El `contexto_alta` predeterminado en el drawer de registro varía según el tipo de centro.

**Columna central:** datos de identificación del ciudadano seleccionado + pestañas contextuales.

**Columna derecha (≈250px):** agenda del centro del día, navegable por fecha, con filtro de profesional. Botón de nueva cita siempre visible.

### 6.2 Pestañas contextuales por rol

| Pestaña | Consulta básica | Tramitación |
|---|---|---|
| Citas | ✓ Completa + registro SIA | ✓ Completa |
| Oferta del centro | ✓ Completa con inscripción | — (bloqueada) |
| Expedientes | — (bloqueada) | ✓ Completa |
| Documentos | Solo lectura | ✓ Con subida |

Las pestañas bloqueadas aparecen desactivadas visualmente (color terciario, sin cursor de clic). No hay candados ni mensajes de error — simplemente no funcionan para ese rol.

### 6.3 Registro de contacto SIA (consulta básica)

En la pestaña Citas del rol `consulta_basica`, aparece un botón secundario "Registrar contacto SIA". Al activarlo, se despliega el formulario inline:

1. **Descripción de la demanda** (texto libre).
2. **Clasificación:** competencia municipal / otra administración / información general.
3. **Urgencia** (solo si es competencia municipal): urgencia 24h / prioritario 5 días / ordinario 15 días.
4. Botón "Registrar y gestionar cita" — el registro SIA se guarda y se abre el drawer de nueva cita.

La cita sale del registro SIA, no al revés. El flujo correcto es: describir la demanda → clasificar → gestionar la cita.

### 6.4 Expedientes de tramitación (rol tramitación)

Para cada prestación con trámite activo:
- **Nombre de la prestación** y profesional que la propuso (el TSR).
- **Timeline de estados** del expediente (solo lectura en condiciones normales).
- **Documentación requerida vs. aportada** — lista con indicadores visuales de completitud.
- **Acciones disponibles:**
  - "Adjuntar documento" — abre drawer de subida. El documento va al expediente externo; una nota recuerda que el TSR lo revisará.
  - "Actualizar estado" — disponible solo cuando la sincronización automática con el gestor de expedientes no ha llegado. Muestra un selector de estados válidos con nota: "La resolución la tramita el gestor de expedientes. Aquí solo actualizas si la sincronización no llegó."

**VIDA no tramita.** La tramitación ocurre en el gestor de expedientes externo. VIDA es la ventana de consulta y el punto de complemento manual cuando la sincronización falla.

---

## 7. Gestión de actividades del centro

### 7.1 Alcance de VIDA en actividades

VIDA gestiona: catálogo centralizado de actividades, sesiones con fecha/hora/espacio/aforo, inscripción de ciudadanos vinculada a su registro, lista de espera, control de asistencia sesión a sesión, y registro opcional de asistencia en historia social (decisión explícita del TSR, nunca automática).

VIDA no gestiona: contenidos formativos, materiales, evaluaciones, certificados, ni publicación automática en webs externas (diferido, pero el modelo lo contempla).

### 7.2 Modelo Actividad / Sesión

Una **Actividad** es el contenedor reutilizable (el taller, el curso). Una **Sesión** es la convocatoria concreta con fecha, hora, espacio y aforo propios. La inscripción apunta siempre a una sesión, no a la actividad.

### 7.3 Creación de actividad — wizard de 4 pasos

El supervisor crea actividades desde la pestaña Actividades. El wizard tiene navegación lateral con los 4 pasos visibles simultáneamente, guardado automático de borrador en cada paso:

1. **Definición** — nombre, tipo, profesional responsable, descripción, población destinataria.
2. **Acceso y aforo** — modo de acceso (libre / prescripción / mixto), aforo total, reparto de plazas si es mixto, lista de espera, visibilidad en directorio.
3. **Sesiones** — lista de sesiones con fecha, hora, espacio y aforo. El formulario de nueva sesión se despliega inline. Toggle para publicar sesiones al guardar o mantenerlas como borrador.
4. **Resumen y publicación** — revisión de todos los datos antes de publicar. El badge "Borrador" pasa a "Publicada" al confirmar — acto irreversible para las sesiones publicadas.

### 7.4 Inscripción de ciudadanos

Desde la vista de sesión, el botón "Inscribir" abre un panel de búsqueda con el motor de matching habitual. Los resultados muestran el estado del ciudadano (historia abierta / sin historia / registrado solo). Si no existe en el sistema, "Registrar persona nueva" crea un registro mínimo vinculado a la inscripción — sin abrir historia social automáticamente.

### 7.5 Control de asistencia

En sesiones realizadas, el botón "Ver asistencia" muestra la lista de inscritos con controles de asistió / no vino por persona. Las estadísticas (% asistencia, no-shows) aparecen en la cabecera del panel. Nota visible: "La asistencia se registra en la historia social solo si el ciudadano tiene historia abierta y el TSR lo decide explícitamente."

---

## 8. Patrones de UI reutilizables

### 8.1 Drawer lateral

Para operaciones que no requieren abandonar el contexto actual (registrar un apunte, crear una cita, adjuntar un documento). El drawer aparece desde la derecha a 280-300px de ancho y convive con la pantalla principal, que queda visible y legible.

Estructura del drawer: cabecera con título y botón de cierre, cuerpo scrollable con el formulario, pie con botones de acción (cancelar / guardar borrador / confirmar).

Se usa el drawer para: entrevistas, anotaciones, gestión, derivaciones, nuevas citas, adjuntar documentos, inscribir en actividades, registrar ciudadanos. Las herramientas con formularios extensos (informe social, escala compleja) usan pantalla completa en su lugar.

### 8.2 Chips de selección

Para clasificaciones con pocas opciones (4-8) que el profesional elige frecuentemente. Más rápidos que un `<select>` y comunican el espacio completo de opciones de un vistazo. Se usan para: tipo de entrevista, subtipo, urgencia, modo de acceso a actividad, clasificación SIA, etc.

### 8.3 Estados de borrador y registro

Todos los formularios operacionales tienen dos estados finales distintos:

- **Borrador:** guardado automático, sin consecuencias en la historia. El indicador de borrador aparece en la topbar. Acción: "Guardar borrador" (explícita si el usuario quiere salir).
- **Registrado / publicado:** acto irreversible que añade al pasado inmutable. Botón primario con texto claro: "Registrar en historia" / "Publicar actividad" / "Confirmar inscripción".

Nunca se usan las palabras "guardar" y "publicar" de forma intercambiable. Guardar es provisional, publicar es definitivo.

### 8.4 Notas de acceso y privacidad

En cada vista donde el rol tiene acceso restringido, aparece una línea de nota discreta (icono de candado + texto en color terciario) que describe explícitamente qué puede y qué no puede ver ese profesional. No es un aviso de error — es información permanente que refuerza la cultura de privacidad.

Ejemplos:
- Panel de detalle de cita para supervisor: "Datos de contacto únicamente · Sin acceso a ficha social ni historia."
- Drawer de adjuntar documento para tramitación: "El documento va al expediente. El TSR lo revisará y decidirá sobre su validez."
- Log de accesos al expediente (nota al pie): "Una modificación desde otro centro es infrecuente y merece verificación."

---

## 9. Pendientes y decisiones abiertas

- **InfoSIA:** chat RAG para responder preguntas sobre prestaciones municipales desde recepción. El hueco natural es un panel adicional en la pestaña "Oferta del centro" del rol `consulta_basica`. Se pospone para una fase posterior. El modelo de datos no necesita cambios para incorporarlo.
- **Publicación de actividades en webs externas:** el modelo de datos de `Actividad` contiene toda la información necesaria para exportar a madrid.es o CMS externos. El adaptador de publicación se diseña cuando haya acuerdo con los canales receptores.
- **Carpeta ciudadana (rol 0):** pendiente de diseño. Cuando esté activa, la visibilidad `ciudadano` en apuntes de historia se habilita y hay que revisar qué datos de Capa 1 y Capa 2 son accesibles para el propio ciudadano.
- **Generación de sesiones recurrentes:** el wizard de actividades crea sesiones individualmente. Para actividades semanales de larga duración, un flujo de "crear sesiones en serie" (frecuencia + fecha fin + excepciones) reduciría la fricción.
- **Notificaciones automáticas al ciudadano:** requiere el módulo de comunicaciones. Afecta a confirmaciones de cita e inscripción en actividades.

---

*Documento elaborado en fase de diseño de UI. Versión inicial: mayo 2026.*
*Elaborado a partir de sesiones de diseño documentadas en el historial de conversaciones del proyecto.*
