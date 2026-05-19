# Módulo: Usuarios y Permisos — VIDA 360

> Este documento describe el modelo conceptual y las decisiones de implementación técnica del sistema de usuarios, roles, permisos, unidades organizativas y perfiles profesionales de VIDA 360. Debe leerse junto a `docs/principios.md` (especialmente los principios 3.4, 3.5, 3.6, 4.2 y 4.15) y `docs/glosario.md` (entradas Rol, Permiso, Profesional, Unidad Organizativa).
>
> **Alcance:** este módulo gestiona los usuarios del sistema (profesionales y personal administrativo) y sus perfiles profesionales. Los ciudadanos tienen un tratamiento diferente y acceden a través de interfaces propias (carpeta ciudadana, canales de comunicación). Ver principio 3.11.

---

## 1. Modelo conceptual

### 1.1 Tres entidades, tres responsabilidades

El módulo de usuarios articula tres entidades con responsabilidades distintas y complementarias:

**Usuario** — identidad de autenticación. La cuenta que tiene credenciales, pertenece a una persona concreta y deja rastro de auditoría en cada operación. Es el "quién ha hecho esto". Todo acceso al sistema pasa por un Usuario.

**Profesional** — perfil organizativo. Los datos que describen a una persona en su dimensión laboral: cargo, titulación, tipo de vínculo con la organización, contacto profesional. Es el "quién es esta persona en la organización". Existe con independencia de si esa persona tiene acceso al sistema.

**Roles y Unidades Organizativas** — marco de autorización. Determinan qué puede hacer un Usuario y sobre qué ámbito de datos puede hacerlo.

La relación entre Usuario y Profesional es **opcional en ambos sentidos**:

- Un Usuario puede no tener Profesional asociado: el administrador técnico del sistema (rol `adm_sistema`) es un caso típico — personal que gestiona la plataforma sin función asistencial directa.
- Un Profesional puede no tener Usuario: monitores de actividades en centros, personal sin necesidad de acceso al sistema. Existen en el sistema porque participan en actividades con ciudadanos, pero no tienen credenciales.

**Profesional es la entidad raíz.** Un profesional existe con independencia de si tiene cuenta de acceso. Un Usuario del sistema, en cambio, casi siempre tiene un Profesional detrás — salvo los perfiles estrictamente técnicos.

La FK vive en `usuarios` como `profesional_id` nullable. Así la relación se navega desde el usuario hacia su perfil cuando existe, y desde el profesional hacia su cuenta cuando existe.

### 1.2 El permiso efectivo como producto de dos dimensiones

El permiso efectivo de un usuario resulta de la intersección de dos dimensiones independientes:

**Rol → responde a ¿qué puede hacer?**
Define las operaciones permitidas con independencia del contexto organizativo. Ejemplo: el rol *Intervención* permite crear y editar apuntes en Historias Sociales.

**Unidad Organizativa (UO) → responde a ¿sobre qué puede hacerlo?**
Delimita el ámbito de datos sobre el que puede ejercer esas operaciones. Ejemplo: un profesional con rol *Intervención* adscrito al Centro de Servicios Sociales de Arganzuela gestiona las Historias de ciudadanos de ese centro, y puede consultar las de otros centros en modo lectura.

Los roles son **globales**: un usuario tiene un rol para todo el sistema, no un rol diferente por UO. La UO determina dónde puede ejercer ese rol. Un usuario puede tener más de un rol y pertenecer a más de una UO. Los permisos efectivos son la unión de todas las combinaciones activas.

La evaluación del permiso efectivo para cualquier acción sigue dos preguntas secuenciales:
1. ¿Tiene el usuario el rol adecuado para realizar esta operación?
2. ¿Pertenece el usuario a la UO adecuada para operar sobre este recurso?

### 1.3 Estructura de las Unidades Organizativas

Las UO son **jerárquicas** (N niveles), **dinámicas** (se crean, eliminan, renombran y reorganizan) y **configurables desde el backoffice** sin necesidad de desarrollo.

Ejemplos de niveles en el Ayuntamiento de Madrid:
```
Ayuntamiento
└── Área de Gobierno de Políticas Sociales
    ├── Dirección General de Servicios Sociales
    │   ├── Departamento de Atención Primaria
    │   │   ├── Centro de Servicios Sociales Arganzuela
    │   │   └── Centro de Servicios Sociales Retiro
    │   └── Departamento de Atención Especializada
    └── Dirección General de Mayores
        └── Centro de Mayores Pradolongo
```

La jerarquía determina el ámbito de supervisión: un responsable adscrito a una DG tiene visibilidad sobre todos los centros bajo esa DG. La comprobación de pertenencia es **descendente**: un usuario adscrito a un nodo tiene acceso a todos los nodos bajo él.

### 1.4 Adscripción de usuarios a UO

La adscripción de un usuario a una UO es responsabilidad del **Administrador de Usuarios** de esa UO, no del administrador del sistema. Al adscribir un usuario a una UO, el usuario puede ejercer en esa UO los roles que tenga asignados globalmente. El administrador de usuarios gestiona personas y su pertenencia territorial, no permisos.

Un usuario puede estar adscrito a más de una UO. La adscripción tiene fechas de vigencia para mantener el historial completo.

### 1.5 Tres niveles de acceso a Historias Sociales

**Nivel 1 — Gestión completa:** el profesional tiene asignada esa Historia en su UO. Puede crear y editar todos los elementos del plan que le corresponden según su rol.

