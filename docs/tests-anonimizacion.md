# Tests funcionales — Anonimización
# VIDA 360

> Este documento define los tests funcionales para la capa de anonimización.
> Está pensado para ser leído por Claude CLI antes de implementar los tests.
> Antes de escribir código, leer también `docs/anonimizacion.md` y
> `docs/decisiones-tecnicas.md` secciones 7 y 8.
>
> Los tests se organizan en cuatro grupos que corresponden a las cuatro
> técnicas y los tres niveles definidos en el documento de anonimización,
> más los casos especiales del dominio.

---

## Grupo 1 — Seudonimización (Nivel 1)

### 1.1 El alias es determinista

Dado el mismo `ciudadano_id` y la misma `APP_PSEUDONYM_KEY`,
el alias generado debe ser siempre idéntico.

```
Dado ciudadano con id=4821
Cuando se genera el alias dos veces
Entonces ambos alias son iguales
```

### 1.2 El alias es opaco

El alias no debe contener ni derivar legiblemente del `ciudadano_id`,
del nombre ni de ningún otro dato del ciudadano.

```
Dado ciudadano con id=4821, nombre="María García"
Cuando se genera el alias
Entonces el alias no contiene "4821"
Y el alias no contiene "María"
Y el alias no contiene "García"
Y el alias tiene el formato "CIU-{8 caracteres hexadecimales}"
```

### 1.3 Alias distintos para ciudadanos distintos

```
Dado ciudadano_A con id=1 y ciudadano_B con id=2
Cuando se generan sus alias
Entonces alias_A != alias_B
```

### 1.4 Cambiar la clave invalida los alias

```
Dado ciudadano con id=4821
Y APP_PSEUDONYM_KEY="clave_original"
Cuando se genera alias_1
Y se cambia APP_PSEUDONYM_KEY="clave_nueva"
Y se genera alias_2
Entonces alias_1 != alias_2
```

### 1.5 La seudonimización suprime los identificadores directos

```
Dado un ciudadano con nombre, apellidos, DNI, teléfono y email
Cuando se aplica el perfil "supervision_interna"
Entonces el resultado no contiene nombre
Y el resultado no contiene apellidos
Y el resultado no contiene DNI
Y el resultado no contiene teléfono
Y el resultado no contiene email
Y el resultado contiene el alias CIU-{...}
```

### 1.6 Los datos no identificativos se preservan en Nivel 1

```
Dado un ciudadano con fecha_nacimiento, sexo, tipo_intervencion y estado_caso
Cuando se aplica el perfil "supervision_interna"
Entonces el resultado contiene fecha_nacimiento exacta
Y el resultado contiene sexo
Y el resultado contiene tipo_intervencion
Y el resultado contiene estado_caso
```

### 1.7 La tabla de correspondencias permite la reversión

```
Dado ciudadano con id=4821 y alias="CIU-4f7a3b"
Cuando se busca el ciudadano por alias="CIU-4f7a3b"
Entonces se obtiene ciudadano con id=4821
```

### 1.8 La reversión requiere el permiso ciudadano.revelar_identidad

```
Dado usuario sin permiso "ciudadano.revelar_identidad"
Cuando intenta revelar la identidad de un alias
Entonces recibe AuthorizationException
```

### 1.9 La reversión queda registrada en auditoría

```
Dado usuario con permiso "ciudadano.revelar_identidad"
Cuando revela la identidad del alias "CIU-4f7a3b" con justificación "revisión de caso"
Entonces existe un registro en auditoría con:
  - usuario_id = id del usuario
  - accion = "revelar_identidad"
  - alias = "CIU-4f7a3b"
  - ciudadano_id = 4821
  - justificacion = "revisión de caso"
  - timestamp != null
```

### 1.10 La reversión sin justificación es rechazada

```
Dado usuario con permiso "ciudadano.revelar_identidad"
Cuando intenta revelar la identidad sin proporcionar justificación
Entonces recibe ValidationException
```

---

## Grupo 2 — Generalización (Nivel 2)

### 2.1 Fecha de nacimiento — precisión "anio"

```
Dado ciudadano con fecha_nacimiento = "1943-07-15"
Cuando se aplica generalización con precision="anio"
Entonces el resultado contiene anio_nacimiento = 1943
Y el resultado no contiene fecha_nacimiento exacta
```

