# Módulo: Usuarios y Permisos — VIDA 360

> Este documento describe el modelo conceptual y las decisiones de implementación técnica del sistema de usuarios, roles, permisos y unidades organizativas de VIDA 360. Debe leerse junto a `docs/principios.md` (especialmente los principios 3.4, 3.5, 3.6 y 4.15) y `docs/glosario.md` (entradas Rol, Permiso, Profesional).
>
> **Alcance:** este módulo gestiona los usuarios del sistema (profesionales y personal administrativo). Los ciudadanos tienen un tratamiento diferente y acceden a través de interfaces propias (carpeta ciudadana, canales de comunicación). Ver principio 3.11.

---

## 1. Modelo conceptual

### 1.1 El permiso efectivo como producto de dos dimensiones

El permiso efectivo de un usuario resulta de la intersección de dos dimensiones independientes:

**Rol → responde a ¿qué puede hacer?**
Define las operaciones permitidas con independencia del contexto organizativo. Ejemplo: el rol *Intervención* permite crear y editar apuntes en Historias Sociales.

**Unidad Organizativa (UO) → responde a ¿sobre qué puede hacerlo?**
Delimita el ámbito de datos sobre el que puede ejercer esas operaciones. Ejemplo: un profesional con rol *Intervención* adscrito al Centro de Servicios Sociales de Arganzuela gestiona las Historias de ciudadanos de ese centro, y puede consultar las de otros centros en modo lectura.

Un usuario puede tener más de un rol y pertenecer a más de una UO. Los permisos efectivos son la unión de todas las combinaciones activas.

### 1.2 Estructura de las Unidades Organizativas

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

La jerarquía determina el ámbito de supervisión: un responsable adscrito a una DG tiene visibilidad sobre todos los centros bajo esa DG.

### 1.3 Adscripción de usuarios a UO

La adscripción de un usuario a una UO es responsabilidad del **Administrador de Usuarios** de esa UO, no del administrador del sistema. Al adscribir un usuario a una UO con un rol concreto, el usuario hereda automáticamente los permisos asociados a ese rol. El administrador de usuarios gestiona personas, no permisos.

Un usuario puede estar adscrito a más de una UO con roles distintos. La adscripción tiene fechas de vigencia para mantener el historial.

### 1.4 Tres niveles de acceso a Historias Sociales

**Nivel 1 — Gestión completa:** el profesional tiene asignada esa Historia en su UO. Puede crear y editar todos los elementos del plan que le corresponden según su rol.

**Nivel 2 — Consulta libre:** cualquier profesional con rol *Intervención* puede leer cualquier Historia Social fuera de su UO sin justificación previa. Garantiza la visión 360 y evita que la UO funcione como escudo. Queda registrado en la auditoría visible para el TSR (principio 3.5).

**Nivel 3 — Consulta con aprobación:** para ciudadanos de colectivos especialmente protegidos, el acceso en consulta desde fuera de la UO responsable requiere aprobación previa del supervisor competente (supervisor de VG para víctimas de violencia de género, supervisor de infancia para menores). Ver sección 3.

---

## 2. Roles del sistema

El sistema define **7 roles** iniciales. Los roles son configurables desde el backoffice: sus permisos atómicos pueden modificarse sin necesidad de desarrollo. Crear un nuevo rol también es una operación de backoffice. Lo que sí requiere código es crear un nuevo permiso atómico, porque implica que alguna parte del sistema lo va a verificar.

---

### ROL 1 — Administración del Sistema (`adm_sistema`)

**Perfil:** Administradores de toda la aplicación. Responsables funcionales en Área de Gobierno (general y por Dirección General).

**Capacidades:** Configuración global de la aplicación: gestión de centros, tipos de centros, catálogo de prestaciones, tipos de planes de intervención, colectivos protegidos, flujos de trabajo, parámetros del sistema. Acceso a todos los módulos de administración.

**Ámbito por UO:** Global. No está limitado por UO para operaciones de configuración.

---

### ROL 2 — Supervisión (`supervision`)

**Perfil:** Directivos con responsabilidad de gestión: directores generales, jefes de departamento, concejales y similares.

