# Geocodificación — VIDA 360

> Este documento describe la arquitectura de geocodificación de VIDA 360 y su impacto en el modelo de datos de direcciones. Afecta a los módulos de Ciudadanía, Intervención, Centros y a la capa de anonimización. Debe leerse junto a `docs/decisiones-tecnicas.md` (sección 9) y `docs/anonimizacion.md` (sección 3.3).

---

## 1. Por qué la geocodificación es infraestructura, no integración

VIDA tiene un patrón establecido para integraciones con sistemas externos: adaptadores con interfaz común y mock activo por defecto. La geocodificación podría modelarse como una integración más, pero tiene una naturaleza distinta.

Las integraciones externas (padrón, GEA, Ciudadano360) modelan sistemas con su propia lógica de negocio — VIDA los consume pero no los controla. El geocoder es diferente: es un servicio que VIDA invoca, controla y configura. El proveedor concreto que hay detrás es un detalle de infraestructura que puede cambiar sin que ningún módulo funcional lo note.

Esta distinción tiene una consecuencia práctica: el geocoder no vive en el módulo de integraciones externas. Vive en la capa de servicios de infraestructura de VIDA, con su propia interfaz, sus propios adaptadores y su propia configuración.

---

## 2. Arquitectura

### 2.1 Interfaz del servicio

Toda la aplicación interactúa con el geocoder a través de una única interfaz:

```php
interface GeocodificadorInterface
{
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion;
}
```

El resultado es siempre la misma estructura independientemente del proveedor:

```php
class ResultadoGeocodificacion
{
    public readonly bool $exito;
    public readonly ?string $tipoVia;        // Calle, Avenida, Plaza, Paseo...
    public readonly ?string $nombreVia;      // Gran Vía, Mayor, Alcalá...
    public readonly ?string $tipoNumeracion; // numero, sin_numero, km
    public readonly ?string $numero;
    public readonly ?string $portal;
    public readonly ?string $escalera;
    public readonly ?string $piso;
    public readonly ?string $puerta;
    public readonly ?string $codigoPostal;
    public readonly ?string $municipio;
    public readonly ?float  $latitud;
    public readonly ?float  $longitud;
    public readonly string  $proveedor;      // Qué adaptador procesó la petición
    public readonly ?string $errorMensaje;   // Descripción del fallo si !$exito
}
```

### 2.2 Adaptadores disponibles

| Identificador | Descripción | Estado |
|---|---|---|
| `mock` | Parser de texto libre + coordenadas aleatorias en bbox de Madrid | Implementado (v1) |
| `bdc` | Base de Datos Ciudad del Ayuntamiento de Madrid | Pendiente |
| `osm` | OpenStreetMap / Nominatim | Pendiente |
| `gmaps` | Google Maps Geocoding API | Pendiente |

El adaptador activo se configura en `configuracion_sistema` con la clave `geocoder.proveedor`. Cambiar de proveedor es una operación de backoffice — no requiere código ni despliegue.

### 2.3 Resolución del adaptador

El servicio `GeocodificadorService` actúa como fachada: lee la configuración, instancia el adaptador correspondiente y delega. Los módulos funcionales inyectan `GeocodificadorInterface` y nunca instancian adaptadores directamente.

```php
class GeocodificadorService implements GeocodificadorInterface
{
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion
    {
        $proveedor = configuracion_sistema('geocoder.proveedor', 'mock');
        $adaptador = $this->resolverAdaptador($proveedor);
        return $adaptador->normalizar($direccionTexto);
    }
}
```

---

## 3. Modelo de datos de dirección

La geocodificación define el modelo canónico de dirección en VIDA. Este modelo se aplica a todas las entidades que tienen dirección: `Ciudadano`, `Centro`, y cualquier entidad futura que la necesite.

### 3.1 Campos

| Campo | Tipo | Descripción |
|---|---|---|
| `direccion_texto` | string | Texto libre tal como lo introdujo el profesional. Siempre se conserva. |
| `direccion_normalizada` | boolean | `false` hasta que el geocoder procesa la dirección con éxito. |
| `tipo_via` | string nullable | Calle / Avenida / Plaza / Paseo / Ronda... |
| `nombre_via` | string nullable | Gran Vía / Mayor / Alcalá... |
| `tipo_numeracion` | enum nullable | `numero` / `sin_numero` / `km` |
| `numero` | string nullable | Número de portal. String para admitir "12 bis", "s/n". |
| `portal` | string nullable | |
| `escalera` | string nullable | |
| `piso` | string nullable | |
| `puerta` | string nullable | |
| `codigo_postal` | string(5) nullable | |
| `municipio` | string nullable | Madrid en producción, configurable para otros municipios. |
| `coordenadas_lat` | decimal(10,7) nullable | Latitud WGS84. |
| `coordenadas_lng` | decimal(10,7) nullable | Longitud WGS84. |
| `geocoder_proveedor` | string nullable | Qué adaptador normalizó esta dirección. Trazabilidad. |
| `origen_direccion` | enum | `profesional` / `padron` / `geocodificacion` |

