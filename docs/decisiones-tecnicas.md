# Decisiones Técnicas — VIDA 360

> **Propósito:** registro de decisiones técnicas concretas adoptadas durante el desarrollo: paquetes elegidos, convenciones de código, herramientas y criterios de uso. Complementa `principios-vida360.md` (el *por qué*) con el *qué exactamente* y el *cómo*.
>
> Cada entrada explica brevemente la decisión y, si hubo alternativas consideradas, por qué se descartaron. Las decisiones pendientes o en revisión se marcan como **[pendiente]**.
>
> **Relación con otros documentos:**
> - `principios-vida360.md` — marco conceptual y principios de diseño. Leer antes de este documento.
> - `glosario.md` — definiciones del dominio.
> - `documentacion-proyecto.md` — visión general y estructura del proyecto.

---

## 1. Testing

### 1.1 Framework de tests: PHPUnit (sin Pest)

**Decisión:** se usa PHPUnit nativo con el atributo `#[Test]` en lugar de Pest.

**Motivo:** el proyecto arrancó con PHPUnit y los primeros módulos implementados (Agenda) establecieron esta convención. Pest añade una capa de sintaxis que no aporta valor suficiente para justificar una migración o una base de código mixta. La legibilidad se consigue mediante nombres de método descriptivos en snake_case.

**Convención de nombres:** `snake_case` completo que describe el comportamiento esperado, sin prefijo `test_`. Ejemplo: `registra_accion_editar_con_diff_correcto_en_datos_antes_y_datos_despues`.

**Tests pendientes de implementación de servicio:** usar `$this->markTestIncomplete('Pendiente: descripción')`. No usar `$this->todo()` ni dejar el cuerpo vacío.

**Ubicación:** `Modules/{NombreModulo}/tests/Feature/` para tests de integración y comportamiento. `Modules/{NombreModulo}/tests/Unit/` para tests de lógica pura sin base de datos.

---

### 1.2 Estilo de especificación de tests en documentación

**Decisión:** los tests en los documentos de diseño funcional (sección `Tests funcionales` de cada módulo) se escriben en **estilo PHPUnit** con el atributo `#[Test]` y nombres en snake_case, no en sintaxis Pest (`it('...')`).

**Motivo:** coherencia entre especificación y código generado. Claude CLI lee los documentos y genera los ficheros PHP directamente; si la sintaxis del documento coincide con la del proyecto, la traducción es directa y sin ambigüedad.

---

### 1.3 Estructura de cada test en documentación

Cada test en los documentos de diseño sigue esta estructura:

```
[bloque de código con la firma del método]
- **Dado** — estado inicial / fixtures necesarios
- **Cuando** — acción que se ejecuta
- **Entonces** — aserciones esperadas
```

El bloque de código incluye solo la firma (no el cuerpo). El cuerpo lo genera el CLI a partir de la descripción Dado/Cuando/Entonces.

---

### 1.4 Rol mínimo en tests de autenticación: `consulta_basica`

**Decisión:** los tests de autenticación y de rutas protegidas asignan siempre el rol `consulta_basica` como mínimo a los usuarios de test.

**Motivo:** sin rol, `destino()` en `LoginController` y la ruta `/` redirigen a `sin-rol` en lugar de `inicio`, generando falsos negativos en tests que verifican comportamiento post-login. Este patrón se consolidó durante la corrección de 17 tests fallidos (2026-06-03).

**Implementación:** en el `setUp()` de la clase de test: `PermisosSeeder + RolesSeeder` sembrados; luego `$this->usuario->assignRole('consulta_basica')`.

---

## 2. Paquetes y librerías

### 2.1 Roles y permisos: `spatie/laravel-permission`

**Decisión:** gestión de roles y permisos atómicos mediante `spatie/laravel-permission`.

**Motivo:** estándar de facto en Laravel, maduro, licencia MIT, compatible con Laravel 11. La evaluación de permisos en tiempo real mediante `can()` es suficiente para los requisitos del proyecto. El scoping por UO se construye encima mediante Policies (no lo resuelve el paquete).

