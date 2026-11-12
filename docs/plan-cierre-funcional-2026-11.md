# Plan de cierre funcional — VIDA 360

**Fecha:** 11 noviembre 2026 (actualizado el mismo día: decisiones D1 a D6 cerradas; avance de la fase 0)
**Base:** repositorio a `bbfc3fa` (11 nov), `SESSION.md`, `BACKLOG.md`, `CHANGELOG-112026.md`, `docs/` tras la puesta al día documental, y las pruebas de la gestión documental en staging.
**Objetivo:** cerrar la funcionalidad que debería estar completa antes de añadir módulos nuevos (requisitos de acción, tareas guiadas, particularidades del CIAM, grupos multifuncionales).

---

## 1. Criterio: qué es «funcionalidad que debería estar completa»

El **circuito básico de atención a una persona** debe poder recorrerse entero en VIDA, por los roles que lo usan a diario, sin pasos rotos ni datos que se pierdan:

> identificar o dar de alta → atender y citar → abrir Historia Social y asignar referencia → valorar → elaborar el plan (general o especializado) → prescribir prestaciones y recursos → hacer seguimiento → documentar e informar.

A eso se suman tres condiciones transversales: **privacidad** (ningún dato de una persona protegida a la vista de quien no debe verlo), **estructura organizativa** (centros, tipos y UO como se van a usar) y **estabilidad** (tests y despliegue fiables).

Todo lo que no forma parte de ese circuito, o depende de terceros, queda fuera de este plan (§6).

---

## 2. Estado del circuito

| Paso | Estado | Lo que falta |
|---|---|---|
| Identificar / dar de alta | Casi completo | **Búsqueda por documento** en el buscador principal (`BuscarCiudadanoPage::buscarPorIdentificador()` devuelve vacío). Búsqueda por nombre sobre 500 registros en PHP (no escala) |
| Atender y citar | Completo (sin probar en staging) | Historial de citas en la ficha (§6 bis); el cuadrante del supervisor no enlaza con la cita; agenda en los mundos demo |
| Historia Social y referencia | Completo (sin probar en staging) | Cambiar la referencia de un caso ya asignado desde la interfaz; autorización por centro dentro de los servicios; centros reales sin configurar |
| Valorar | Completo | Escalas: comprobar `RegistrarEscalaPage` contra lo previsto para la fase 2 y cerrar I-06 |
| Plan general (PISO) | Completo | — |
| **Plan especializado (PIA…)** | **Roto** | `PlanPage` guarda siempre `general_asp`; falta el flujo de derivación desde el PISO y la entrada directa por interfaz; la tabla `derivaciones` no existe; cualquier tipo de plan se ofrece en cualquier centro |
| **Prescribir prestaciones** | **Parcial** | Solicitud de prestación diseñada (`modulo-solicitudes-expedientes.md`), sin implementar; `statPrestaciones()` devuelve null; aviso al TSR tras asignar plaza solo en el log; `PrescripcionService` sin conectar al TSR (ya se puede: la asignación existe) |
| **Seguimiento** | **Parcial** | Programar el siguiente seguimiento: sí. **Evaluar los objetivos por estado: no** |
| Documentar | Custodia completa | Rechazo de ficheros vacíos y PDF en blanco; registro de la causa técnica; lectura del código 2 de `qpdf`; retirada lógica y vínculo con solicitud (para `consulta_basica`) |
| **Informar** | **Sin interfaz** | Generar informes desde plantilla y el PISO firmado desde la interfaz operativa (`modulo-documentos.md` §4); variables auxiliares |
| Actividades del centro | Solo el alta | Edición, baja, sesiones e **inscripción de personas** desde Intervención (lo necesitan el CIAM y los requisitos de acción) |

**Roles:** `intervencion` y `supervision` tienen superficie completa. **`consulta_basica`** (ventanilla, información, auxiliares) tiene el diseño cerrado y nada implementado de lo nuevo. `tramitacion`, diferido.

**Estructura:** el tipo de centro sigue siendo una clave de catálogo. **Tipo de Centro** tiene diseño, instrucciones y tests listos (rama A). Varias UO con varios centros, sin decidir.

