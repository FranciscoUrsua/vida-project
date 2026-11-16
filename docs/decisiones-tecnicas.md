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

**Actualización (2026-11-15, fase 4):** el inicio de `consulta_basica` es ahora la búsqueda de ciudadanos (`User::destinoInicial()`). Los tests de login esperan `route('ciudadania.buscar')` y los que abren «/» siguen la redirección (`followingRedirects()`).

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

### 3.9 Bootstrap como único sistema de estilos y catálogo cerrado de clases

**Decisión (2026-09-28):** sustituye a la 3.8. Las superficies operativa y pública usan solo Bootstrap 5.3. Las clases propias se limitan a un catálogo cerrado (`config/ui-catalogo.php`: 47 clases al cerrar la migración, frente a unas 560 antes), cada una con su motivo. Tokens solo como variables Sass (`_bootstrap-overrides.scss`, `_vida-sass-tokens.scss`); sin `var(--color-*)`, colores literales ni estilos inline estructurales. `php artisan ui:auditar` lo comprueba y bloquea el CI. Colores de tema propios (`protected`) se añaden a los mapas de Bootstrap en `_bootstrap-vida.scss`. Filament y los PDF quedan fuera.

**Ampliación (2026-09-29):** las clases que se deciden en PHP salen de una `App\Support\Ui\FuenteClasesCss`, que declara todas las que puede devolver; el auditor las comprueba contra el CSS compilado y no admite en un atributo de clase ninguna otra interpolación. El color de un estado o tipo sale siempre del enum `Tono` (`tono()` en el enum del valor o `Tonos` del módulo): las tablas de clases no se escriben en las vistas. El catálogo queda en 39 clases.

**Motivo:** las normas anteriores se incumplían sin que nada lo detectara (botón invisible por una variable CSS inexistente, cuatro formas de pintar un mismo elemento). Ver `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

**BEM** sigue siendo la nomenclatura de las clases del catálogo.

---

### 3.8 CSS BEM para componentes operativos (sustituida por la 3.9)

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

---

## Sección 12 — Documentos: un único logotipo por organización

Fecha de decisión: 2026-09-24

**Contexto:** `EstiloInforme.logo_cabecera` (por UO, con herencia jerárquica) pedía una ruta de fichero escrita a mano en el formulario de Filament, sin ningún control de subida. Además, la aplicación ya tenía un logotipo de identidad visual configurable (`organizacion_configuracion` clave `logo_path`, subido desde Sistema → Configuración → «Identidad visual», usado hoy en el sidebar operativo de Intervención y Supervisión).

**Decisión:** de momento se asume que cada organización que despliega VIDA 360 tiene un único logotipo, reutilizado tanto en la identidad visual de la aplicación (sidebar) como en la cabecera de los informes PDF generados. Se elimina la duplicidad: no se gestiona un logo distinto por UO.

**Implementación:**
- El logotipo se sigue gestionando en un único sitio: Sistema → Configuración → «Identidad visual» (`ConfiguracionOrganizacionResource\Pages\ListConfiguracion`), clave `logo_path` en `organizacion_configuracion`.
- `Modules\Organizacion\Models\Configuracion::logoPathAbsoluto()` resuelve la ruta absoluta en disco (no la URL pública que usa `logoUrl()` para el navegador) porque dompdf necesita una ruta de fichero real para incrustar la imagen, no una URL.
- `ServicioGeneracionPDF::generarBorrador()` sobreescribe siempre `estilo['logo_cabecera']` con `Configuracion::logoPathAbsoluto()`, ignorando el valor que devuelva `ResolverEstiloInforme` para ese campo.
- El campo `logo_cabecera` de `EstiloInforme` (columna `estilos_informe.logo_cabecera` y su resolución jerárquica en `ResolverEstiloInforme`) **no se elimina del esquema ni del resolver** — se deja de exponer en el formulario de `EstiloInformeResource` por ser una ruta manual sin utilidad real, pero el código y los tests de herencia jerárquica sobre ese campo se mantienen intactos por si en el futuro se necesita volver a un logo por UO. Revisar esta decisión si esa necesidad aparece.

**Alternativas descartadas:** añadir un tipo `archivo` con `FileUpload` al CRUD genérico de `ConfiguracionOrganizacionResource` (clave/valor arbitrarios) para un segundo logo específico de informes — descartada porque duplicaría el logotipo ya existente y contradice la premisa de «un único logo».

**Nota técnica:** el `FileUpload` de identidad visual acepta SVG (correcto para el sidebar, renderizado por el navegador). El soporte de SVG en dompdf es limitado — si un logo SVG no se renderiza bien en el PDF, es un problema conocido de dompdf, no un bug de esta implementación. Recomendar PNG/JPG si el logo debe aparecer en informes.

---

## Sección 13 — Usuarios: `SoftDeletes` en `users` (antes no lo tenía)

Fecha de decisión: 2026-09-24

> **Sustituida el 2026-11-13 por la Sección 15** (RN-20: los usuarios y profesionales no se borran, ni siquiera con borrado lógico). Se conserva como historia.

**Contexto (bug reportado):** borrar un usuario desde `UsuarioResource` en Filament lanzaba `Illuminate\Database\QueryException`. Causa: `App\Models\User` no usaba `SoftDeletes`, así que `DeleteAction` (que llama a `$record->delete()`) intentaba un `DELETE` físico sobre `users`; en cuanto el usuario tenía una fila en `usuario_uo` (FK `usuario_id` con `onDelete('restrict')`, ver migración `create_usuario_uo_rol_table`), la BD rechazaba el borrado. El principio 4.2 (soft deletes como norma en entidades de dominio) ya cubría `users` conceptualmente — de hecho `docs/modulo-usuarios-permisos.md` §4.2 nunca listó `deleted_at` en el esquema de `usuarios`, así que esto era una omisión de implementación, no una decisión deliberada de no aplicarlo.

**Decisión:** `User` usa `SoftDeletes` como el resto de entidades de dominio. `DeleteAction::make()` en `UsuarioResource` no cambia — con `SoftDeletes` en el modelo, ya hace soft delete automáticamente (mismo patrón que `ProfesionalResource`, que tampoco tiene lógica especial de borrado).

**Implementación:**
- Migración `add_deleted_at_to_users_table` — añade `deleted_at` (no se edita la migración original `0001_01_01_000000_create_users_table.php`: el pasado es inmutable, principio 4.2).
- `User` — trait `SoftDeletes` + `@property Carbon|null $deleted_at`.
- **Efecto colateral encontrado y corregido en la misma sesión:** el índice único `users_email_unique` era un índice único normal, así que el email de un usuario con soft delete quedaba bloqueado para siempre — crear una cuenta nueva con ese email fallaba con `UniqueConstraintViolationException` aunque la cuenta original estuviera desactivada. Migración `make_users_email_unique_index_exclude_soft_deleted` sustituye el índice por uno parcial de PostgreSQL (`CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL`), y `UsuarioResource` pasa `modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at')` a la validación `unique()` del campo email para que coincida con el comportamiento de la BD.