**Alternativas descartadas:** implementación propia — coste de mantenimiento injustificado para un problema bien resuelto por el ecosistema.

---

### 2.2 Jerarquía de UO: `staudenmeier/laravel-adjacency-list`

**Decisión:** jerarquía de Unidades Organizativas mediante Adjacency List con `staudenmeier/laravel-adjacency-list`.

**Motivo:** soporte nativo para consultas recursivas en Eloquent (ancestros, descendientes, profundidad) sobre una estructura `parent_id`. Correcto para jerarquías dinámicas donde los nodos cambian con frecuencia, a diferencia de Nested Sets que requiere recálculo de índices en cada inserción.

**Uso principal:** scope automático de UO en el visor de supervisión de Auditoría y en las Policies de autorización.

---

### 2.3 Cifrado en aplicación: [pendiente]

Paquete o estrategia para cifrado de campos sensibles de ciudadanos antes de persistencia. Ver principio técnico 4.10 en `principios-vida360.md`. Decisión diferida hasta el módulo Ciudadanía.

---

### 2.4 Tooling de calidad de código: PHPStan + Rector + Pint

**Decisión (2026-06-03):** conjunto completo de herramientas estáticas integradas en CI.

- `nunomaduro/larastan` v3.10 + `phpstan/phpstan` v2.2 — análisis estático nivel 6.
- `phpstan-baseline.neon` — 772 errores heredados (a reducir progresivamente).
- `rector/rector` v2.4 + `driftingly/rector-laravel` v2.5 — refactoring automático.
- `pint.json` — preset laravel con reglas adicionales.
- `.github/workflows/quality.yml` — CI en cada push/PR a master.

**Nota:** `nunomaduro/larastan` está marcado como abandonado upstream; el sucesor es `larastan/larastan`. Mantener el actual hasta la próxima sesión de actualización de dependencias.

---

## 3. Arquitectura y convenciones de código

### 3.1 Idioma del código: español

Todo el código, comentarios, nombres de entidades, mensajes de error y documentación están en español. Excepciones: nombres de librerías y frameworks externos, y términos técnicos sin traducción establecida (`trait`, `middleware`, `observer`, etc.).

Esto incluye: nombres de métodos, variables, clases de dominio, columnas de base de datos, claves de configuración, comandos Artisan y mensajes de validación.

---

### 3.2 PHPDoc obligatorio en clases y métodos públicos

Toda cabecera de clase y método público lleva PHPDoc con descripción, `@param`, `@return` y `@throws` donde aplique. Los comentarios explican el *por qué*, no el *qué*. El *qué* lo dice el código.

---

### 3.3 Un único punto de creación por concepto transversal

Los conceptos transversales (auditoría, notificaciones, etc.) tienen un único punto de entrada: un servicio inyectable. Ningún componente llama directamente al modelo subyacente para crear registros de auditoría — todo pasa por `AuditService`. Esta restricción es verificable mediante test arquitectural (ver test `la_purga_es_la_unica_via_legitima_de_delete_sobre_audits` en el módulo Auditoría como ejemplo del patrón).

---

### 3.4 Módulos Laravel como unidad de organización

El código se organiza en módulos Laravel (`Modules/{Nombre}/`), cada uno con su estructura interna: `Models`, `Services`, `Http`, `database/migrations`, `database/factories`, `tests`. Los módulos no se acoplan directamente entre sí — se comunican a través de interfaces o eventos.

---

### 3.5 Factories con estados nombrados

Todas las factories de modelos con estados relevantes para los tests definen esos estados mediante métodos nombrados (`->basico()`, `->urgencia()`, `->anulado()`, etc.), no mediante arrays de atributos ad hoc en cada test. Esto centraliza la definición del estado y hace los tests más legibles.

---

### 3.6 Principio "binding encuentra, policy decide"

**Decisión (2026-06-03):** el route model binding de modelos con GlobalScopes de UO (HistoriaSocial, Apunte, etc.) se realiza **sin** el scope (`withoutGlobalScopes()`), de modo que el binding siempre resuelve el modelo. La autorización de acceso la emite la Policy o el middleware de rol (403), no el binding (404).

