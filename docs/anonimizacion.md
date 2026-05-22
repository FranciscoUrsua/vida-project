# Anonimización y Seudonimización — VIDA 360

> Este documento describe la estrategia de anonimización y seudonimización de VIDA 360. Es una capacidad **transversal** del sistema: no pertenece a ningún módulo funcional concreto, sino que se aplica en múltiples contextos — supervisión interna, extracción analítica, datos abiertos, y cualquier otro escenario donde el acceso completo a los datos personales no sea necesario ni apropiado.
>
> Debe leerse junto a `docs/principios-vida360.md` (principios 4.10 y 4.17) y `docs/api.md` (sección 12). Las decisiones de implementación técnica están en `docs/decisiones-tecnicas.md` (sección 7).

---

## 1. Por qué la anonimización es una capacidad transversal

La tentación habitual es tratar la anonimización como un problema de la API o del módulo de analítica. En VIDA es algo más amplio.

Un supervisor no necesita ver el nombre y el DNI de los ciudadanos de un profesional para revisar la carga de trabajo de ese profesional. Un Director General no necesita identificar a personas concretas para analizar la distribución de prestaciones por distrito. Un investigador externo no necesita saber quién es nadie para estudiar patrones de exclusión social.

En todos estos casos, proporcionar los datos identificativos no añade valor — añade riesgo. La anonimización no es una restricción que se impone al sistema: es una herramienta que el sistema ofrece para que cada contexto acceda exactamente a la información que necesita, ni más ni menos.

Esto tiene además una dimensión legal: el RGPD establece el principio de minimización de datos — solo deben tratarse los datos personales estrictamente necesarios para la finalidad declarada. Ofrecer anonimización integrada en el sistema es parte del cumplimiento por diseño (*privacy by design*), no un añadido posterior.

---

## 2. Problema de reidentificación

La anonimización no consiste solo en eliminar el nombre y el DNI. Combinando atributos aparentemente inocuos — edad, sexo, código postal — es posible identificar a personas concretas. Este riesgo se conoce como **reidentificación por combinación de atributos** y es especialmente relevante en servicios sociales, donde el universo de personas en una zona geográfica pequeña puede ser muy reducido.

Ejemplo: una mujer de 83 años que vive en una calle concreta puede ser la única persona con ese perfil en ese entorno. Aunque eliminemos su nombre, sigue siendo identificable.

El objetivo de la estrategia de anonimización de VIDA no es solo eliminar identificadores directos, sino garantizar que la combinación de atributos restantes no permita la reidentificación. La precisión de los datos debe calibrarse en función de ese riesgo, no reducirse uniformemente.

---

## 3. Técnicas disponibles

VIDA emplea cuatro técnicas complementarias, aplicables en distintas combinaciones según el caso de uso.

### 3.1 Seudonimización

Sustituye los identificadores directos (nombre, DNI, teléfono, email) por un alias opaco y **consistente**: la misma persona siempre recibe el mismo alias dentro de un contexto dado. Por ejemplo, el ciudadano con id interno 4821 siempre aparece como `CIU-4f7a3b`.

**Algoritmo de generación del alias:** HMAC-SHA256 del `ciudadano_id` usando una clave `APP_PSEUDONYM_KEY` almacenada en `.env`, separada de `APP_KEY` para que una rotación de clave de aplicación no invalide los alias existentes. El alias tiene el formato `CIU-` seguido de los primeros 8 caracteres del hash en hexadecimal. El alias se computa en vuelo — no se almacena en base de datos, lo que elimina una tabla de datos sensibles.

La **tabla de correspondencias** existe para la búsqueda inversa: dado un alias que aparece en un log o en un informe, localizar al ciudadano. Se implementa como una consulta que aplica el mismo HMAC sobre todos los `ciudadano_id` activos, no como una tabla precalculada.

La seudonimización es **reversible** si se tiene la `APP_PSEUDONYM_KEY`. Por esta razón, en sentido legal sigue siendo un dato personal — la persona sigue siendo identificable para quien posee esa clave. Su uso está indicado para contextos internos donde la reversibilidad controlada tiene valor: un supervisor puede operar con aliases y, si surge una razón legítima, solicitar la revelación de identidad con trazabilidad completa del acceso.

### 3.2 Supresión

Elimina directamente los campos identificativos. Simple e irreversible. No resuelve por sí sola el problema de reidentificación — si quedan atributos cuasi-identificadores con suficiente precisión, la persona puede seguir siendo identificable.

Se usa en combinación con otras técnicas, no como técnica única.

### 3.3 Generalización

Reduce la precisión de los atributos cuasi-identificadores hasta un nivel en que dejan de ser discriminantes. No elimina el dato — lo hace menos preciso.