### 2.2 Fecha de nacimiento — precisión "decada"

```
Dado ciudadano con fecha_nacimiento = "1943-07-15"
Cuando se aplica generalización con precision="decada"
Entonces el resultado contiene rango_edad = "1940-1949"
Y el resultado no contiene fecha_nacimiento exacta
```

### 2.3 Dirección — precisión "calle_sin_numero" con dirección normalizada

```
Dado ciudadano con:
  nombre_via = "Gran Vía"
  tipo_via = "Calle"
  numero = "28"
  piso = "3"
  puerta = "izq"
  direccion_normalizada = true
Cuando se aplica generalización con precision="calle_sin_numero"
Entonces el resultado contiene nombre_via = "Gran Vía"
Y el resultado contiene tipo_via = "Calle"
Y el resultado no contiene numero
Y el resultado no contiene piso
Y el resultado no contiene puerta
```

### 2.4 Dirección — supresión completa si no está normalizada

```
Dado ciudadano con direccion_normalizada = false
Cuando se aplica cualquier perfil de Nivel 2 o superior
Entonces todos los campos de dirección están ausentes o null en el resultado
```

### 2.5 Código postal — generalización a distrito (3 primeros dígitos)

```
Dado ciudadano con codigo_postal = "28013"
Cuando se aplica generalización de código postal
Entonces el resultado contiene distrito_proxy = "280"
Y el resultado no contiene codigo_postal exacto
```

### 2.6 Identificadores directos suprimidos en Nivel 2

```
Dado ciudadano con nombre, apellidos, DNI, teléfono y email
Cuando se aplica el perfil "analitica_interna"
Entonces el resultado no contiene nombre
Y el resultado no contiene apellidos
Y el resultado no contiene DNI
Y el resultado no contiene teléfono
Y el resultado no contiene email
Y el resultado no contiene alias (a diferencia del Nivel 1)
```

### 2.7 El perfil "analitica_interna" es irreversible

El Nivel 2 no genera alias ni tabla de correspondencias.
No hay forma de llegar al ciudadano desde el dato anonimizado.

```
Dado una colección anonimizada con perfil "analitica_interna"
Cuando se intenta recuperar el ciudadano original desde cualquier campo
Entonces no existe ningún mecanismo de reversión disponible
```

---

## Grupo 3 — K-anonimato (Nivel 3)

### 3.1 Ninguna combinación de cuasi-identificadores aparece menos de K veces

```
Dado un conjunto de 100 ciudadanos con distribución variada de
  (sexo, rango_edad, nombre_via, colectivo_principal)
Y K = 5
Cuando se aplica el perfil "datos_abiertos"
Entonces para cada combinación única de (sexo, rango_edad, calle_o_distrito, colectivo)
  el número de registros con esa combinación >= 5
```

### 3.2 Generalización en cascada — de calle a distrito

```
Dado un conjunto donde 3 ciudadanas de 80-89 años viven en "Calle Pez"
Y K = 5
Cuando se aplica k-anonimato
Entonces esas 3 ciudadanas tienen calle_generalizada = distrito_proxy (no "Calle Pez")
Y se verifica que la nueva combinación con distrito_proxy aparece >= 5 veces
```

### 3.3 Generalización en cascada — de decada a supresión de colectivo

```
Dado un conjunto donde tras generalizar a distrito sigue habiendo combinaciones < K
Cuando se aplica el siguiente paso de la cascada
Entonces se suprime colectivo_principal en esos registros
Y se verifica que la nueva combinación aparece >= K veces
```

### 3.4 Supresión de registro como último recurso

```
Dado un conjunto donde un ciudadano es el único con su combinación
Y la cascada completa de generalización no consigue alcanzar K
Cuando se aplica k-anonimato
Entonces ese registro no aparece en el resultado final
```

### 3.5 El job no entrega si no supera la validación

```
Dado un job de extracción con perfil "datos_abiertos"
Cuando el proceso de k-anonimato detecta combinaciones < K tras la cascada completa
Y no puede resolverlas sin suprimir más del umbral permitido de registros
Entonces el job queda en estado "error_k_anonimato"
Y no se genera fichero de descarga
Y se genera alerta al responsable técnico
```