**Motivo:** un 404 por scope es confuso para el usuario y puede ser un vector de enumeración de recursos. Un 403 explícito es correcto: el recurso existe, pero el usuario no tiene acceso.

**Implementación:** `Route::bind('historia', fn ($value) => HistoriaSocial::withoutGlobalScopes()->findOrFail($value))` en `IntervencionServiceProvider::boot()`.

---

### 3.7 Separación Filament / Livewire

**Filament** gestiona la capa de configuración y backoffice: catálogos, plantillas, parámetros del sistema, usuarios y permisos. **Livewire** gestiona las capas operativas: el trabajo diario de los profesionales con ciudadanos, planes, apuntes y agenda. Esta separación es estructural (ver Principio 3.12).

Los modelos sensibles de ciudadanos (`HistoriaSocial`, `Apunte`, `Ciudadano`, `PlanDeIntervencion`) se gestionan exclusivamente vía Livewire, no desde Resources de Filament.

---

### 3.8 CSS BEM para componentes operativos

**Decisión (2026-06-14):** los estilos de los paneles operativos (intervención, ciudadano, auditoría) usan nomenclatura BEM (Bloque__Elemento--Modificador) en lugar de estilos inline o clases utilitarias ad hoc.

**Ejemplos en producción:** `.acceso-fila--propio`, `.acceso-fila--sospechoso`, `.acceso-fila--anomalo` (panel de accesos al expediente); `.op-topbar`, `.topbar__user*` (layout operativo); `.ciudadano-layout`, `.hs-stats-bar`, `.hs-stat__val` (pantalla del ciudadano).

**Motivo:** los estilos inline dificultan el theming por municipio y el override en tests visuales. BEM hace los componentes reconocibles y reutilizables.

---

## 4. Base de datos

### 4.1 Inmutabilidad de registros de auditoría

La tabla `audits` no tiene operación de UPDATE ni DELETE en la capa de aplicación. La única excepción es la purga por retención ejecutada por `AuditPurgeCommand`. Ninguna migración puede añadir operaciones de modificación sobre esta tabla sin documentar la excepción aquí.

**Nota técnica (2026-06-14):** la purga usa el query builder directamente (`DB::table('audits')->where(...)->delete()`) para evitar la `LogicException` que lanza el método `delete()` de instancia del modelo `Audit`. `const UPDATED_AT = null` en el modelo.

---

### 4.2 Soft deletes como norma en entidades de dominio

Las entidades principales de dominio (ciudadanos, planes, apuntes, etc.) usan `SoftDeletes`. El borrado físico solo se aplica en datos auxiliares o de catálogo sin valor histórico.

---

### 4.3 Búsqueda sobre campos cifrados: carga en PHP (temporal)

**Decisión (2026-06-01):** mientras no exista un índice hash determinista, la búsqueda por nombre en ciudadanos cifrados carga ≤ 500 registros y filtra en PHP (`BuscarCiudadanoPage`). Aceptable para el volumen actual de desarrollo; revisar en producción.

**TODO documentado:** reemplazar por índice hash determinista cuando el módulo Ciudadanía esté completo.

---

## 5. Gestión del proyecto

### 5.1 Mensajes de commit

Formato: `[Módulo] verbo en imperativo + descripción breve`. Ejemplos:

```
[Auditoría] Añadir AuditService con resolución de ciudadano_id
[Agenda] Corregir scope de slots en días festivos
[Infra] Actualizar phpunit.xml con suite de Auditoría
```

Los commits de documentación usan `[Docs]` como prefijo.

---

### 5.2 CHANGELOG separado por mes

**Decisión (junio 2026):** a partir de julio 2026, el CHANGELOG se mantiene en archivos mensuales (`CHANGELOG-MMYYYY.md`). El archivo del mes en curso es el único que se edita activamente. Los anteriores son de solo lectura.

- `CHANGELOG-052026.md` — mayo 2026
- `CHANGELOG-062026.md` — junio 2026 (en curso)

---

## 6. Control de cambios generados por IA

