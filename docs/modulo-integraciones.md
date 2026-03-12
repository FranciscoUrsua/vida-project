# Módulo: Integraciones — VIDA 360

> Este documento describe la arquitectura de integraciones de VIDA 360 con sistemas externos. El principio rector es que VIDA nunca depende directamente de un sistema externo: toda integración se realiza a través de una interfaz con adaptadores intercambiables. Esto permite desarrollar y desplegar VIDA con adaptadores simulados (mock) y sustituirlos por implementaciones reales cuando la integración esté disponible, sin modificar el código que los consume.
>
> Este documento es un inventario vivo: se irá completando a medida que se diseñen e implementen las integraciones.

---

## 1. Principios de diseño

**Desacoplamiento total:** ningún módulo funcional de VIDA conoce la implementación concreta de una integración. Solo conoce la interfaz. El módulo `Ciudadania` llama a `FuenteIdentidadInterface::consultar($nif)` sin saber si detrás hay una llamada REST al padrón municipal o un adaptador mock que devuelve datos ficticios.

**Configuración, no código:** el adaptador activo para cada integración se configura en `config/integraciones.php`. Cambiar de mock a real es una operación de configuración y despliegue, no de desarrollo.

**Mocks realistas:** los adaptadores mock no devuelven simplemente `true` o `null`. Devuelven datos ficticios pero estructuralmente correctos, que permiten desarrollar y probar los flujos completos sin depender de que los servicios externos estén disponibles.

**Fallos controlados:** toda integración puede fallar. Los adaptadores deben lanzar excepciones tipadas (`IntegracionNoDisponibleException`, `RespuestaInvalidaException`) que el código consumidor maneja explícitamente. Nunca se permite que un fallo de integración externa rompa un flujo crítico sin una alternativa degradada.

**Trazabilidad:** toda llamada a un sistema externo queda registrada en el log técnico: sistema llamado, parámetros (sin datos sensibles), resultado, tiempo de respuesta. Esto es imprescindible para auditoría y para diagnosticar problemas.

---

## 2. Configuración

```php
// config/integraciones.php
return [
    'padron'                  => \Modules\Integraciones\Adapters\Mock\PadronMockAdapter::class,
    'ciudadano360'            => \Modules\Integraciones\Adapters\Mock\Ciudadano360MockAdapter::class,
    'gestor_expedientes'      => \Modules\Integraciones\Adapters\Mock\GestorExpedientesMockAdapter::class,
    'carpeta_ciudadana'       => \Modules\Integraciones\Adapters\Mock\CarpetaCiudadanaMockAdapter::class,
    'notificaciones'          => \Modules\Integraciones\Adapters\Mock\NotificacionesMockAdapter::class,
    'directorio_corporativo'  => \Modules\Integraciones\Adapters\Mock\DirectorioCorporativoMockAdapter::class,
    'geocodificacion'         => \Modules\Integraciones\Adapters\Mock\GeocodificacionMockAdapter::class,
    'proveedor_externo'       => \Modules\Integraciones\Adapters\Mock\ProveedorExternoMockAdapter::class,
];
```

El `IntegracionesServiceProvider` lee esta configuración y vincula cada interfaz a su adaptador en el contenedor de servicios de Laravel. El código consumidor resuelve la interfaz por inyección de dependencias, nunca instancia el adaptador directamente.

---

## 3. Inventario de integraciones salientes

### 4.1 Padrón municipal

**Interfaz:** `FuenteIdentidadInterface`
**Dirección:** VIDA consulta al padrón (solo lectura)
**Propósito:** verificar empadronamiento y precargar datos de contacto en el alta de ciudadanos
**Proveedor:** Ayuntamiento de Madrid (API REST interna)
**Estado:** pendiente de implementación — mock activo

**Operaciones previstas:**
- `estaEmpadronado(string $documentoIdentidad): bool`
- `consultarDatos(string $documentoIdentidad): DatosCiudadanoPadron`
- `consultarConvivientes(string $documentoIdentidad): array`

**Restricciones de seguridad:**
- La consulta **no debe realizarse** para ciudadanas del colectivo VVG, independientemente de que se disponga del documento de identidad. No es suficiente con ignorar la respuesta: la consulta misma no debe lanzarse, para no dejar traza en los logs del padrón.
- Los datos precargados desde el padrón se marcan con `fuente: padron` y `verificado: false` hasta confirmación del profesional.
- Los convivientes importados desde el padrón se incorporan a la unidad de convivencia con `fuente: padron` y `verificado: false`.