**No se ha tocado:** ninguna otra tabla ni relación de `User`. No hay cascada de soft delete hacia `usuario_uo`/`usuario_rol`/roles de Spatie — esas filas se conservan tal cual (es exactamente lo que se pretende: el historial de adscripciones y roles de un usuario borrado sigue siendo consultable). No se ha añadido `TrashedFilter`/`RestoreAction`/`ForceDeleteAction` en Filament: ningún otro resource del proyecto los tiene tampoco (patrón existente, no una omisión).

**Bug pre-existente detectado durante la verificación, no corregido (fuera de alcance de este cambio):** `User::booted()` sobreescribe incondicionalmente `$user->name = $user->email` en el hook `creating`, incluso cuando se pasa un `name` explícito. Esto hace fallar `TF-AUTH-16` y `TF-AUTH-17` (`tests/Feature/Auth/AutenticacionTest.php`), que esperan que el nombre completo del usuario aparezca en la UI. Confirmado reproducible en `master` sin ninguno de los cambios de esta sesión. Anotado en `BACKLOG.md`.

---

## Sección 14 — Dependencias del sistema de la custodia

Fecha: 2026-11-11

**Contexto:** la tubería de entrada de documentos (`IngestaDocumentoService`) depende de binarios y servicios del sistema operativo, no de paquetes de Composer. Tras una actualización del sistema en el servidor de pruebas, AppArmor dejó de permitir a `qpdf` y `gs` leer la zona temporal y la subida de PDF falló sin dejar causa en el log (se tardó una hora en verlo). Desde el 2026-11-11 la causa queda registrada (`FalloHerramientaExterna`, ver `docs/modulo-documentos.md` §3), pero las dependencias tienen que estar documentadas y revisarse.

**Dependencias** (rutas configurables en `config/documentos.php`, claves `binarios.*`):