**Nivel 2 — Consulta libre:** cualquier profesional con rol *Intervención* puede leer cualquier Historia Social fuera de su UO sin justificación previa. Garantiza la visión 360 y evita que la UO funcione como escudo. Queda registrado en la auditoría visible para el TSR (principio 3.5).

**Nivel 3 — Consulta con aprobación:** para ciudadanos de colectivos especialmente protegidos, el acceso en consulta desde fuera de la UO responsable requiere aprobación previa del supervisor competente. Ver sección 3.

---

## 2. Roles del sistema

El sistema define **7 roles** iniciales. Los roles son configurables desde el backoffice: sus permisos atómicos pueden modificarse sin necesidad de desarrollo. Crear un nuevo rol también es una operación de backoffice. Lo que sí requiere código es crear un nuevo permiso atómico, porque implica que alguna parte del sistema lo va a verificar.

Cada rol tiene configurado su **nivel de supervisión** para la asignación (ver sección 2.8).

---

### ROL 1 — Administración del Sistema (`adm_sistema`)

**Perfil:** Administradores de toda la aplicación. Responsables funcionales en Área de Gobierno (general y por Dirección General). Personal técnico sin función asistencial directa — estos usuarios típicamente no tienen Profesional asociado.

**Capacidades:** Configuración global de la aplicación: gestión de centros, tipos de centros, catálogo de prestaciones, tipos de planes de intervención, colectivos protegidos, flujos de trabajo, parámetros del sistema. Acceso a todos los módulos de administración.

**Ámbito por UO:** Global. No está limitado por UO para operaciones de configuración.

**Nivel de supervisión:** Requiere aprobación previa antes de que la asignación sea efectiva.

---

### ROL 2 — Supervisión (`supervision`)

**Perfil:** Directivos con responsabilidad de gestión: directores generales, jefes de departamento, concejales y similares.

**Capacidades:**
- Acceso de lectura a toda la información de su ámbito funcional (Historias Sociales, planes, apuntes, prestaciones, actividad de profesionales).
- Consulta de trazabilidad: informes de accesos a expedientes por parte de profesionales.
- Validación de acceso a datos sensibles de ciudadanos especialmente protegidos en su DG.
- Firma o rechazo de propuestas que requieran aprobación de supervisión.
- No incluye las funcionalidades completas de gestión o intervención, salvo las estrictamente necesarias para sus responsabilidades.

**Ámbito por UO:** Limitado a su DG / departamento y la estructura bajo ella.

**Nivel de supervisión:** Requiere aprobación previa antes de que la asignación sea efectiva.

---

### ROL 3 — Administración de Usuarios (`adm_usuarios`)

**Perfil:** Personal referente en Área de Gobierno (jefes de departamento, sección, unidad) y en Distritos (jefes de departamento, direcciones, jefaturas de sección).

**Capacidades:**
- Alta, modificación y baja de usuarios en su ámbito funcional.
- Adscripción de usuarios a UO y gestión de su vigencia.
- Gestión de agendas de los usuarios de su UO.
- Gestión de suplencias.
- No tiene acceso a la configuración de permisos por rol (responsabilidad de `adm_sistema`).
- Puede asignar cualquier rol existente, pero la asignación de roles con aprobación requerida (`adm_sistema`, `supervision`) genera una solicitud que debe aprobar el supervisor competente antes de ser efectiva.

**Ámbito por UO:** Limitado a su UO y la estructura bajo ella. No puede gestionar usuarios de UO superiores o paralelas.

**Nivel de supervisión:** Alerta supervisada con reconocimiento obligatorio.

---

### ROL 4 — Intervención (`intervencion`)

**Perfil:** Personal técnico con intervención profesional directa: trabajadores sociales, psicólogos, educadores sociales, terapeutas ocupacionales, auxiliares de servicios sociales en ASP, y otros profesionales con responsabilidades equivalentes, tanto en ASP como en atención especializada.

**Capacidades:**
- Gestión completa de Historias Sociales asignadas en su UO: valoraciones, apuntes, planes de intervención, seguimientos.
- Consulta de Historias Sociales fuera de su UO (Nivel 2, sujeto a auditoría).
- Derivaciones e inter-consultas entre servicios.
- Agenda propia con gestión de citas individuales y grupales.
- Firma integrada de informes sociales.
- Acceso a datos de categoría especial de ciudadanos especialmente protegidos, previa aprobación y con las restricciones del Nivel 3.
- **Anotaciones privadas:** puede crear, leer y eliminar anotaciones de uso estrictamente personal. Ver sección 4.7.

**Ámbito por UO:** Gestión completa en su UO; consulta libre fuera de ella (salvo colectivos protegidos).

**Nivel de supervisión:** Alerta supervisada con reconocimiento obligatorio.

---

### ROL 5 — Tramitación (`tramitacion`)

**Perfil:** Auxiliares administrativos, administrativos, jefes de negociado.

**Capacidades:**
- Alta y modificación de ciudadanos en el sistema.
- Gestión de citas y agendas.
- Apertura, cierre y reactivación de Historias Sociales.
- Incorporación de personas a unidades de convivencia.
- Gestión de traslados y fusiones de registros.
- Tramitación de solicitudes de prestaciones: inicio de procesos administrativos, subida de documentos, seguimiento de estado.
- No tiene acceso a apuntes profesionales ni a contenido clínico/social de las Historias.

**Ámbito por UO:** Limitado a su UO.

