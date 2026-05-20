# API de VIDA 360

> **Estado: BORRADOR** — documento generado en sesión de diseño el 2026-05-20. Pendiente de revisión y refinamiento antes de incorporar al repositorio.
>
> Este documento recoge las decisiones de arquitectura y diseño de la API de VIDA 360. Debe leerse junto a `docs/principios-vida360.md` (especialmente el principio 4.5) y `docs/modulo-usuarios-permisos.md`. Antes de implementar cualquier componente de la API, este documento es de lectura obligatoria.

---

## 1. Principios generales

La API de VIDA 360 no es solo una interfaz para el frontend: es el mecanismo por el que sistemas externos autorizados consultan o actúan sobre VIDA. Esto implica que la API es un contrato con terceros — administraciones, proveedores, sistemas municipales — y como tal exige estabilidad, seguridad y trazabilidad de primer nivel.

Tres principios rigen todas las decisiones de este documento:

**Seguridad por diseño, no por añadido.** La sensibilidad de los datos tratados — datos personales de ciudadanos en situación de vulnerabilidad — exige que la seguridad sea una decisión de arquitectura, no una capa que se añade después. Cada decisión de diseño tiene en cuenta las implicaciones de seguridad.

**Compatibilidad hacia atrás como compromiso.** Los sistemas que integran con VIDA — especialmente otras administraciones — tienen ciclos de cambio largos. Cambiar una integración puede requerir lanzar un contrato que tarde un año en tramitarse. El compromiso de compatibilidad hacia atrás es de al menos 10 años para la v1. Una v2 de la API solo se crea si existe una v2 de VIDA que la justifique.

**Todo logado.** Toda operación sobre datos de ciudadanos a través de la API queda registrada con sistema cliente, usuario actuante, timestamp, operación y resultado. Esto no es negociable.

---

## 2. Estructura de facetas

La API se organiza en cuatro facetas con contratos diferenciados por audiencia. Un único sistema de autenticación y scopes por debajo; rutas, middleware y recursos independientes por faceta.

```
/api/v1/operacional   — proveedores, otras administraciones, sistemas municipales internos
/api/v1/analitica     — datalake, sistemas de inteligencia de negocio
/api/v1/publica       — portal de datos abiertos
/api/v1/ciudadano     — carpeta social ciudadana, app móvil
```

**Faceta operacional.** Acceso al modelo completo con todas las salvaguardas de seguridad. Operaciones de lectura y escritura según scopes. Siempre requiere identificación de sistema cliente y usuario actuante.

**Faceta analítica.** Solo lectura. Datos anonimizados o agregados según el perfil de anonimización configurado. Orientada a consultas bulk mediante jobs asíncronos, no a peticiones síncronas. Ver sección 8.

**Faceta pública.** Solo lectura. Entidades sin datos personales: centros, catálogo de prestaciones, actividades, estadísticas agregadas anonimizadas. Sin autenticación o con autenticación mínima.

**Faceta ciudadano.** Autenticación del propio ciudadano. Acceso exclusivo a sus propios datos. Fuera del alcance de este documento — se definirá al diseñar la carpeta social ciudadana.

---

## 3. Autenticación y autorización en dos capas

Toda petición a las facetas operacional y analítica pasa por dos capas de autorización independientes.

### 3.1 Capa 1 — Autenticación de sistema cliente

Identifica qué sistema está realizando la llamada. Se implementa mediante OAuth2 client credentials. El sistema cliente obtiene un token de acceso presentando su `client_id` y `client_secret`. Ese token tiene asociados los **scopes** que determinan qué puede hacer ese sistema y sobre qué ámbito.

### 3.2 Capa 2 — Identificación del usuario actuante

Identifica a la persona que está realizando la acción a través del sistema cliente. Esta capa es **obligatoria** para cualquier operación sobre entidades que contengan datos de ciudadanos. Es opcional para operaciones sobre entidades de catálogo o configuración (centros, actividades, catálogo de prestaciones).