| Dependencia | Para qué | Paquete (Ubuntu) |
|---|---|---|
| `qpdf` | Saber si un PDF pide contraseña; forma expandida para buscar contenido activo | `qpdf` |
| `gs` (Ghostscript) | Saneado a PDF/A-2b, recompresión y medición de tinta (`inkcov`) | `ghostscript` |
| `pdfinfo` | Número de páginas | `poppler-utils` |
| `pdfsig` | Firma de informes (fase 5): comprobar que el PDF subido tiene una firma íntegra y leer su titular | `poppler-utils` |
| `pdftotext` | Firma de informes (fase 5): buscar el identificador del informe y la marca de borrador en el PDF subido | `poppler-utils` |
| `soffice` (LibreOffice headless) | Conversión de ODT y DOCX a PDF | `libreoffice-writer-nogui` |
| Imagick | Conversión de JPEG, PNG y HEIC a PDF | `php8.5-imagick` (con la política de ImageMagick que limita memoria y área) |
| clamd (ClamAV) | Antivirus por socket (`binarios.clamd_socket`) | `clamav-daemon` |

**AppArmor en el servidor de pruebas:** Ubuntu confina `qpdf` y `gs`. Sus perfiles no dejan leer la zona temporal de ingesta (`storage/app/tmp/ingesta/{uuid}/original`, sin extensión). Hay reglas locales con esta línea exacta en `/etc/apparmor.d/local/qpdf` y en `/etc/apparmor.d/local/gs`:

```
/var/www/vida-project/vida/storage/app/tmp/** rwk,
```

Tras editarlas: `sudo apparmor_parser -r /etc/apparmor.d/qpdf /etc/apparmor.d/gs`. En la máquina de desarrollo las reglas cubren también `storage/framework/testing/**` y `storage/app/tmp/**` del repositorio local.

**Revisión obligatoria tras cada actualización del sistema** (`apt upgrade`, cambio de versión de Ubuntu o de los paquetes de arriba): una actualización puede cambiar los perfiles de AppArmor o los binarios. Prueba rápida en staging: subir desde la ficha de un ciudadano **un PDF y una imagen**. Si alguno falla con «No se ha podido procesar el PDF» o «No se ha podido convertir el fichero», buscar `FalloHerramientaExterna` en `storage/logs/laravel.log`: el registro trae el binario, el código de salida y la salida de error (p. ej. `Permission denied`). Comprobar también `sudo aa-status` y `systemctl status clamav-daemon`.

---

## Sección 15 — Usuarios y profesionales: sin borrado; desactivación y salida de centro

Fecha de decisión: 2026-11-13 (addenda D5 de Tipo de Centro, RN-20 a RN-23 de `docs/modulo-tipo-centro.md`)

**Decisión:** `User` y `Profesional` no se borran. `delete()` y `forceDelete()` lanzan `LogicException`, los recursos de Filament no ofrecen borrado (`canDelete()` y `canDeleteAny()` falsos) y la acción «Desactivar» (`App\Filament\Acciones\DesactivarAction`, también en Mi equipo) los sustituye. Las adscripciones (`UsuarioUo`) tampoco se borran: se cierran con fecha de fin.

- **Desactivar un usuario** (`users.desactivado_en`, `desactivado_por_id`): no puede iniciar sesión, cierra sus adscripciones vigentes y conserva sus registros. El proveedor de autenticación `eloquent_activos` (`AppServiceProvider`, `config/auth.php`) excluye a los desactivados: vale para el login, la sesión abierta, «recordarme» y Filament.
- **Desactivar un profesional**: `activo = false` y `fecha_fin`; si tiene cuenta, se desactiva antes.
- **Salida de un centro (RN-21):** cerrar la adscripción a la UO de un centro exige que el profesional no tenga casos de referencia vigentes en él ni citas confirmadas desde hoy en sus slots de ese centro. Lo comprueba el modelo `UsuarioUo` al adelantar la fecha de fin (cualquier camino, también el repeater de Filament) con `SalidaCentroService::exigirSalidaLibre()`; el rechazo (`SalidaCentroBloqueada`) trae el recuento por centro y los enlaces a «Repartir casos» y a la gestión de ausencias. Desactivar exige lo mismo en todos los centros (RN-22). Al cerrarse, los perfiles horarios del profesional en ese centro se cierran: si no, seguiría entrando en el sorteo y el reparto.
- **El caso es del centro (RN-23):** `asignaciones_profesional.centro_id` es obligatorio. La migración `2026_11_13_100002` rellenó las 474 filas anteriores a la asignación por centro con el centro de la UO de su historia; sin centro indicado, `AsignacionProfesional` toma el de la UO de la historia.