### 3.6 El resultado del job registra la versión del perfil aplicado

```
Dado un job de extracción con perfil "datos_abiertos" versión 3
Cuando el job completa con éxito
Entonces el registro del job contiene perfil_version = 3
Y ese dato es inmutable — no cambia si el perfil se actualiza después
```

### 3.7 Orden correcto de la cascada de generalización

La cascada debe seguir exactamente este orden sin saltarse pasos:

```
1. rango_edad: de "anio" a "decada"
2. calle: de nombre_via a distrito_proxy (3 primeros dígitos CP)
3. colectivo_principal: suprimir
4. registro completo: suprimir
```

```
Dado un conjunto donde la combinación (mujer, 1943, "Calle Mayor", PSH) aparece 2 veces
Y K = 5
Cuando se aplica k-anonimato
Entonces el sistema intenta primero generalizar rango_edad a decada (1940-1949)
Y si no alcanza K, generaliza calle a distrito_proxy
Y si no alcanza K, suprime colectivo_principal
Y si no alcanza K, suprime el registro
Y en ningún caso salta un paso de la cascada
```

### 3.8 K-anonimato no se aplica en tiempo real

```
Dado una petición síncrona a cualquier endpoint de la API
Cuando el endpoint devuelve datos anonimizados
Entonces el nivel de anonimización aplicado es máximo Nivel 2
Y nunca se invoca el proceso de k-anonimato de forma síncrona
```

---

## Grupo 4 — AnonimizadorService (contrato del servicio)

### 4.1 El servicio acepta una colección y un perfil y devuelve una colección

```
Dado una colección de N ciudadanos
Y un perfil válido
Cuando se invoca AnonimizadorService::anonimizar($coleccion, $perfil)
Entonces el resultado es una colección de N elementos (salvo supresiones de k-anonimato)
Y ningún elemento del resultado es un modelo Eloquent — son arrays o DTOs
```

### 4.2 El servicio es transparente para el código consumidor

```
Dado un controlador que obtiene ciudadanos con $query->get()
Cuando se envuelve el resultado en AnonimizadorService::anonimizar()
Entonces el controlador no necesita conocer qué perfil se aplica
Y no necesita conocer qué campos se transforman
```

### 4.3 Perfil inexistente lanza excepción

```
Dado un perfil con id="perfil_que_no_existe"
Cuando se invoca AnonimizadorService con ese perfil
Entonces lanza PerfilAnonimizacionNotFoundException
```

### 4.4 Perfil inactivo lanza excepción

```
Dado un perfil con estado=inactivo
Cuando se invoca AnonimizadorService con ese perfil
Entonces lanza PerfilAnonimizacionInactivoException
```

### 4.5 La capa de transformación actúa después del descifrado

```
Dado un ciudadano con nombre cifrado en base de datos
Cuando se aplica anonimización Nivel 1
Entonces el campo "nombre" no aparece en el resultado (suprimido o seudonimizado)
Y el proceso no expone en ningún momento el nombre cifrado en bruto
```

### 4.6 Colección vacía devuelve colección vacía sin error

```
Dado una colección vacía
Cuando se aplica cualquier perfil
Entonces el resultado es una colección vacía
Y no se lanza ninguna excepción
```

---

## Grupo 5 — Casos especiales del dominio

### 5.1 PSH — coordenadas de pernocta en lugar de dirección

Las PSH no tienen dirección postal. El campo `pernocta_lat/lng` es su
equivalente funcional y debe tratarse de forma diferente en la anonimización.

```
Dado ciudadano PSH con pernocta_lat=40.41, pernocta_lng=-3.70
Y sin campos de dirección postal
Cuando se aplica el perfil "analitica_interna"
Entonces el resultado no contiene pernocta_lat ni pernocta_lng exactos
Y el resultado contiene zona_intervencion derivada de las coordenadas
(la zona de intervención es el dato analíticamente útil)
```

### 5.2 PSH — el alias seudonimizado funciona igual que para cualquier ciudadano

```
Dado ciudadano PSH con nivel_identificacion = "no_identificado"
Y alias_operativo = "Paco el del puente"
Cuando se aplica el perfil "supervision_interna"
Entonces el resultado contiene el alias CIU-{...} (no el alias_operativo)
Y el alias_operativo no aparece en el resultado
```