**Mock:** devuelve datos ficticios coherentes con el formato real. Incluye un conjunto de NIFs de prueba con distintas situaciones: empadronado con convivientes, empadronado sin convivientes, no empadronado.

---

### 4.2 Ciudadano360

**Interfaz:** `FuenteIdentidadInterface` (segunda implementación de la misma interfaz)
**Dirección:** VIDA consulta a Ciudadano360 (solo lectura)
**Propósito:** precargar datos de contacto electrónico (email, teléfono) en el alta
**Proveedor:** Ayuntamiento de Madrid (aplicación interna)
**Estado:** pendiente de implementación — mock activo

**Notas de diseño:**
- Ciudadano360 no es una fuente completa: hay ciudadanos que no aparecen.
- VIDA guarda sus propios datos de contacto. Ciudadano360 es solo una fuente de precarga en el alta, no una fuente de sincronización continua.
- Los datos precargados se marcan con `fuente: ciudadano360` y `verificado: false`.

---

### 4.3 Gestor de expedientes administrativos

**Interfaz:** `GestorExpedientesInterface`
**Dirección:** bidireccional
**Propósito:** tramitación de prestaciones que generan expediente administrativo
**Proveedor:** sistema de gestión de expedientes del Ayuntamiento de Madrid
**Estado:** pendiente de implementación — mock activo

**Operaciones previstas:**
- `iniciarSolicitud(array $datos): string` — devuelve número de expediente
- `consultarEstado(string $numeroExpediente): EstadoExpediente`
- `incorporarResolucion(string $numeroExpediente): Resolucion`

**Notas de diseño:**
- VIDA no gestiona expedientes administrativos: inicia la solicitud, consulta el estado y recibe la resolución. La tramitación interna es responsabilidad del gestor externo.
- Ver principio 2.3 en `docs/principios.md`.

---

### 4.4 Carpeta ciudadana

**Interfaz:** `CarpetaCiudadanaInterface`
**Dirección:** VIDA publica hacia la carpeta ciudadana
**Propósito:** poner a disposición del ciudadano documentos generados por VIDA (informes, resoluciones, citaciones)
**Proveedor:** plataforma de carpeta ciudadana del Ayuntamiento de Madrid
**Estado:** pendiente de implementación — mock activo

**Operaciones previstas:**
- `publicarDocumento(int $ciudadanoId, Documento $documento): bool`
- `retirarDocumento(int $ciudadanoId, string $documentoId): bool`

---

### 4.5 Notificaciones y comunicaciones con ciudadanos

**Interfaz:** `NotificacionesInterface`
**Dirección:** VIDA envía hacia los canales de comunicación externos
**Propósito:** comunicaciones con el ciudadano (confirmaciones de cita, avisos, recordatorios, documentos disponibles en carpeta ciudadana)
**Proveedor:** pasarela multicanal (SMS, email, WhatsApp) — proveedor por definir
**Estado:** pendiente de implementación — mock activo

**Operaciones previstas:**
- `enviar(Notificacion $notificacion): bool`

**Notas de diseño:**
- El canal concreto (SMS, email, WhatsApp) es un atributo de la notificación, no de la interfaz.
- La multicanalidad es un derecho, no una ventaja (principio 3.11): el sistema debe intentar la comunicación por el canal preferido del ciudadano y tener alternativas configuradas.
- La videollamada se tratará como integración separada cuando se defina el proveedor.
- Las comunicaciones entre profesionales son internas al sistema y no usan esta integración. Ver módulo Mensajería Interna.

---

### 4.5b Mensajería interna entre profesionales

**Tipo:** módulo funcional interno, no integración con sistema externo
**Propósito:** comunicación entre profesionales dentro de VIDA: solicitudes de aprobación de acceso a colectivos protegidos, alertas de supervisión, coordinación entre equipos, notificaciones del sistema a usuarios
**Estado:** pendiente de diseño y desarrollo como módulo propio

**Notas de diseño:**
- Es un módulo funcional de VIDA, no una integración. No depende de servicios externos como email o WhatsApp.
- Es la infraestructura sobre la que se apoya el enrutamiento de solicitudes de aprobación de acceso a colectivos protegidos, con gestión de suplencias. Ver `docs/modulo-usuarios-permisos.md`, sección 3.
- Debe contemplar: mensajes directos entre profesionales, notificaciones automáticas del sistema (alertas, solicitudes pendientes, cambios de estado), y buzón de alertas externas (VIOMAD y similares).
- El módulo de mensajería interna será un módulo propio: `Modules/Mensajeria/`.