**Datos anteriores:** las 13 cuentas y 6 profesionales con `deleted_at` (todos de prueba) se dejan como están, por decisión del desarrollador: el trait `SoftDeletes` se conserva solo para que sigan ocultos, y el índice único parcial del email no cambia.


---

## Sección 16 — Permisos: `RolesSeeder` aditivo; documentos: vías de acceso de otros módulos

Fecha de decisión: 2026-11-15 (fase 4, `docs/instrucciones-cli/2026-11-fase4-consulta-basica.md`)

**`RolesSeeder` es aditivo.** Antes sincronizaba (`syncPermissions`): re-ejecutarlo quitaba a cada rol lo que se hubiera configurado en el backoffice. Ahora solo añade los permisos que faltan (TF-CBA-29). Retirar un permiso de un rol se hace con una migración de datos, como la que quitó `plan.leer` a `supervision` en la fase 3. En la BD compartida los permisos nuevos los añade siempre una migración (`2026_11_15_100004`, `2026_11_15_130001`), porque los seeders no se ejecutan al desplegar.

**Documentos: contrato `AccesoDocumentoAdicional`.** `DocumentoPolicy` decide la lectura por tres vías: carpeta completa (`documento.ver_carpeta`), propios del día (`documento.gestionar_propios`) y las que aportan otros módulos implementando `Modules\Documentos\Contracts\AccesoDocumentoAdicional`, registradas con la etiqueta `DocumentoPolicy::ETIQUETA_ACCESOS`. Así Documentos no conoce a Prestaciones (principio 3.4 de este documento): Prestaciones registra `AccesoDocumentosTramitacion` (tramitación lee los documentos de las personas cuyas solicitudes tramita). Toda vía nueva de lectura de documentos se añade así, no en la policy.

---

## Sección 17 — Firma de informes: PDF firmado externamente, `pdfsig` y AutoFirma preparada

Fecha de decisión: 2026-11-16 (fase 5, `docs/instrucciones-cli/2026-11-fase5-informes.md`; F5-1 y F5-2 confirmadas por el desarrollador)

**Dos formas de firmar (F5-1).** (a) **Activa:** el autor descarga el «PDF para firmar» (el PDF final sin marca de borrador y con «Ref. INF-000123» en el pie de cada página), lo firma con su certificado fuera de VIDA (AutoFirma de escritorio u otra herramienta PAdES) y lo sube en el paso «Firma» del asistente. (b) **Preparada e inactiva:** AutoFirma integrada desde el navegador, tras el contrato `FirmadorInforme` (`FirmaAutoFirma`), activable con `DOCUMENTOS_AUTOFIRMA_ACTIVA=true` cuando se haya probado en puestos reales.

**Validación mínima (F5-2).** `ValidadorFirmaPdf` exige al menos una firma íntegra según `pdfsig`, el mismo número de páginas que el PDF para firmar generado en ese momento y el identificador del informe en todas las páginas, sin la marca de borrador. Guarda el titular del certificado (`informes.firmante_certificado`). **No** valida la cadena de confianza (CRL/OCSP) ni extrae el número de colegiado: pendiente (BACKLOG). `pdfsig` y `pdftotext` se ejecutan con `LC_ALL=C` (se interpreta su salida en inglés). El aviso `NSS_Init failed: security library: bad database` de `pdfsig` es inocuo: verifica la integridad sin base de datos NSS.

**El PDF firmado no se reescribe.** Entra en la custodia por el canal `generado` (sin conversión, saneado PDF/A ni recompresión, que invalidarían la firma). TF-INF-15 comprueba que el fichero custodiado es byte a byte el subido y que `pdfsig` lo sigue dando por íntegro.

**`pdfsig` en el servidor.** Es del paquete `poppler-utils` (el mismo que `pdfinfo`), instalado en local y en staging (la misma máquina). A 2026-11-16 ningún perfil de AppArmor lo confina, a diferencia de `qpdf` y `gs` (sección 14). Los ficheros de trabajo van a `storage/app/tmp/firma/{uuid}` (0700/0600) y se borran al terminar. Si una actualización lo confinara, el síntoma es «No se ha podido comprobar la firma del PDF» y `FalloHerramientaExterna` con `pdfsig` en `storage/logs/laravel.log`; la solución es una regla local como las de `qpdf` y `gs`.

