# Documentación del Proyecto VIDA 360

> **VIDA 360** (Visión Integral de la Persona en Atención Social) es la plataforma de gestión de servicios sociales del Ayuntamiento de Madrid. Centraliza la historia social de los ciudadanos, los planes de intervención, los recursos y prestaciones disponibles, y las comunicaciones entre profesionales.
>
> Este documento es la referencia técnica del proyecto. Está organizado por módulos Laravel; cada capítulo incluye una introducción funcional extraída de los documentos de diseño, seguida de la referencia de código (modelos Eloquent y recursos Filament implementados). Los módulos pendientes de implementación se documentan como capítulos planificados.
>
> Generado: marzo 2026. Actualizado: junio 2026. Fuentes: `docs/`, código fuente de `vida/`, `CHANGELOG-052026.md`, `CHANGELOG-062026.md`.

---

## Índice

1. [Introducción](#1-introducción)
2. [Núcleo (`app/`)](#2-núcleo-app)
3. [Módulo Organización](#3-módulo-organización)
4. [Módulo Usuarios y Permisos](#4-módulo-usuarios-y-permisos)
5. [Módulo Prestaciones](#5-módulo-prestaciones)
6. [Módulo Centro](#6-módulo-centro)
7. [Módulo Mensajes](#7-módulo-mensajes)
8. [Módulo Escalas](#8-módulo-escalas)
9. [Módulo Documentos](#9-módulo-documentos)
10. [Módulo Agenda](#10-módulo-agenda)
11. [Módulo Ciudadanía](#11-módulo-ciudadanía)
12. [Módulo Intervención](#12-módulo-intervención)
13. [Módulo Auditoría](#13-módulo-auditoría)
14. [Módulo Integraciones — Planificado](#14-módulo-integraciones--planificado)
15. [Anexo A: Principios Técnicos](#anexo-a-principios-técnicos-de-vida-360)
16. [Anexo B: Glosario](#anexo-b-glosario)

---

## Estado de implementación (junio 2026)

| Módulo | Estado | Tests | Notas |
|---|---|---|---|
| Organización | ✅ Completo | — | Catálogos base implementados |
| Usuarios y Permisos | ✅ Completo | TF-USU-01..15 ✅ | TF-USU-16/17 bloqueados (ver decisiones) |
| Prestaciones | ✅ Completo | TF-PRE-01..13 ✅ | TF-PRE-13 con stub de Intervención |
| Centro | ✅ Completo | — | Entidad Servicio implementada (mayo 2026) |
| Mensajes | ✅ Completo | — | BuzonPage operativa (junio 2026) |
| Escalas | ✅ Fase 1 | TF-ESC-A01..C04 ✅ | Fase 2 (Livewire pases) pendiente |
| Documentos | ✅ Completo | TF-DOC-01..21 ✅ | Merge tags implementados |
| Agenda | ✅ Dominio completo | 45 tests ✅ | UI en fixtures de desarrollo; 30 tests bloqueados |
| Ciudadanía | 🚧 En desarrollo | TF-LW-NAV-16..24 ✅ | Alta + ficha implementadas; CiudadanoIdentificadores pendiente |
| Intervención | 🚧 En desarrollo | TF-LW-AGE, CIU, CAS, BUS, BUZ ✅ | CiudadanoPage operativa; derivaciones y PISO completos pendientes |
| Auditoría | ✅ Completo | TF-AUD-01..29 ✅ | Implementado junio 2026 |
| Integraciones | 📋 Planificado | — | |

---

## 1. Introducción

### 1.1 Contexto organizativo

El modelo organizativo de los servicios sociales municipales de Madrid es análogo al sanitario. Toda persona tiene asignado un **centro de servicios sociales** y un **Trabajador Social de Referencia (TSR)** según su dirección postal. El TSR es el gestor global del caso: conoce la situación integral de la persona, coordina las intervenciones activas y decide las derivaciones a atención especializada.

La **Atención Social Primaria (ASP)** es la puerta de entrada universal. El primer contacto pasa habitualmente por el **Servicio de Información y Asesoramiento (SIA)**, que evalúa la demanda, informa sobre prestaciones ajenas o deriva al TSR cuando corresponde.

El TSR puede derivar a **atención especializada** (dependencia, infancia, inserción sociolaboral, salud mental, etc.). Existen puertas de entrada alternativas para **Violencia de Género** y **Personas Sin Hogar**, con circuitos diferenciados pero siempre con Historia Social única.

### 1.2 Stack tecnológico

| Componente | Tecnología |
|---|---|
| Framework | Laravel 12 / PHP 8.3 |
| Base de datos (dev/prod) | PostgreSQL 15 |
| Base de datos (tests) | SQLite in-memory |
| Frontend | Blade/Livewire 4, Alpine.js, Bootstrap 5, Vite |
| Backoffice | Filament 5.3 |
| Módulos | nwidart/laravel-modules v12 |
| Roles y permisos | spatie/laravel-permission |
| Jerarquía de UO | staudenmeier/laravel-adjacency-list |
| Adjuntos | spatie/laravel-medialibrary |
| Auditoría | trait `Auditable` → tabla `audits` + `AuditService` |
| Versionado histórico | trait `Versionable` → tabla `versiones` |
| Análisis estático | PHPStan nivel 6 + Rector + Pint |
| CI | GitHub Actions (quality + ci workflows) |

### 1.3 Principio de diseño dual: Filament vs. Livewire

**Filament** gestiona la capa de configuración y backoffice: catálogos, plantillas, parámetros del sistema, usuarios y permisos. **Livewire** gestiona las capas operativas: el trabajo diario de los profesionales con ciudadanos, planes, apuntes y agenda. Esta separación es estructural.

Los modelos sensibles de ciudadanos (`HistoriaSocial`, `Apunte`, `Ciudadano`, `PlanDeIntervencion`) se gestionan exclusivamente vía Livewire. Ningún Resource de Filament gestiona datos de ciudadanos directamente.

### 1.4 Dos tipos de usuario

| Tipo | Modelo | Tabla | Authenticatable |
|---|---|---|---|
| Profesional/Admin | `App\Models\User` | `users` | Sí (Sanctum) |
| Ciudadano | `App\Models\Ciudadano` (stub) | `ciudadanos` | No |

### 1.5 Navegación operativa

La interfaz operativa (Livewire) tiene su propio layout (`operativo.blade.php`) con sidebar de 196px, topbar de 56px y área principal flexible. Las pantallas principales son:

- `/intervencion/agenda` — AgendaPage (vistas día/semana/mes, fixture de desarrollo)
- `/intervencion/casos` — MisCasosPage (planes activos del profesional)
- `/intervencion/buscar` — BuscarCiudadanoPage (búsqueda con tres niveles de acceso)
- `/intervencion/mensajes` — BuzonPage (alertas / avisos / mensajes)
- `/intervencion/ciudadano/{historia}` — CiudadanoPage (pantalla de trabajo con Historia Social)
- `/ciudadano/{ciudadano}/ficha` — FichaCiudadanoPage (vista transversal del ciudadano)

---

## 2. Núcleo (`app/`)

Esta sección agrupa los modelos que viven en `app/Models/` fuera de cualquier módulo: las entidades transversales que todos los módulos consumen.

### User

**Archivo:** `app/Models/User.php`
**Tabla:** `users`
**Descripción:** Cuenta de acceso al sistema. Representa la identidad de autenticación — las credenciales y la trazabilidad de auditoría. Es el *"quién ha hecho esto"* en todo el sistema.

Un `User` casi siempre tiene un `Profesional` asociado (su perfil organizativo), salvo perfiles técnicos sin función asistencial (`adm_sistema`), para los que `profesional_id` es `null`.

**Traits:** `HasRoles` (Spatie), `TieneUO`, `TieneRoles`, `Notifiable`

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `profesional_id` | int FK nullable | Perfil organizativo (nullable para admins técnicos) |
| `name` | string | |
| `email` | string | |
| `password` | string | Cast `hashed` — no usar bcrypt() al crear |
| `email_verified_at` | datetime | |
| `primer_acceso` | boolean | `true` hasta que complete el onboarding |

**Filament:** `UsuarioResource` (grupo *Usuarios y Profesionales*)

---

### UnidadOrganizativa

**Archivo:** `app/Models/UnidadOrganizativa.php`
**Tabla:** `unidades_organizativas`
**Descripción:** Nodo en la jerarquía organizativa del ayuntamiento. La jerarquía se implementa como *Adjacency List* con `parent_id` auto-referencial; las consultas recursivas se ejecutan mediante CTEs nativas de PostgreSQL via `staudenmeier/laravel-adjacency-list`.

**Traits:** `HasRecursiveRelationships`, `SoftDeletes`

**Propiedades clave (incluidas las de junio 2026):**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `nombre` | string | |
| `nombre_corto` | string(40) nullable | Para mostrar en badges de la UI operativa |
| `tipo` | string | Clave de catálogo (`ayuntamiento`, `dg`, `departamento`, `centro`…) |
| `parent_id` | int FK nullable | Nodo padre; null para la raíz |
| `plan_nombre_completo` | string nullable | Nombre completo del plan de intervención para esta UO (fallback: «Plan de intervención») |
| `plan_nombre_corto` | string nullable | Nombre corto del plan para esta UO (fallback: «Plan») |
| `activa` | boolean | |

**Filament:** `UnidadOrganizativaResource` (grupo *Organización*) — incluye sección «Plan de intervención» y campo `nombre_corto`.

---

### Audit

**Archivo:** `app/Models/Audit.php`
**Tabla:** `audits`
**Descripción:** Registro inmutable de toda acción realizada sobre datos sensibles. `update()` y `delete()` por instancia lanzan `LogicException`. Ver módulo Auditoría (sección 13) para la implementación completa.

**Nota:** `const UPDATED_AT = null`. La purga usa el query builder directamente.

---

### Version

**Archivo:** `app/Models/Version.php`
**Tabla:** `versiones`
**Descripción:** Snapshot histórico polimórfico. Cada fila contiene el estado completo de una entidad en el momento **anterior** a un cambio. Aplicado mediante el trait `App\Traits\Versionable`.

---

### Stubs del Núcleo

Los siguientes modelos existen como stubs o implementaciones parciales:

| Modelo | Descripción | Estado |
|---|---|---|
| `Ciudadano` | Ciudadano/beneficiario de los servicios | Activo (módulo Ciudadanía) |
| `HistoriaSocial` | Historia social del ciudadano | Activo (módulo Intervención) |
| `Apunte` | Acto profesional registrado en la historia | Parcial |
| `AccesoProtegido` | Solicitudes de acceso a ciudadanos de colectivos protegidos | Activo |

---

## 3. Módulo Organización

**Namespace:** `Modules\Organizacion\Models`

Mantiene las tablas maestras de la estructura territorial y organizativa municipal: distritos, zonas de trabajo, colectivos especialmente protegidos y configuración de la organización. Diseñado para ser adaptable a cualquier municipio.

**Modelos:** `Distrito`, `Zona`, `ColectivoProtegido`, `Configuracion`

**Configuración de la organización (junio 2026):** `Configuracion` expone métodos estáticos `logoUrl()` y `nombreAplicacion()` (claves `logo_path` y `nombre_aplicacion` en `organizacion_configuracion`). La acción «Identidad visual» en Filament permite subir logotipo y cambiar el nombre de la aplicación. El sidebar Livewire muestra el logo con tres niveles de fallback: imagen → nombre texto → "VIDA360" + icono por defecto.

**Filament:** grupo *Organización*. `ConfiguracionOrganizacionResource` incluye la acción «Identidad visual».

---

## 4. Módulo Usuarios y Permisos

**Namespace:** `Modules\Usuarios`

Gestiona roles, permisos atómicos, adscripciones UO y acceso a colectivos protegidos. 15 tests funcionales pasando (TF-USU-01..15). TF-USU-16 y TF-USU-17 bloqueados pendientes de confirmar modelado de `unidad_organizativa_id` en apuntes.

**Roles definidos:** `adm_sistema`, `adm_usuarios`, `supervision`, `intervencion`, `tramitacion`, `consulta_basica`.

**Seguridad en profundidad (mayo 2026):** Policies completas para HistoriaSocial, Apunte, Ciudadano y PlanDeIntervencion con tres pasos: permiso atómico → ámbito UO → colectivo protegido. GlobalScope `AmbitoUoScope` aplicado a todos los modelos sensibles.

**Filament:** grupo *Usuarios y Profesionales*.

---

## 5. Módulo Prestaciones

**Namespace:** `Modules\Prestaciones`

Catálogo de ~112 prestaciones organizadas en 8 objetivos generales. 13 tests funcionales (TF-PRE-01..13). TF-PRE-13 con stub de integración de Intervención.

**Selector de prestaciones en Centro (junio 2026):** `SelectorPrestacionesCentro` — componente Livewire con SlideOver en Filament. Layout dos columnas (catálogo 2/3 + seleccionadas 1/3), búsqueda por texto, filtros por segmento (pendiente de implementar), modal de detalle. El filtro por segmento tiene TODO: ver BACKLOG.

---

## 6. Módulo Centro

**Namespace:** `Modules\Centro`

Gestiona centros de servicios sociales, redes de centros, espacios, actividades y prescripciones de plaza.

**Entidad Servicio (mayo 2026):** Servicio ≠ Centro. El servicio no tiene infraestructura propia (Art. 53, Ley 12/2022). Implementado como entidad independiente relacionada opcionalmente con Centro.

**PrescripcionService:** `liberarPlaza()` usa un resolver inyectable para el TSR activo. En producción debe conectarse al módulo Ciudadanía cuando esté disponible. Actualmente devuelve null (ver BACKLOG).

---

## 7. Módulo Mensajes

**Namespace:** `Modules\Mensajes`

Sistema de mensajería interna con dos severidades de alerta: `aviso` (descartable) y `alerta` (requiere reconocimiento explícito en 4 horas laborales).

**BuzonPage (junio 2026):** bandeja unificada con tres pestañas (Alertas / Avisos / Mensajes). Reconocimiento de alertas, respuesta a hilos, contador de no leídos, modal de redacción de nuevo mensaje con búsqueda de destinatario.

**Topbar operativo (junio 2026):** `layouts/operativo.blade.php` incluye `<header class="op-topbar">` con menú de usuario Alpine.js (avatar con iniciales, nombre, rol, cerrar sesión). El dropdown Bootstrap del sidebar fue eliminado.

---

## 8. Módulo Escalas

**Namespace:** `Modules\Escalas`

Diseñador y aplicador de escalas de valoración estandarizadas.

**Estado:** Fase 1 completa (mayo 2026). Fase 2 pendiente.

**Modelos:** `TipoEscala`, `PaseEscala`

**TipoEscala:** schema configurable con secciones, ítems y opciones. Validación al guardar. Inmutabilidad de código y opciones cuando existen pases asociados. Scope `scopeAplicables`.

**PaseEscala:** estados `borrador` / `completado`. Inmutabilidad de scores e interpretación en estado `completado`. Métodos `calcularScores()`, `asignarInterpretacion()`, `completar()`.

**Escalas incluidas en seeder:** Barthel, Pfeiffer SPMSQ, Lawton-Brody AIVD. Pendientes de confirmar licencia: Zarit ZBI, GDS Yesavage.

**TipoEscalaResource (Filament):** grupo «Informes y Plantillas», sort 4. Formulario en 3 pestañas (datos, estructura, rangos) con Builder nativo de Filament.

**TipoFichaResource (Filament, junio 2026):** grupo «Informes y Plantillas», sort 3. Formulario con Builder de 6 tipos de bloque (texto, numero, select, booleano, fecha, escala). Validación de schema y guardia de inmutabilidad cuando existen fichas asociadas. 10 tests TF-INT-H01..H10 pasando.

**Tests:** TF-ESC-A01..C04 (18 tests ✅), TF-INT-H01..H10 (10 tests ✅).

---

## 9. Módulo Documentos

**Namespace:** `Modules\Documentos`

Gestión de plantillas de informe, generación de documentos y merge tags.

**Merge tags (mayo 2026):** `MergeTagsCatalogo` con 26 variables en 6 categorías. `PlantillaInformeResource` con campo `secciones` como Builder de dos tipos de bloque (automatico / texto_libre con RichEditor y merge tags). Variables pendientes de módulo Intervención devuelven `'—'`.

**Tests:** TF-DOC-01..21 (21 tests ✅).

---

## 10. Módulo Agenda

**Namespace:** `Modules\Agenda`

Sistema de agenda configurable por centro con tres modos (básico, estándar, avanzado).

**Estado:** dominio completo (45 tests pasando). La interfaz de usuario usa fixtures de desarrollo en `AgendaPage`. 30 tests bloqueados por servicios pendientes (ver BACKLOG).

**Servicios implementados:** `SlotMaterializadorService`, lógica de dominio completa de cuadrantes, horarios y excepciones.

**Servicios pendientes:** `CuadranteGeneratorService`, `DisponibilidadService`, `SlotExpirationJob`, lógica de ciclo de vida de `Cita`, `GestionAusenciaService`.

**AgendaPage (junio 2026):** vistas día/semana/mes con navegación de fechas, 4 KPIs y leyenda de colores de tipos de cita. Los KPIs usan `// TODO:` hasta que los servicios exporten los métodos necesarios. Enlace del nombre del ciudadano en cita bifurca según rol: `intervencion` → `CiudadanoPage`, otros → `FichaCiudadanoPage`.

---

## 11. Módulo Ciudadanía

**Namespace:** `Modules\Ciudadania`

Gestión del expediente del ciudadano: alta, identificación, situación social y ficha transversal.

**Estado:** en desarrollo activo (junio 2026).

**Componentes implementados:**
- Alta de ciudadano con validación de duplicados.
- `FichaCiudadanoPage`: vista transversal accesible a todos los roles. Edición de Capa 1 (datos de identificación), modal de añadir documento identificativo, banner de Historia Social (clicable para rol `intervencion`, no clicable para el resto), widget de prestaciones (`CiudadanoPrestacionResumen`), panel de accesos recientes al expediente (roles `intervencion`, `supervision`, `adm_sistema`).
- `CiudadanoPrestacionResumen`: tabla de agregación de prestaciones sin historia social. Scopes `activas()` y `recientes()`.
- `BuscarCiudadanoPage`: búsqueda con tres niveles de acceso. Modal de solicitud de acceso para colectivos protegidos.

**Entidades pendientes:** `CiudadanoIdentificador` (tabla `ciudadano_identificadores`), `UnidadDeConvivencia`.

**Acceso con `withoutGlobalScope(AmbitoUoScope::class)`:** la ficha es accesible aunque el ciudadano no tenga historia social en la UO del usuario.

---

## 12. Módulo Intervención

**Namespace:** `Modules\Intervencion`

Gestión de la Historia Social, planes de intervención, apuntes y herramientas de trabajo del TSR.

**Estado:** en desarrollo activo (junio 2026).

**Componentes implementados:**
- `CiudadanoPage`: pantalla principal de trabajo. Layout de 4 cuadrantes (datos ciudadano + UC / toolbox / timeline + accesos / trabajo + stats). Banda PISO activo a ancho completo. 7 herramientas: entrevista, anotación, derivación, gestión/coordinación (inline), valoración y escala (pantalla completa). Cabecera estructurada del ciudadano con nombre, HS, UO, badge estado, fecha nacimiento, teléfono y domicilio. Estadísticas de contexto (`statApuntes`, `statPrestaciones`—null—, `statUltimoContacto`).
- `RegistrarValoracionPage`, `RegistrarEscalaPage`.
- `MisCasosPage`: tabla paginada de planes activos con semáforo de colores y cabecera PISO configurable.
- `AccesosExpedienteQuery`: query object compartido que encapsula la lógica de filtrado por visibilidad.
- Panel de últimos accesos al expediente en `CiudadanoPage` y `FichaCiudadanoPage`.

**Nombre del Plan configurable por UO (junio 2026):** `CiudadanoPage` usa `planNombreCorto()` y `planNombreCompleto()` en lugar del literal «PISO». Los valores se configuran en `UnidadOrganizativa`.

**Entidades y tablas pendientes:** `Derivacion`, `ciudadano_identificadores`, `unidades_convivencia`.

**AmbitoUoScope y binding:** ver `decisiones-tecnicas.md` sección 3.6.

**Tests:** TF-LW-AGE-01..14, TF-LW-CAS-01..07, TF-LW-BUS-01..10, TF-LW-BUZ-01..06, TF-LW-CIU-01..23, TF-LW-NAV-01..24, TF-INT-G02, TF-AUD-INT-01..11. Total >100 tests operativos.

---

## 13. Módulo Auditoría

**Implementado:** junio 2026 (2026-06-14).

**Componentes:**
- `AuditService` — punto único de escritura. Resuelve `ciudadano_id`, enriquece contexto con canal y ruta.
- `AuditObserver` — registra automáticamente `crear/editar/eliminar` para modelos con trait `Auditable`.
- `AuditPurgeCommand` — `audit:purge` scheduled a las 03:00. Retención configurable vía `CatalogoSistema`.
- `AuditarAccesoCiudadano` middleware — red de seguridad de segunda línea; registra acceso en rutas con `{ciudadano}`.
- `AuditResource` (Filament) — solo lectura; scope de UO para supervisores; filtro de fechas obligatorio (máx 90 días). Grupo *Sistema*, sort 6.
- Panel de accesos recientes al expediente en `FichaCiudadanoPage` y `CiudadanoPage`.

**Trait `Auditable` aplicado a:** `Ciudadano`, `HistoriaSocial`, `Apunte` (Intervención), `PlanDeIntervencion`, `Valoracion`, `Entrevista`, `PaseEscala`, `Informe`, `CiudadanoIdentificador`.

**Acciones auditadas:** `ver | crear | editar | eliminar | exportar | imprimir | acceso_restringido` (enum `AccionAuditEnum`).

**Tests:** TF-AUD-01..29 (29 tests, 74 assertions, todos ✅).

---

## 14. Módulo Integraciones — Planificado

Pendiente. Ver `docs/decisiones-pendientes.md` sección Integraciones para el detalle de las 6 decisiones pendientes.

---

## Anexo A: Principios Técnicos de VIDA 360

Ver `docs/principios-vida360.md`.

---

## Anexo B: Glosario

Ver `docs/glosario.md`.
