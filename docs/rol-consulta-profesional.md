# Rol de consulta profesional (`consulta_profesional`) y encargo profesional — VIDA 360

**Estado:** diseño funcional cerrado el 23 nov 2026; pendientes del §11 resueltos el 25 nov 2026 en `docs/instrucciones-cli/2026-11-encargo-profesional.md`. Implementados el modelo y servicio del encargo (bloque A, sin sus tests) y la superficie, el ámbito y los permisos (bloque C, 25 nov 2026): el rol entra en «Mis derivaciones». Después, el 25 nov 2026, los bloques B (interfaz del TSR y bandeja), D (documentos y protegidas) y E (agenda y citas). Pendiente el bloque F (mensajes y centro activo).
**Origen:** pruebas en staging, ronda 1 (EST-02: el rol no tenía superficie y se quedaba en «Redirigiendo…»).
**Documentos relacionados:** `docs/modulo-usuarios-permisos.md` (ROL 6), `docs/instrucciones-cli/2026-11-fase3-circuito-intervencion.md` (F3-3 a F3-6, derivaciones), `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (D1.5, D1.6, RN-15 a RN-19), `docs/roles-consulta-basica-tramitacion.md`, `docs/modulo-agenda.md`, `docs/modulo-documentos.md`, `docs/modulo-mensajes.md`.

---

## 1. Propósito y perfil

`consulta_profesional` es el rol de **cualquier profesional que trabaja con un ciudadano por encargo y no necesita la Historia Social completa**: terapeutas ocupacionales, abogadas (p. ej. del Servicio de Orientación Jurídica), psicólogas, educadoras sociales, servicios de gestión indirecta y otros profesionales con intervención puntual.

Como siempre, el rol depende de lo que la persona hace en su servicio, no de su titulación (principio 3.3): una abogada integrada en el equipo de un CIAM trabaja con `intervencion`; en el SOJ, con `consulta_profesional`.

**Principio:** su actuación nace de una **derivación del trabajador social de referencia (TSR)**. El contexto de lo que tiene que trabajar con la persona está escrito en esa derivación; por eso no necesita la Historia Social. Si necesita más información, la pide al TSR por mensajes.

---

## 2. Superficie

| Elemento | Detalle |
|---|---|
| **Página de inicio** | «Mis derivaciones». En la prioridad de R4.5 va justo después de Agenda y es superficie elegible en la preferencia de página de inicio |
| **Menú** | Mis derivaciones, Agenda, Buscador, Mensajes |
| **Mis derivaciones** | Encargos asignados al profesional, activos y cerrados (histórico): persona, centro o servicio, TSR que deriva, motivo y contexto, documentos adjuntos por el TSR, estado y antigüedad |
| **Barra superior** | Centro activo con selector si tiene varios centros (D1.7) |

---

## 3. El encargo profesional

La derivación actual (fase 3) va de centro a centro, crea un **plan especializado** y da al personal del centro del plan lectura de la Historia Social completa (F3-3). No sirve para un encargo puntual a un profesional sin acceso a la Historia. El encargo profesional es una **modalidad de derivación sin plan especializado**:

1. **Origen:** el TSR deriva a un **servicio o centro**, con el motivo y el contexto del encargo, y puede adjuntar documentos.
2. **Asignación:** la dirección o la supervisión del centro o servicio de destino lo **acepta asignando un profesional** adscrito a ese centro, o lo rechaza con motivo. Reutiliza el flujo y la bandeja de derivaciones de F3-6.
3. **Sin plan especializado:** aceptar no crea plan ni da acceso a la Historia Social.
4. **Resultado:** el profesional registra su actuación (atención realizada, informe adjunto) y cierra el encargo. El resultado aparece en el timeline de la Historia Social como **apunte** visible para los profesionales de intervención. El profesional no ve los demás apuntes.
5. **Mensajes:** desde el encargo, con la persona y el encargo enlazados, para pedir información al TSR (`modulo-mensajes.md`).
6. **Estados e historial:** los de la derivación (pendiente, aceptada, rechazada, anulada) más **cerrada** con resultado; historial inmutable como `derivacion_eventos`.

---

## 4. Qué ve y qué hace

| Bloque | Acceso |
|---|---|
| Datos de identificación y contacto | Lectura |
| Unidad de convivencia | Lectura |
| Relaciones | Lectura |
| Encargo (motivo, contexto, documentos del TSR) | Lectura |
| Resultado del encargo | Registro y cierre |
| Documentos | Ver §6 |
| Historia Social (apuntes, planes, valoraciones, timeline) | Sin acceso |
| Prestaciones, situación social (Capa 2) | Sin acceso |
| Citas | Las suyas, en su agenda (§8) |

No edita la ficha.

---

## 5. Ámbito

- **Sus ciudadanos asignados:** las personas de los encargos en los que es o ha sido el profesional asignado, **incluido el histórico**.
- El **buscador** solo le devuelve esas personas. No busca a cualquier ciudadano (a diferencia de `consulta_basica`).
- Todo acceso queda auditado, como hoy (`AuditarAccesoCiudadano`).

### 5.1 Personas protegidas

**RN-17 se amplía:** el ámbito reducido de una persona protegida incluye al **profesional asignado a un encargo activo** sobre ella, que la ve con identidad (como RN-19 hace con la bandeja de derivaciones). Ejemplo: el TSR deriva a una mujer víctima de violencia de género a la abogada del SOJ.

*A confirmar en la implementación:* cerrado el encargo, la persona protegida aparece en su histórico enmascarada, con «Solicitar acceso» (RN-18), porque la salvedad es solo para el encargo activo.

---

## 6. Documentos

- Sube y genera documentos sobre la persona **sin el límite del día** de `consulta_basica`: son su trabajo profesional.
- Esos documentos van a la carpeta de la persona y los ven los profesionales con acceso a ella (el TSR incluido).
- Ve **los suyos y los que el TSR adjunte al encargo**. No ve el resto de la carpeta.
- Retirada lógica y versiones, según `modulo-documentos.md` §2.1.

---

## 7. Multicentro y RN-15

Estos profesionales trabajarán con frecuencia a tiempo parcial en varios centros. Se aplica lo ya decidido en la addenda D1:

- **Una adscripción por centro**, con su perfil horario en cada uno (D1.5).
- **RN-15:** para actuar tiene que estar adscrito al centro o servicio del encargo.
- **Centro de la operación = el del encargo** (D1.6). Una actuación sobre un encargo del CIAM Usera cuenta en Usera aunque ese día trabaje en Vallecas.
- **Centro activo** por el perfil horario del día, con selector en la barra superior (D1.7).

**A verificar:** trabajar en **turnos distintos el mismo día** (mañana en un centro, tarde en otro). Hay que comprobar que el perfil horario admite franjas por día y, si es así, que `centroActivo()` tenga en cuenta la hora, no solo el día.

---

## 8. Agenda

Necesita agenda propia para citar a las personas de sus encargos:

- Perfiles horarios por centro (`perfiles_horario_profesional`, uno activo por profesional y centro), con **días y turnos distintos en centros distintos**.
- Slots y citas en el centro de cada perfil, con los tipos de slot que ofrezca ese centro.
- Sus citas aparecen en el historial de citas de la persona con la etiqueta pública del tipo y el centro (`modulo-citas.md` §6 bis).

**Pendiente:** si sus citas las da él mismo, la ventanilla del centro o ambos.

---

## 9. Permisos atómicos (orientativo)

| Permiso | Uso |
|---|---|
| `derivacion.ver_asignadas` | Bandeja «Mis derivaciones» (permiso de superficie) |
| `derivacion.registrar_resultado` | Registrar la actuación y cerrar el encargo |
| `ciudadano.ver_ficha` | Ficha en lectura, limitada por policy a sus ciudadanos asignados |
| `documento.subir` | Subir documentos a la carpeta de la persona |
| `agenda.ver_propia` | Agenda (el mismo permiso de superficie que en R4) |

La limitación a sus ciudadanos asignados va en las policies (`CiudadanoPolicy`), no en el permiso.

---

## 10. Impacto en otros documentos

- **`modulo-usuarios-permisos.md` ROL 6:** sustituir las capacidades por las de este documento; ámbito = ciudadanos de sus encargos, con histórico.
- **Fase 3, F3-3:** el acceso del equipo del plan a la Historia Social se evalúa **por permiso**. Un `consulta_profesional` adscrito al centro de un plan no hereda la lectura de la Historia.
- **Derivaciones (F3-5, F3-6):** nueva modalidad «encargo profesional» sin plan especializado (§3).
- **Addenda D1, RN-17:** ampliación del §5.1.
- **`modulo-documentos.md`:** visibilidad de documentos para este rol (§6).
- **`modulo-agenda.md`:** franjas por día en el perfil horario, si hace falta (§7).
- **Seeder de cargos sugeridos:** sin cambios (TO → `consulta_profesional` es coherente con este diseño).

---

## 11. Pendiente

1. **Origen del encargo:** desde el plan principal (como F3-6) o también desde la Historia Social.
2. **Citas:** quién las da (§8).
3. **Histórico de personas protegidas:** confirmar el enmascaramiento tras el cierre (§5.1).
4. **Franjas horarias el mismo día:** verificación técnica (§7).

---

## 12. Decisiones tomadas (23 nov 2026)

| Tema | Decisión |
|---|---|
| Perfil del rol | Profesionales que trabajan por encargo y no necesitan la Historia Social completa (TO, abogadas, psicólogas, educadoras…) |
| Contexto del trabajo | El que escribe el TSR en la derivación; para más información, mensajes al TSR |
| Destino de la derivación | A un servicio o centro; la dirección asigna al profesional |
| Ámbito | Sus ciudadanos asignados, incluido el histórico; el buscador solo devuelve esos |
| Ficha | Lectura: identificación, contacto, unidad de convivencia, relaciones. No edita |
| Actuación | Resultado del encargo, visible como apunte en la Historia Social |
| Documentos | Los suyos sin límite de día y los que le pase el TSR; nada más de la carpeta |
| Protegidas | Identidad visible mientras el encargo está activo (RN-17 ampliada) |
| Adscripción | RN-15: adscrito al centro o servicio del encargo; multicentro frecuente |
| Agenda | Propia, con días y turnos distintos en centros distintos |
| Página de inicio | «Mis derivaciones» |