### 3.2 Implementación como trait

Dado que varias entidades comparten este modelo, los campos y la lógica asociada se implementan como un trait `TieneDireccion`:

```php
trait TieneDireccion
{
    // Campos del modelo de dirección
    // Método de acceso a dirección formateada
    // Scope de búsqueda por proximidad (cuando haya coordenadas reales)
    // Método de indicación de si la dirección está normalizada
}
```

Las migraciones añaden los campos a cada tabla que los necesite — no hay tabla centralizada de direcciones, porque las direcciones son atributos de las entidades, no entidades independientes.

### 3.3 Prioridad de fuentes

Cuando la dirección proviene del padrón municipal, llega ya estructurada y con coordenadas. En ese caso no se invoca el geocoder — los campos estructurados se rellenan directamente y `origen_direccion` se establece a `padron`. El geocoder solo actúa sobre direcciones introducidas manualmente por el profesional.

| Origen | `origen_direccion` | ¿Se invoca geocoder? |
|---|---|---|
| Introducida por el profesional | `profesional` | Sí |
| Importada del padrón | `padron` | No |
| Normalizada posteriormente por job | `geocodificacion` | — (es el resultado) |

---

## 4. Flujo de normalización

### 4.1 En tiempo de guardado (síncrono)

Al guardar una entidad con dirección introducida manualmente, el observer `DireccionObserver` invoca el geocoder con un timeout de 3 segundos:

- Si el geocoder responde con éxito: se rellenan los campos estructurados, `direccion_normalizada = true`, `geocoder_proveedor` registra el adaptador usado.
- Si el geocoder falla o no responde en 3 segundos: se guarda solo `direccion_texto`, `direccion_normalizada = false`. Se encola un job de reintento. El profesional ve una advertencia pero **no se bloquea el guardado**.

La no-disponibilidad del geocoder nunca impide registrar a un ciudadano o crear un centro. La normalización es best-effort.

### 4.2 Job de reintento (asíncrono)

El job `NormalizarDireccionJob` procesa las entidades con `direccion_normalizada = false`. Se ejecuta en cola de baja prioridad. Si el reintento falla, el job se reencola con backoff exponencial hasta un máximo configurable de intentos.

Las entidades que tras N intentos siguen sin normalizar quedan marcadas con `direccion_normalizada = false` de forma permanente y aparecen en el panel de backoffice como pendientes de revisión manual.

### 4.3 Normalización masiva

Para migraciones o importaciones masivas de entidades sin normalizar, existe el comando artisan:

```bash
php artisan vida:normalizar-direcciones --entidad=ciudadano --pendientes
```

Procesa en batches con throttling para no saturar el proveedor de geocoding ni el sistema.

---

## 5. El geocoder mock (v1)

El adaptador `MockGeocodificador` es la implementación de referencia para desarrollo y pruebas. Su objetivo es que toda la lógica que consume el resultado de la geocodificación funcione correctamente sin depender de ningún servicio externo.

### 5.1 Parser de texto libre

El parser aplica una serie de reglas en orden para extraer los campos estructurados del texto libre. Las direcciones españolas tienen una estructura suficientemente predecible para que esto funcione razonablemente bien:

**Paso 1 — Tipo de vía:** busca prefijos comunes al inicio del texto, con variantes abreviadas.

```
C/ | Calle | Cl.          → Calle
Avda. | Av. | Avenida     → Avenida
Pza. | Plaza              → Plaza
Pº | Paseo                → Paseo
Ctra. | Carretera         → Carretera
Rda. | Ronda              → Ronda
(sin prefijo reconocido)  → Calle  (valor por defecto)
```

**Paso 2 — Número:** busca el primer número aislado después del nombre de la vía. Admite "s/n" y "sin número" como `tipo_numeracion = sin_numero`.

**Paso 3 — Nombre de vía:** el texto entre el tipo de vía y el número.

**Paso 4 — Resto:** piso, puerta y similares se extraen del texto restante después del número con patrones como `\d+[ºª]`, `izq|dcha|izda`, letras solas. La precisión aquí es menor — son datos de menor relevancia para la anonimización.

**Paso 5 — Código postal:** busca un grupo de 5 dígitos que empiece por 28 (Madrid).

Si el parser no puede extraer con seguridad un campo, lo deja `null` en lugar de inventar un valor incorrecto.