**Tests sin dependencias externas.** `FirmadorSimulado` (`Modules/Documentos/tests/Support`) firma PAdES `adbe.pkcs7.detached` en PHP: actualización incremental con diccionario de firma, campo invisible y `/AcroForm`, y CMS con `openssl_cms_sign` sobre un certificado autofirmado generado en el test (no se versionan certificados). Pensado para los PDF de dompdf (xref clásica).

**Cómo probar AutoFirma integrada antes de activarla:**
1. En un puesto con AutoFirma instalada (versión 1.8 o posterior) y un certificado de empleado público (o uno de pruebas de la FNMT) en el almacén del sistema o del navegador.
2. Copiar el cliente JavaScript de AutoFirma (`autoscript.js`, del paquete de integración del Gobierno de España) en `vida/public/vendor/autofirma/autoscript.js`. No se carga desde CDN.
3. Poner `DOCUMENTOS_AUTOFIRMA_ACTIVA=true` en el `.env` del entorno de pruebas y `php artisan config:cache`.
4. Redactar un informe, ir a «Firma» y pulsar «Firmar con AutoFirma». El navegador descarga el PDF para firmar, llama a `AutoScript.sign(…, 'SHA256withRSA', 'PAdES', 'signatureSubFilter=ETSI.CAdES.detached', …)` y envía el resultado en base64 a `NuevoInformeWizard::firmarConAutoFirma()`, que pasa la misma validación que la subida.
5. Comprobar el informe firmado (`metodo_firma = autofirma_certificado_empleado_publico`), el titular guardado y que el PDF de la carpeta se abre con la firma válida en un lector PDF.
6. Si funciona en los puestos reales, activar en staging y después en producción. Mientras esté inactiva, el botón no se muestra y el endpoint responde 403 (TF-INF-19).


---

## Sección 18 — Procesos en segundo plano en el servidor: planificador por cron y cola con PM2

Fecha de decisión: 2026-11-16

**Contexto:** el servidor de staging (que es también el de desarrollo local, sección 17) no tenía ninguna entrada de cron para `schedule:run`: ni en `/etc/cron.d` ni en el crontab de `www-data` ni en el de `jupiter`. Las tareas programadas no se ejecutaban nunca, aunque la cola sí: `queue:work` corría bajo PM2. Nada de esto figuraba en el repositorio. A 2026-11-16 el planificador registra siete tareas (`php artisan schedule:list`): `EscalarAlertasVencidasJob` (cada 15 min), `expedientes:consultar` (cada hora), `denominaciones:aplicar` (00:10), `audit:purge` (03:00), `CitaCierreJob` (19:45, lunes a viernes), `SlotExpirationJob` (20:00) y `agenda:publicar-cuadrantes` (día 20, 02:30).

### Decisiones concretas

**El planificador se lanza desde el crontab de `jupiter`**, el usuario de despliegue y el mismo con el que corre la cola:

```
* * * * * cd /var/www/vida-project/vida && php artisan schedule:run >> /dev/null 2>&1
```

No se usa `www-data`, porque haría falta sudo y la cola ya corre como `jupiter`. No hay problema de permisos entre usuarios: caché, sesiones y cola están en Redis (`CACHE_STORE`, `SESSION_DRIVER` y `QUEUE_CONNECTION=redis`), así que `withoutOverlapping` y los bloqueos no escriben ficheros, y `storage/logs/laravel.log` es escribible por el grupo `www-data`.

**La cola corre con PM2 como `jupiter`** (`php artisan queue:work --sleep=3 --tries=3`). El servicio systemd `pm2-jupiter` está habilitado y la recupera tras un reinicio. Las tareas `$schedule->job(...)` solo encolan en Redis: sin el worker, el cron por sí solo no ejecuta los jobs.

**Comprobación tras montar o migrar un servidor:** `crontab -l` (como `jupiter`) debe mostrar la línea de arriba; `ps aux | grep queue:work` debe mostrar el worker; `php artisan schedule:list` lista las tareas y su próxima ejecución.

**Pendiente (BACKLOG):** el script de despliegue (`.github/workflows/ci.yml`) no ejecuta `php artisan queue:restart`. El worker sigue con el código que cargó al arrancar hasta que se reinicia a mano, y los jobs se ejecutan con código desfasado tras cada push. En producción, el cron y el worker irán en la configuración del servidor (cron.d y supervisor o systemd), no en el crontab de un usuario personal.
