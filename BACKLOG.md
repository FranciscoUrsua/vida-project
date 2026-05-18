# BACKLOG — VIDA 360

Registro de ideas, mejoras, decisiones pendientes y deuda técnica.
Actualizar con fecha y contexto breve al añadir cada entrada.

---

# 💡 IDEAS Y FUNCIONALIDADES AMBICIOSAS

> Ideas que requieren decisión de diseño antes de poder planificarse.

---

**Chat IA para búsqueda de información en la aplicación** — 2025-05-13
Asistente conversacional integrado en la interfaz operativa que permita a los profesionales
consultar información del sistema en lenguaje natural: "¿cuántas plazas libres hay en centros
de mayores en Carabanchel?", "¿qué prestaciones tiene activas este ciudadano?".
Restricción irrenunciable: el principio 3.9 aplica por completo. La IA consulta e informa;
nunca crea, modifica ni elimina registros.
Requiere: definir el modelo de lenguaje, el alcance de datos consultables, la gestión de
permisos en las consultas IA (el asistente no puede devolver datos a los que el profesional
no tendría acceso por los canales normales), y el log de auditoría de consultas.

---

**Generación IA de cuadrantes de agenda** — (desde `docs/modulo-agenda.md`)
Componente IA para proponer cuadrantes mensuales a partir de demanda histórica.
Requiere: definir métricas de input, criterios de calidad de la propuesta, y flujo de
validación profesional antes de publicar. Diferido hasta tener datos históricos suficientes.

---

**Panel de análisis estadístico y cuadro de mandos** — 2025-05-13
Explotación de los datos del sistema para planificación basada en evidencia: indicadores
de carga asistencial por UO, evolución de prestaciones, detección de patrones de demanda.
Conexión natural con el principio de transparencia y rendición de cuentas del nuevo modelo
de servicios sociales. Diferido a fases posteriores del proyecto.

---

**Alertas automáticas por patrones anómalos de acceso** — (desde `docs/modulo-auditoria.md`)
Detección de accesos masivos, accesos reiterados fuera de horario u otros patrones sospechosos,
con alerta automática al supervisor. Diferido a fases posteriores.

---

**Integración con sistemas SIEM corporativos** — (desde `docs/modulo-auditoria.md`)
Exportación de logs de auditoría a sistemas de gestión de eventos de seguridad del Ayuntamiento.
Diferido hasta que se identifique el sistema SIEM del municipio adoptante.

---

**Notificación al ciudadano por cambio de cita** — (desde `docs/modulo-agenda.md`)
Avisar al ciudadano cuando su cita se modifica o cancela.
Diferido a la definición del módulo de comunicaciones ciudadanas (canal a determinar:
carpeta ciudadana, SMS, email).

---

**Gestión de conflictos de espacio con bloqueo efectivo** — (desde `docs/modulo-agenda.md`)
El sistema actual solo genera aviso cuando dos actividades compiten por el mismo espacio.
Evaluar si se justifica un sistema de reserva con bloqueo real. Diferido a fases posteriores.

---

**Módulo de comunicaciones con el ciudadano**
Canal de comunicación oficial bidireccional entre el sistema y el ciudadano. Prerequisito
para notificaciones de cita, avisos de prestaciones, etc. Requiere definición del canal
disponible en el municipio adoptante.

---

# 🔧 DEUDA TÉCNICA Y DECISIONES PENDIENTES

> Implementaciones con diseño claro pero momento postergado, o decisiones de diseño
> que deben tomarse antes de continuar con un módulo.

---

**Revisar modelado de `unidad_organizativa_id` en apuntes — BLOQUEANTE** — 2025-05-13
`Módulo: Intervención / Usuarios`
Los tests TF-USU-16 y TF-USU-17 (protección granular de apuntes de colectivos protegidos)
asumen que cada apunte lleva `unidad_organizativa_id` y que la Policy evalúa visibilidad
apunte a apunte. Verificar y confirmar este modelado en `docs/modulo-intervencion.md`
antes de implementar esos tests.
Ref: `docs/instrucciones-cli/usuarios-tests.md` — TF-USU-16 y TF-USU-17.

---

**Completar TF-PRE-13 con integración real de Intervención** — 2025-05-13
`Módulo: Prestaciones / Intervención`
El test TF-PRE-13 (resolución histórica de prestación desde un plan de intervención)
está implementado con stub. Completar la integración real cuando el módulo Intervención
esté disponible.
Ref: `docs/instrucciones-cli/prestaciones-tests.md` — TF-PRE-13.