**Nivel de supervisión:** Alerta supervisada con reconocimiento obligatorio.

---

### ROL 6 — Consulta Profesional Puntual (`consulta_profesional`)

**Perfil:** Abogados del Servicio de Orientación Jurídica (SOJ), servicios de gestión indirecta de distritos y otros profesionales externos con necesidad de acceso puntual.

**Capacidades:**
- Consulta de informes, procesos abiertos, trámites y citas.
- Registro del resultado de una cita como intervención puntual.
- No puede consultar apuntes profesionales de otros agentes.
- No tiene acceso a contenido de intervención de otras personas.

**Ámbito por UO:** Limitado a los ciudadanos y procesos explícitamente asignados.

**Nivel de supervisión:** Alerta supervisada con reconocimiento obligatorio.

---

### ROL 7 — Consulta y Gestión Básica (`consulta_basica`)

**Perfil:** Ordenanzas, personal de información y control, personal auxiliar de programas temporales de empleo en servicios de gestión directa, personal auxiliar de gestión indirecta.

**Capacidades:**
- Consulta básica: acceso a la ficha/cabecera del ciudadano (datos de identificación), sin acceso al contenido de la Historia Social.
- Visualización de citas pendientes.
- Gestión básica de soporte: crear y modificar citas, modificar datos de identificación del ciudadano, subida de documentos a carpeta de usuario o a procesos administrativos de solicitudes.

**Ámbito por UO:** Limitado a su UO.

**Nivel de supervisión:** Alerta supervisada con reconocimiento obligatorio.

---

### 2.8 Niveles de supervisión en la asignación de roles

La asignación de roles por parte de `adm_usuarios` está sujeta a supervisión para prevenir el uso indebido de privilegios. Se definen dos niveles según la criticidad del rol:

**Aprobación previa** (`adm_sistema`, `supervision`): la asignación no es efectiva hasta que el supervisor de la UO superior la aprueba explícitamente. El rol no se activa en el sistema hasta recibir aprobación. Si se deniega, el usuario no obtiene el rol y el solicitante recibe notificación con la justificación. Este flujo es idéntico al de acceso a colectivos protegidos.

**Alerta supervisada** (resto de roles): la asignación es efectiva inmediatamente. Se genera una alerta que el supervisor de la UO superior debe reconocer explícitamente como leída. El módulo de alertas gestiona el escalado si la alerta no se reconoce en el plazo configurado.

El nivel de supervisión requerido por cada rol es un atributo configurable desde el backoffice, no un valor hardcodeado. Se almacena en la tabla de configuración de roles (ver sección 4.2).

Todo movimiento de roles queda registrado en el log de auditoría con: usuario que realizó la asignación, rol asignado, usuario destinatario, timestamp, y resultado de la supervisión.

---

## 3. Acceso a colectivos especialmente protegidos

Los colectivos especialmente protegidos (actualmente mujeres víctimas de violencia de género y menores) requieren un proceso de aprobación previa para cualquier acceso desde fuera de la UO responsable. Ver principio 3.6.

### Flujo de solicitud de acceso

1. El profesional solicita acceso a la Historia de un ciudadano especialmente protegido fuera de su UO.
2. El sistema muestra un formulario de solicitud con campo de **justificación obligatorio**.
3. El sistema envía un **mensaje interno** al supervisor competente de la UO responsable (supervisor de VG o de infancia según el colectivo).
4. El supervisor aprueba o deniega la solicitud. La resolución queda registrada.
5. Si se aprueba, el acceso se habilita con las condiciones y duración indicadas. Queda registrado en la auditoría visible.
6. Si se deniega, el profesional recibe notificación con la justificación del supervisor.

### Excepción: servicios de atención de urgencia

Los servicios de atención social de urgencia (SAMUR Social en Madrid) tienen **acceso preautorizado** en modo consulta a Historias de ciudadanos especialmente protegidos, sin necesidad de aprobación previa. Este acceso queda registrado en la auditoría con indicación del régimen de emergencia. El supervisor competente puede revisarlo a posteriori.

Los servicios con acceso de emergencia preautorizado son **configurables desde el backoffice** (principio 4.15).

### Gestión de suplencias del supervisor

El enrutamiento de solicitudes de aprobación al supervisor competente, incluyendo suplencias por vacaciones o baja, es responsabilidad del módulo de mensajería interna. Ver documentación del módulo de mensajería interna.

---

## 4. Implementación técnica

### 4.1 Paquetes seleccionados

**`spatie/laravel-permission`** para la gestión de roles y permisos atómicos. Es el estándar de facto en el ecosistema Laravel: maduro, bien mantenido, licencia MIT, compatible con Laravel 10. Resuelve la dimensión de roles y la evaluación de permisos en tiempo real mediante `can()`.

**`staudenmeir/laravel-adjacency-list`** para la jerarquía de UO. Añade soporte nativo para consultas recursivas en Eloquent (ancestros, descendientes, profundidad) sobre una estructura de Adjacency List (`parent_id`). Es la opción correcta para una jerarquía dinámica donde los nodos cambian con frecuencia.

El **scoping por UO** no lo resuelve ningún paquete: se construye encima de Spatie mediante Laravel Policies. Las Policies son la capa de autorización por recurso — evalúan si el usuario tiene el permiso requerido Y si está operando dentro de su ámbito de UO o ejerciendo consulta libre.

### 4.2 Modelo de datos