El comportamiento difiere según el origen del usuario:

**Usuario interno** (profesional con `User` en VIDA): el token de la petición lleva el identificador del `User`. VIDA resuelve internamente su rol, su UO y sus permisos. El sistema cliente no necesita declarar nada sobre el rol.

**Usuario externo** (profesional de otra administración o proveedor): el usuario no existe en VIDA. El sistema cliente declara explícitamente el rol con el que actúa mediante un claim en el token. VIDA confía en ese claim porque ha autorizado a ese sistema y los scopes del cliente limitan qué roles puede declarar.

La lógica es: **los scopes OAuth2 del sistema cliente hacen dos cosas a la vez** — autorizan qué endpoints puede llamar Y acotan qué roles de usuario puede declarar. Un cliente no puede declarar un rol que no tiene concedido en sus scopes.

### 3.3 Excepciones a la Capa 2

Las siguientes entidades no contienen datos de ciudadanos y no requieren identificación de usuario actuante:

- Centros y tipos de centro
- Catálogo de prestaciones
- Tipos de actividad y actividades
- Segmentos de población
- Cualquier entidad de catálogo o configuración sin referencia a ciudadanos

---

## 4. Modelo de autorización de clientes externos

### 4.1 Scopes con granularidad contextual

La autorización de un cliente externo se define en tres dimensiones independientes:

**Operaciones permitidas** — qué puede hacer. Ejemplos: `participantes.crear`, `expediente.leer`, `prestacion.actualizar_estado`. Definibles y configurables desde el backoffice de API sin necesidad de código.

**Ámbito** — sobre qué recursos puede hacerlo. Un proveedor que gestiona un centro concreto solo puede operar sobre ese centro. Una administración solo puede actualizar expedientes cuya gestión le corresponde. El ámbito se define en el momento del alta del cliente.

**Roles declarables** — qué categorías profesionales puede presentar ese sistema para sus usuarios externos. Un sistema cliente no puede declarar `adm_sistema` si ese rol no está en su lista de roles permitidos.

### 4.2 Modelo de datos

```
clientes_api
- id
- nombre
- client_id
- activo
- fecha_expiracion
- fecha_fin_contrato (nullable — para proveedores)
- responsable_funcional_id  (FK a users — Director/Subdirector General)
- responsable_tecnico_interno_id  (FK a users)
- contacto_responsable_externo  (nombre + email)
- contacto_tecnico_externo  (nombre + email)
- created_at, updated_at

cliente_api_scopes
- cliente_id
- scope
- ambito_tipo  (centro / prestacion / ciudadano_gestionado / global)
- ambito_id  (FK nullable al recurso concreto)

cliente_api_roles_permitidos
- cliente_id
- rol  (debe ser uno de los roles definidos en el sistema)
```

---

## 5. Ciclo de vida de clientes API

### 5.1 Alta

El alta es un proceso deliberado. Requiere:

- Solicitud formal gestionada desde el backoffice de API.
- Definición explícita de scopes, ámbito y roles declarables.
- Identificación de los cuatro contactos: responsable funcional interno, responsable técnico interno, contacto responsable externo, contacto técnico externo.
- Para proveedores: fecha de fin de contrato obligatoria, alineada con la vigencia del contrato.

El responsable funcional interno (Director/Subdirector General) recibe una notificación ejecutiva cuando se da de alta un nuevo cliente bajo su ámbito.

### 5.2 Mantenimiento

Las credenciales tienen **fecha de expiración obligatoria**. La renovación es un acto consciente que fuerza la revisión periódica de si el acceso sigue siendo necesario y apropiado.

El sistema genera alertas automáticas a los 90, 30 y 7 días antes de la expiración, dirigidas a:

- Contacto técnico externo e interno: alerta técnica con instrucciones de renovación.
- Responsable funcional interno: notificación ejecutiva — "el acceso del sistema X finaliza el próximo [fecha] y requiere una decisión sobre renovación o baja".

### 5.3 Baja planificada