**Capacidades:**
- Acceso de lectura a toda la información de su ámbito funcional (Historias Sociales, planes, apuntes, prestaciones, actividad de profesionales).
- Consulta de trazabilidad: informes de accesos a expedientes por parte de profesionales.
- Validación de acceso a datos sensibles de ciudadanos especialmente protegidos en su DG (es el responsable de tratamiento).
- Firma o rechazo de propuestas que requieran aprobación de supervisión.
- No incluye las funcionalidades completas de gestión o intervención, salvo las estrictamente necesarias para sus responsabilidades.

**Ámbito por UO:** Limitado a su DG / departamento y la estructura bajo ella.

---

### ROL 3 — Administración de Usuarios (`adm_usuarios`)

**Perfil:** Personal referente en Área de Gobierno (jefes de departamento, sección, unidad) y en Distritos (jefes de departamento, direcciones, jefaturas de sección).

**Capacidades:**
- Alta, modificación y baja de usuarios en su ámbito funcional.
- Asignación y modificación de adscripción de usuarios a UO con sus roles.
- Gestión de agendas de los usuarios de su UO.
- Gestión de suplencias.
- No tiene acceso a la configuración de permisos por rol (eso es responsabilidad de `adm_sistema`).

**Ámbito por UO:** Limitado a su UO y la estructura bajo ella. No puede gestionar usuarios de UO superiores o paralelas.

---

### ROL 4 — Intervención (`intervencion`)

**Perfil:** Personal técnico con intervención profesional directa: trabajadores sociales, psicólogos, educadores sociales, terapeutas ocupacionales, auxiliares de servicios sociales en ASP, y otros profesionales con responsabilidades equivalentes, tanto en ASP como en atención especializada.

**Capacidades:**
- Gestión completa de Historias Sociales asignadas en su UO: valoraciones, apuntes, planes de intervención, seguimientos.
- Consulta de Historias Sociales fuera de su UO (Nivel 2, sujeto a auditoría).
- Derivaciones e inter-consultas entre servicios.
- Agenda propia con gestión de citas individuales y grupales.
- Firma integrada de informes sociales.
- Acceso a datos de categoría especial de ciudadanos especialmente protegidos, previa identificación por su DG correspondiente y con las restricciones del Nivel 3.

**Ámbito por UO:** Gestión completa en su UO; consulta libre fuera de ella (salvo colectivos protegidos).

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

---

### ROL 6 — Consulta Profesional Puntual (`consulta_profesional`)

**Perfil:** Abogados del Servicio de Orientación Jurídica (SOJ), servicios de gestión indirecta de distritos y otros profesionales externos con necesidad de acceso puntual.

**Capacidades:**
- Consulta de informes, procesos abiertos, trámites y citas.
- Registro del resultado de una cita como intervención puntual.
- No puede consultar apuntes profesionales de otros agentes.
- No tiene acceso a contenido de intervención de otras personas.

**Ámbito por UO:** Limitado a los ciudadanos y procesos explícitamente asignados.

---

### ROL 7 — Consulta y Gestión Básica (`consulta_basica`)

**Perfil:** Ordenanzas, personal de información y control, personal auxiliar de programas temporales de empleo en servicios de gestión directa, personal auxiliar de gestión indirecta.

**Capacidades:**
- Consulta básica: acceso a la ficha/cabecera del ciudadano (datos de identificación), sin acceso al contenido de la Historia Social.
- Visualización de citas pendientes.
- Gestión básica de soporte: crear y modificar citas, modificar datos de identificación del ciudadano, subida de documentos a carpeta de usuario o a procesos administrativos de solicitudes.

**Ámbito por UO:** Limitado a su UO.

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

El enrutamiento de solicitudes de aprobación al supervisor competente, incluyendo suplencias por vacaciones o baja, es responsabilidad del módulo de mensajería interna. Este módulo garantiza que la solicitud llega siempre a quien tiene la competencia en el momento de la solicitud. Ver documentación del módulo de mensajería interna.

---

## 4. Implementación técnica

### 4.1 Paquetes seleccionados

**`spatie/laravel-permission`** para la gestión de roles y permisos atómicos. Es el estándar de facto en el ecosistema Laravel: maduro, bien mantenido, licencia MIT, compatible con Laravel 10. Resuelve la dimensión de roles.

