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

**Customizador de marca (logo, colores, tipografía)** — mayo 2026
Permitir que cada municipio que adopte VIDA 360 adapte la apariencia visual a su imagen corporativa sin tocar código: logo, color primario, color secundario y tipografía (Google Fonts). Los cambios deben aplicarse tanto al backoffice Filament como a la superficie operativa Livewire.

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

**Módulo Escalas fase 2 — Livewire de aplicación del pase** — 2026-05-26
`Módulo: Escalas / Intervención`
Componente Livewire para aplicar un pase de escala desde la Historia Social del ciudadano:
selección de instrumento, presentación sección a sección, confirmación de instrucciones si
`confirmar_instrucciones=true`, cierre del pase con cálculo de scores, y visualización del
historial de pases por escala ordenado cronológicamente.
Bloqueante: definir el punto de entrada desde la Historia Social (¿pestaña independiente?
¿acción en la ficha de valoración?).

---

**Clarificación licencia Zarit ZBI** — 2026-05-26
`Módulo: Escalas`
Contactar con Steven Zarit (Pennsylvania State University) para confirmar si el uso en un
sistema público municipal de servicios sociales no comercial está cubierto por la excepción
de uso clínico. Si se confirma, añadir al `EscalaSeeder`.
Ref: `docs/modulo-escala.md §7.2`.

---

**Clarificación licencia GDS de Yesavage** — 2026-05-26
`Módulo: Escalas`
Aclarar si la versión original de 30 ítems (1983) está en dominio público y si la traducción
española validada es de libre uso en contexto público no comercial.
Ref: `docs/modulo-escala.md §7.2`.

---

**Conectar PrescripcionService al TSR activo del ciudadano** — 2026-05-18
`Módulo: Centros / Ciudadanía`
`PrescripcionService::liberarPlaza()` usa un resolver inyectable para el TSR activo del ciudadano.
En producción debe conectarse al módulo Ciudadanía (o al registro de HistoriaSocial del módulo Intervención)
cuando esté disponible. Actualmente el resolver por defecto devuelve null (sin actualización de alerta).
Ref: `Modules/Centro/app/Services/PrescripcionService.php` — método `setTsrResolver`.

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

**Calibración del valor K en perfiles de anonimización** — 2026-05-21
'Transversal / API'
K=10 como valor por defecto para datos abiertos es conservador. Evaluar si perfiles
de investigación con convenio pueden usar K=5 con salvaguardas adicionales.
Requiere consulta con el Delegado de Protección de Datos.

---

**Tratamiento de colectivos protegidos en extracciones anonimizadas** — 2026-05-21
'Transversal / API'
Definir si los registros de ciudadanos de colectivos protegidos (VVG, PSH) se excluyen
de extracciones de Nivel 2 y 3 incluso después de anonimizar, o si la anonimización
es garantía suficiente. Requiere análisis legal y consulta con DPD.

---

**Generalización de ubicación para PSH en extracciones analíticas** — 2026-05-21
'Transversal / API'
Las PSH tienen coordenadas de pernocta, no dirección postal. Definir cómo se
generaliza este campo en extracciones: zona, distrito de intervención, o supresión
si la densidad es insuficiente para garantizar k-anonimato.

---

**Validación formal del proceso de k-anonimato por el DPD** — 2026-05-21
'Transversal / API'
Antes de la primera publicación en el portal de datos abiertos, someter el proceso
de k-anonimato a revisión formal por el Delegado de Protección de Datos.
Bloqueante para la activación del perfil datos_abiertos.

---

**Sandbox de API** — 2026-05-20
'API'
Entorno separado con datos ficticios para desarrollo de integraciones externas.
Diferido hasta que haya un integrador externo real que lo necesite.
Prerequisito: factories y seeders completos para todas las entidades principales.

---

---

**Fusionar app/Services/HistoriaSocialService con Modules/Intervencion/Services/HistoriaSocialService** — 2026-05-25
`Intervencion`
Actualmente coexisten dos HistoriaSocialService con namespaces distintos. El de App\Services es
un stub de integración con el módulo de Mensajes. El de Modules\Intervencion\Services es la capa
de seguridad nueva. Cuando se consolide el módulo Intervencion, deben fusionarse en uno solo.

---

**Módulo Ciudadania — implementar Ciudadano completo** — 2026-05-25
`Ciudadania` (pendiente de crear)
El modelo App\Models\Ciudadano es un stub que ya tiene AmbitoUoScope y CiudadanoPolicy
pero no tiene lógica de negocio real (deduplicación por documento, niveles de identificación,
historial de cambios en datos personales, relaciones con Historia Social, etc.).
La referencia definitiva es Modules\Ciudadania\Models\Ciudadano (módulo pendiente de crear).

---

**Revisar permisos nuevos en UI Livewire** — 2026-05-25
`Ciudadania / Intervencion`
Los nuevos permisos `ciudadano.leer`, `ciudadano.eliminar`, `historia.crear`, `historia.eliminar`,
`apunte.leer`, `apunte.editar`, `apunte.eliminar`, `plan.leer`, `plan.eliminar` están creados
en el seeder y asignados a los roles, pero ningún componente Livewire los verifica todavía
porque esa UI no existe aún. Al implementar cada vista, verificar que se usan los permisos correctos.

---

**Merge tags — variables pendientes de módulo Intervención** — 2026-05-28
`Documentos`
Las siguientes variables del `MergeTagsCatalogo` devuelven `'—'` porque dependen de
relaciones/modelos del módulo Intervención aún no disponibles:
- `numero_expediente`, `motivo_demanda` — campo en HistoriaSocial stub (pendiente de implementación completa)
- `lista_prestaciones`, `fecha_inicio_plan`, `objetivos_plan` — requieren modelo `PlanDeIntervencion`
- `cargo_profesional`, `numero_colegiado` — requieren campos extendidos en Profesional
- `nombre_centro`, `direccion_centro`, `telefono_centro` — requieren relación `User→Centro` en módulo Usuarios
Al implementar cada módulo, completar `ResolverFuentesInforme::construirMapaValores()` con el valor real.

---

**Semántica temporal en el log de accesos**
'Usuarios'
El log debe mostrar la UO del usuario en el momento del acceso, no la actual. Dos opciones de implementación: (a) snapshot de unidad_organizativa_id en la tabla de auditoría en el momento del acceso — preferida, dato inmutable; (b) reconstrucción a posteriori consultando usuario_uo por fecha — más frágil. Pendiente de decidir en el diseño del módulo de auditoría. En la UI, considerar nota discreta al expandir un registro cuando el centro del log difiere del centro actual del profesional.

---

## Convenciones de este fichero

- Añadir entradas con fecha en formato `YYYY-MM-DD`.
- Incluir el módulo afectado cuando sea específico.
- Cuando una deuda técnica se resuelva, mover la entrada a `CHANGELOG.md` con la nota
  "Resuelto en [fecha]" y eliminarla de aquí.
- Claude CLI puede añadir entradas a este fichero cuando detecte decisiones pendientes
  o cuando las instrucciones de una sesión lo indiquen explícitamente.