Para proveedores con fecha de fin de contrato registrada, el proceso de alertas culmina en una **baja automática** en esa fecha. El proveedor conoce desde el alta cuándo expira su acceso. No requiere intervención manual si la decisión es no renovar.

### 5.4 Baja de emergencia

Operación de un solo clic en el backoffice de API. Se ejecuta en segundos. Revoca el acceso de forma inmediata. La comunicación con la entidad cliente se gestiona después — primero cortar, luego hablar.

La baja, planificada o de emergencia, no elimina el historial de llamadas. El log de auditoría es inmutable.

### 5.5 Notificaciones por nivel

Cada evento del ciclo de vida genera notificaciones diferenciadas por audiencia:

| Evento | Contacto técnico | Responsable funcional |
|---|---|---|
| Alta de cliente | Alerta técnica con credenciales | Notificación ejecutiva |
| Próxima expiración (90/30/7 días) | Alerta con instrucciones | Notificación ejecutiva |
| Baja planificada | Confirmación técnica | Notificación ejecutiva |
| Baja de emergencia | Alerta inmediata | Notificación ejecutiva urgente |

---

## 6. Versionado

### 6.1 Política de versiones

La API sigue versionado semántico en la URL (`/api/v1/`). Una versión nueva solo se crea cuando existe una versión nueva de VIDA que introduce cambios incompatibles hacia atrás. El versionado de la API y el de la aplicación están alineados por diseño.

El compromiso de compatibilidad hacia atrás de la v1 es de **mínimo 10 años**. Este compromiso existe porque los consumidores externos — especialmente otras administraciones — tienen ciclos de cambio largos que pueden requerir licitaciones y contratos.

### 6.2 Qué es un breaking change

Son breaking changes y requieren nueva versión:

- Añadir un campo obligatorio en una petición.
- Eliminar o renombrar un campo en la respuesta.
- Cambiar el tipo de un campo.
- Cambiar la semántica de un endpoint aunque la firma sea igual.

No son breaking changes y pueden introducirse en v1:

- Añadir campos nuevos opcionales en la respuesta.
- Añadir endpoints nuevos.
- Añadir parámetros opcionales en la petición.
- Añadir nuevos códigos de error (sin modificar los existentes).

Los consumidores externos deben programar defensivamente: ignorar campos desconocidos en la respuesta y no asumir que la respuesta es exhaustiva.

### 6.3 Comunicación de cambios

Todo cambio, incluso los non-breaking, se notifica a los clientes registrados con antelación suficiente. El backoffice de API contiene el directorio de destinatarios — contacto técnico externo para cambios técnicos, responsable funcional para cambios con impacto en integraciones vigentes.

---

## 7. Rate limiting y cuotas

### 7.1 Escrituras

Límite bajo por defecto — del orden de decenas de peticiones por minuto por cliente. Suficiente para cualquier flujo de trabajo normal; detiene inmediatamente un bucle descontrolado por error de programación.

Las operaciones de actualización masiva periódica (por ejemplo, actualización mensual de miles de expedientes) son **excepciones explícitas** configuradas en `cliente_api_scopes` para el cliente y endpoint concretos. No son excepciones al modelo — son el modelo funcionando como se diseñó.

### 7.2 Lecturas

Las facetas operacional y ciudadano aplican siempre **paginación**. El sistema asume que detrás hay una persona esperando. Límites por petición configurables por cliente.

La faceta analítica utiliza un modelo de **jobs asíncronos** — ver sección 8. No aplica rate limiting por petición sino por volumen de jobs activos por cliente.

### 7.3 Throttling adaptativo

Los jobs de extracción masiva se encolan con baja prioridad y solo se procesan cuando las colas operacionales están por debajo de un umbral configurable. En horario laboral, los jobs se ralentizan automáticamente si el sistema está bajo carga. Preferentemente se ejecutan en horario nocturno.

---

## 8. Extracción masiva y jobs asíncronos

Para la faceta analítica y cualquier operación de lectura bulk, el modelo de interacción no es síncrono sino asíncrono:

1. El cliente realiza una **solicitud de extracción** → recibe un `job_id` y un `202 Accepted`.
2. El sistema encola el job con baja prioridad.
3. El cliente puede consultar el **estado del job** mediante el `job_id`.
4. Cuando el job finaliza, VIDA notifica al cliente mediante webhook (si está configurado) o el cliente detecta el estado `completado` en su siguiente consulta de estado.
5. El cliente descarga el resultado desde el **endpoint de descarga**, disponible durante un periodo configurable.

Este modelo elimina los timeouts en consultas largas, permite priorizar según carga del sistema, y proporciona un punto natural para aplicar la anonimización antes de entregar los datos.

---

## 9. Webhooks entrantes

Para sistemas con los que VIDA mantiene un diálogo bidireccional (ejemplo: Gestor de Expedientes Administrativos), el modelo preferido es **webhooks entrantes** en lugar de clientes mutuos.

VIDA registra una URL de callback en el sistema externo en el momento en que inicia una operación: "cuando este expediente cambie de estado, avísame aquí". El sistema externo no necesita ser cliente de la API de VIDA — solo necesita poder hacer un POST a una URL cuando ocurre un evento.

VIDA recibe el webhook en una **cola de recepción** que desacopla la disponibilidad: responde inmediatamente con `202 Accepted` y procesa el evento de forma asíncrona. Si el procesamiento interno falla, reintenta con backoff exponencial antes de generar una alerta a los responsables técnicos.

---

## 10. Idempotencia

Todas las operaciones de escritura soportan **claves de idempotencia**. El cliente genera un identificador único y lo incluye en la cabecera de la petición:

```
Idempotency-Key: 7f3a9b2c-4e1d-4f8a-9c3b-2d1e5f7a8b9c
```

VIDA guarda la clave junto al resultado durante 24 horas. Si llega la misma clave otra vez, devuelve el resultado original sin ejecutar nada. El cliente puede reintentar con total seguridad ante fallos de red.

Las operaciones de lectura son idempotentes por naturaleza y no requieren este mecanismo.

---

## 11. Contrato de errores

### 11.1 Estructura de respuesta de error

Uniforme en todas las facetas:

```json
{
  "error": {
    "codigo": "PRESTACION_DUPLICADA",
    "mensaje": "Ya existe una prestación activa de este tipo para este ciudadano",
    "traza": "err_01jx4k2m9n3p",
    "timestamp": "2026-05-20T10:23:41Z"
  }
}
```

El campo `traza` es un identificador único que aparece también en el log interno de VIDA. Permite localizar exactamente qué ocurrió sin que el cliente tenga que describir nada más.

El campo `codigo` es el que el sistema cliente usa para tomar decisiones programáticas. El campo `mensaje` es legible para humanos.

### 11.2 Códigos HTTP

| Código | Semántica | ¿Reintentar? |
|---|---|---|
| 400 | Petición malformada | No, sin corregir |
| 401 | No autenticado | No, sin credenciales válidas |
| 403 | Sin permiso para esta operación o recurso | No |
| 404 | Recurso no existe | No |
| 409 | Conflicto — duplicado o estado incompatible | No, sin resolver el conflicto |
| 422 | Petición válida pero semánticamente inválida | No, sin corregir |
| 503 | VIDA no disponible | Sí, con backoff |

Los errores 503 incluyen la cabecera `Retry-After` con el tiempo recomendado de espera.

### 11.3 Política de reintentos recomendada para clientes

Backoff exponencial con jitter: primer reintento a los 2 segundos, luego 4, luego 8, con componente aleatorio para evitar que múltiples clientes reintenten simultáneamente tras una caída. Máximo 5 reintentos antes de registrar el error y alertar al equipo técnico.

---

## 12. Anonimización

La anonimización es una capacidad transversal del sistema, no exclusiva de la API. Se aplica en tres niveles según el caso de uso. Ver `docs/anonimizacion.md` para la definición completa de perfiles y configuración.