**`staudenmeir/laravel-adjacency-list`** para la jerarquía de UO. Añade soporte nativo para consultas recursivas en Eloquent (ancestros, descendientes, profundidad) sobre una estructura de Adjacency List (`parent_id`). Es la opción correcta para una jerarquía dinámica donde los nodos cambian con frecuencia.

El **scoping por UO** no lo resuelve ningún paquete: se construye encima de Spatie mediante Laravel Policies. Las Policies son la capa de autorización por recurso — evalúan si el usuario tiene el permiso requerido Y si está operando dentro de su ámbito de UO o ejerciendo consulta libre.

### 4.2 Modelo de datos

```
unidades_organizativas
- id
- nombre
- parent_id (FK a sí misma, nullable para la raíz)
- tipo (ayuntamiento / dg / departamento / centro / ...)
- activa (boolean)
- created_at, updated_at

usuario_uo_rol  (tabla pivot de adscripción)
- id
- usuario_id (FK a users)
- unidad_organizativa_id (FK a unidades_organizativas)
- rol_id (FK a roles de Spatie)
- tipo_vinculo (interno / contratado)
- fecha_inicio
- fecha_fin (nullable)
- created_at, updated_at
```

Los permisos atómicos y los roles los gestiona Spatie en sus tablas propias (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`).

### 4.3 Estrategia de permisos: mixta

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

Esta estrategia garantiza que el administrador del sistema tiene visibilidad real sobre lo que puede hacer cada rol, sin depender de documentación externa.

### 4.4 Laravel Policies para el scoping por UO

Cada recurso sensible (HistoriaSocial, Apunte, PlanDeIntervencion, Ciudadano) tiene su Policy correspondiente. La Policy evalúa en orden:

1. ¿Tiene el usuario el permiso atómico requerido para esta operación?
2. Si sí: ¿el recurso pertenece a la UO del usuario (gestión completa) o es de otra UO (consulta libre)?
3. Si es consulta libre: ¿el ciudadano pertenece a un colectivo especialmente protegido?
4. Si es colectivo protegido: ¿existe una aprobación de acceso vigente para este usuario y este ciudadano?

### 4.5 Backoffice de administración

El backoffice de usuarios y permisos debe ofrecer:

- **Gestión de UO:** crear, editar, desactivar UO; cambiar su posición en la jerarquía.
- **Gestión de usuarios:** alta, baja, modificación; adscripción a UO con rol y tipo de vínculo; historial de adscripciones.
- **Gestión de roles y permisos:** visualización de la matriz rol × permiso; modificación de permisos por rol (solo `adm_sistema`).
- **Gestión de colectivos protegidos:** alta y baja de colectivos; configuración de servicios con acceso de emergencia preautorizado.
- **Gestión de suplencias:** delegada al módulo de mensajería interna.

### 4.6 Referencias de código

*(Se completará a medida que avance la implementación)*

- Modelo de UO: `App\Models\UnidadOrganizativa`
- Pivot de adscripción: `App\Models\UsuarioUoRol`
- Seeders de permisos: `Database\Seeders\PermisosSeeder`
- Seeders de roles: `Database\Seeders\RolesSeeder`
- Policies: `App\Policies\HistoriaSocialPolicy`, `App\Policies\ApuntePolicy`, etc.
- Controladores de backoffice: `App\Http\Controllers\Admin\`

---

## 5. Decisiones pendientes

- **Definición exhaustiva del catálogo de permisos atómicos:** la lista de la sección 4.3 es orientativa. Debe revisarse y completarse antes de iniciar la implementación.
- **Módulo de mensajería interna:** gestión de suplencias y enrutamiento de solicitudes de aprobación de acceso a colectivos protegidos.
- **Rol 0 para ciudadanos:** cuando se implemente el acceso ciudadano, se definirá un rol específico con permisos de consulta sobre la propia Historia Social y operaciones básicas de autogestión.
- **Integración con directorio corporativo:** en despliegues municipales, los usuarios pueden estar ya en un directorio LDAP/Active Directory. La estrategia de sincronización o federación de identidades se definirá en fase de implantación.

---

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026.*