Niveles de generalización definidos en VIDA por campo:

| Campo | Nivel 1 de generalización | Nivel 2 de generalización |
|---|---|---|
| `fecha_nacimiento` | Año (`anio`): 1943 | Década (`decada`): "1940-1949" |
| `nombre_via` + `numero` | Calle sin número (`calle_sin_numero`): "Calle Gran Vía" | — (ver cascada k-anonimato) |
| `codigo_postal` | Distrito proxy (`distrito_proxy`): primeros 3 dígitos, "280" | — |
| `sexo` | Se mantiene — baja cardinalidad, necesario para análisis | — |

La generalización de la dirección merece atención específica: la precisión territorial es **relevante para la toma de decisiones** (distribución de recursos por zona, detección de concentraciones de necesidad). La estrategia no es degradar a barrio o distrito desde el principio, sino mantener la calle sin número como primer nivel. Solo la cascada de k-anonimato degrada más si es necesario.

**Prerequisito:** la generalización de dirección requiere que el campo `direccion_normalizada` sea `true`. Si la dirección no está normalizada por el geocoder, se aplica supresión completa en lugar de generalización. Ver `docs/geocodificacion.md`.

### 3.4 K-anonimato

Garantiza que cada combinación de atributos cuasi-identificadores aparece **al menos K veces** en el conjunto de datos. Si una combinación aparece menos de K veces, se generaliza o suprime según la cascada definida hasta que se cumpla el umbral.

Ejemplo con K=5: si en el conjunto de datos solo hay 3 mujeres en la década 1940-1949 en la "Calle Mayor", sus registros se generalizan — el campo de calle pasa a `distrito_proxy` — hasta que haya al menos 5 personas con ese perfil combinado.

**Cuasi-identificadores en VIDA:** los cuatro atributos que se evalúan en el k-anonimato son `sexo`, `rango_edad` (fecha generalizada), `calle_generalizada` (nombre de calle o distrito proxy según el nivel de generalización alcanzado) y `colectivo_principal`. La elección de estos cuatro responde a que son los atributos que, combinados, tienen mayor capacidad discriminante en el contexto de servicios sociales.

El k-anonimato no se puede aplicar registro a registro: requiere procesar el conjunto completo de datos para evaluar las combinaciones. Esto lo hace apropiado para extracciones asíncronas (jobs) pero no para respuestas síncronas de la API.

Es el estándar para publicación de microdatos en portales de datos abiertos y el que resistiría mejor una auditoría formal de protección de datos.

---

## 4. Niveles de anonimización

VIDA define tres niveles estándar según el caso de uso. Cada nivel es un **perfil predefinido** que combina las técnicas anteriores. Los perfiles son configurables desde el backoffice de API y versionados.

### Nivel 1 — Seudonimización

**Técnicas:** seudonimización de identificadores directos. El resto de campos sin modificar.

**Caso de uso:** supervisión interna, consultas de gestión donde la identidad no es relevante pero el resto de datos sí lo es con total precisión.

**Reversibilidad:** sí, con autorización explícita y trazabilidad en el log de auditoría.

**Estatus legal:** sigue siendo dato personal. Solo aplicable en contextos internos con base legal establecida.

**Ejemplo:** un supervisor revisa la lista de casos abiertos de un profesional. Ve alias, fechas, estados y tipos de intervención, pero no nombres ni documentos de identidad. Si necesita saber quién es una persona concreta por una razón justificada, el sistema registra esa solicitud y revela la identidad.

### Nivel 2 — Generalización

**Técnicas:** supresión de identificadores directos (nombre, DNI, teléfono, email) + generalización de atributos cuasi-identificadores según la tabla de la sección 3.3.

**Caso de uso:** datalake interno, analítica de gestión, reporting para responsables funcionales, sistemas de inteligencia de negocio municipales.

**Reversibilidad:** no. Una vez generalizado, el dato original no es recuperable desde el dato generalizado.

**Estatus legal:** puede considerarse dato anonimizado si la generalización es suficiente para el contexto. Requiere evaluación caso a caso.

**Ejemplo:** el datalake recibe registros con año de nacimiento (no fecha exacta), distrito (no dirección), sexo y tipo de intervención. Suficiente para análisis de distribución y tendencias; insuficiente para identificar a nadie.

### Nivel 3 — K-anonimato

**Técnicas:** supresión de identificadores directos + generalización + verificación de k-anonimato sobre el conjunto completo con K configurable (por defecto K=10 para datos abiertos).

**Caso de uso:** portal de datos abiertos, cesión de microdatos a investigadores externos, cualquier extracción que salga del ámbito municipal.

**Reversibilidad:** no.