**Privacidad** (`BACKLOG`, «Acceso a expedientes: pendientes»): personas relacionadas protegidas visibles en ficha y expediente; `VerFichaPage` no comprueba que la ficha sea de la historia; `guardar()` de la ficha decide por rol.

**Estabilidad:** los tests de Supervisión no están en `phpunit.xml` (3 fallos que nunca se ejecutan); CI de calidad en rojo; BD local = staging; rendimiento de `CiudadanoPage` y `plan-page`.

---

## 3. Decisiones que necesita el equipo (no las puede tomar Claude CLI)

| # | Decisión | Bloquea | Prioridad |
|---|---|---|---|
| D1 | ✅ **Decidida.** Varias UO con varios centros: UO de centro instrumental 1:1, jerarquía orgánica + funcional por tipo, ámbito = unión, atención solo con adscripción a centro, multicentro con una adscripción por centro. Ver `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` §1 | — | — |
| D2 | ✅ **Decidida.** El tipo manda: la migración fija los valores del tipo, descarta los del centro y elimina sus columnas. Ver la addenda, §6 | — | — |
| D3 | ✅ **Decidida.** Solicitudes de prestación y expedientes: una sola puerta por vía de acceso, bandeja de la unidad tramitadora, gestor agnóstico con registro manual, concesión e inicio por separado. Ver `docs/modulo-solicitudes-expedientes.md` | — | — |
| D4 | ✅ **Decidida.** Las personas protegidas solo las gestiona el personal adscrito al centro; las UO superiores necesitan acceso concedido por el director del centro. Ver la addenda, §7 | — | — |
| D5 | ✅ **Decidida.** No se borran usuarios; no se deja un centro con casos o citas pendientes; los casos son del centro. Ver la addenda, §8 | — | — |
| D6 | ✅ **Decidida.** Las escalas se aplican desde la caja de herramientas de la Historia Social (ya existe: `RegistrarEscalaPage`). Sin orden obligatorio respecto a la valoración: lo decide el profesional. En las tareas guiadas podrán ser un paso de la secuencia | — | — |

---

## 4. Secuencia propuesta

Cada fase termina con la suite de sus módulos en verde, `ui:auditar` limpio y CHANGELOG/SESSION/BACKLOG al día.

### Fase 0 — Estabilizar y probar (1–2 sesiones)
1. **Prueba en staging de asignación y citas** por el equipo (pendiente desde el 1 oct). Configurar los centros según D2.
2. Correcciones de la ingesta: ficheros vacíos, PDF en blanco (`inkcov`), código 2 de `qpdf`, registro de la causa técnica, nota sobre AppArmor.
3. `Modules/Supervision/tests` en `phpunit.xml` y sus 3 fallos; CI de calidad (memoria de PHPStan).
4. ~~BD de desarrollo propia (`vida_dev`)~~ — aplazada por el desarrollador hasta la versión 1.0: la BD compartida local/staging se mantiene a propósito.
5. Reformatear en Markdown los documentos incorporados el 10 nov (duda 1 del CHANGELOG), sin cambiar contenido.

*Listo para encargar:* puntos 2–5. *Necesita al equipo:* punto 1.

### Fase 1 — Privacidad (1 sesión)
Cerrar «Acceso a expedientes: pendientes»: personas relacionadas protegidas, `VerFichaPage`, `guardar()` por policy, búsqueda por documento en `BuscarCiudadanoPage`. Es requisito antes de que usuarios reales trabajen con datos reales.

*Listo para encargar* (instrucciones cortas a partir del BACKLOG).

### Fase 2 — Estructura: Tipo de Centro (2–3 sesiones)
Ejecutar `2026-10-tipo-centro-implementacion.md` (rama A) con la addenda (`2026-11-tipo-centro-addenda-d1.md`): ámbito por las dos jerarquías, centro de trabajo y barra superior, migración con «el tipo manda», personas protegidas solo para el centro con concesión del director, y bajas de profesionales sin borrado ni casos pendientes. Resuelve de paso los tipos de plan por centro.

*Listo para encargar.* Puede detenerse solo si el tipo de algún centro de staging no se deduce de los datos.

