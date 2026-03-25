# Documentación del Proyecto VIDA 360

> **VIDA 360** (Visión Integral de la Persona en Atención Social) es la plataforma de gestión de servicios sociales del Ayuntamiento de Madrid. Centraliza la historia social de los ciudadanos, los planes de intervención, los recursos y prestaciones disponibles, y las comunicaciones entre profesionales.
>
> Este documento es la referencia técnica del proyecto. Está organizado por módulos Laravel; cada capítulo incluye una introducción funcional extraída de los documentos de diseño, seguida de la referencia de código (modelos Eloquent y recursos Filament implementados). Los módulos pendientes de implementación se documentan como capítulos planificados.
>
> Generado: marzo 2026. Fuentes: `docs/`, código fuente de `vida/`.

---

## Índice

1. [Introducción](#1-introducción)
2. [Núcleo (`app/`)](#2-núcleo-app)
3. [Módulo Organización](#3-módulo-organización)
4. [Módulo Usuarios y Permisos](#4-módulo-usuarios-y-permisos)
5. [Módulo Prestaciones](#5-módulo-prestaciones)
6. [Módulo Centro](#6-módulo-centro)
7. [Módulo Mensajes](#7-módulo-mensajes)
8. [Módulo Ciudadanía — Planificado](#8-módulo-ciudadanía--planificado)
9. [Módulo Intervención — Planificado](#9-módulo-intervención--planificado)
10. [Módulo Integraciones — Planificado](#10-módulo-integraciones--planificado)
11. [Anexo A: Principios Técnicos](#anexo-a-principios-técnicos-de-vida-360)
12. [Anexo B: Glosario](#anexo-b-glosario)

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
| Jerarquía de UO | staudenmeir/laravel-adjacency-list |
| Adjuntos | spatie/laravel-medialibrary |
| Auditoría | trait `Auditable` → tabla `audits` |
| Versionado histórico | trait `Versionable` → tabla `versiones` |

### 1.3 Principio de diseño dual: Filament vs. Livewire

**Filament** gestiona la capa de configuración y backoffice: catálogos, plantillas, parámetros del sistema, usuarios y permisos. **Livewire** gestiona las capas operativas: el trabajo diario de los profesionales con ciudadanos, planes, apuntes y agenda. Esta separación es estructural (ver [Principio 3.12](#principio-312)).

### 1.4 Dos tipos de usuario

| Tipo | Modelo | Tabla | Authenticatable |
|---|---|---|---|
| Profesional/Admin | `App\Models\User` | `users` | Sí (Sanctum) |
| Ciudadano | `App\Models\Ciudadano` (stub) | `ciudadanos` | No |

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

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `profesional()` | `BelongsTo<Profesional>` | Perfil organizativo del usuario; null para admins técnicos |

**Filament:** `UsuarioResource` (grupo *Organización*)

---

### UnidadOrganizativa

**Archivo:** `app/Models/UnidadOrganizativa.php`
**Tabla:** `unidades_organizativas`
**Descripción:** Nodo en la jerarquía organizativa del ayuntamiento: puede ser el propio ayuntamiento (raíz), un Área de Gobierno, una Dirección General, un Departamento o un Centro. La jerarquía se implementa como *Adjacency List* con `parent_id` auto-referencial; las consultas recursivas (ancestros, descendientes) se ejecutan mediante CTEs nativas de PostgreSQL via `staudenmeir/laravel-adjacency-list`.

El permiso efectivo de un usuario resulta de la intersección de su rol (¿qué puede hacer?) y su UO (¿sobre qué puede hacerlo?). Un responsable adscrito a un nodo tiene visibilidad sobre todos los nodos bajo él.

**Traits:** `HasRecursiveRelationships`, `SoftDeletes`

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `nombre` | string | |
| `tipo` | string | Clave de catálogo (`ayuntamiento`, `dg`, `departamento`, `centro`…) |
| `parent_id` | int FK nullable | Nodo padre; null para la raíz |
| `activa` | boolean | |

**Relaciones directas:**

| Método | Tipo | Descripción |
|---|---|---|
| `padre()` | `BelongsTo<UnidadOrganizativa>` | UO padre; null para el nodo raíz |
| `hijas()` | `HasMany<UnidadOrganizativa>` | UOs un nivel por debajo |
| `usuarios()` | `HasMany<UsuarioUo>` | Usuarios adscritos a esta UO |

**Relaciones recursivas** (via trait):

- `->ancestors()` — todos los nodos superiores en la jerarquía
- `->descendants()` — todos los nodos inferiores (cualquier profundidad)
- `->ancestorsAndSelf()`, `->descendantsAndSelf()`, `->siblings()`

**Scopes:**

| Método | Descripción |
|---|---|
| `scopeActivas()` | Solo UOs activas |
| `scopeRaiz()` | Solo la raíz (sin padre) |

**Filament:** `UnidadOrganizativaResource` (grupo *Organización*)

---

### UsuarioUo

**Archivo:** `app/Models/UsuarioUo.php`
**Tabla:** `usuario_uo`
**Descripción:** Pivot de adscripción usuario–UO con historial. Registra en qué UO opera un usuario, con qué tipo de vínculo y durante qué período. Un usuario puede estar adscrito a varias UOs simultáneamente.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `usuario_id` | int FK | |
| `unidad_organizativa_id` | int FK | |
| `tipo_vinculo` | string | `interno` / `contratado` / `externo` |
| `fecha_inicio` | date | |
| `fecha_fin` | date nullable | Adscripción activa si null |

---

### CatalogoSistema

**Archivo:** `app/Models/CatalogoSistema.php`
**Tabla:** `catalogos_sistema`
**Descripción:** Implementa el [Principio 3.10](#principio-310): valores puramente clasificatorios que un administrador funcional puede añadir, renombrar u ordenar desde el backoffice sin necesidad de un deploy. **Restricción crítica:** estos valores nunca deben usarse en lógica de negocio (`match`/`if`); si el código toma decisiones basándose en un valor, ese catálogo debe ser un enum PHP.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `grupo` | string | Identificador del catálogo (ej: `prestacion.objetivo_general`) |
| `clave` | string | Valor interno usado en BD y código |
| `etiqueta` | string | Texto visible en la UI |
| `orden` | int | Control de presentación |
| `activo` | boolean | Baja lógica sin borrado físico |

**Métodos estáticos:**

| Método | Descripción |
|---|---|
| `opcionesParaSelect(string $grupo)` | Devuelve `[clave => etiqueta]` para selects de Filament |
| `opcionesParaSelectConPrefijo(string $grupo, string $prefijo)` | Igual, filtrado por prefijo de clave (útil para subcategorías dependientes) |

---

### Version

**Archivo:** `app/Models/Version.php`
**Tabla:** `versiones`
**Descripción:** Snapshot histórico polimórfico. Cada fila contiene el estado completo de una entidad en el momento **anterior** a un cambio. El registro principal tiene siempre el estado actual; esta tabla guarda el historial. Aplicado mediante el trait `App\Traits\Versionable` a todas las entidades no auxiliares del sistema.

Para conocer el estado de una entidad en la fecha X: recuperar el snapshot con `created_at` inmediatamente anterior a X.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `versionable_type` | string | Clase del modelo (`Profesional`, `Centro`…) |
| `versionable_id` | int | ID del registro |
| `datos` | array (JSON) | Estado completo en el momento del cambio |
| `usuario_id` | int FK nullable | Usuario que realizó el cambio |
| `motivo` | string nullable | Texto libre para cambios que lo requieren |
| `created_at` | timestamp | El timestamp ES la fecha de la versión |

**Nota:** `UPDATED_AT = null` (no se actualiza).

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `versionable()` | `MorphTo` | Entidad a la que pertenece esta versión |
| `usuario()` | `BelongsTo<User>` | Usuario que generó el cambio |

---

### Stubs del Núcleo

Los siguientes modelos existen como stubs en `app/Models/` y se implementarán en sus módulos correspondientes:

| Modelo | Descripción | Módulo objetivo |
|---|---|---|
| `Ciudadano` | Ciudadano/beneficiario de los servicios | [Módulo Ciudadanía](#8-módulo-ciudadanía--planificado) |
| `HistoriaSocial` | Historia social del ciudadano | [Módulo Intervención](#9-módulo-intervención--planificado) |
| `Apunte` | Acto profesional registrado en la historia | [Módulo Intervención](#9-módulo-intervención--planificado) |
| `AccesoProtegido` | Solicitudes de acceso a ciudadanos de colectivos protegidos | [Módulo Usuarios](#4-módulo-usuarios-y-permisos) |

---

## 3. Módulo Organización

**Namespace:** `Modules\Organizacion\Models`
**Directorio:** `vida/Modules/Organizacion/`

### Introducción funcional

El módulo Organización mantiene las tablas maestras de la estructura territorial y organizativa municipal: distritos, zonas de trabajo, colectivos especialmente protegidos y configuración de la organización. Es el módulo más básico del sistema — del que dependen el resto — y está diseñado para ser adaptable a cualquier municipio, no solo a Madrid.

Los **colectivos especialmente protegidos** (actualmente menores y víctimas de violencia de género) son configurables desde backoffice, siguiendo el [Principio 3.11](#principio-311): añadir un nuevo colectivo protegido es una operación de configuración, no de desarrollo.

---

### Distrito

**Archivo:** `Modules/Organizacion/app/Models/Distrito.php`
**Tabla:** `distritos`
**Descripción:** División territorial del municipio. Los 21 distritos de Madrid son los valores iniciales, adaptables a otros municipios. Un ciudadano tiene asignado un centro de servicios sociales según su distrito de residencia.

**Propiedades clave:** `id`, `nombre`, `codigo`, `latitud`, `longitud` (decimal:7), `activo`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `zonas()` | `HasMany<Zona>` | Zonas pertenecientes a este distrito |

**Scopes:** `scopeActivos()` — solo distritos activos.

**Filament:** `DistritoResource` (grupo *Organización*)

---

### Zona

**Archivo:** `Modules/Organizacion/app/Models/Zona.php`
**Tabla:** `zonas`
**Descripción:** Agrupación configurable de unidades censales dentro de un distrito. Permite distribuir la carga de trabajo entre profesionales del mismo centro.

**Propiedades clave:** `id`, `nombre`, `distrito_id`, `descripcion`, `activa`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `distrito()` | `BelongsTo<Distrito>` | Distrito al que pertenece esta zona |

**Scopes:** `scopeActivas()`

**Filament:** `ZonaResource` (grupo *Organización*)

---

### ColectivoProtegido

**Archivo:** `Modules/Organizacion/app/Models/ColectivoProtegido.php`
**Tabla:** `colectivos_protegidos`
**Descripción:** Define los colectivos cuyos ciudadanos requieren aprobación previa para el acceso desde fuera de la UO responsable. El middleware de autorización consulta esta tabla dinámicamente — añadir un nuevo colectivo es una operación de backoffice.

**Propiedades clave:** `id`, `nombre`, `descripcion`, `requiere_aprobacion_previa` (boolean), `activo`

**Scopes:**

| Método | Descripción |
|---|---|
| `scopeActivos()` | Solo colectivos activos |
| `scopeRequierenAprobacion()` | Solo los que requieren aprobación previa |

**Filament:** `ColectivoProtegidoResource` (grupo *Organización*)

---

### ServicioEmergenciaPreautorizado

**Archivo:** `Modules/Organizacion/app/Models/ServicioEmergenciaPreautorizado.php`
**Tabla:** `servicios_emergencia_preautorizados`
**Descripción:** Registra los servicios (como SAMUR Social) que tienen acceso preautorizado en modo consulta a historias de ciudadanos especialmente protegidos, sin necesidad de aprobación previa. El acceso queda registrado en la auditoría y el supervisor puede revisarlo a posteriori.

**Propiedades clave:** `id`, `nombre`, `descripcion`, `activo`

**Scopes:** `scopeActivos()`

**Filament:** `ServicioEmergenciaResource` (grupo *Organización*)

---

### Configuracion

**Archivo:** `Modules/Organizacion/app/Models/Configuracion.php`
**Tabla:** `organizacion_configuracion`
**Descripción:** Almacena pares clave-valor configurables desde el backoffice. El tipo de dato del valor (`texto`, `numero`, `booleano`, `json`) determina cómo se castea al leerlo.

**Propiedades clave:** `id`, `clave`, `valor`, `descripcion`, `tipo`

**Métodos de dominio:**

| Método | Descripción |
|---|---|
| `valorCasteado()` | Devuelve el valor casteado según el tipo declarado |

**Scopes:** `scopeTipo(string $tipo)` — filtra por tipo de configuración.

**Filament:** `ConfiguracionOrganizacionResource` y `ConfiguracionHorarioLaboralResource` (grupo *Organización*)

---

## 4. Módulo Usuarios y Permisos

**Namespace:** `Modules\Usuarios\Models`
**Directorio:** `vida/Modules/Usuarios/`

### Introducción funcional

El módulo de usuarios articula tres entidades con responsabilidades distintas:

- **`User`** (núcleo) — identidad de autenticación. El "quién ha hecho esto".
- **`Profesional`** — perfil organizativo. El "quién eres en la organización".
- **Roles y UO** — marco de autorización. El "qué puedes hacer y dónde".

El permiso efectivo resulta de la intersección de dos dimensiones independientes. El **rol** define qué operaciones puede realizar (con independencia del contexto). La **UO** delimita el ámbito de datos sobre los que puede ejercerlas. Esta separación es estructural — ver [Principio 3.3](#principio-33).

El sistema define **7 roles iniciales** (`adm_sistema`, `supervision`, `adm_usuarios`, `intervencion`, `tramitacion`, `consulta_profesional`, `consulta_basica`), todos configurables desde el backoffice con sus permisos atómicos.

La asignación de roles está sujeta a supervisión. Los roles de alta criticidad requieren **aprobación previa** (el rol no se activa hasta que el supervisor lo aprueba). El resto genera una **alerta supervisada** que el supervisor debe reconocer explícitamente.

---

### Profesional

**Archivo:** `Modules/Usuarios/app/Models/Profesional.php`
**Tabla:** `profesionales`
**Traits:** `Versionable`, `SoftDeletes`
**Descripción:** Perfil organizativo de una persona: cargo, titulación, tipo de vínculo contractual y contacto profesional. Existe con independencia de si tiene cuenta de acceso al sistema. Es la entidad raíz: un `User` casi siempre tiene un `Profesional` detrás, pero no al revés.

Sujeta a versionado: sus datos cambian a lo largo de la vida laboral y el sistema debe poder conocer el estado en cualquier fecha pasada.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `nombre`, `apellido1`, `apellido2` | string | |
| `sexo` | string | `M` / `F` / `D` |
| `cargo_id` | int FK | Catálogo configurable |
| `categoria_profesional` | string nullable | Nivel administrativo: A1, A2, C1… |
| `titulacion_id` | int FK nullable | |
| `tipo_relacion_id` | int FK | Funcionario, contratado, externo… |
| `organizacion` | string nullable | Para profesionales externos |
| `email_profesional` | string nullable | |
| `fecha_inicio` / `fecha_fin` | date | Vigencia del contrato |
| `activo` | boolean | Para consultas rápidas sin filtrar por fecha |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `cargo()` | `BelongsTo<Cargo>` | Cargo que ocupa este profesional |
| `titulacion()` | `BelongsTo<Titulacion>` | Titulación académica |
| `tipoRelacion()` | `BelongsTo<TipoRelacionProfesional>` | Tipo de vínculo contractual |
| `usuario()` | `HasOne<User>` | Cuenta de acceso vinculada, si existe |

**Accesores:** `getNombreCompletoAttribute()` — nombre + apellido1 [+ apellido2].

**Scopes:** `scopeActivos()` — solo profesionales activos.

**Filament:** `ProfesionalResource` (grupo *Profesionales*)

---

### Cargo

**Archivo:** `Modules/Usuarios/app/Models/Cargo.php`
**Tabla:** `cargos`
**Descripción:** Catálogo configurable de cargos organizativos (Trabajador/a Social, Técnico/a de Acogida, etc.).

**Propiedades clave:** `id`, `nombre`, `descripcion`, `activo`

**Relaciones:** `profesionales()` — `HasMany<Profesional>`

**Scopes:** `scopeActivos()`

**Filament:** `CargoResource` (grupo *Profesionales*)

---

### Titulacion

**Archivo:** `Modules/Usuarios/app/Models/Titulacion.php`
**Tabla:** `titulaciones`
**Descripción:** Catálogo configurable de titulaciones académicas.

**Propiedades clave:** `id`, `nombre`, `activo`

**Relaciones:** `profesionales()` — `HasMany<Profesional>`

**Scopes:** `scopeActivas()`

**Filament:** `TitulacionResource` (grupo *Profesionales*)

---

### TipoRelacionProfesional

**Archivo:** `Modules/Usuarios/app/Models/TipoRelacionProfesional.php`
**Tabla:** `tipos_relacion_profesional`
**Descripción:** Catálogo configurable de tipos de vínculo con la organización (funcionario, interino, contratado laboral, externo, voluntario…). El campo `es_externo` determina si el campo `organizacion` de `Profesional` es relevante.

**Propiedades clave:** `id`, `nombre`, `es_externo` (boolean), `activo`

**Relaciones:** `profesionales()` — `HasMany<Profesional>`

**Scopes:** `scopeActivos()`

**Filament:** `TipoRelacionProfesionalResource` (grupo *Profesionales*)

---

### ConfiguracionRol

**Archivo:** `Modules/Usuarios/app/Models/ConfiguracionRol.php`
**Tabla:** `configuracion_roles`
**Descripción:** Extiende los roles de Spatie con atributos de negocio propios de VIDA, sin modificar las tablas nativas de Spatie. El campo clave es `nivel_supervision`, que determina si la asignación de ese rol requiere aprobación previa o solo alerta supervisada.

**Propiedades clave:** `id`, `rol_id` (FK Spatie, unique), `nivel_supervision` (enum: `aprobacion_previa` / `alerta_supervisada`)

**Relaciones:** `rol()` — `BelongsTo<Role>` (Spatie)

**Filament:** `ConfiguracionRolResource` (grupo *Organización*)

---

### UsuarioRol

**Archivo:** `Modules/Usuarios/app/Models/UsuarioRol.php`
**Tabla:** `usuario_rol`
**Descripción:** **Fuente de verdad** para la asignación de roles. La tabla `model_has_roles` de Spatie es el estado derivado activo, optimizado para la evaluación rápida de `can()` en cada request. Un `Observer` mantiene ambas tablas sincronizadas.

El campo `estado` gestiona el flujo de aprobación previa:
- `pendiente_aprobacion` — asignación solicitada, aún no activa en Spatie.
- `activo` — vigente, sincronizado en `model_has_roles`.
- `inactivo` — revocado, eliminado de `model_has_roles`.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `usuario_id` | int FK | |
| `rol_id` | int FK | Rol de Spatie |
| `fecha_inicio` / `fecha_fin` | date | |
| `asignado_por` | int FK nullable | Usuario que realizó la asignación |
| `estado` | string | `pendiente_aprobacion` / `activo` / `inactivo` |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `usuario()` | `BelongsTo<User>` | Usuario al que corresponde este historial |
| `rol()` | `BelongsTo<Role>` | Rol de Spatie asignado |
| `asignadoPor()` | `BelongsTo<User>` | Usuario que realizó la asignación |

**Scopes:**

| Método | Descripción |
|---|---|
| `scopeVigentes()` | Estado activo con fecha vigente |
| `scopePendientes()` | Estado `pendiente_aprobacion` |

**Filament:** `UsuarioRolResource` (grupo *Organización*)

---

### Recursos Filament del módulo

| Resource | Grupo de navegación | Descripción |
|---|---|---|
| `UsuarioResource` | Organización | CRUD de cuentas de usuario |
| `UsuarioRolResource` | Organización | Supervisión del historial de roles |
| `ConfiguracionRolResource` | Organización | Configuración del nivel de supervisión por rol |
| `ProfesionalResource` | Profesionales | CRUD de profesionales |
| `CargoResource` | Profesionales | Catálogo de cargos |
| `TitulacionResource` | Profesionales | Catálogo de titulaciones |
| `TipoRelacionProfesionalResource` | Profesionales | Catálogo de tipos de vínculo |
| `UnidadOrganizativaResource` | Organización | Gestión del árbol de UOs |

---

## 5. Módulo Prestaciones

**Namespace:** `Modules\Prestaciones\Models`
**Directorio:** `vida/Modules/Prestaciones/`

### Introducción funcional

El módulo Prestaciones mantiene el **catálogo oficial de prestaciones** del sistema municipal. Es la fuente de verdad sobre qué prestaciones existen, qué condiciones tienen y cómo se accede a ellas. Su función en el sistema es exclusivamente de **referencia**: los demás módulos consultan el catálogo pero no lo modifican. El mantenimiento se realiza íntegramente desde Filament.

El catálogo de referencia es la Guía de Prestaciones del Ayuntamiento de Madrid (edición 2024), con 112 prestaciones organizadas en ocho objetivos generales. El diseño permite que cualquier entidad municipal configure su propio catálogo sin dependencia de la estructura madrileña.

Los campos clasificatorios (`objetivo_general`, `categoria_especifica`, `nivel_atencion`, etc.) usan `catalogos_sistema` para ser configurables desde backoffice. Solo `tipo_prestacion` y `nivel_garantia` son enums PHP porque el código actúa de forma diferente según su valor.

---

### Prestacion

**Archivo:** `Modules/Prestaciones/app/Models/Prestacion.php`
**Tabla:** `prestaciones`
**Traits:** `Versionable`, `SoftDeletes`
**Descripción:** Entrada del catálogo oficial de prestaciones. Sujeta a versionado: el sistema debe poder reconstruir cómo era una prestación en el momento en que se incluyó en un plan de intervención.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `codigo` | string | Código jerárquico del catálogo (`010101`…) |
| `nombre` | string | |
| `tipo_prestacion` | enum | `servicio` / `economica` |
| `nivel_garantia` | enum | `garantizada` / `condicionada` |
| `objetivo_general` | string | Clave de `catalogos_sistema` |
| `categoria_especifica` | string | Clave de `catalogos_sistema` |
| `nivel_atencion` | string | Clave de `catalogos_sistema` |
| `competencia` | string | Clave de `catalogos_sistema` |
| `forma_gestion` | string | Clave de `catalogos_sistema` |
| `financiacion` | string | Clave de `catalogos_sistema` |
| `poblacion_destinataria` | array (JSONB) | Array de claves de `catalogos_sistema` |
| `modalidades` | array (JSONB) | `presencial`, `telefonica`, `telematica` |
| `activa` | boolean | |
| Campos descriptivos | text | `finalidad`, `descripcion`, `requisitos`, `procedimiento`, `compatibilidad`, etc. |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `tiposCentro()` | `HasMany<PrestacionTipoCentro>` | Tipos de centro que pueden prestar esta prestación |

**Scopes:**

| Método | Descripción |
|---|---|
| `scopeActivas()` | Solo prestaciones activas |
| `scopeDeServicio()` | Solo tipo `servicio` |
| `scopeEconomicas()` | Solo tipo `economica` |

**Filament:** `PrestacionResource` con `VersionesRelationManager` (sin grupo específico)

---

### PrestacionTipoCentro

**Archivo:** `Modules/Prestaciones/app/Models/PrestacionTipoCentro.php`
**Tabla:** `prestacion_tipo_centro`
**Descripción:** Registra qué tipos de centro pueden prestar cada prestación. El módulo Centros consume esta tabla para filtrar las prestaciones disponibles en cada centro.

**Propiedades clave:** `id`, `prestacion_id` (FK), `tipo_centro` (clave de `catalogos_sistema`)

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `prestacion()` | `BelongsTo<Prestacion>` | Prestación a la que pertenece este tipo de centro |

---

## 6. Módulo Centro

**Namespace:** `Modules\Centro\Models`
**Directorio:** `vida/Modules/Centro/`

### Introducción funcional

El módulo de Centros resuelve dos necesidades complementarias: proporcionar un catálogo operativo de todos los centros y recursos disponibles en el sistema municipal, y gestionar la disponibilidad de plazas, la inscripción en centros y la participación en actividades, ya sea por acceso libre o por prescripción desde un plan de intervención.

Un **centro** es cualquier equipamiento con presencia física que ofrece prestaciones sociales: centros de servicios sociales, centros de acogida, centros de día, pisos tutelados, centros de emergencia. VIDA 360 gestiona lo relevante para el profesional: saber qué hay disponible, poder prescribir y hacer seguimiento. La gestión operativa interna (espacios, control de asistencia, certificados) corresponde a herramientas especializadas de cada centro.

El ciclo de vida de una **prescripción** es: `pendiente` → `en_lista_espera` → `asignada` → `activa` → `finalizada` / `cancelada`. **La asignación nunca es automática**: cuando se libera una plaza, el sistema genera una alerta al TSR activo del ciudadano, que revisa y confirma o cancela la asignación.

---

### Centro

**Archivo:** `Modules/Centro/app/Models/Centro.php`
**Tabla:** `centros`
**Traits:** `Versionable`, `SoftDeletes`
**Descripción:** Entidad raíz del módulo. Tiene lugar físico, está orientado a uno o varios segmentos de población, ofrece prestaciones del catálogo y puede gestionar plazas, actividades o ambas.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `nombre` / `nombre_corto` | string | |
| `tipo_gestion` | string | `municipal_directo` / `municipal_concertado` / `privado_concertado` / `privado_puro` |
| `unidad_organizativa_id` | int FK nullable | UO a la que pertenece |
| `distrito_id` | int FK nullable | Distrito municipal |
| `coordenadas` | string nullable | Para geolocalización |
| `inscripcion_libre` | boolean | `true` = libre elección; `false` = por domicilio |
| `horario` | array nullable | JSON para visualización (gestión completa en Agenda, pendiente) |
| `activo` | boolean | |
| `fecha_alta` / `fecha_baja` | date | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `unidadOrganizativa()` | `BelongsTo<UnidadOrganizativa>` | UO a la que pertenece el centro |
| `distrito()` | `BelongsTo<Distrito>` | Distrito municipal donde se ubica |
| `coleccionesPlazas()` | `HasMany<ColeccionPlazas>` | Colecciones de plazas del centro |
| `actividades()` | `HasMany<Actividad>` | Actividades programadas |
| `directores()` | `HasMany<DirectorCentro>` | Historial de directores |
| `contactos()` | `HasMany<ContactoCentro>` | Personas de contacto adicionales |
| `inscripciones()` | `HasMany<InscripcionCentro>` | Inscripciones de ciudadanos |
| `redes()` | `BelongsToMany<Red>` via `red_centro` | Redes a las que pertenece |
| `segmentosPoblacion()` | `BelongsToMany<SegmentoPoblacion>` via `centro_segmento_poblacion` | Segmentos atendidos |
| `prestaciones()` | `BelongsToMany<Prestacion>` via `centro_prestacion` | Prestaciones ofrecidas |

**Scopes:** `scopeActivos()` — centros activos y sin `fecha_baja`.

**Filament:** `CentroResource` con `ColeccionesPlazasRelationManager` (grupo *Centros*)

---

### Red

**Archivo:** `Modules/Centro/app/Models/Red.php`
**Tabla:** `redes`
**Descripción:** Agrupación de centros que comparten un pool de plazas común. Permite consultar disponibilidad agregada a nivel de red sin revisar cada centro individualmente. Un centro puede pertenecer a varias redes.

**Propiedades clave:** `id`, `nombre`, `nombre_corto`, `descripcion`, `activa`, `fecha_alta`, `fecha_baja`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centros()` | `BelongsToMany<Centro>` via `red_centro` | Centros que componen la red |

**Scopes:** `scopeActivas()`

**Filament:** `RedResource` (grupo *Centros*)

---

### ColeccionPlazas

**Archivo:** `Modules/Centro/app/Models/ColeccionPlazas.php`
**Tabla:** `colecciones_plazas`
**Descripción:** Grupo homogéneo de plazas dentro de un centro. Define el tipo de plaza y el modo de acceso. Un mismo centro puede tener varias colecciones con tipos y modos distintos.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `centro_id` | int FK | |
| `nombre` | string | Ej: "Plazas de acogida", "Centro de día" |
| `tipo_plaza` | string | `pernocta` / `dia` |
| `modo_acceso` | string | `libre` / `prescripcion_directa` / `prescripcion_lista_espera` |
| `capacidad` | int | Número total de plazas |
| `activa` | boolean | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | Centro al que pertenece |
| `espacios()` | `HasMany<Espacio>` | Espacios físicos que componen la colección |

**Scopes:** `scopeActivas()`

---

### Espacio

**Archivo:** `Modules/Centro/app/Models/Espacio.php`
**Tabla:** `espacios`
**Descripción:** Unidad física dentro de una colección de plazas (dormitorio, habitación, módulo). Contiene una o varias plazas. Una cama doble se modela como un espacio con dos plazas, no como una plaza con capacidad dos.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `coleccion_plazas_id` | int FK | |
| `nombre` | string | Ej: "Dormitorio 3", "Habitación 12" |
| `tipo_espacio_id` | int FK | Catálogo configurable (TipoEspacio) |
| `capacidad` | int | Número de plazas |
| `planta` | string nullable | |
| `accesible` | boolean | Adaptado para movilidad reducida |
| `genero` | string nullable | `mixto` / `mujeres` / `hombres` |
| `activo` | boolean | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `coleccionPlazas()` | `BelongsTo<ColeccionPlazas>` | Colección a la que pertenece |
| `tipoEspacio()` | `BelongsTo<TipoEspacio>` | Tipo del espacio (catálogo) |
| `plazas()` | `HasMany<Plaza>` | Plazas individuales dentro del espacio |

**Scopes:** `scopeActivos()`, `scopeAccesibles()`

---

### Plaza

**Archivo:** `Modules/Centro/app/Models/Plaza.php`
**Tabla:** `plazas`
**Descripción:** Unidad mínima asignable a una persona. El estado se mantiene desnormalizado para consultas rápidas de disponibilidad; la ocupación efectiva se rastrea a través de la `Prescripcion` activa que apunta a la plaza.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `espacio_id` | int FK | |
| `nombre` | string | Ej: "Cama 1", "Cama 2" |
| `estado` | string | `libre` / `ocupada` / `reservada` / `mantenimiento` |
| `activa` | boolean | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `espacio()` | `BelongsTo<Espacio>` | Espacio físico en el que se encuentra |
| `prescripcion()` | `HasOne<Prescripcion>` | Prescripción activa en esta plaza |

**Scopes:** `scopeLibres()`, `scopeOcupadas()`

---

### Actividad

**Archivo:** `Modules/Centro/app/Models/Actividad.php`
**Tabla:** `actividades`
**Descripción:** Taller, charla, seminario, grupo de apoyo, curso u otro tipo de actividad organizada por el centro. Independiente de las plazas. Las actividades no tienen periodicidad modelada — se materializan en **sesiones** explícitas. La inscripción apunta siempre a una sesión concreta.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `centro_id` | int FK | |
| `tipo_actividad_id` | int FK | Catálogo configurable (TipoActividad) |
| `modo_acceso` | string | `libre` / `prescripcion` / `mixta` |
| `aforo_total` | int nullable | |
| `aforo_prescripcion` | int nullable | Solo relevante si `modo_acceso = mixta` |
| `requiere_inscripcion_centro` | boolean | Si `true`, el ciudadano debe tener `InscripcionCentro` activa |
| `activa` | boolean | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | Centro que organiza la actividad |
| `tipoActividad()` | `BelongsTo<TipoActividad>` | Tipo de actividad (catálogo) |
| `sesiones()` | `HasMany<SesionActividad>` | Sesiones convocadas |

**Scopes:** `scopeActivas()`, `scopeDeAccesoLibre()`, `scopeDeAccesoPorPrescripcion()`

---

### SesionActividad

**Archivo:** `Modules/Centro/app/Models/SesionActividad.php`
**Tabla:** `sesiones_actividad`
**Descripción:** Materialización concreta de una actividad en el tiempo. El control de aforo opera a nivel de sesión. Una sesión puede sobreescribir el aforo definido en la actividad.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `actividad_id` | int FK | |
| `fecha` | date | |
| `hora_inicio` / `hora_fin` | time | |
| `aforo_total` / `aforo_prescripcion` | int nullable | Sobreescribe el de la actividad si se especifica |
| `estado` | string | `programada` / `celebrada` / `cancelada` |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `actividad()` | `BelongsTo<Actividad>` | Actividad a la que pertenece esta sesión |

**Scopes:** `scopeProgramadas()`, `scopeCelebradas()`

---

### InscripcionCentro

**Archivo:** `Modules/Centro/app/Models/InscripcionCentro.php`
**Tabla:** `inscripciones_centro`
**Descripción:** Registro de un ciudadano en un centro, independiente de cualquier plaza o actividad. Necesaria en centros configurados con `requiere_inscripcion_centro`. La inscripción es indefinida; la baja es siempre explícita.

**Propiedades clave:** `id`, `ciudadano_id`, `centro_id`, `fecha_alta`, `fecha_baja`, `motivo_baja`, `activa`, `notas`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | Centro en el que está inscrito el ciudadano |

**Scopes:** `scopeActivas()`

---

### DirectorCentro

**Archivo:** `Modules/Centro/app/Models/DirectorCentro.php`
**Tabla:** `directores_centro`
**Descripción:** Historial de responsables del centro. El responsable puede ser un `Profesional` del sistema o una persona externa (con datos de contacto propios). El registro activo es el que tiene `fecha_fin = null`.

**Propiedades clave:** `id`, `centro_id`, `profesional_id` (nullable), `nombre` / `telefono` / `email` (para externos), `fecha_inicio`, `fecha_fin`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | Centro que dirige |
| `profesional()` | `BelongsTo<Profesional>` | Profesional del sistema, si aplica |

**Scopes:** `scopeActivos()` — fecha_fin null

---

### ContactoCentro

**Archivo:** `Modules/Centro/app/Models/ContactoCentro.php`
**Tabla:** `contactos_centro`
**Descripción:** Directorio de personas de contacto operativo del centro sin cuenta en VIDA 360. Puede haber varios activos simultáneamente.

**Propiedades clave:** `id`, `centro_id`, `nombre`, `rol` (ej: "Coordinador"), `telefono`, `email`, `activo`, `notas`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | Centro al que pertenece |

---

### Prescripcion

**Archivo:** `Modules/Centro/app/Models/Prescripcion.php`
**Tabla:** `prescripciones`
**Traits:** `Versionable`, `SoftDeletes`
**Descripción:** Vincula a un ciudadano, desde un plan de intervención, con una colección de plazas o una sesión de actividad. El campo `tipo_destino` + `destino_id` implementa una relación polimórfica manual (no usa Eloquent `morphTo`).

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `profesional_id` | int FK | Profesional que emite la prescripción |
| `ciudadano_id` | int FK | |
| `plan_intervencion_id` | int FK nullable | |
| `tipo_destino` | string | `coleccion_plazas` / `sesion_actividad` |
| `destino_id` | int | FK polimórfica según `tipo_destino` |
| `plaza_id` | int FK nullable | Se asigna cuando hay plaza concreta disponible |
| `estado` | string | `pendiente` / `en_lista_espera` / `asignada` / `activa` / `finalizada` / `cancelada` |
| `fecha_prescripcion` / `fecha_inicio` / `fecha_fin` | date | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `profesional()` | `BelongsTo<Profesional>` | Profesional que emite la prescripción |
| `ciudadano()` | `BelongsTo<Ciudadano>` | Beneficiario |
| `plaza()` | `BelongsTo<Plaza>` | Plaza concreta asignada (nullable) |
| `listaEspera()` | `HasOne<ListaEspera>` | Lista de espera generada por esta prescripción |

**Métodos de dominio:**

| Método | Descripción |
|---|---|
| `destino()` | Resuelve el destino polimórfico: devuelve `ColeccionPlazas` o `SesionActividad` |

**Scopes:** `scopeActivas()`, `scopePendientes()`

---

### ListaEspera

**Archivo:** `Modules/Centro/app/Models/ListaEspera.php`
**Tabla:** `listas_espera`
**Descripción:** Posición en lista de espera asociada a una prescripción sin disponibilidad inmediata. La lista puede operar a nivel de colección de plazas o a nivel de red. Cuando se libera una plaza, el sistema notifica al TSR **activo** del ciudadano en ese momento (puede no coincidir con el profesional que realizó la prescripción original).

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `prescripcion_id` | int FK | |
| `coleccion_plazas_id` | int FK nullable | Si la lista opera a nivel de colección |
| `red_id` | int FK nullable | Si la lista opera a nivel de red |
| `posicion` | int | Posición en la lista (se recalcula con cada movimiento) |
| `fecha_entrada` | datetime | |
| `profesional_alerta_id` | int FK | TSR activo en el momento de generar la alerta |
| `estado` | string | `activa` / `asignada` / `cancelada` |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `prescripcion()` | `BelongsTo<Prescripcion>` | Prescripción que origina la espera |
| `coleccionPlazas()` | `BelongsTo<ColeccionPlazas>` | Colección de plazas en espera |
| `red()` | `BelongsTo<Red>` | Red en espera |
| `profesionalAlerta()` | `BelongsTo<Profesional>` | TSR activo al generar la alerta |

**Scopes:** `scopeActivas()`

---

### Catálogos del módulo Centro

Estas entidades son configurables desde Filament sin necesidad de desarrollo:

#### SegmentoPoblacion

**Tabla:** `segmentos_poblacion`
**Descripción:** Colectivos atendidos por los centros (personas sin hogar, mayores, VVG, menores, personas con discapacidad, etc.).

**Propiedades:** `id`, `nombre`, `descripcion`, `activo`

**Filament:** `SegmentoPoblacionResource` (grupo *Catálogos de centros*)

---

#### TipoEspacio

**Tabla:** `tipos_espacio`
**Descripción:** Tipos de espacio físico (dormitorio individual, compartido, habitación doble, módulo familiar, etc.).

**Propiedades:** `id`, `nombre`, `descripcion`, `activo`

**Filament:** `TipoEspacioResource` (grupo *Catálogos de centros*)

---

#### TipoActividad

**Tabla:** `tipos_actividad`
**Descripción:** Tipos de actividad organizables (taller, charla, seminario, grupo de apoyo, curso, etc.).

**Propiedades:** `id`, `nombre`, `descripcion`, `activo`

**Filament:** `TipoActividadResource` (grupo *Catálogos de centros*)

---

## 7. Módulo Mensajes

**Namespace:** `Modules\Mensajes\Models`
**Directorio:** `vida/Modules/Mensajes/`

### Introducción funcional

El módulo cubre dos necesidades diferenciadas que comparten infraestructura pero tienen ciclos de vida y reglas de negocio distintos:

**Sistema de alertas:** canal unidireccional generado por la propia aplicación. Comunica a los profesionales eventos que requieren su atención. Dos niveles de gravedad: `aviso` (ignorable sin acción formal) y `alerta` (requiere reconocimiento explícito en 4 horas laborales). Si vence sin reconocimiento, **escala un único nivel** al supervisor de la UO. No se crea alerta nueva — la alerta original cambia a estado `escalada`.

**Mensajería interna:** canal bidireccional entre profesionales, diseñado para evitar que información sensible sobre ciudadanos circule por canales externos (email, Teams, WhatsApp). La mensajería es estrictamente **uno a uno**. Las conversaciones se organizan en hilos. El **TSR responsable** puede decidir incorporar un mensaje a la Historia Social del ciudadano — acción explícita, con posibilidad de editar el contenido antes de registrarlo.

---

### Alerta

**Archivo:** `Modules/Mensajes/app/Models/Alerta.php`
**Tabla:** `alertas`
**Descripción:** Notificación generada por la aplicación. El origen (el objeto que generó la alerta) se referencia mediante polimorfismo (`origen_type` / `origen_id`), permitiendo que la interfaz ofrezca un enlace directo al contexto.

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `tipo` | enum | `aviso` / `alerta` |
| `origen_type` / `origen_id` | string / int | Origen polimórfico (Centros, Intervención, Sistema…) |
| `titulo` / `cuerpo` | string / text | |
| `destinatario_type` | enum | `usuario` / `rol_uo` |
| `destinatario_usuario_id` | int FK nullable | Ref. a `users` si tipo = `usuario` |
| `destinatario_rol` | string nullable | Rol objetivo si tipo = `rol_uo` |
| `destinatario_uo_id` | int FK nullable | UO objetivo si tipo = `rol_uo` |
| `estado` | enum | `pendiente` / `reconocida` / `escalada` / `vencida` |
| `expira_en` | timestamp nullable | Calculado en horas laborales (4h) |
| `escalada_en` | timestamp nullable | Momento de la escalada |
| `escalada_a_usuario_id` | int FK nullable | Supervisor que hereda la alerta |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `destinatarioUsuario()` | `BelongsTo<User>` | Usuario destinatario directo |
| `destinatarioUo()` | `BelongsTo<UnidadOrganizativa>` | UO objetivo (para rol_uo) |
| `escaladaA()` | `BelongsTo<User>` | Supervisor que hereda la alerta |
| `reconocimientos()` | `HasMany<AlertaReconocimiento>` | Reconocimientos individuales |

**Scopes:** `scopePendientes()`, `scopeEscaladas()`, `scopeVencidas()`, `scopeParaUsuario(int $userId)`

---

### AlertaReconocimiento

**Archivo:** `Modules/Mensajes/app/Models/AlertaReconocimiento.php`
**Tabla:** `alerta_reconocimientos`
**Descripción:** Registra el reconocimiento individual de cada destinatario real, incluyendo la herencia por escalada y los descartes de avisos. Permite auditar quién reconoció cada alerta y cuándo.

**Propiedades clave:** `id`, `alerta_id`, `usuario_id`, `tipo` (enum: `reconocida` / `escalada` / `descartada`), `reconocida_en`, `ip_address`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `alerta()` | `BelongsTo<Alerta>` | Alerta que se reconoce |
| `usuario()` | `BelongsTo<User>` | Usuario que reconoce |

**Filament:** `LogAlertasResource` — vista de solo lectura para supervisión (grupo *Mensajería*)

---

### MensajeHilo

**Archivo:** `Modules/Mensajes/app/Models/MensajeHilo.php`
**Tabla:** `mensajes_hilos`
**Descripción:** Conversación entre dos profesionales. Tiene exactamente dos participantes. Las respuestas se acumulan en el mismo hilo.

**Propiedades clave:** `id`, `asunto`, `creado_por_id`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `creadoPor()` | `BelongsTo<User>` | Usuario que inició el hilo |
| `participantes()` | `HasMany<MensajeParticipante>` | Los dos participantes del hilo |
| `mensajes()` | `HasMany<Mensaje>` | Mensajes del hilo |

**Scopes:** `scopeDeUsuario(int $userId)` — hilos en los que participa un usuario

---

### MensajeParticipante

**Archivo:** `Modules/Mensajes/app/Models/MensajeParticipante.php`
**Tabla:** `mensajes_participantes`
**Descripción:** Gestiona el estado de lectura y archivado de forma independiente para cada participante del hilo.

**Propiedades clave:** `id`, `hilo_id`, `usuario_id`, `rol` (enum: `remitente_inicial` / `participante`), `fecha_ultima_lectura`, `archivado_en`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `hilo()` | `BelongsTo<MensajeHilo>` | Hilo de mensajes al que pertenece esta participación |
| `usuario()` | `BelongsTo<User>` | Usuario participante |

**Métodos de dominio:**

| Método | Descripción |
|---|---|
| `mensajesNoLeidos(): int` | Número de mensajes del hilo no leídos por este participante |

---

### Mensaje

**Archivo:** `Modules/Mensajes/app/Models/Mensaje.php`
**Tabla:** `mensajes`
**Implementa:** `HasMedia` (spatie/laravel-medialibrary)
**Descripción:** Mensaje individual dentro de un hilo. Los adjuntos se gestionan mediante la colección `adjuntos_mensaje` en disco local.

**Propiedades clave:** `id`, `hilo_id`, `remitente_id`, `cuerpo`, `created_at`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `hilo()` | `BelongsTo<MensajeHilo>` | Hilo de conversación al que pertenece |
| `remitente()` | `BelongsTo<User>` | Usuario que envió el mensaje |
| `referenciasCiudadano()` | `HasMany<MensajeReferenciaCiudadano>` | Ciudadanos mencionados en el mensaje |
| `registrosHistoria()` | `HasMany<MensajeRegistroHistoria>` | Incorporaciones del mensaje a Historias Sociales |

**Métodos de medialibrary:**

| Método | Descripción |
|---|---|
| `registerMediaCollections()` | Registra la colección `adjuntos_mensaje` en disco local |
| `registerMediaConversions()` | Sin conversiones (documentos, no imágenes) |

---

### MensajeReferenciaCiudadano

**Archivo:** `Modules/Mensajes/app/Models/MensajeReferenciaCiudadano.php`
**Tabla:** `mensajes_referencias_ciudadano`
**Descripción:** Enlace informativo entre un mensaje y un ciudadano. Permite navegar desde el mensaje al expediente del ciudadano. **No implica** registro en la Historia Social — para eso existe `MensajeRegistroHistoria`.

**Propiedades clave:** `id`, `mensaje_id`, `ciudadano_id`

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `mensaje()` | `BelongsTo<Mensaje>` | Mensaje que origina esta referencia |
| `ciudadano()` | `BelongsTo<Ciudadano>` | Ciudadano referenciado |

---

### MensajeRegistroHistoria

**Archivo:** `Modules/Mensajes/app/Models/MensajeRegistroHistoria.php`
**Tabla:** `mensajes_registro_historia`
**Descripción:** Materializa la decisión explícita del TSR de incorporar un mensaje a la Historia Social de un ciudadano. Solo el TSR responsable del expediente puede crear estos registros. El contenido registrado puede diferir del mensaje original (el TSR puede editar antes de registrar).

**Propiedades clave:**

| Campo | Tipo | Descripción |
|---|---|---|
| `mensaje_id` | int FK | Mensaje original |
| `ciudadano_id` | int FK | Ciudadano cuya historia se actualiza |
| `registrado_por_id` | int FK | Debe ser el TSR responsable |
| `cuerpo_registrado` | text | Copia editada del mensaje |
| `visibilidad` | enum | `privada` / `profesionales` (nunca `ciudadano`) |
| `registrado_en` | timestamp | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `mensaje()` | `BelongsTo<Mensaje>` | Mensaje original cuyo contenido se incorporó |
| `ciudadano()` | `BelongsTo<Ciudadano>` | Ciudadano cuyo expediente recibió el registro |
| `registradoPor()` | `BelongsTo<User>` | Profesional que incorporó el mensaje al expediente |

---

## 8. Módulo Ciudadanía — Planificado

> **Estado:** especificado y diseñado. Pendiente de implementación. El código actual incluye un stub `app/Models/Ciudadano.php`.

### Introducción funcional

El ciudadano es la entidad central de todo el sistema — la unidad de continuidad alrededor de la cual se organizan historias, planes, prestaciones y actos profesionales. Principios rectores del módulo:

- **Identidad única:** cada persona tiene un único registro en VIDA, independientemente de cuántos servicios utilice.
- **El pasado es inmutable:** ningún dato histórico se sobrescribe. Los cambios generan nuevas versiones.
- **Cifrado en aplicación:** todos los datos del ciudadano se cifran antes de persistirse.
- **Separación en tres capas:** (1) Identificación y contacto, (2) Ficha social, (3) Historia social.

### Entidades previstas

| Entidad | Tabla | Descripción |
|---|---|---|
| `Ciudadano` | `ciudadanos` | Datos de identificación y contacto (cifrados). Incluye `alias` para PSH |
| `CiudadanoIdentificador` | `ciudadano_identificadores` | Historial de documentos de identidad (DNI, NIE, pasaporte, NI-HSU-CM) |
| `CiudadanoRelacion` | `ciudadano_relaciones` | Relaciones entre ciudadanos (familiar, representante, cuidador…) |
| `UnidadConvivencia` | `unidades_convivencia` | Unidad de convivencia con sus miembros y fechas de vigencia |
| `CiudadanoFicha` | `ciudadano_fichas` | Fichas sociales versionadas (situación económica, familiar, vivienda, salud…) |

### Servicios previstos

| Servicio | Descripción |
|---|---|
| `NormalizadorCiudadano` | Normaliza datos identificativos antes de cualquier búsqueda (nombres, documentos, teléfonos) |
| `MotorMatching` | Detección de duplicados con score de similitud (Jaro-Winkler / Levenshtein) |
| `FusionCiudadanos` | Fusión de registros duplicados con trazabilidad completa y posibilidad de reversión |

### Casos especiales

- **Personas Sin Hogar (PSH):** admiten registro con `nivel_identificacion = no_identificado`. El `alias` es el identificador operativo. Las coordenadas del lugar habitual de pernocta sustituyen al domicilio.
- **Víctimas de Violencia de Género (VVG):** la consulta al padrón **no se realiza** (no se deja traza en los logs del padrón). El domicilio registrado puede diferir del padrón.

---

## 9. Módulo Intervención — Planificado

> **Estado:** especificado y diseñado. Pendiente de implementación. El código actual incluye stubs `app/Models/HistoriaSocial.php` y `app/Models/Apunte.php`.

### Introducción funcional

El módulo de Intervención es el núcleo funcional de VIDA 360. Soporta el ciclo completo de atención social: desde el primer contacto en el SIA hasta el cierre del Plan de Intervención.

**Flujo general:**
1. **Acogida** (SIA o TSR): clasificación de la demanda y gestión de cita.
2. **Entrevista con el TSR:** recogida de información y valoración de la situación.
3. **Valoración estructurada:** mediante fichas configurables por tipo de valoración.
4. **Plan de Intervención (PISO):** objetivos, prestaciones comprometidas y compromisos del ciudadano.
5. **Seguimiento:** revisiones periódicas mediante entrevistas y apuntes.
6. **Cierre:** por objetivos cumplidos, abandono o cambio de circunstancias.

### Entidades previstas

| Entidad | Descripción |
|---|---|
| `HistoriaSocial` | Instrumento central: recoge el proceso de intervención con el ciudadano |
| `RegistroContactoSIA` | Interacción en el SIA, con nivel de urgencia y clasificación de la demanda |
| `Entrevista` | Contenedor de trabajo del profesional durante el encuentro con el ciudadano |
| `Valoracion` | Diagnóstico estructurado mediante fichas configurables |
| `PlanDeIntervencion` | Acuerdo formal con objetivos, prestaciones y compromisos; dos tipos: general ASP y especializado |
| `Apunte` | Mecanismo de asociación de elementos heterogéneos al plan (polimórfico) |

### Modelo de valoración configurable

Las valoraciones se definen mediante tres niveles de configuración en backoffice:
- `TipoFicha` — estructura de campos y reglas de visibilidad condicional (schema JSON)
- `TipoValoracion` — composición de fichas por contexto (ASP, especializada, etc.)
- `Ficha` — datos reales de valoración

### Visibilidad de apuntes

Tres niveles: `privada` (solo el autor), `profesionales` (cualquier profesional con acceso a la historia) y `ciudadano` (visible desde la carpeta ciudadana cuando esté activa).

---

## 10. Módulo Integraciones — Planificado

> **Estado:** arquitectura diseñada. Pendiente de implementación. Todos los adaptadores son mocks activos.

### Introducción funcional

Toda integración con sistemas externos se implementa mediante el **patrón adaptador** ([Principio 3.6](#principio-36)). Ningún módulo funcional de VIDA conoce la implementación concreta de una integración — solo conoce la interfaz. Cambiar de mock a real es una operación de configuración y despliegue, no de desarrollo.

### Integraciones salientes previstas

| Integración | Interfaz | Estado |
|---|---|---|
| Padrón municipal | `FuenteIdentidadInterface` | Mock activo |
| Ciudadano360 | `FuenteIdentidadInterface` | Mock activo |
| Gestor de expedientes | `GestorExpedientesInterface` | Mock activo |
| Carpeta ciudadana | `CarpetaCiudadanaInterface` | Mock activo |
| Notificaciones multicanal | `NotificacionesInterface` | Mock activo |
| Directorio corporativo (LDAP/AD) | `DirectorioCorporativoInterface` | Mock activo |
| Geocodificación | `GeocodificacionInterface` | Mock activo |
| Proveedores externos | `ProveedorExternoInterface` | Mock activo |
| HSU-CM (Comunidad de Madrid) | Por definir | Pendiente de diseño |

### API de VIDA para sistemas externos

VIDA es API First. La autorización para sistemas externos opera en dos capas:
1. **Sistema cliente** — OAuth2 client credentials o API keys con scopes.
2. **Usuario actuante** — token que identifica al profesional que realiza la acción. Sin esta capa, un sistema externo podría actuar sin trazabilidad real.

Sistemas externos con acceso entrante previsto: gestor de expedientes, HSU-CM, VIOMAD (Policía Municipal, con restricciones muy estrictas y análisis legal previo obligatorio).

---

## Anexo A: Principios Técnicos de VIDA 360

> Extraído de `docs/principios-vida360.md`. Marco conceptual que explica *por qué* el sistema está diseñado como está. Debe leerse antes de tomar decisiones de arquitectura o implementación.

---

### Principio 1: Contexto organizativo

El modelo organizativo de los servicios sociales municipales es análogo al sanitario. La ASP es la puerta de entrada universal. El TSR es el eje de la visión 360. Existen puertas alternativas para VG y PSH con circuitos diferenciados pero Historia Social única.

### Principio 2: Marco competencial

VIDA distingue tres situaciones: (a) informar sobre prestaciones ajenas, (b) colaborar en tramitaciones compartidas (API o ficheros Excel), (c) gestionar íntegramente una prestación municipal. Ambos modos de intercambio —API e importación de ficheros— son realidades permanentes, no soluciones provisionales.

---

### Principio 3.1

**Sin valores de negocio hardcodeados.** Los catálogos, estructuras de valoración, plantillas de planes y clasificaciones son configurables desde backoffice. No aplica a valores que el código necesita conocer para tomar decisiones — esos van como enums (ver 3.10).

### Principio 3.2

**Diferimiento explícito sobre ambigüedad.** Las decisiones no maduras se documentan explícitamente como diferidas con su justificación, en lugar de resolverlas prematuramente. Las decisiones diferidas son ciudadanas de primera clase en la documentación.

### Principio 3.3

**Separación de dimensiones en permisos.** El rol (¿qué puede hacer?) y la UO (¿dónde puede hacerlo?) son dimensiones independientes que se evalúan secuencialmente. Un mismo profesional puede tener roles distintos en distintas UO. Esta separación no debe colapsarse.

### Principio 3.4

**Evitar enums para valores inestables.** Los enums se reservan para valores con alta estabilidad estructural y pocos valores semánticamente claros. Para clasificaciones con más de dos o tres valores o posibilidad de evolución, se prefieren catálogos en tabla.

### Principio 3.5

**Patrones transversales sobre soluciones por entidad.** Los problemas que se repiten (versionado, permisos, auditoría) se resuelven con un patrón único aplicado de forma consistente. El versionado polimórfico mediante snapshots JSON es el ejemplo canónico.

### Principio 3.6

**Adaptador como patrón por defecto para integraciones.** Toda integración con sistemas externos se implementa mediante adaptador, con un mock activo por defecto. Aísla las dependencias externas y permite desarrollo sin conexiones reales.

### Principio 3.7

**Modelo de planes de intervención.** Una Historia Social puede tener varios Planes activos simultáneamente. Las derivaciones a especializada son prestaciones. El plan de ASP contiene de un vistazo todo lo activo con una persona.

### Principio 3.8

**Interoperabilidad pragmática.** El sistema soporta dos modos de intercambio permanentes: API y importación/exportación de ficheros estructurados. Ambos son ciudadanos de primera clase, no parches.

### Principio 3.9

**La IA asiste, nunca decide.** Ningún componente de IA puede tomar decisiones sobre personas. La IA puede analizar, clasificar, sugerir; pero toda acción con consecuencias requiere validación explícita de un profesional. El sistema debe hacer visible cuándo una recomendación proviene de IA.

### Principio 3.10

**Enums para lógica, catálogos configurables para clasificación.** Usar enum PHP cuando el código toma decisiones basándose en el valor. Usar `catalogos_sistema` cuando el valor es puramente descriptivo o clasificatorio. **Restricción crítica:** los valores de `catalogos_sistema` nunca pueden referenciarse en lógica de negocio.

### Principio 3.11

**Colectivos protegidos como configuración.** Los colectivos con acceso restringido a su expediente se gestionan mediante tabla configurable, no código hardcodeado. Añadir un nuevo colectivo protegido es una operación de configuración.

### Principio 3.12

**Filament para configuración, Livewire para operación.** Filament gestiona catálogos, plantillas, parámetros, usuarios y permisos. Livewire gestiona el trabajo diario de los profesionales. Esta separación es estructural y no debe mezclarse.

### Principio 4.1 — Colectivos con necesidades específicas

**PSH:** no tienen domicilio fijo. El sistema permite registro sin domicilio y asignación por criterios alternativos (coordenadas del lugar de pernocta, zona de intervención). Es una excepción estructural, no un subtipo de ciudadano.

**VVG:** circuito de acceso independiente. La consulta al padrón no se lanza. El domicilio registrado en VIDA puede diferir del padrón. La Historia Social es única; el circuito diferenciado afecta al flujo de acceso, no a la estructura del expediente.

---

## Anexo B: Glosario

> Extraído y enlazado desde `docs/glosario.md`. Cada término se vincula a la sección del documento donde se implementa o se describe su diseño. Términos sin implementación aún se marcan como *planificado*.

---

### Ciudadanía

| Término | Sección del documento |
|---|---|
| **Ciudadano/a** | [Módulo Ciudadanía](#8-módulo-ciudadanía--planificado) — *planificado*; stub en [Núcleo](#stubs-del-núcleo) |
| **Representante** | [Módulo Ciudadanía](#8-módulo-ciudadanía--planificado) — *planificado* (`CiudadanoRelacion`) |
| **Unidad de Convivencia** | [Módulo Ciudadanía](#8-módulo-ciudadanía--planificado) — *planificado* (`UnidadConvivencia`) |

---

### Profesionales y actos profesionales

| Término | Sección del documento |
|---|---|
| **Profesional** | [Profesional](#profesional) — Módulo Usuarios y Permisos |
| **Profesional de Referencia (TSR)** | [Módulo Usuarios y Permisos](#4-módulo-usuarios-y-permisos) — roles `intervencion` |
| **Historia Social (HS / HSU)** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado*; stub en [Núcleo](#stubs-del-núcleo) |
| **Ficha Social** | [Módulo Ciudadanía](#8-módulo-ciudadanía--planificado) — *planificado* (`CiudadanoFicha`) |
| **Apunte (acto profesional)** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado*; stub en [Núcleo](#stubs-del-núcleo) |
| **Valoración** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |
| **Seguimiento** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |
| **Anotación** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |
| **Entrevista** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |
| **Gestión / Coordinación** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |
| **Derivación** | [Módulo Intervención](#9-módulo-intervención--planificado) — tipo de prestación del catálogo |
| **Informe Social** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |
| **Plan Individualizado de Intervención (DIS / PISO)** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |
| **Mesa** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* (pendiente de diseño) |
| **Agenda, Cita** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* (módulo Agenda pendiente) |
| **Indicador y Baremo** | [Módulo Intervención](#9-módulo-intervención--planificado) — *planificado* |

---

### Recursos

| Término | Sección del documento |
|---|---|
| **Centro** | [Centro](#centro) — Módulo Centro |
| **Servicio** | [Módulo Centro](#6-módulo-centro) — nota: Servicio ≠ Centro (sin infraestructura propia); diseño pendiente |
| **Prestación** | [Prestacion](#prestacion) — Módulo Prestaciones |
| **Plaza** | [Plaza](#plaza) — Módulo Centro |
| **Lista de Espera** | [ListaEspera](#listaespera) — Módulo Centro |
| **Solicitud** | [Módulo Prestaciones](#5-módulo-prestaciones) — configurable por tipo de prestación; diseño pendiente |
| **Taller y Actividad** | [Actividad](#actividad) y [SesionActividad](#sesionactividad) — Módulo Centro |

---

### Unidades gestoras, orgánicas y organizativas

| Término | Sección del documento |
|---|---|
| **Distrito** | [Distrito](#distrito) — Módulo Organización |
| **Zona** | [Zona](#zona) — Módulo Organización |
| **Área de Gobierno** | [UnidadOrganizativa](#unidadorganizativa) — Núcleo (tipo de nodo en el árbol de UOs) |

---

### Semántica del proyecto

| Término | Sección del documento |
|---|---|
| **Módulo (funcional)** | [Introducción](#1-introducción) — paquete de funcionalidad del negocio; puede corresponder a uno o varios Modules de Laravel |
| **Rol y Permiso** | [Módulo Usuarios y Permisos](#4-módulo-usuarios-y-permisos) — `UsuarioRol`, `ConfiguracionRol` |
| **Traza** | [Version](#version) — tabla `versiones`; también tabla `audits` via trait `Auditable` |
| **HSU (Historia Social Única)** | [Módulo Integraciones](#10-módulo-integraciones--planificado) — campo de interoperabilidad con CM; `CiudadanoIdentificador.tipo = ni_hsu_cm` |
| **Interoperabilidad** | [Módulo Integraciones](#10-módulo-integraciones--planificado) — principio adaptador; API saliente y entrante |

---

*Documentación generada en marzo 2026. Fuentes: código fuente del repositorio y documentos de diseño en `docs/`.*