**Estatus legal:** anonimización en sentido técnico y legal. Los datos resultantes no son datos personales a efectos del RGPD si el proceso está correctamente implementado y documentado.

**Ejemplo:** el portal de datos abiertos publica estadísticas de intervención social por barrio con distribución por rango de edad y sexo, garantizando que ninguna combinación de atributos identifica a menos de 10 personas.

---

## 5. Perfiles de anonimización

Un perfil de anonimización es la configuración concreta de cómo se aplica la anonimización a un conjunto de campos para un caso de uso específico. Cada perfil define, campo a campo, qué técnica se aplica y con qué parámetros.

Los perfiles son configurables desde el backoffice de API y versionados. Un cambio de perfil no afecta a extracciones pasadas — cada extracción registra qué versión de perfil se aplicó.

### Perfiles predefinidos

| Perfil | Nivel | Caso de uso principal |
|---|---|---|
| `supervision_interna` | 1 | Revisión de carga de trabajo y casos por supervisores |
| `analitica_interna` | 2 | Datalake municipal, reporting interno |
| `datos_abiertos` | 3 | Portal de datos abiertos del Ayuntamiento |
| `investigacion_externa` | 3 | Cesión a investigadores con convenio |

Los perfiles pueden crearse y modificarse desde el backoffice. Crear un perfil nuevo es una operación de configuración; implementar una técnica de anonimización nueva sí requiere desarrollo.

### Estructura de un perfil (JSON)

```json
{
  "id": "analitica_interna",
  "nivel": 2,
  "version": 3,
  "campos": [
    { "campo": "nombre", "tecnica": "suprimir" },
    { "campo": "apellidos", "tecnica": "suprimir" },
    { "campo": "documento_identidad", "tecnica": "suprimir" },
    { "campo": "telefono", "tecnica": "suprimir" },
    { "campo": "email", "tecnica": "suprimir" },
    { "campo": "fecha_nacimiento", "tecnica": "generalizar", "precision": "anio" },
    { "campo": "nombre_via", "tecnica": "generalizar", "precision": "calle_sin_numero",
      "prerequisito": "direccion_normalizada", "fallback": "suprimir" },
    { "campo": "numero", "tecnica": "suprimir" },
    { "campo": "codigo_postal", "tecnica": "generalizar", "precision": "distrito_proxy" },
    { "campo": "sexo", "tecnica": "mantener" }
  ],
  "k_anonimato": null
}
```

---

## 6. Implementación técnica

### 6.1 Capa de transformación

La anonimización se implementa como una **capa de transformación** que actúa después del descifrado de campos sensibles y antes de serializar la respuesta o el fichero de extracción. Es transparente para el código consumidor: los modelos Eloquent devuelven datos descifrados; la capa de anonimización los transforma antes de que salgan del sistema.

Esta arquitectura encaja directamente con el cifrado en aplicación que ya tiene VIDA (principio 4.10): los campos sensibles ya están marcados en los modelos. Esa misma marcación se usa para identificar qué campos son candidatos a anonimización, sin duplicar configuración.

La capa de transformación se implementa como un servicio independiente — `AnonimizadorService` — que recibe una colección de registros y un perfil, y devuelve la colección transformada. No tiene dependencias con módulos funcionales.

### 6.2 K-anonimato en jobs asíncronos

El k-anonimato no puede aplicarse en tiempo real porque requiere evaluar el conjunto completo. Se aplica exclusivamente en el procesamiento de jobs asíncronos de extracción (ver `docs/api.md`, sección 8):

1. El job extrae los datos en bruto (descifrados internamente).
2. Aplica la supresión y generalización del perfil registro a registro.
3. Evalúa las combinaciones de atributos cuasi-identificadores sobre el conjunto completo.
4. Generaliza o suprime los registros que no alcanzan el umbral K.
5. Valida el resultado: ninguna combinación puede aparecer menos de K veces.
6. Entrega el fichero resultante.

El paso de validación es obligatorio y bloquea la entrega si no se cumple. Un job que no supera la validación queda en estado `error_k_anonimato` y genera una alerta al responsable técnico interno.

### 6.3 Reversibilidad del Nivel 1

La reversibilidad de la seudonimización requiere una tabla de correspondencias `alias → ciudadano_id` que nunca sale del sistema. La revelación de identidad a partir de un alias es una operación privilegiada que:

- Requiere el permiso atómico `ciudadano.revelar_identidad` (no incluido en ningún rol estándar — se asigna explícitamente).
- Queda registrada en el log de auditoría con usuario, timestamp y justificación obligatoria.
- Es visible para el supervisor competente.

### 6.4 Versionado de perfiles

