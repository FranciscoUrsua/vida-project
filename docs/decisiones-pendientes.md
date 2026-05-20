# Decisiones pendientes — VIDA 360

> Inventario de decisiones pospostas durante el análisis funcional, organizado por módulo.
> Generado: mayo 2026. Actualizado: mayo 2026. Fuente: revisión de `docs/`, `BACKLOG.md` y `CHANGELOG.md`.

**Total: 31 decisiones** — 4 bloqueantes · 14 técnicas/organizativas · 13 diferidas a fases posteriores

---

## Leyenda

| Etiqueta | Significado |
|---|---|
| 🔴 bloqueante | Debe resolverse antes de continuar con el módulo |
| 🟡 organizativa | Decisión fuera del equipo técnico; requiere validación funcional u organizativa |
| 🔵 técnica | Decisión de diseño técnico pendiente de tomar |
| ⚪ diferida | Pospuesta con rationale documentado; planificable en fases posteriores |

---

## Módulo Agenda

> **Fase 1 completa (mayo 2026):** toda la lógica de dominio está implementada y los 45 tests funcionales pasan. La decisión A-01 queda cerrada. Las pendientes restantes son de interfaz, integraciones externas y documentación de adopción.

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| A-02 | **Visibilidad del cuadrante para el propio profesional** | 🔵 técnica | Qué parte del cuadrante puede ver y editar el profesional sobre el suyo propio (solicitud de cambio de franja, visualización de compañeros). Diferido al diseño de la interfaz Livewire. |
| A-03 | **Integración RRHH vía API para excepciones de profesional** | 🔵 técnica | Campo `origen = api_rrhh` en `ExcepcionProfesional` preparado; adaptador mock activo. Diferido hasta identificar el sistema de RRHH del municipio adoptante. |
| A-04 | **Onboarding de un centro nuevo en el módulo Agenda** | ⚪ diferida | Configuración mínima necesaria para activar el módulo en un centro nuevo y comportamiento del sistema si no existe `HorarioCentro`. Diferido al diseño de la interfaz de onboarding. |
| A-05 | **Estrategia de migración entre modos de agenda** | ⚪ diferida | La subida de modo `basico` → `estandar` es compatible sin transformación de datos. Documentar explícitamente en la guía de adopción. |
| A-06 | **Interfaz Livewire / Filament del módulo Agenda** | 🔵 técnica | Pendiente de implementar toda la capa de presentación: vista de agenda del profesional, gestión de ausencias, cuadrante del supervisor, formularios de eventos. Ver `docs/modulo-agenda.md §5`. |
| A-07 | **Generación IA de cuadrantes (modo avanzado)** | ⚪ diferida | Diseño del componente IA: modelo, inputs, outputs, criterios de calidad. Requiere datos históricos suficientes. Diferido hasta que el módulo lleve al menos un año en producción. |

---

## Módulo Intervención

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| I-01 | **Objetivos del plan como lista estructurada con indicadores** | ⚪ diferida | Actualmente `objetivos` es texto libre. Se prevé evolución a lista estructurada con indicadores medibles para permitir cierre cuantitativo del plan. Fase posterior. |
| I-02 | **Pilotaje de la Self-Sufficient Matrix (SSM)** | 🟡 organizativa | La arquitectura soporta la SSM como `tipo_ficha` configurable. La decisión de adopción, pilotaje y formación es organizativa, no técnica. |
| I-03 | **Asistencia de IA durante la entrevista** | ⚪ diferida | Descartado en fase inicial por complejidad. IA sugiriendo preguntas al profesional y proponiendo estructura de fichas en tiempo real. Retomar cuando el flujo base esté consolidado. Requisitos documentados en el historial de decisiones. |
| I-04 | **Transcripción automática de audio** | ⚪ diferida | Descartado como flujo estándar. Razones: riesgo de inhibición del ciudadano, complejidad del consentimiento en contextos de vulnerabilidad, dificultades con colectivos con problemas de lenguaje, coste de mantenimiento de modelo local. Puede reconsiderarse como opción voluntaria para tipos específicos de entrevista. |

---

## Módulo Centros

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| C-01 | **Diseño de la entidad Servicio** | 🔴 bloqueante | Servicio ≠ Centro (el servicio no tiene infraestructura propia). Diseño pendiente. Necesario para completar el módulo de Centros y el catálogo de recursos. |
| C-02 | **Conectar `PrescripcionService` al TSR activo del ciudadano** | 🔵 técnica | `PrescripcionService::liberarPlaza()` usa un resolver inyectable. Actualmente devuelve `null`. En producción debe conectarse al módulo Ciudadanía o a `HistoriaSocial` de Intervención cuando esté disponible. Ref: `Modules/Centro/app/Services/PrescripcionService.php`. |
| C-03 | **Gestión presupuestaria de centros privados puros** | ⚪ diferida | Coste por plaza contratada y distribución presupuestaria anual para centros tipo `privado_puro` (pensiones, hoteles). Diferido por complejidad y dependencia de procesos administrativos externos. |

---

## Módulo Prestaciones

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| P-01 | **Diseño de la entidad Solicitud de prestación** | 🔴 bloqueante | La solicitud es configurable por tipo de prestación. El diseño detallado está pendiente. Bloquea la implementación completa del flujo de prestaciones. |
| P-02 | **Completar TF-PRE-13 con integración real de Intervención** | 🔵 técnica | Resolución histórica de prestación desde un plan de intervención. Actualmente implementado con stub. Completar cuando el módulo Intervención esté disponible. Ref: `docs/instrucciones-cli/prestaciones-tests.md`. |