```
usuarios
- id
- profesional_id (FK a profesionales, nullable)
- name
- email
- password
- email_verified_at
- remember_token
- created_at, updated_at

unidades_organizativas
- id
- nombre
- parent_id (FK a sí misma, nullable para la raíz)
- tipo (ayuntamiento / dg / departamento / centro / ...)
- activa (boolean)
- created_at, updated_at

usuario_uo  (adscripción usuario–UO, con historial)
- id
- usuario_id (FK a usuarios)
- unidad_organizativa_id (FK a unidades_organizativas)
- tipo_vinculo (interno / contratado / externo)
- fecha_inicio
- fecha_fin (nullable)
- created_at, updated_at

usuario_rol  (asignación usuario–rol, con historial)
- id
- usuario_id (FK a usuarios)
- rol_id (FK a roles de Spatie)
- fecha_inicio
- fecha_fin (nullable)
- asignado_por (FK a usuarios)
- estado (pendiente_aprobacion / activo / inactivo)
- created_at, updated_at

configuracion_roles  (extiende roles de Spatie sin modificar sus tablas)
- id
- rol_id (FK a roles de Spatie, unique)
- nivel_supervision (enum: aprobacion_previa / alerta_supervisada)
- created_at, updated_at
```

Los permisos atómicos y los roles los gestiona Spatie en sus tablas propias (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`).

### 4.3 Sincronización entre usuario_rol y Spatie

`usuario_rol` es la **fuente de verdad** para la asignación de roles. `model_has_roles` de Spatie es un estado derivado optimizado para la evaluación rápida de `can()` en cada request.

La sincronización se mantiene mediante un **Observer** en el modelo `UsuarioRol`:

- Al crear una adscripción con `estado = activo` y fecha vigente → `assignRole()` en Spatie.
- Al establecer `fecha_fin` o cambiar `estado` a `inactivo` → `removeRole()` en Spatie.
- Al aprobar una asignación pendiente (`estado: pendiente_aprobacion → activo`) → `assignRole()` en Spatie.

Un comando Artisan de reconciliación (`usuarios:reconciliar-roles`) sincroniza `model_has_roles` con el estado actual de `usuario_rol`. Debe ejecutarse al arrancar el sistema por primera vez y tras migraciones.

### 4.4 Estrategia de permisos: mixta

Los **permisos atómicos** se definen en código mediante seeders. Son contratos del sistema: cada permiso corresponde a una verificación en algún punto del código. Ejemplos:

```
ciudadano.ver_ficha
ciudadano.ver_datos_contacto
historia.leer
historia.editar
historia.abrir
historia.cerrar
apunte.crear
apunte.leer_propio
apunte.leer_ajeno
plan.crear
plan.editar
prestacion.asignar
usuario.crear
usuario.editar
usuario.dar_baja
centro.gestionar
configuracion.acceder
trazabilidad.consultar
colectivo_protegido.solicitar_acceso
colectivo_protegido.aprobar_acceso
```

Los **roles** son configurables desde el backoffice: el administrador del sistema puede ver qué permisos tiene cada rol, añadir o quitar permisos de un rol, y crear nuevos roles asignándoles permisos existentes. Crear un permiso nuevo sigue requiriendo código porque implica implementar la verificación correspondiente en el sistema.

### 4.5 Laravel Policies para el scoping por UO

Cada recurso sensible (HistoriaSocial, Apunte, PlanDeIntervencion, Ciudadano) tiene su Policy correspondiente. La Policy evalúa en orden:

1. ¿Tiene el usuario el permiso atómico requerido para esta operación?
2. Si sí: ¿el recurso pertenece a la UO del usuario (gestión completa) o es de otra UO (consulta libre)?
3. Si es consulta libre: ¿el ciudadano pertenece a un colectivo especialmente protegido?
4. Si es colectivo protegido: ¿existe una aprobación de acceso vigente para este usuario y este ciudadano?

### 4.6 Backoffice de administración

El backoffice de usuarios y permisos debe ofrecer:

- **Gestión de UO:** crear, editar, desactivar UO; cambiar su posición en la jerarquía.
- **Gestión de usuarios:** alta, baja, modificación; adscripción a UO con tipo de vínculo y vigencia; historial de adscripciones y roles.
- **Gestión de roles y permisos:** visualización de la matriz rol × permiso; modificación de permisos por rol (solo `adm_sistema`); configuración del nivel de supervisión por rol.
- **Gestión de colectivos protegidos:** alta y baja de colectivos; configuración de servicios con acceso de emergencia preautorizado.
- **Gestión de suplencias:** delegada al módulo de mensajería interna.

### 4.7 Anotaciones privadas del profesional

Las anotaciones privadas son un caso especial dentro del tipo `Anotacion` del modelo de apuntes. Sus reglas son distintas al resto de apuntes:

- **Solo el autor puede crearlas, leerlas y eliminarlas.** Ningún otro usuario tiene acceso, independientemente de su rol o jerarquía. Esto incluye al supervisor.
- **No forman parte de la Historia Social visible.** No aparecen en ninguna vista de la Historia accesible a otros profesionales ni al ciudadano.
- **No están sujetas a la auditoría visible** del TSR (principio 3.5), aunque sí al log técnico de base de datos (principio 4.3).
- **No son exportables** ni aparecen en informes generados.

**Implicación de implementación:** el campo `privada` (boolean) en la entidad `Apunte` distingue este tipo. Las Policies deben incluir una verificación explícita: si `apunte.privada === true`, solo el `apunte.profesional_id` tiene acceso, sin excepción. Esta regla tiene precedencia sobre cualquier otra regla de acceso.

### 4.8 Referencias de código

*(Se completará a medida que avance la implementación)*

- Modelo de Usuario: `App\Models\Usuario`
- Modelo de Profesional: `Modules\Usuarios\Models\Profesional`
- Modelo de UO: `App\Models\UnidadOrganizativa`
- Pivot de adscripción a UO: `App\Models\UsuarioUo`
- Pivot de roles con historial: `App\Models\UsuarioRol`
- Observer de sincronización con Spatie: `App\Observers\UsuarioRolObserver`
- Seeders de permisos: `Database\Seeders\PermisosSeeder`
- Seeders de roles: `Database\Seeders\RolesSeeder`
- Comando de reconciliación: `App\Console\Commands\ReconciliarRoles`
- Policies: `App\Policies\HistoriaSocialPolicy`, `App\Policies\ApuntePolicy`, etc.
- Controladores de backoffice: `App\Http\Controllers\Admin\`

---

## 5. Modelo de Profesional

### 5.1 Propósito y alcance

La entidad `Profesional` recoge los datos que describen a una persona en su dimensión laboral dentro de la organización. No es la cuenta del sistema (`Usuario`) ni es el perfil de autorización (roles y UO). Es el puesto: quién eres profesionalmente, qué cargo ocupas, qué titulación tienes, cómo contactarte en el trabajo.

`Profesional` está sujeta a versionado (principio 4.2): sus datos cambian a lo largo de la vida laboral de la persona y el sistema debe poder conocer el estado en cualquier fecha pasada.

### 5.2 Modelo de datos

```
profesionales
- id
- nombre
- apellido1
- apellido2
- sexo (M / F / D)                          — principio 4.14
- cargo_id (FK a cargos)                    — catálogo configurable
- categoria_profesional                     — nivel administrativo: A1, A2, C1...
- titulacion_id (FK a titulaciones, nullable)
- tipo_relacion_id (FK a tipos_relacion_profesional)
- organizacion (nullable)                   — nombre de la org. externa si no es ayuntamiento
- email_profesional (nullable)
- telefono_profesional (nullable)
- extension (nullable)                      — extensión telefónica interna
- fecha_inicio
- fecha_fin (nullable)
- activo (boolean)                          — para consultas rápidas sin filtrar por fecha
- created_at, updated_at
- deleted_at                                — SoftDeletes

cargos  (catálogo configurable desde backoffice)
- id
- nombre                                    — "Trabajador/a Social", "Técnico/a de Acogida"...
- descripcion (nullable)
- activo (boolean)
- created_at, updated_at

tipos_relacion_profesional  (catálogo configurable)
- id
- nombre                                    — funcionario, interino, contratado laboral, externo, voluntario...
- es_externo (boolean)                      — determina si el campo organizacion es relevante
- activo (boolean)
- created_at, updated_at

titulaciones  (catálogo configurable)
- id
- nombre
- activo (boolean)
- created_at, updated_at
```

### 5.3 Versionado de Profesional

El versionado de `Profesional` se implementa mediante la tabla polimórfica `versiones` (Opción A, decisión transversal a todo el sistema). Ver sección 5.4.

Los campos más susceptibles de cambio a lo largo de la vida laboral son `cargo_id`, `categoria_profesional`, `tipo_relacion_id` y `titulacion_id`. El snapshot completo del registro garantiza que cualquier consulta histórica ("¿qué cargo tenía este profesional en enero de 2024?") se resuelve con una sola consulta sin reconstrucción de diffs.

### 5.4 Versionado transversal del sistema

El versionado aplica a todas las entidades relevantes del sistema: `Profesional`, `Ciudadano`, `Centro`, y cualquier entidad no auxiliar que pueda cambiar a lo largo del tiempo. La implementación es un trait `Versionable` que se aplica a cada modelo con una línea.

```
versiones
- id
- versionable_type                          — nombre de la clase ('Profesional', 'Ciudadano'...)
- versionable_id                            — id del registro
- datos                                     — JSON con el estado completo del registro
- usuario_id (FK a usuarios)               — quién hizo el cambio
- motivo (nullable)                         — texto libre para cambios que lo requieran
- created_at                               — el timestamp ES la fecha de la versión
```

Índice compuesto sobre `(versionable_type, versionable_id, created_at)`.

El trait `Versionable` escucha el evento `updating` de Eloquent y antes de guardar el cambio crea una versión con el estado completo actual. La versión registra el estado **anterior** al cambio; el registro principal tiene siempre el estado actual. Para conocer el estado en una fecha X: si X es posterior al último cambio, el registro actual es la respuesta; si no, se busca la versión más reciente anterior a X.

El campo `datos` guarda siempre el snapshot completo, no el diff. Guardar solo los campos modificados complicaría la reconstrucción del estado histórico, que requeriría reproducir todos los diffs desde el origen.

---

## 6. Decisiones pendientes

- **Definición exhaustiva del catálogo de permisos atómicos:** la lista de la sección 4.4 es orientativa. Debe revisarse y completarse antes de iniciar la implementación de cada módulo.
- **Módulo de mensajería interna y alertas:** gestión de suplencias, enrutamiento de solicitudes de aprobación de acceso a colectivos protegidos, alertas de supervisión de roles y reconocimiento de lectura.
- **Rol 0 para ciudadanos:** cuando se implemente el acceso ciudadano, se definirá un rol específico con permisos de consulta sobre la propia Historia Social y operaciones básicas de autogestión.
- **Integración con directorio corporativo:** en despliegues municipales, los usuarios pueden estar ya en un directorio LDAP/Active Directory. La estrategia de sincronización o federación de identidades se definirá en fase de implantación.
- **Profesionales de servicios externalizados:** el personal de servicios de ayuda a domicilio y otros proveedores complejos se contempla en fases futuras. La estructura de `tipos_relacion_profesional` y el campo `organizacion` están preparados para absorberlos cuando llegue el momento.
- **Número de empleado:** si se confirma la integración con el sistema de RRHH del ayuntamiento, añadir `numero_empleado` a `profesionales` para facilitar la sincronización con LDAP/AD.


## 7. Tests funcionales — Módulo Usuarios (fase 2)

### TF-USU-19 a TF-USU-42

> Este fichero especifica los tests funcionales derivados de las decisiones de diseño
> del hilo de usuarios (marzo 2026). Complementa `docs/instrucciones-cli/usuarios-tests.md`
> (TF-USU-01 a TF-USU-18). La implementación la realiza Claude CLI.
>
> Añadir estos tests a `docs/instrucciones-cli/usuarios-tests.md` y actualizar
> `docs/modulo-usuarios-permisos.md` con la tabla de estado una vez implementados.

---

### Convenciones

- **Framework:** PHPUnit con atributo `#[Test]`. No usar Pest.
- **Base de datos:** PostgreSQL (`vida_testing`). No usar SQLite.
- **Ubicación:** `Modules/Usuarios/tests/Feature/`
- **Patrón:** Dado / Cuando / Entonces.
- **Negativo obligatorio:** los tests de restricciones de dominio deben verificarse también
  en negativo (el test debe fallar si se elimina la validación que protege).

---

### Actores reutilizados

Definir en un `setUp()` base o trait compartido:

- `$admin` — usuario con rol `adm_sistema`.
- `$adm_usuarios` — usuario con rol `adm_usuarios`, adscrito a una UO concreta.
- `$profesional` — usuario con rol `intervencion`, adscrito a la misma UO que `$adm_usuarios`.
- `$supervisor` — usuario con rol `supervision`, adscrito a la UO padre de la anterior.
- `$uo_raiz` — UO sin parent_id (nodo raíz).
- `$uo_hija` — UO con `parent_id = $uo_raiz->id`.
- `$uo_nieta` — UO con `parent_id = $uo_hija->id`.

---

### Grupo A — Historial de roles (`UsuarioRol`)

Estos tests verifican que la asignación de roles mantiene historial completo y que
la tabla `usuario_rol` es la fuente de verdad.

---

**TF-USU-19 — Asignar un rol crea un registro en usuario_rol con fecha_inicio y estado activo**

- **Dado** `$profesional` sin roles asignados.
- **Cuando** se crea un registro en `usuario_rol` con `usuario_id = $profesional->id`,
  `rol_id` del rol `intervencion`, `fecha_inicio = hoy`, `fecha_fin = null`,
  `estado = activo`.
- **Entonces** existe exactamente un registro en `usuario_rol` con esos valores
  y `$profesional->hasRole('intervencion')` devuelve `true`.

---

**TF-USU-20 — Cerrar un rol establece fecha_fin y lo elimina de Spatie**

- **Dado** `$profesional` con rol `intervencion` activo en `usuario_rol`.
- **Cuando** se actualiza el registro con `fecha_fin = hoy`.
- **Entonces** `$profesional->fresh()->hasRole('intervencion')` devuelve `false`
  y el registro en `usuario_rol` conserva `fecha_inicio` original con `fecha_fin = hoy`.

---

**TF-USU-21 — El historial de roles se preserva al reasignar el mismo rol**

- **Dado** `$profesional` con un registro en `usuario_rol` con `fecha_fin` pasada (rol anterior).
- **Cuando** se crea un nuevo registro en `usuario_rol` para el mismo rol con nueva `fecha_inicio`.
- **Entonces** existen dos registros en `usuario_rol` para ese usuario y ese rol.
  El primero tiene `fecha_fin` pasada. El segundo tiene `fecha_fin = null` y estado activo.
  `$profesional->fresh()->hasRole('intervencion')` devuelve `true`.

---

**TF-USU-22 — Un usuario puede tener varios roles activos simultáneamente**

- **Dado** `$profesional` sin roles.
- **Cuando** se crean dos registros activos en `usuario_rol`: uno para `intervencion`
  y otro para `adm_usuarios`.
- **Entonces** `$profesional->fresh()->hasRole('intervencion')` devuelve `true`
  y `$profesional->fresh()->hasRole('adm_usuarios')` devuelve `true`.

---

**TF-USU-23 — El comando de reconciliación sincroniza model_has_roles con usuario_rol**

- **Dado** `$profesional` con rol `intervencion` activo en `usuario_rol`
  pero sin entrada en `model_has_roles` (simulando desincronización).
- **Cuando** se ejecuta `Artisan::call('usuarios:reconciliar-roles')`.
- **Entonces** `$profesional->fresh()->hasRole('intervencion')` devuelve `true`.

---

### Grupo B — Jerarquía de Unidades Organizativas

Estos tests verifican el modelo de árbol y las consultas de descendencia.

---

**TF-USU-24 — Una UO puede tener UO hijas**

- **Dado** `$uo_raiz` y `$uo_hija` con `parent_id = $uo_raiz->id`.
- **Cuando** se consulta `$uo_raiz->children`.
- **Entonces** la colección contiene `$uo_hija` y solo ella.

---

**TF-USU-25 — Los descendientes de una UO incluyen todos los niveles inferiores**

- **Dado** `$uo_raiz`, `$uo_hija` y `$uo_nieta` encadenadas.
- **Cuando** se consulta `$uo_raiz->descendants`.
- **Entonces** la colección contiene tanto `$uo_hija` como `$uo_nieta`.

---

**TF-USU-26 — Un usuario adscrito a una UO padre tiene visibilidad sobre UO hijas**

- **Dado** `$supervisor` adscrito a `$uo_raiz` con rol `supervision`.
  `$profesional` adscrito a `$uo_nieta` con rol `intervencion`.
- **Cuando** se evalúa si `$supervisor` tiene acceso al ámbito de `$uo_nieta`.
- **Entonces** el resultado es `true` porque `$uo_nieta` es descendiente de `$uo_raiz`.

---

**TF-USU-27 — Un usuario no tiene visibilidad sobre UO que no son descendientes suyas**

- **Dado** `$uo_paralela` con el mismo `parent_id` que `$uo_hija` (hermana, no descendiente).
  `$adm_usuarios` adscrito a `$uo_hija`.
- **Cuando** se evalúa si `$adm_usuarios` tiene acceso al ámbito de `$uo_paralela`.
- **Entonces** el resultado es `false`.

---

**TF-USU-28 — Desactivar una UO no elimina las adscripciones existentes**

- **Dado** `$uo_hija` activa con `$profesional` adscrito.
- **Cuando** se actualiza `$uo_hija` con `activa = false`.
- **Entonces** el registro en `usuario_uo` sigue existiendo.
  La UO aparece como inactiva pero el historial no se borra.

---

### Grupo C — Adscripción a Unidades Organizativas (`UsuarioUo`)

---

**TF-USU-29 — Un usuario puede estar adscrito a más de una UO simultáneamente**

- **Dado** `$profesional` sin adscripciones.
- **Cuando** se crean dos registros en `usuario_uo`: uno para `$uo_hija`
  y otro para `$uo_nieta`, ambos con `fecha_fin = null`.
- **Entonces** `$profesional->unidadesOrganizativas()->count()` devuelve 2.

---

**TF-USU-30 — La adscripción tiene fechas de vigencia y mantiene historial**

- **Dado** `$profesional` con una adscripción a `$uo_hija` con `fecha_fin` pasada.
- **Cuando** se crea una nueva adscripción a `$uo_hija` con nueva `fecha_inicio`.
- **Entonces** existen dos registros en `usuario_uo` para ese usuario y esa UO.
  Solo el más reciente tiene `fecha_fin = null`.

---

**TF-USU-31 — adm_usuarios no puede adscribir usuarios a UO fuera de su ámbito**

- **Dado** `$adm_usuarios` adscrito a `$uo_hija`. `$uo_raiz` es la UO padre (fuera de su ámbito).
- **Cuando** `$adm_usuarios` intenta crear una adscripción de `$profesional` a `$uo_raiz`.
- **Entonces** la operación lanza una excepción de autorización o devuelve error 403.

---

### Grupo D — Supervisión en asignación de roles

Estos tests verifican el mecanismo de control de privilegios en la asignación de roles.

---

**TF-USU-32 — Asignar un rol de aprobación requerida crea una solicitud pendiente**

- **Dado** rol `supervision` con `nivel_supervision = aprobacion_previa` en `configuracion_roles`.
  `$adm_usuarios` intenta asignar ese rol a `$profesional`.
- **Cuando** se crea el registro en `usuario_rol` con `estado = pendiente_aprobacion`.
- **Entonces** `$profesional->fresh()->hasRole('supervision')` devuelve `false`
  (el rol no está activo en Spatie hasta la aprobación).
  Existe un registro en `usuario_rol` con `estado = pendiente_aprobacion`.

---

**TF-USU-33 — Aprobar una solicitud pendiente activa el rol en Spatie**

- **Dado** un registro en `usuario_rol` con `estado = pendiente_aprobacion` para el rol `supervision`.
- **Cuando** el supervisor aprueba la solicitud actualizando `estado = activo`.
- **Entonces** `$profesional->fresh()->hasRole('supervision')` devuelve `true`.

---

**TF-USU-34 — Denegar una solicitud pendiente no activa el rol**

- **Dado** un registro en `usuario_rol` con `estado = pendiente_aprobacion` para el rol `supervision`.
- **Cuando** el supervisor deniega la solicitud actualizando `estado = denegado`.
- **Entonces** `$profesional->fresh()->hasRole('supervision')` devuelve `false`.
  El registro en `usuario_rol` permanece con `estado = denegado` para trazabilidad.

---

**TF-USU-35 — Asignar un rol de alerta supervisada lo activa inmediatamente**

- **Dado** rol `intervencion` con `nivel_supervision = alerta_supervisada` en `configuracion_roles`.
- **Cuando** se crea el registro en `usuario_rol` con `estado = activo`.
- **Entonces** `$profesional->fresh()->hasRole('intervencion')` devuelve `true` de inmediato,
  sin necesidad de aprobación previa.

---

### Grupo E — Modelo Profesional

Estos tests verifican la entidad `Profesional` y su relación con `Usuario`.

---

**TF-USU-36 — Un profesional puede existir sin usuario asociado**

- **Dado** ningún usuario creado.
- **Cuando** se crea un `Profesional` con `usuario_id = null` y todos los campos obligatorios.
- **Entonces** el registro se guarda sin error. `Profesional::find($id)->usuario` devuelve `null`.

---

**TF-USU-37 — Un usuario puede existir sin profesional asociado**

- **Dado** ningún profesional creado.
- **Cuando** se crea un `Usuario` con `profesional_id = null`.
- **Entonces** el registro se guarda sin error. `Usuario::find($id)->profesional` devuelve `null`.

---

**TF-USU-38 — La relación usuario–profesional es navegable en ambos sentidos**

- **Dado** `$profesional` y `$usuario` con `profesional_id = $profesional->id`.
- **Cuando** se navega `$usuario->profesional` y `$profesional->usuario`.
- **Entonces** `$usuario->profesional->id === $profesional->id`
  y `$profesional->usuario->id === $usuario->id`.

---

**TF-USU-39 — El cargo de un profesional referencia el catálogo y es obligatorio**

- **Dado** ningún `Cargo` creado.
- **Cuando** se intenta crear un `Profesional` con `cargo_id = null`.
- **Entonces** se lanza una excepción de validación o constraint de base de datos.

---

**TF-USU-40 — El campo organizacion es relevante solo para profesionales externos**

- **Dado** un `TipoRelacionProfesional` con `es_externo = true` y otro con `es_externo = false`.
- **Cuando** se crea un `Profesional` con el tipo externo y `organizacion = 'Cruz Roja'`.
- **Entonces** `$profesional->organizacion === 'Cruz Roja'`.
- **Y cuando** se crea un `Profesional` con el tipo interno y `organizacion = null`.
- **Entonces** el registro se guarda sin error (el campo es nullable para internos).

---

### Grupo F — Versionado de Profesional

Estos tests verifican que el trait `Versionable` genera snapshots correctos.

---

**TF-USU-41 — Modificar un profesional crea una versión con el estado anterior**

- **Dado** `$profesional` con `cargo_id = $cargo_a->id`.
- **Cuando** se actualiza `cargo_id = $cargo_b->id`.
- **Entonces** existe un registro en `versiones` con `versionable_type = 'Profesional'`,
  `versionable_id = $profesional->id`, y `datos` (JSON) con `cargo_id = $cargo_a->id`
  (el estado anterior al cambio).
  El registro principal tiene `cargo_id = $cargo_b->id`.

---

**TF-USU-42 — El snapshot de versión contiene el estado completo del registro, no solo el diff**

- **Dado** `$profesional` con varios campos rellenos (`nombre`, `cargo_id`, `tipo_relacion_id`, etc.).
- **Cuando** se actualiza únicamente `cargo_id`.
- **Entonces** el registro en `versiones` tiene en `datos` todos los campos del profesional
  en su estado anterior, no solo `cargo_id`.

---

### Tabla de estado inicial

| Test | Descripción | Estado |
|---|---|---|
| TF-USU-19 | Asignar rol crea registro en usuario_rol con estado activo | ✅ |
| TF-USU-20 | Cerrar rol establece fecha_fin y lo elimina de Spatie | ✅ |
| TF-USU-21 | Historial de roles se preserva al reasignar el mismo rol | ✅ |
| TF-USU-22 | Usuario puede tener varios roles activos simultáneamente | ✅ |
| TF-USU-23 | Comando de reconciliación sincroniza model_has_roles | ✅ |
| TF-USU-24 | UO puede tener UO hijas | ✅ |
| TF-USU-25 | Descendientes de UO incluyen todos los niveles | ✅ |
| TF-USU-26 | Usuario adscrito a UO padre tiene visibilidad sobre UO hijas | ✅ |
| TF-USU-27 | Usuario no tiene visibilidad sobre UO paralelas | ✅ |
| TF-USU-28 | Desactivar UO no elimina adscripciones existentes | ✅ |
| TF-USU-29 | Usuario puede estar adscrito a más de una UO | ✅ |
| TF-USU-30 | Adscripción mantiene historial con fechas de vigencia | ✅ |
| TF-USU-31 | adm_usuarios no puede adscribir fuera de su ámbito | _(pendiente)_ No existe Policy/Service para autorización de adscripción. |
| TF-USU-32 | Rol con aprobación requerida crea solicitud pendiente | ✅ |
| TF-USU-33 | Aprobar solicitud pendiente activa rol en Spatie | ✅ |
| TF-USU-34 | Denegar solicitud no activa el rol | ✅ |
| TF-USU-35 | Rol con alerta supervisada se activa inmediatamente | ✅ |
| TF-USU-36 | Profesional puede existir sin usuario | ✅ |
| TF-USU-37 | Usuario puede existir sin profesional | ✅ |
| TF-USU-38 | Relación usuario–profesional es navegable en ambos sentidos | ✅ |
| TF-USU-39 | Cargo de profesional es obligatorio | ✅ |
| TF-USU-40 | Campo organizacion relevante solo para externos | ✅ |
| TF-USU-41 | Modificar profesional crea versión con estado anterior | ✅ |
| TF-USU-42 | Snapshot de versión contiene estado completo, no solo diff | ✅ |
| **Total** | | **23 ✅, 1 pendiente** |

---

*Documento elaborado en fase de diseño del proyecto. Actualizado en marzo 2026 con las decisiones del hilo de diseño del módulo de usuarios.*