---

### 4.6 Directorio corporativo

**Interfaz:** `DirectorioCorporativoInterface`
**Dirección:** VIDA consulta al directorio (autenticación y datos de usuario)
**Propósito:** autenticación federada de usuarios profesionales contra LDAP/Active Directory municipal
**Proveedor:** directorio corporativo del Ayuntamiento de Madrid
**Estado:** pendiente de implementación — mock activo

**Notas de diseño:**
- En despliegues fuera del Ayuntamiento de Madrid, esta integración puede no existir y la autenticación es local.
- El adaptador mock permite el desarrollo y las pruebas con usuarios locales de Laravel.

---

### 4.7 Proveedores externos

**Interfaz:** `ProveedorExternoInterface`
**Dirección:** proveedores envían datos a VIDA (importación)
**Propósito:** recepción de datos de actividad de servicios externalizados (partes de actividad del SAD, listas de beneficiarios de talleres, etc.)
**Formato habitual:** ficheros Excel o CSV
**Estado:** pendiente de implementación — mock activo

**Operaciones previstas:**
- `importar(UploadedFile $fichero, string $tipoProveedor): ResultadoImportacion`

**Notas de diseño:**
- El proceso de importación incluye matching automático con ciudadanos existentes en VIDA y resolución manual de casos ambiguos. Ver módulo Ciudadania, sección de matching de identidades.
- El formato de los ficheros varía por proveedor: el adaptador real de cada proveedor encapsula la lógica de parsing y normalización.

---

### 4.8 Historia Social Única de la Comunidad de Madrid (HSU-CM)

**Interfaz:** por definir
**Dirección:** bidireccional
**Propósito:** interoperabilidad con el sistema HSU de la Comunidad de Madrid; sincronización del NI-HSU-CM
**Proveedor:** Comunidad de Madrid
**Estado:** pendiente de diseño y implementación — sin mock por ahora

**Notas de diseño:**
- El NI-HSU-CM se almacena como identificador complementario del ciudadano en VIDA.
- El alcance y las condiciones de esta integración dependen del convenio de interoperabilidad con la Comunidad de Madrid. Ver `docs/glosario.md`, entrada Interoperabilidad.

---

### 4.8b Geocodificación

**Interfaz:** `GeocodificacionInterface`
**Dirección:** VIDA consulta al servicio de geocodificación (solo lectura)
**Propósito:** obtener coordenadas geográficas (latitud, longitud) a partir de una dirección postal
**Proveedores posibles:** Base de Datos Ciudad del Ayuntamiento de Madrid, Google Maps Geocoding API, OpenStreetMap Nominatim, u otro servicio equivalente
**Estado:** pendiente de implementación — mock activo

**Operaciones previstas:**
- `geocodificar(string $domicilio): Coordenadas`
- `geocodificarConfianza(string $domicilio): ResultadoGeocodificacion` — devuelve coordenadas más un score de confianza en el resultado

**Notas de diseño:**
- El helper de geocodificación se invoca automáticamente al crear o actualizar cualquier entidad con domicilio: ciudadano, unidad de convivencia, centro, servicio.
- La geocodificación es un proceso asíncrono cuando se realiza en masa (importaciones). En alta individual puede ser síncrona.
- Si el servicio no está disponible o la dirección no se resuelve, las coordenadas quedan vacías sin bloquear el flujo principal.
- El proveedor activo se configura en `config/integraciones.php` como el resto de adaptadores. Cambiar de Base de Datos Ciudad a Google Maps es una operación de configuración.

**Mock:** devuelve coordenadas aleatorias dentro del rango geográfico del municipio de Madrid (latitud entre 40.30 y 40.65, longitud entre -3.83 y -3.52). Suficiente para desarrollar y probar los flujos que dependen de coordenadas.

**Nota de implementación:** la geocodificación se invoca desde varios módulos (Ciudadanía, Recursos, Organización). Debe implementarse como un trait `TieneDomicilioGeocodificable` que los modelos con domicilio pueden usar de forma consistente, en lugar de inyectar la interfaz en cada modelo individualmente. El trait se encarga de llamar al servicio y persistir las coordenadas resultantes.