---

## Módulo Usuarios y permisos

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| U-01 | **Revisar modelado de `unidad_organizativa_id` en apuntes** | 🔴 bloqueante | Los tests TF-USU-16 y TF-USU-17 (protección granular de apuntes de colectivos protegidos) asumen que cada apunte lleva `unidad_organizativa_id` y que la Policy evalúa visibilidad apunte a apunte. Confirmar este modelado en `docs/modulo-intervencion.md` antes de implementar. |
| U-02 | **Implementar TF-USU-16 y TF-USU-17** | 🔵 técnica | Dependiente de U-01. Una vez confirmado el modelado, implementar ambos tests y eliminar el aviso de bloqueo en las instrucciones CLI. Ref: `docs/instrucciones-cli/usuarios-tests.md`. |

---

## Módulo Mensajes y alertas

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| M-01 | **Integración `HorarioLaboralService` con Agenda** | 🔵 técnica | El cálculo de vencimientos en horas laborales usa un horario por defecto hasta que el módulo de Agenda esté disponible. En ese momento, el servicio debe actualizarse para consumir el calendario laboral real. |
| M-02 | **Notificación externa de aviso** | ⚪ diferida | Correo de aviso del tipo "tienes mensajes nuevos en VIDA" sin exponer contenido, para profesionales que no consultan la aplicación frecuentemente. Requiere decisión explícita antes de implementarse. |
| M-03 | **Delegación de mensajería por ausencia** | ⚪ diferida | Cuando un profesional está de baja o vacaciones, sus mensajes directos no tienen receptor activo. Se diseñará conjuntamente con el módulo de Agenda. Gap conocido y documentado. |

---

## Módulo Integraciones

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| INT-01 | **Definir proveedor de pasarela multicanal (notificaciones)** | 🟡 organizativa | Canal de comunicación oficial bidireccional con el ciudadano. Prerequisito para notificaciones de cita, avisos de prestaciones, etc. Canal a determinar según la infraestructura del municipio adoptante (carpeta ciudadana, SMS, email). |
| INT-02 | **Definir proveedor de videollamada** | 🟡 organizativa | Sin proveedor definido. |
| INT-03 | **Diseñar la integración HSU-CM** | 🔵 técnica | Convenio de interoperabilidad con la Historia Social Única de la Comunidad de Madrid: scopes expuestos y modelo de usuario actuante. |
| INT-04 | **Estrategia de autenticación para despliegues fuera del Ayuntamiento de Madrid** | 🔵 técnica | Para municipios adoptantes sin la infraestructura de autenticación del Ayuntamiento de Madrid. |
| INT-05 | **Análisis de base legal y EIPD para la integración VIOMAD** | 🔴 bloqueante | Análisis de base legal y Evaluación de Impacto en Protección de Datos requeridos antes de cualquier implementación de la integración con VIOMAD. |
| INT-06 | **Definir política completa de scopes de API** | 🔵 técnica | Qué sistemas pueden acceder a qué datos y con qué condiciones. Inventario de integraciones a completar conforme se identifiquen en fases posteriores. |

---

## Transversal / Principios

| # | Decisión | Tipo | Notas |
|---|---|---|---|
| T-01 | **Matching y deduplicación de identidades** | ⚪ diferida | Estrategia para gestionar cambios de documento identificativo, cambios de nombre/sexo y posibles duplicidades entre ciudadanos. |
| T-02 | **Interfaz con el gestor de expedientes administrativos** | ⚪ diferida | Integración para iniciar solicitudes, consultar estados e incorporar resoluciones a la Historia Social desde el gestor de expedientes municipal. |
| T-03 | **Integración con la carpeta ciudadana del Ayuntamiento** | ⚪ diferida | Exposición de APIs para publicar información y documentos en la carpeta ciudadana municipal. |
| T-04 | **Integración con el RAG del SIA** | ⚪ diferida | Incorporación de la herramienta de asistencia al profesional del SIA como capa opcional de apoyo. |
| T-05 | **Análisis de sesgo para componentes de IA** | ⚪ diferida | Metodología y criterios específicos para evaluar el impacto de cada componente de IA sobre los distintos colectivos atendidos. |
| T-06 | **Compatibilidad ENI / ENS para adopción por otras administraciones** | 🔵 técnica | Revisar cumplimiento del Esquema Nacional de Interoperabilidad y del Esquema Nacional de Seguridad si el proyecto se adopta por otros ayuntamientos. Las decisiones de arquitectura actuales no deben cerrar esta puerta. |

---

## Resumen por módulo

| Módulo | Bloqueantes | Técnicas/Org. | Diferidas | Total |
|---|---|---|---|---|
| Agenda | — | 3 | 3 | **6** *(A-01 cerrado; A-06, A-07 añadidos)* |
| Intervención | — | 1 | 3 | **4** |
| Centros | 1 | 1 | 1 | **3** |
| Prestaciones | 1 | 1 | — | **2** |
| Usuarios y permisos | 1 | 1 | — | **2** |
| Mensajes y alertas | — | 1 | 2 | **3** |
| Integraciones | 1 | 3 | — | **6** (*+ 2 org.)* |
| Transversal | — | 1 | 5 | **6** |
| **Total** | **4** | **14** | **13** | **31** |

---

*Documento generado a partir de la revisión del análisis funcional — mayo 2026.
Para registrar la resolución de una decisión, mover la entrada a `CHANGELOG.md` con la nota "Resuelto en [fecha]" y eliminarla de este fichero.*