**Nivel 1 — Seudonimización.** Para supervisión interna y consultas con restricción de identidad. Los identificadores directos (nombre, DNI) se sustituyen por un alias opaco y consistente. Reversible con autorización explícita y trazabilidad. Sigue siendo dato personal en sentido legal.

**Nivel 2 — Generalización.** Para la faceta analítica interna y el datalake. Fechas en rangos, geografía a nivel de calle sin número o con rango de portales (preservando la precisión territorial necesaria para la toma de decisiones), supresión de identificadores directos. Configurable por campo en el backoffice.

**Nivel 3 — K-anonimato.** Para datos abiertos y cualquier extracción fuera del ámbito municipal. Garantiza que cada combinación de atributos cuasi-identificadores aparece al menos K veces (K configurable, típicamente 5 o 10). Se aplica como paso de procesamiento previo en el job de extracción, no registro a registro.

Los perfiles de anonimización son configurables desde el backoffice y versionados. Cada extracción registra qué perfil se aplicó.

---

## 13. Backoffice de API

El backoffice de API es un panel Filament independiente del backoffice de configuración de la aplicación (`/admin`). Se accede desde `/api-admin` y está restringido a perfiles técnicos con autenticación reforzada (2FA obligatorio).

Funcionalidad mínima:

- **Panel de clientes:** estado de cada cliente, fecha de expiración, alertas pendientes. Los clientes próximos a expirar aparecen destacados.
- **Gestión de clientes:** alta, modificación, baja planificada y baja de emergencia. La baja de emergencia debe ser prominente y ejecutarse en un solo clic.
- **Gestión de scopes y ámbitos:** configuración de operaciones permitidas, ámbito y roles declarables por cliente.
- **Panel de errores:** errores recientes agrupados por cliente y código de error, con enlace a la traza en el log de auditoría.
- **Panel de jobs:** estado de las extracciones asíncronas activas y recientes.
- **Gestión de perfiles de anonimización:** configuración de los perfiles aplicables a cada tipo de extracción.

---

## 14. Documentación técnica y sandbox

### 14.1 Documentación generada

La documentación de la API se genera automáticamente mediante **Scribe**, que analiza rutas, form requests, tipos de retorno y PHPDoc para producir una especificación OpenAPI 3.0 con interfaz navegable. Las descripciones de negocio y los flujos end-to-end se añaden manualmente como tutoriales dentro de la propia documentación.

La documentación generada vive en `public/docs` y está orientada a integradores externos, no al equipo de desarrollo. No se crea un "manual del integrador" como documento independiente — los flujos de integración end-to-end se documentan como tutoriales en la sección correspondiente de Scribe.

Scribe genera además una **colección de Postman** que los integradores pueden importar directamente y usar contra el entorno sandbox.

### 14.2 Sandbox

El sandbox es un entorno separado con su propia base de datos poblada con datos ficticios mediante factories y seeders, y con todos los adaptadores de integraciones externas en modo mock. No hay conexión al padrón real ni a ningún sistema externo de producción.

El onboarding de un nuevo integrador consiste en: credenciales de sandbox + colección de Postman + enlace a la documentación.

El sandbox es útil pero no urgente. Se creará cuando haya el primer integrador externo real que lo necesite. Ver `BACKLOG.md`.

---

## 15. Decisiones pendientes

- Definición detallada de los scopes iniciales de la faceta operacional.
- Diseño del sistema de notificaciones del ciclo de vida de clientes (plantillas técnicas y ejecutivas).
- Estrategia concreta de k-anonimato: valor de K por defecto, proceso de validación del resultado antes de publicar. Ver `docs/anonimizacion.md` (pendiente de creación).
- Diseño detallado del panel Filament para el backoffice de API.
- Definición de los flujos end-to-end prioritarios a documentar como tutoriales (GEA como primer candidato).
- Sandbox: diferido hasta que haya un integrador externo real. Registrado en `BACKLOG.md`.