Cada perfil tiene un campo `version` que se incrementa con cada modificación. Las extracciones registran la versión del perfil aplicada. Esto garantiza que es posible reconstruir qué transformación se aplicó a cualquier extracción pasada.

### 6.5 Cuasi-identificadores y cascada de generalización para k-anonimato

**Cuasi-identificadores evaluados:**

Los cuatro atributos que se evalúan en la fase de k-anonimato son, por orden de capacidad discriminante:

1. `sexo`
2. `rango_edad` — fecha de nacimiento ya generalizada (`anio` o `decada`)
3. `calle_generalizada` — `nombre_via` sin número, o `distrito_proxy` si ya se generalizó más
4. `colectivo_principal` — colectivo de atención principal del ciudadano

**Cascada de generalización cuando una combinación no alcanza K:**

Cuando una combinación de cuasi-identificadores aparece menos de K veces en el conjunto, se aplica la siguiente cascada en orden estricto, sin saltarse pasos:

| Paso | Acción |
|---|---|
| 1 | `rango_edad`: pasar de precisión `anio` a `decada` |
| 2 | `calle_generalizada`: pasar de `calle_sin_numero` a `distrito_proxy` (primeros 3 dígitos del CP) |
| 3 | `colectivo_principal`: suprimir |
| 4 | Registro completo: suprimir del resultado |

El criterio del orden es minimizar la pérdida de información analítica: se generaliza primero lo que menos utilidad pierde. La edad en décadas sigue siendo útil; perder la calle es más costoso pero necesario si la densidad es insuficiente.

Tras cada paso se reevalúa el conjunto completo. Si tras el paso 4 siguen existiendo combinaciones problemáticas (lo que no debería ocurrir una vez suprimidos los registros individuales), el job falla con estado `error_k_anonimato`.

**Casos especiales en la cascada:**

- **VVG:** los campos de dirección se suprimen desde el inicio, sin pasar por la cascada. No se generaliza — se suprime directamente.
- **PSH:** no tienen `nombre_via` ni `codigo_postal`. El campo equivalente es `zona_intervencion`, que se incluye como cuasi-identificador sustituto y se suprime si la combinación no alcanza K.
- **Colectivos extra-protegidos:** `colectivo_principal` se suprime desde el inicio (paso 0), sin esperar a que la cascada lo alcance.

---

## 7. Casos de uso concretos

### Supervisión interna (Nivel 1)

Un supervisor accede a la lista de casos abiertos de los profesionales de su UO para revisar cargas de trabajo. El sistema aplica el perfil `supervision_interna`: los ciudadanos aparecen como aliases, el resto de datos (fechas, estados, tipos de intervención, centro) se muestran con precisión completa.

Si el supervisor necesita saber quién es `CIU-4f7a3b` por una razón concreta, solicita la revelación de identidad. El sistema le pide una justificación, registra el acceso y muestra el nombre. El TSR del ciudadano recibe una notificación de que su expediente ha sido consultado con revelación de identidad.

### Datalake municipal (Nivel 2)

El job nocturno de sincronización con el datalake aplica el perfil `analitica_interna`. Los registros resultantes contienen año de nacimiento, nombre de calle sin número, barrio, sexo y los campos no identificativos necesarios para el análisis. El datalake nunca recibe nombres, DNIs ni fechas exactas.

### Portal de datos abiertos (Nivel 3)

La extracción periódica para el portal aplica el perfil `datos_abiertos` con K=10. El job evalúa todas las combinaciones de rango de edad, barrio y sexo. Si alguna combinación tiene menos de 10 registros, los generaliza a nivel de distrito. El fichero resultante solo se publica si supera la validación de k-anonimato. El responsable técnico aprueba la publicación antes de que el fichero sea accesible.

---

## 8. Decisiones pendientes

- **Calibración del valor de K por perfil:** K=10 como valor por defecto para datos abiertos es conservador. Evaluar si perfiles de investigación con convenio pueden usar K=5 con salvaguardas adicionales (acuerdo de confidencialidad, acceso controlado).
- **Tratamiento de colectivos especialmente protegidos en extracciones:** definir si los registros de ciudadanos de colectivos protegidos (VVG, PSH) se excluyen de las extracciones de Nivel 2 y 3 incluso después de anonimizar, o si la anonimización es suficiente garantía.
- **Generalización de dirección para PSH:** las PSH no tienen dirección postal — tienen coordenadas de lugar de pernocta. Definir cómo se generaliza este campo en extracciones analíticas (zona, distrito de intervención, solo si hay suficiente densidad).
- **Validación formal del proceso de k-anonimato:** antes de la primera publicación en el portal de datos abiertos, someter el proceso a revisión por el Delegado de Protección de Datos.