---

### 4.9 Otras administraciones y organismos

Integraciones previstas pero pendientes de diseño:

| Organismo | Propósito | Estado |
|---|---|---|
| INSS / Seguridad Social | Verificación de prestaciones, IMV | Pendiente de diseño |
| Agencia Tributaria | Verificación de datos económicos | Pendiente de diseño |

---

## 4. API de VIDA 360 para sistemas externos (integraciones entrantes)

VIDA es API First: todas sus entidades disponen de una API REST completa. Esta API no es solo para el frontend — es el mecanismo por el que sistemas externos autorizados pueden consultar datos o realizar operaciones sobre VIDA.

### 5.1 Modelo de autorización en dos capas

Autorizar un sistema externo no es suficiente. El modelo de autorización para la API entrante tiene dos capas independientes y obligatorias:

**Capa 1 — Autorización del sistema cliente**
¿Está este sistema autorizado para conectarse a VIDA? Se implementa con OAuth2 client credentials o API keys por sistema. Cada sistema cliente tiene sus credenciales y sus *scopes* permitidos, que determinan qué endpoints puede llamar y qué operaciones puede realizar. La asignación de scopes a un sistema cliente es una operación de configuración con aprobación explícita — no de desarrollo.

**Capa 2 — Autorización del usuario actuante**
¿La persona en cuyo nombre actúa este sistema tiene permiso para realizar esta operación sobre estos datos? Cada petición a la API debe incluir un token que identifique al usuario actuante y acredite su rol en el sistema origen. VIDA verifica que ese rol es equivalente al rol de VIDA necesario para la operación antes de ejecutarla.

Sin esta segunda capa, un sistema cliente autorizado podría consultar o modificar cualquier dato sin trazabilidad real de quién lo pidió. El ejemplo ilustrativo: cuando la HSU-CM consulte la historia de un ciudadano, VIDA debe saber qué trabajador social de Getafe hizo la consulta, no solo que fue "el sistema HSU-CM".

**Auditoría:** toda petición a la API registra el sistema cliente, el usuario actuante, la operación, los recursos afectados y el resultado. Esta información es visible en los informes de trazabilidad del rol `supervision`.

### 5.2 Scopes de API

Los scopes definen el perímetro de lo que puede hacer cada sistema cliente. Son configurables desde el backoffice y se asignan explícitamente a cada cliente. Ejemplos:

| Scope | Descripción |
|---|---|
| `ciudadano:leer` | Consulta de datos de identificación y contacto |
| `historia:leer` | Consulta de historia social (requiere usuario actuante con rol equivalente) |
| `apunte:crear` | Creación de apuntes en historia social |
| `alerta:crear` | Creación de alertas externas pendientes de revisión profesional |
| `prestacion:consultar` | Consulta de prestaciones asignadas |
| `expediente:notificar` | Notificación de cambios de estado de expediente (solo gestor de expedientes) |

La creación de nuevos scopes requiere desarrollo. La asignación de scopes a clientes es configuración.

### 5.3 Inventario de sistemas con acceso entrante previsto

#### Gestor de expedientes administrativos (entrante)

El gestor de expedientes puede notificar a VIDA cuando hay una resolución mediante webhook. VIDA incorpora la resolución como un apunte automático en la historia, claramente marcado como `fuente: gestor_expedientes` y `tipo: resolucion_administrativa`.

El gestor **no escribe directamente** en historias sociales: solo notifica resoluciones a través del endpoint específico con scope `expediente:notificar`. VIDA es responsable de mantener la información actualizada en la historia. Esta separación preserva la integridad de la historia social y la responsabilidad profesional sobre su contenido.

#### Historia Social Única de la Comunidad de Madrid (HSU-CM, entrante)

Un trabajador social de otro municipio puede consultar la historia de un ciudadano que antes residía en Madrid. La petición llega autenticada con las credenciales del sistema HSU-CM (Capa 1) y con la identidad y rol verificado del profesional solicitante (Capa 2). VIDA verifica que el rol es equivalente a `intervencion` y que no hay restricciones de colectivo protegido antes de responder.

El alcance de datos que se expone a la HSU-CM se define en el convenio de interoperabilidad con la Comunidad de Madrid.

#### VIOMAD — Policía Municipal