Este proyecto usa Claude CLI para generar código. Las herramientas de IA pueden tomar decisiones de implementación que no afloran a menos que se revise el código generado línea a línea. Las siguientes prácticas tienen como objetivo mantener esas decisiones visibles y auditables, sin depender de que alguien lea todo el código producido en cada sesión.

---

### 6.1 CHANGELOG

Claude CLI tiene instrucción explícita de hacer una entrada en el CHANGELOG al final de cada tarea de codificación, describiendo los cambios realizados: ficheros creados o modificados, decisiones tomadas, y cualquier desviación respecto a la especificación original.

**Formato:** [Keep a Changelog](https://keepachangelog.com/es/1.0.0/). Cada entrada bajo la versión o fecha correspondiente, con subsecciones `Añadido`, `Modificado`, `Corregido` según aplique.

**Propósito:** el CHANGELOG no es solo documentación histórica para futuros colaboradores — es el mecanismo principal para que el equipo sepa qué ha hecho la IA en cada sesión sin tener que auditar el diff completo. Una entrada ausente o incompleta es una señal de que algo no se ha registrado correctamente.

**Ubicación a partir de julio 2026:** `CHANGELOG-MMYYYY.md` en la raíz del repositorio (ver sección 5.2).

---

### 6.2 Registro de instrucciones a CLI

Cada conjunto de instrucciones enviado a Claude CLI se guarda en `docs/instrucciones-cli/` como fichero de texto, con nombre que incluye fecha y módulo. Ejemplos:

```
docs/instrucciones-cli/2024-11-agenda-tests-funcionales.md
docs/instrucciones-cli/2024-11-auditoria-implementacion-service.md
```

**Propósito:** tener la referencia exacta de qué se le pidió a la IA en cada ocasión. Junto con el CHANGELOG, permite reconstruir la cadena: *instrucción recibida → código generado → cambios registrados*. Si el código generado no corresponde a lo que se pidió, la discrepancia es detectable sin ambigüedad.

**Qué incluir en cada fichero:** el prompt o instrucciones tal como se enviaron, sin editar. Si hubo iteraciones o correcciones en la misma sesión, incluirlas todas en orden.

---

## 7. Anonimización y seudonimización

Fecha de decisión: 2026-05-21

Contexto: diseño de la API y de las capacidades de extracción analítica y publicación de datos abiertos.

**Decisión**

La anonimización se implementa como una capa de transformación independiente — AnonimizadorService — que actúa después del descifrado de campos sensibles y antes de serializar la respuesta o el fichero de extracción. Es transparente para el código consumidor.

Se definen tres niveles técnicos: seudonimización, generalización y k-anonimato. Su aplicación se configura mediante perfiles versionados gestionados desde el backoffice de API. Ver docs/anonimizacion.md para la especificación completa.

---

### Decisiones técnicas concretas

**Seudonimización:** alias opaco y consistente por ciudadano (CIU-{hash}). La tabla de correspondencias alias → ciudadano_id nunca sale del sistema. La reversión requiere el permiso atómico ciudadano.revelar_identidad, queda registrada en auditoría con justificación obligatoria.

**Generalización de dirección:** se mantiene a nivel de nombre de calle sin número de portal, o con rango de portales si la calle tiene suficiente densidad de población. No se degrada a barrio o distrito salvo que sea necesario para el k-anonimato. Justificación: la precisión territorial es relevante para la toma de decisiones de recursos.

**K-anonimato:** se aplica exclusivamente en jobs asíncronos de extracción, nunca en tiempo real. Valor de K configurable por perfil; K=10 por defecto para datos abiertos. El job no entrega el fichero si no supera la validación. Un job que falla la validación queda en estado error_k_anonimato y genera alerta al responsable técnico.

**Integración con cifrado existente:** los campos marcados para cifrado en los modelos (principio 4.10) son la fuente de verdad para identificar qué campos son candidatos a anonimización. No se duplica configuración.

**Perfiles versionados:** cada perfil tiene un campo version. Las extracciones registran la versión aplicada. Es posible reconstruir qué transformación se aplicó a cualquier extracción pasada.

---

### Alternativas descartadas

**Anonimización a nivel de base de datos** (vistas materializadas anonimizadas): descartada porque no permite la reversibilidad controlada del Nivel 1 ni la flexibilidad de perfiles por caso de uso.
Anonimización en el datalake (transformar los datos al llegar al datalake, no al salir de VIDA): descartada porque implica que datos personales completos viajan hasta el datalake. La transformación debe ocurrir antes de que el dato abandone el perímetro de VIDA.

---

**Decisiones pendientes**

Ver sección 8 de docs/anonimizacion.md.

---

## Sección 8 — Ubicación de los modelos de infraestructura de API

Fecha de decisión: 2026-05-21
Contexto: durante el diseño del backoffice de API surgió la pregunta de si los modelos relacionados con la gestión de clientes API y perfiles de anonimización debían vivir en un módulo nwidart (Modules/Api/) o en el núcleo de la aplicación (app/Models/Api/).

---

### Decisión
Los modelos de infraestructura de API — ClienteApi, ClienteApiScope, ClienteApiRolPermitido, PerfilAnonimizacion y sus relaciones — viven en app/Models/Api/, con sus servicios asociados en app/Services/Api/. El ApiAdminPanelProvider vive en app/Providers/Filament/, junto al AdminPanelProvider existente.

No se crea un módulo nwidart para la API.

---

### Justificación

**Naturaleza transversal, no de dominio.** Los módulos nwidart existentes (Ciudadanía, Intervención, Organización, Centros) encapsulan dominios funcionales con entidades, flujos y pantallas propios. Los modelos de API son infraestructura de integración transversal — los necesita el middleware de autenticación que se ejecuta en cada request de cualquier módulo, no un dominio funcional concreto. Esta naturaleza es análoga a User o Audit, que también viven en el núcleo por ser transversales.

**Coherencia con decisiones existentes.** El CHANGELOG documenta fricciones reales con namespaces y autoload de módulos nwidart, especialmente cuando un módulo necesita ser conocido por otros módulos o por el núcleo. Los modelos de API estarían en esa situación por definición. La misma lógica que llevó a centralizar los Resources de Filament en app/Filament/Resources/ aplica aquí.

**Simplicidad.** No hay lógica de dominio compleja que justifique el overhead de un módulo nwidart completo — provider, registro en bootstrap, estructura de carpetas duplicada. La ganancia en organización no compensa la fricción añadida.

---

### Estructura resultante

```
app/
  Models/
    Api/
      ClienteApi.php
      ClienteApiScope.php
      ClienteApiRolPermitido.php
      PerfilAnonimizacion.php
  Services/
    Api/
      AnonimizadorService.php
      GestorClientesApi.php
      ValidadorKAnonimato.php
  Filament/
    Resources/
      ApiAdmin/
        ClienteApiResource.php
        PerfilAnonimizacionResource.php
  Providers/
    Filament/
      AdminPanelProvider.php
      ApiAdminPanelProvider.php
```

---

## Sección 9 — Geocodificación y modelo canónico de dirección

Fecha de decisión: 2026-05-21

**Contexto:** el modelo de dirección en texto libre presenta limitaciones para anonimización, análisis territorial y cualquier funcionalidad futura que dependa de coordenadas. Se decide adoptar un modelo estructurado con normalización automática mediante un servicio de geocodificación desacoplado del proveedor concreto.

**Decisión**
La dirección se almacena en dos representaciones simultáneas: el texto libre original (direccion_texto, siempre conservado) y los campos estructurados extraídos por el geocoder. La normalización es best-effort y nunca bloquea el guardado.

El geocoder es un servicio de infraestructura interno — no una integración externa — con interfaz GeocodificadorInterface y adaptadores intercambiables. El proveedor activo se configura en configuracion_sistema con la clave geocoder.proveedor. Cambiar de proveedor es una operación de backoffice sin necesidad de código ni despliegue.

Ver docs/geocodificacion.md para la especificación completa.

### Decisiones técnicas concretas

**Modelo de dirección como trait.** Los campos estructurados se implementan en el trait TieneDireccion, aplicable a cualquier entidad que tenga dirección (Ciudadano, Centro, entidades futuras). No hay tabla centralizada de direcciones — son atributos de las entidades.

**Campos del modelo canónico:** direccion_texto, direccion_normalizada (boolean), tipo_via, nombre_via, tipo_numeracion (enum: numero/sin_numero/km), numero, portal, escalera, piso, puerta, codigo_postal, municipio, coordenadas_lat, coordenadas_lng, geocoder_proveedor, origen_direccion (enum: profesional/padron/geocodificacion).

**Flujo de normalización.** El observer DireccionObserver invoca el geocoder al guardar con timeout de 3 segundos. Si falla, guarda solo direccion_texto con direccion_normalizada = false y encola un job de reintento. El job NormalizarDireccionJob procesa pendientes en cola de baja prioridad con backoff exponencial.

**Prioridad de fuentes.** Las direcciones procedentes del padrón llegan ya estructuradas y no pasan por el geocoder — origen_direccion = padron. El geocoder solo actúa sobre direcciones introducidas manualmente.

**Geocoder mock para v1.** El adaptador MockGeocodificador implementa un parser de texto libre con reglas para extraer tipo de vía, nombre y número, más coordenadas aleatorias dentro del bbox de Madrid (lat: 40.31–40.53, lng: -3.83– -3.52). Permite desarrollar y testear toda la lógica dependiente de coordenadas sin servicios externos. Ver docs/geocodificacion.md, sección 5.

---

## Sección 10 — Auditoría: modelo `Audit` inmutable e `AuditService` como punto único

Fecha de decisión: 2026-06-14

**Contexto:** implementación completa del módulo Auditoría. Decisiones de diseño que no estaban cubiertas por los principios generales.

### Decisiones concretas

**`Audit` como modelo inmutable.** `update()` y `delete()` por instancia lanzan `LogicException`. La purga usa el query builder directamente para evitar esta excepción. `const UPDATED_AT = null`.

**`AuditService::contextoBase()` usa `request()->path()` en lugar de `request()->hasSession()`.** En el entorno de test con PHPUnit, la sesión no está iniciada aunque el request sea HTTP real. `path()` es siempre accesible.

**`getCiudadanoId()` en modelos con scopes de UO usa `withoutGlobalScopes()`.** Los modelos Apunte, PlanDeIntervencion, Valoracion y Entrevista tienen AmbitoUoScope activo. La resolución de ciudadano_id para auditoría es una operación interna del sistema, no un acceso de datos de usuario, y debe funcionar independientemente del contexto de UO del usuario autenticado.

**El supervisor de auditoría requiere adscripción al árbol de UOs (comportamiento más restrictivo).** Cualquier usuario con rol `supervision` puede ver accesos a expedientes, pero solo los de su subtree de UO. Más seguro que el comportamiento anterior (supervision veía todos los accesos).

**`uoSubtreeIds()` devuelve `array`, no `Collection`.** Por ello se usa `in_array()` en vez de `->contains()` en las verificaciones de auditoría.

---

## Sección 11 — CI/CD: deploy sin estado local corrupto

Fecha de decisión: 2026-06-12

**Contexto:** bug en producción donde `view:cache` reportaba éxito pero servía compiled views desactualizadas.

### Decisiones concretas

**`git fetch origin && git reset --hard origin/master`** en lugar de `git pull origin master` para evitar fallos por cambios locales en el servidor (artefactos de npm build generados por `www-data`).

**`set -e` al inicio del script de deploy.** Abortar ante cualquier error en lugar de continuar silenciosamente.

**`php artisan optimize:clear` antes de `config:cache / route:cache / view:cache`.** Elimina archivos corruptos o inaccesibles de deploys anteriores.

**`chown/chmod` sobre `storage/` y `bootstrap/cache/` antes de los comandos `artisan`.** Garantiza que el usuario de despliegue (`jupiter`) pueda escribir en directorios donde `www-data` puede haber generado archivos en requests previas.