---

**Completar TF-USU-16 y TF-USU-17 tras revisión de Intervención** — 2025-05-13
`Módulo: Usuarios / Intervención`
Dependiente de la deuda anterior. Una vez confirmado el modelado de apuntes,
implementar estos dos tests y eliminar el aviso de bloqueo en las instrucciones CLI.
Ref: `docs/instrucciones-cli/usuarios-tests.md`.

---

**Definir diseño de entidad Servicio** — (desde `docs/documentacion-proyecto.md`)
`Módulo: Centros`
Servicio ≠ Centro (el servicio no tiene infraestructura propia). El diseño está pendiente.
Necesario para completar el módulo de Centros y para el catálogo de recursos.

---

**Definir diseño de Solicitud de prestación** — (desde `docs/documentacion-proyecto.md`)
`Módulo: Prestaciones`
La solicitud es configurable por tipo de prestación. El diseño detallado está pendiente.

---

**Integración RRHH vía API para excepciones de profesional** — (desde `docs/modulo-agenda.md`)
`Módulo: Agenda / Integraciones`
El campo `origen = api_rrhh` en `ExcepcionProfesional` está preparado. El adaptador mock
está activo. Diferido hasta identificar el sistema de RRHH del municipio adoptante.

---

**Definir visibilidad del cuadrante para el propio profesional** — (desde `docs/modulo-agenda.md`)
`Módulo: Agenda`
Qué parte del cuadrante puede ver y editar el profesional sobre el suyo propio
(solicitud de cambio de franja, visualización de compañeros...).
Diferido al diseño de la interfaz Livewire.

---

**Documentar estrategia de migración entre modos de agenda** — (desde `docs/modulo-agenda.md`)
`Módulo: Agenda`
Cuando un centro sube de modo `basico` a `estandar`, los datos existentes son compatibles
sin transformación. Documentar esto explícitamente en la guía de adopción.

---

**Objetivos del plan como lista estructurada con indicadores** — (desde `docs/modulo-intervencion.md`)
`Módulo: Intervención`
Actualmente el campo `objetivos` es texto libre. Se prevé evolución a lista estructurada
con indicadores medibles. Diferido a fases posteriores.

---

**Implementar servicios pendientes del módulo Agenda (30 tests bloqueados)** — 2026-05-18
`Módulo: Agenda`
Tras implementar `SlotMaterializadorService`, quedan pendientes:
- `CuadranteGeneratorService` (bloquea PF-03.1, PF-03.4, PF-03.5, PF-10.1)
- `DisponibilidadService` (bloquea PF-04.2, PF-09.1, PF-09.2)
- `SlotExpirationJob` (bloquea PF-04.4, PF-04.5, PF-06.3)
- Lógica de ciclo de vida de `Cita` (bloquea PF-05.1, PF-05.2, PF-05.4–PF-05.8)
- Bloqueo de slots al crear `EventoAgenda` (bloquea PF-04.3, PF-08.1–PF-08.4)
- `GestionAusenciaService` (bloquea PF-06.1, PF-06.2, PF-07.1–PF-07.5)
- Validación de solapamiento en `PerfilHorarioProfesional` (bloquea PF-02.3)
Ver `docs/modulo-agenda.md §8` para detalle de cada test.

---

**Estrategia de onboarding de un centro nuevo en el módulo Agenda** — (desde `docs/modulo-agenda.md`)
`Módulo: Agenda`
Definir la configuración mínima necesaria para activar el módulo en un centro nuevo y
el comportamiento del sistema si un centro no tiene `HorarioCentro` configurado.
Diferido al diseño de la interfaz de onboarding.

---

**Compatibilidad ENI / ENS para adopción por otras administraciones** — 2025-05-13
`Transversal`
Si el proyecto va a ser adoptado por otros ayuntamientos, revisar el cumplimiento del
Esquema Nacional de Interoperabilidad y el Esquema Nacional de Seguridad.
Las decisiones de arquitectura actuales no deben cerrar esta puerta.

---

## Convenciones de este fichero

- Añadir entradas con fecha en formato `YYYY-MM-DD`.
- Incluir el módulo afectado cuando sea específico.
- Cuando una deuda técnica se resuelva, mover la entrada a `CHANGELOG.md` con la nota
  "Resuelto en [fecha]" y eliminarla de aquí.
- Claude CLI puede añadir entradas a este fichero cuando detecte decisiones pendientes
  o cuando las instrucciones de una sesión lo indiquen explícitamente.