### 5.2 Coordenadas aleatorias

Las coordenadas se generan aleatoriamente dentro del bounding box aproximado del municipio de Madrid:

```php
$lat = 40.31 + (mt_rand() / mt_getrandmax()) * (40.53 - 40.31);
$lng = -3.83 + (mt_rand() / mt_getrandmax()) * (-3.52 - (-3.83));
```

Para otros municipios, el bbox sería configurable — un argumento más para tener el geocoder desacoplado de la configuración del municipio.

### 5.3 Lo que el mock no hace

El mock no valida que la dirección exista realmente, no calcula coordenadas precisas, y no resuelve ambigüedades entre calles con el mismo nombre. Todo esto es responsabilidad de los adaptadores reales. El mock garantiza que el contrato de `ResultadoGeocodificacion` se cumple y que los campos tienen valores razonables para desarrollo.

---

## 6. Implementación del adaptador BDC (pendiente)

La Base de Datos Ciudad del Ayuntamiento de Madrid es el geocoder de referencia para producción. Devuelve direcciones normalizadas según el callejero oficial municipal y coordenadas en el sistema de referencia ETRS89, que habrá que convertir a WGS84 para coherencia con el resto del sistema.

La implementación del adaptador `BdcGeocodificador` queda pendiente para cuando la integración con el BDC esté disponible. El contrato de la interfaz no cambia — solo se añade un nuevo adaptador y se cambia la configuración del proveedor activo.

Pendiente definir: autenticación con el BDC, endpoint de consulta, manejo de respuestas con múltiples candidatas.

---

## 7. Implicaciones en otros módulos

### Anonimización

La normalización de direcciones es prerequisito para la anonimización de Nivel 2 y 3. Un campo `nombre_via` bien extraído permite aplicar `calle_sin_numero` de forma fiable. Un campo `coordenadas_lat/lng` permite análisis de proximidad y visualización territorial sin necesidad de texto. Ver `docs/anonimizacion.md`, sección 3.3.

### Módulo de Ciudadanía

El modelo `Ciudadano` usa el trait `TieneDireccion`. La dirección introducida en el SIA (Sistema de Información y Acogida) se normaliza automáticamente. La dirección procedente del padrón se almacena directamente sin pasar por el geocoder.

**Caso especial PSH:** las personas sin hogar no tienen dirección postal. El campo `direccion_texto` puede quedar vacío; en su lugar, el modelo `Ciudadano` tiene campos específicos para coordenadas de lugar de pernocta habitual (`pernocta_lat`, `pernocta_lng`) y zona de intervención. El trait `TieneDireccion` no aplica a este campo — es un modelo diferente. Ver `docs/modulo-ciudadania.md`.

### Módulo de Centros

El modelo `Centro` usa el trait `TieneDireccion`. Las coordenadas de los centros son relevantes para funcionalidades futuras de proximidad (asignación de ciudadanos al centro más cercano, mapas de cobertura territorial).

### Funcionalidades futuras dependientes de coordenadas

Las siguientes funcionalidades planificadas dependen de tener coordenadas fiables en el modelo de dirección. Solo serán viables cuando haya un geocoder real activo (BDC u otro):

- **Análisis de concentración territorial:** detección de zonas con alta densidad de intervención para planificación de recursos.
- **Asignación geográfica de UTS:** asignar ciudadanos a la Unidad de Trabajo Social más cercana a su domicilio.
- **Mapas de cobertura:** visualización de la distribución territorial de prestaciones y centros.
- **Proximidad en búsquedas:** "centros a menos de X km de este ciudadano".

Con el geocoder mock estas funcionalidades pueden desarrollarse y testarse con datos ficticios, pero no producirán resultados geográficamente coherentes hasta que haya coordenadas reales.

---

## 8. Decisiones pendientes

- **Implementación del adaptador BDC:** bloqueante para producción con coordenadas reales. Requiere acceso al API del BDC y definición del proceso de conversión ETRS89 → WGS84.
- **Tratamiento de múltiples candidatas:** cuando el geocoder devuelve varias direcciones posibles para un texto ambiguo, definir si se toma la primera, se presenta al profesional para que elija, o se deja sin normalizar. La BDC y Google Maps tienen este comportamiento frecuentemente.
- **Bbox configurable para otros municipios:** la configuración del bounding box para el geocoder mock debería venir de `configuracion_sistema` para que sea útil fuera de Madrid.
- **Conversión y almacenamiento de coordenadas:** decidir si se almacenan como dos decimales separados (actual) o como tipo `Point` de PostGIS si se prevé uso intensivo de consultas geoespaciales. PostGIS añade complejidad pero habilita consultas de proximidad eficientes a escala.