### Fase 3 — Circuito de intervención completo (3–4 sesiones)
1. **Planes especializados**: creación con el `tipo` correcto según el ámbito del tipo de plan; derivación desde el PISO (con tabla `derivaciones`) y entrada directa (`admite_entrada_directa`), según `modulo-intervencion.md` §5.1. Prioritario para el CIAM.
2. **Evaluación de objetivos en el seguimiento** (`modulo-intervencion.md` §6).
3. **Prescripción de plazas**: conectar `PrescripcionService` al TSR vigente (`AsignacionReferenciaService`) y aviso real al TSR al asignar plaza (alerta). El widget de prestaciones va en la fase 3 bis.
4. **Cambiar la referencia** desde la ficha o el expediente (supervisión, con motivo) y autorización por centro en los servicios de asignación.

*Necesita análisis breve:* punto 1 (concretar el flujo de interfaz de la derivación). *Listo para encargar:* 2–4.

### Fase 3 bis — Solicitudes de prestación y expedientes (3–4 sesiones)
Implementar `docs/modulo-solicitudes-expedientes.md`: vía de acceso en el catálogo, solicitud con formulario y documentos, bandeja de tramitación, adaptador manual y simulador del gestor, incidencias, resolución, prestaciones de la persona (concedida / activa) y resumen en la ficha. Sustituye a `SolicitudServicio`. Implanta el rol `tramitacion` en lo que toca a este módulo.

*Necesita redactar instrucciones y tests* (el diseño está cerrado).

### Fase 4 — Atención administrativa: `consulta_basica` (2 sesiones)
Instrucciones y tests nuevos a partir de `roles-consulta-basica-tramitacion.md` §3 y del delta de citas (§6 bis): ficha por rol, estado de la Historia Social con referencia, historial de citas, alta y relaciones, documentos propios del día (retirada lógica en la custodia), permisos nuevos.

*Necesita redactar instrucciones* (el diseño está cerrado).

### Fase 5 — Informes (2 sesiones)
Interfaz operativa de informes desde plantilla y PISO firmado (`modulo-documentos.md` §4), con variables auxiliares si hacen falta para los modelos reales.

*Necesita revisar el diseño* de `modulo-documentos.md` §4 contra lo que piden hoy los profesionales.

### Fase 6 — Actividades grupales (1–2 sesiones)
Ciclo de vida completo: edición, baja, sesiones e inscripción de personas desde Intervención. Prepara el terreno para el CIAM (talleres) y para los requisitos de acción.

*Necesita análisis breve* (la inscripción está esbozada en `modulo-centros.md` §2.6–2.7).

**Después:** requisitos de acción y tareas guiadas, particularidades del CIAM, grupos multifuncionales.

---

## 4 bis. Avance (11 nov, tarde)

- Fase 0: A1, A2, A3 y A5 hechos; A4 aplazado hasta la 1.0. Pendiente la prueba en staging de asignación y citas por el equipo.
- Fase 1: en curso (B1).
- Decisiones D1 a D6 cerradas. D1, D2, D4 y D5 están en la addenda de Tipo de Centro; D3, en `docs/modulo-solicitudes-expedientes.md`.

## 5. Lo que se puede hacer en paralelo con el equipo

Mientras el equipo prueba en staging y toma D1–D3, Claude CLI puede hacer la fase 0 (puntos 2–5) y la fase 1, que no dependen de ninguna decisión.

---

## 6. Fuera de este plan (conscientemente)

- El resto de `tramitacion` (traslados y fusiones, `roles-consulta-basica-tramitacion.md` §4.2.5). Lo que toca a solicitudes va en la fase 3 bis.
- Gestión completa de listas de espera de plazas (`modulo-solicitudes-expedientes.md` §8).
- Integraciones reales: Cita Previa, BDC, HSU, VIOMAD (requiere EIPD) y el gestor de expedientes (no existe; adaptador manual mientras tanto).
- Comunicaciones y notificaciones al ciudadano.
- Analítica, indicadores y datos abiertos (requiere validación del DPD).
- Genograma, chat IA, generación IA de cuadrantes.
- Rendimiento de `CiudadanoPage` y `plan-page`: no bloquea, pero conviene antes de la fase 3, que añade más a esas pantallas.
- Importación de datos históricos del CIAM (proyecto de migración aparte).