VIOMAD es la aplicación de Policía Municipal para reportar situaciones de riesgo en personas vulnerables detectadas durante servicios policiales. La integración con VIDA es posible pero requiere una arquitectura asimétrica y restringida que proteja la integridad de la historia social.

**VIOMAD → VIDA (escritura limitada):**
VIOMAD puede crear un tipo específico de apunte — `alerta_externa` — mediante el scope `alerta:crear`. Este apunte no entra directamente en la historia social: se deposita en un buzón de alertas pendientes de revisión profesional. Un profesional de VIDA con rol `supervision` o `intervencion` revisa la alerta y decide si la incorpora a la historia, la desestima o la escala. Ninguna alerta externa modifica la historia sin pasar por la validación de un profesional de servicios sociales.

**VIDA → VIOMAD (lectura muy restringida):**
Si se habilita la consulta desde VIOMAD, el scope es mínimo: solo confirmar si existe una historia abierta para esa persona y el nombre del profesional de referencia asignado. Nunca el contenido de la historia. Un agente de policía no necesita conocer la situación social de una persona — solo necesita saber a quién de servicios sociales llamar.

Esta integración requiere análisis específico antes de habilitarse en producción: análisis de base legal, evaluación de impacto en protección de datos (EIPD) y aprobación explícita. Se documenta aquí para que el diseño la contemple, no para implementarla en las primeras fases.

---

## 5. Normalización de datos en entrada

Todo dato que entra a VIDA desde un sistema externo, independientemente del adaptador, pasa por una capa de normalización antes de ser utilizado. Esta capa es responsabilidad del módulo que consume la integración, no del adaptador.

Las reglas de normalización se definen en el módulo `Ciudadania` para datos de ciudadanos, y se documentan en el documento correspondiente.

---

## 6. Estructura del módulo

```
Modules/Integraciones/
├── Contracts/                          ← interfaces salientes (VIDA llama a externos)
│   ├── FuenteIdentidadInterface.php
│   ├── GestorExpedientesInterface.php
│   ├── CarpetaCiudadanaInterface.php
│   ├── NotificacionesInterface.php
│   ├── DirectorioCorporativoInterface.php
│   ├── GeocodificacionInterface.php
│   └── ProveedorExternoInterface.php
├── Adapters/
│   ├── Mock/
│   │   ├── PadronMockAdapter.php
│   │   ├── Ciudadano360MockAdapter.php
│   │   ├── GestorExpedientesMockAdapter.php
│   │   ├── CarpetaCiudadanaMockAdapter.php
│   │   ├── NotificacionesMockAdapter.php
│   │   ├── DirectorioCorporativoMockAdapter.php
│   │   ├── GeocodificacionMockAdapter.php
│   │   └── ProveedorExternoMockAdapter.php
│   └── Real/                           ← vacío, se irá poblando
├── Api/                                ← controladores de la API entrante
│   ├── Controllers/
│   │   ├── ApiCiudadanoController.php
│   │   ├── ApiHistoriaController.php
│   │   └── ApiAlertaExternaController.php
│   └── Middleware/
│       ├── AutenticacionClienteApi.php ← verifica credenciales del sistema cliente
│       └── AutenticacionUsuarioApi.php ← verifica token del usuario actuante
├── Exceptions/
│   ├── IntegracionNoDisponibleException.php
│   ├── RespuestaInvalidaException.php
│   └── ScopeInsuficienteException.php
├── DTOs/
│   ├── DatosCiudadanoPadron.php
│   ├── DatosContactoCiudadano360.php
│   ├── Coordenadas.php
│   └── ResultadoGeocodificacion.php
├── Traits/
│   └── TieneDomicilioGeocodificable.php
└── Providers/
    └── IntegracionesServiceProvider.php
```

---

## 7. Decisiones pendientes

- Definir proveedor de pasarela multicanal (notificaciones)
- Definir proveedor de videollamada
- Diseñar la integración HSU-CM: convenio de interoperabilidad, scopes expuestos y modelo de usuario actuante
- Definir estrategia de autenticación para despliegues fuera del Ayuntamiento de Madrid
- Análisis de base legal y EIPD para la integración VIOMAD antes de cualquier implementación
- Definir política completa de scopes de API: qué sistemas pueden acceder a qué datos y con qué condiciones
- Completar el inventario con integraciones que se identifiquen en fases posteriores

---

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026.*
*Este documento es un inventario vivo que se completa a medida que se diseñan e implementan las integraciones.*