### 5.3 VVG — el domicilio protegido no se incluye en extracciones de Nivel 2 y 3

El domicilio de ciudadanas VVG puede ser un recurso de acogida cuya
dirección es en sí misma información sensible que no debe aparecer
en extracciones analíticas aunque esté "anonimizada".

```
Dado ciudadana marcada como VVG
Y con domicilio registrado diferente al padrón
Cuando se aplica el perfil "analitica_interna" o "datos_abiertos"
Entonces todos los campos de dirección están suprimidos (no generalizados)
independientemente del valor de direccion_normalizada
```

### 5.4 Colectivo protegido — supresión anticipada en Nivel 3

```
Dado ciudadano perteneciente a colectivo marcado como "extra_protegido"
Cuando se aplica el perfil "datos_abiertos"
Entonces el campo colectivo_principal está suprimido desde el inicio
sin esperar a que la cascada de k-anonimato lo alcance
```

### 5.5 Ciudadano con múltiples colectivos

```
Dado ciudadano con colectivos [PSH, VVG]
Cuando se aplica cualquier perfil de Nivel 2 o superior
Entonces se aplica la protección más restrictiva de todos sus colectivos
```

---

## Grupo 6 — Perfiles (configuración y versionado)

### 6.1 Modificar un perfil incrementa su versión

```
Dado perfil "analitica_interna" en versión 3
Cuando se modifica cualquier campo de su configuración
Entonces el perfil queda en versión 4
Y la configuración de la versión 3 sigue accesible en el historial
```

### 6.2 El historial de versiones es inmutable

```
Dado perfil "analitica_interna" con historial de versiones 1, 2, 3
Cuando se modifica el perfil (versión 4)
Entonces las versiones 1, 2 y 3 no cambian
Y no existe ningún mecanismo para editarlas
```

### 6.3 Una extracción registra la versión del perfil en el momento de ejecución

```
Dado perfil "analitica_interna" en versión 3 en el momento T1
Cuando se ejecuta un job de extracción en T1
Y el perfil se actualiza a versión 4 en T2
Entonces el job ejecutado en T1 tiene registrado perfil_version = 3
Y no se modifica aunque el perfil cambie después
```

### 6.4 Los perfiles predefinidos del sistema no pueden eliminarse

```
Para cada perfil en ["supervision_interna", "analitica_interna",
                     "datos_abiertos", "investigacion_externa"]
Cuando se intenta eliminar el perfil
Entonces lanza PerfilSistemaNoEliminableException
```

### 6.5 Un perfil personalizado sin extracciones asociadas puede eliminarse

```
Dado perfil personalizado "mi_perfil" sin extracciones asociadas
Cuando se elimina el perfil
Entonces el perfil queda eliminado sin errores
```

### 6.6 Un perfil personalizado con extracciones asociadas no puede eliminarse

```
Dado perfil personalizado "mi_perfil" con al menos una extracción asociada
Cuando se intenta eliminar el perfil
Entonces lanza PerfilConExtraccionesException
```

---

## Notas para la implementación de los tests

**Factories necesarias:**
- `CiudadanoFactory` con estado `psh()`, `vvg()`, `con_direccion_normalizada()`, `sin_direccion_normalizada()`
- `PerfilAnonimizacionFactory` con estados para cada perfil predefinido
- Factory de colecciones con distribuciones controladas para los tests de k-anonimato

**Consideración sobre k-anonimato:**
Los tests del Grupo 3 requieren colecciones de tamaño controlado con distribuciones específicas. Usar datasets fijos en lugar de datos aleatorios para garantizar determinismo. Los tests de k-anonimato son los más lentos — marcarlos con `@group slow` para poder excluirlos en ciclos de desarrollo rápido.

**Consideración sobre cifrado:**
Los tests que verifican que la anonimización actúa después del descifrado (test 4.5) necesitan que el entorno de tests tenga `APP_KEY` y `APP_PSEUDONYM_KEY` configuradas en `phpunit.xml`. No usar las claves de producción.

**Tests de auditoría (1.8, 1.9, 1.10):**
Verificar contra la tabla de auditoría real, no contra mocks del sistema de log. La auditoría de revelación de identidad es un requisito funcional, no un detalle de implementación.
