# Tests funcionales — Tipo de Centro

> Incluye la addenda D1 (11 nov 2026): `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (D1, D2, D4 y D5).

TF-TCE-01 a TF-TCE-60. Diseño funcional: `docs/modulo-tipo-centro.md`. Instrucciones de implementación: `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md`.

Especificaciones de comportamiento, no código. Claude CLI implementa cada test siguiendo los patrones del proyecto y rellena la tabla de estado del final.

## Convenciones

- Framework: PHPUnit con atributo `#[Test]`. No usar Pest.
- Base de datos: PostgreSQL (`vida_testing`). No usar SQLite.
- Ubicación: `Modules/Centro/tests/Feature/TipoCentro/` (`TipoCentroTest`, `CentroTipoTest`, `HorarioEfectivoTest`, `CalendarioEfectivoTest`, `CatalogoEfectivoTest`, `ConfiguracionAsignacionTest`, `PermisosTipoCentroTest`, `MigracionTipoCentroTest`). Los que tocan generación de slots, en `Modules/Agenda/tests/Feature/TipoCentro`/; los de tipos de plan, en `Modules/Intervencion/tests/Feature/TipoCentro`/.
- Patrón: Dado / Cuando / Entonces.
- Negativo obligatorio: cada test de restricción debe fallar si se elimina la protección. Compruébalo al menos una vez por grupo y anótalo en el CHANGELOG. Los marcados [negativo] son los críticos.
- Fechas: `Carbon::setTestNow('2026-11-02')` (lunes) salvo que el test diga otra cosa.
- Rama de asignación: los tests del grupo F que dependen de servicios de asignación (TF-TCE-28 y TF-TCE-29) se marcan `markTestSkipped` con motivo explícito si la fase de asignación no está implementada (rama B). TF-TCE-30 solo aplica en rama A.

## Actores y datos reutilizados

Trait `TipoCentroTestSetup`:

- `$admin` — administrador del sistema.
- `$uoPrimaria`, `$uoIgualdad` — UO funcionales.
- `$tipoCss` — código `css_general`, denominación "Centro de Servicios Sociales" / "CSS", UO `$uoPrimaria`, `inscripcion_libre` = false, sorteo, horario L-V 9:00-14:00 (atención 9:00-14:00) vigente desde 2026-01-01, segmentos `poblacion_general`, tipo de plan PISO.
- `$tipoCiam` — código `ciam`, "Centro Integral de Atención a la Mujer" / "CIAM", UO `$uoIgualdad`, `inscripcion_libre` = true, `libre_eleccion`, horario L-V 9:00-19:00, segmento vg, tipo de plan PIA.
- `$cssNorte`, `$cssSur` — tipo `$tipoCss`, heredan horario.
- `$ciamVallecas` — tipo `$tipoCiam`, UO administrativa: Junta de Puente de Vallecas.
- `$directoraNorte` — directora de `$cssNorte`. `$directoraSur` — directora de `$cssSur`.
- `$p1`, `$p2` — prestaciones en `$tipoCss`. `$p3` — prestación en `$tipoCiam`.
- Fichas de valoración `$fichaVivienda` y `$fichaVG`.

Estructura organizativa (addenda D1, §4.1):

- `$uoArea`, con `$uoPrimaria` y `$uoIgualdad` como hijas orgánicas; `$uoJuntaNorte` y `$uoJuntaVallecas` (UO orgánicas, sin centro).
- Cada centro con **su UO instrumental**: `$uoCssNorte`, `$uoCssSur` (hijas orgánicas de `$uoJuntaNorte`) y `$uoCiamVallecas` (hija orgánica de `$uoJuntaVallecas`).
- `$ciamUsera` (tipo `$tipoCiam`, UO `$uoCiamUsera`, hija de otra junta).
- Usuarios: `$coordIgualdad` (`supervision`, adscrito a `$uoIgualdad`); `$jefaJuntaVallecas` (`supervision`, adscrita a `$uoJuntaVallecas`); `$coordPrimaria` (`supervision`, `$uoPrimaria`); `$psicologa` (`intervencion`, adscrita a `$uoCiamVallecas` y `$uoCiamUsera`, perfil horario L-M en Vallecas y X-J en Usera); `$tecnicaArea` (`intervencion`, adscrita a `$uoArea`, sin centro); `$tecnicaIgualdad` (`intervencion`, adscrita a `$uoIgualdad`).

## Grupo A — Tipo de centro

### TF-TCE-01 — Crear un tipo

- Dado `$admin`.
- Cuando crea un tipo con código, denominación, UO funcional y horario; y otro sin UO funcional.
- Entonces el primero existe con activo = true, `permite_restriccion_en_centro` = false, `ventana_reparto_meses` = 12, `meses_inactividad_caso` = 6 y una denominación vigente desde hoy. El segundo se rechaza.

### TF-TCE-02 — Código inmutable [negativo]

- Dado `$tipoCss`.
- Cuando se intenta cambiar su codigo.
- Entonces se lanza `LogicException` y el código no cambia.

### TF-TCE-03 — Renombrado programado

- Dado `$tipoCss`.
- Cuando `$admin` programa la denominación "Centro Especializado" / "CEF" desde 2027-01-01.
- Entonces `denominacionEn('2026-12-31')` es "CSS"; `denominacionEn('2027-01-01')` es "CEF"; la caché siglas sigue en "CSS". Con `setTestNow('2027-01-01')` y el comando denominaciones:aplicar, la caché pasa a "CEF". El id y el codigo del tipo no cambian, ni los `tipo_centro_id` de `$cssNorte` y `$cssSur`.

### TF-TCE-04 — Denominaciones sin solape

- Dado una denominación de `$tipoCss` vigente desde 2026-01-01 sin fin.
- Cuando se intenta crear directamente otra vigente desde 2026-06-01 sin cerrar la anterior.
- Entonces se rechaza. Con `programarDenominacion()` se permite y la anterior queda cerrada el 2026-05-31.

### TF-TCE-05 — Baja de tipo [negativo]

- Dado `$tipoCss` con dos centros activos, y un tipo `$tipoVacio` sin centros.
- Cuando se intenta dar de baja cada uno.
- Entonces la baja de `$tipoCss` se rechaza. `$tipoVacio` queda con activo = false y `fecha_baja`, y sigue existiendo en la tabla.

### TF-TCE-06 — Versionado del tipo

- Dado `$tipoCss` con `meses_inactividad_caso` = 6.
- Cuando `$admin` lo cambia a 9.
- Entonces existe una versión con el valor 6 en el snapshot, el usuario y la fecha.

## Grupo B — Centro y tipo

### TF-TCE-07 — Tipo obligatorio

- Dado `$admin`.
- Cuando crea un centro sin tipo.
- Entonces se rechaza.

### TF-TCE-08 — Tipo inmutable [negativo]

- Dado `$cssNorte`.
- Cuando se intenta cambiar su `tipo_centro_id` a `$tipoCiam`, por el modelo y por el formulario de Filament (como `$admin`).
- Entonces las dos vías se rechazan y el tipo no cambia.

### TF-TCE-09 — Alta en tipo de baja

- Dado `$tipoVacio` dado de baja.
- Cuando `$admin` intenta crear un centro de ese tipo.
- Entonces se rechaza.

### TF-TCE-10 — Sustitución por baja y alta

- Dado `$cssSur`.
- Cuando `$admin` lo da de baja y crea `$ciamSur` de tipo `$tipoCiam` con `$cssSur` como antecesor.
- Entonces `$cssSur` tiene activo = false y sigue existiendo; `$ciamSur`->antecesor es `$cssSur`; las asignaciones e historias vinculadas a `$cssSur` no cambian.

### TF-TCE-11 — UO funcional y UO orgánica

- Dado `$ciamVallecas`.
- Cuando se consulta su configuración efectiva.
- Entonces `uoFuncional($ciamVallecas)` es `$uoIgualdad`; su UO es `$uoCiamVallecas`, cuyo padre orgánico es `$uoJuntaVallecas`. Cambiar la UO funcional de `$tipoCiam` cambia la de `$ciamVallecas` sin tocar su UO ni su padre orgánico.

## Grupo C — Horario y calendario

### TF-TCE-12 — Herencia del horario

- Dado `$cssNorte` con `hereda_horario_tipo` = true.
- Cuando se pide su horario efectivo hoy.
- Entonces es L-V 9:00-14:00 y `origen('horario')` es tipo.

### TF-TCE-13 — Horario propio sustituye entero

- Dado `$cssSur` con horario propio L-J 8:00-15:00 (sin viernes).
- Cuando se pide su horario efectivo un viernes.
- Entonces está cerrado ese día y sus horas son 8:00-15:00 el resto: no se mezcla con el viernes ni con las horas del tipo. `origen('horario')` es centro.

### TF-TCE-14 — Propagación solo a quien hereda

- Dado `$cssNorte` heredando y `$cssSur` con horario propio.
- Cuando `$admin` crea en `$tipoCss` un horario 8:30-14:30 vigente desde hoy (cerrando el anterior).
- Entonces `$cssNorte` pasa a 8:30-14:30; `$cssSur` no cambia.

### TF-TCE-15 — Volver al valor del tipo

- Dado `$cssSur` con horario propio.
- Cuando `$directoraSur` ejecuta Volver al valor del tipo.
- Entonces `hereda_horario_tipo` = true, los campos de horario del registro quedan a null, el resto de parámetros de agenda del `HorarioCentro` se conservan, y el horario efectivo es el del tipo.

### TF-TCE-16 — Vigencias del horario del tipo

- Dado `$tipoCss` con horario de invierno (9:00-14:00) hasta 2027-06-30 y de verano (8:00-13:00) desde 2027-07-01.
- Cuando se pide el horario de `$cssNorte` el 2027-06-30 y el 2027-07-01.
- Entonces es 9:00-14:00 y 8:00-13:00 respectivamente. Crear un tercer horario que solape cualquiera de los dos se rechaza.

### TF-TCE-17 — Cierres acumulables y prioridad

- Dado en `$tipoCss` un cierre el 2026-11-09 y horario reducido 9:00-12:00 el 2026-11-10; en `$cssNorte` horario reducido 10:00-13:00 el 2026-11-09.
- Cuando se pide `dia()` de `$cssNorte` y de `$cssSur` el 9, el 10 y el 11.
- Entonces `$cssNorte`: día 9 abierto 10:00-13:00 (manda el centro); día 10 abierto 9:00-12:00 (manda el tipo); día 11, horario normal. `$cssSur`: día 9 cerrado; día 10 9:00-12:00; día 11 normal.

### TF-TCE-18 — La generación de slots usa el calendario efectivo

- Dado el calendario de TF-TCE-17 y un profesional con perfil en `$cssSur`.
- Cuando se genera y publica el cuadrante de noviembre de `$cssSur`.
- Entonces no hay slots el día 9; el día 10 no hay slots fuera de 9:00-12:00; el día 11 hay slots en el horario normal.

### TF-TCE-19 — Un cierre no cancela citas [negativo]

- Dado `$cssNorte` con tres citas confirmadas el 2026-11-16.
- Cuando `$admin` añade en `$tipoCss` un cierre el 2026-11-16.
- Entonces las tres citas siguen confirmada y sus slots no cambian; la acción devuelve el aviso "3 citas afectadas en `$cssNorte`".

## Grupo D — Catálogo

### TF-TCE-20 — Prestaciones del centro = prestaciones del tipo [negativo]

- Dado `$cssNorte` y una fila antigua en `centro_prestacion` que asocia `$cssNorte` con `$p3`.
- Cuando se piden las prestaciones efectivas de `$cssNorte`.
- Entonces son exactamente `$p1` y `$p2`. `$p3` no aparece: `centro_prestacion` no se lee.

### TF-TCE-21 — Restricción desactivada

- Dado `$tipoCss` con `permite_restriccion_en_centro` = false.
- Cuando se intenta crear una exclusión de `$p1` en `$cssNorte`; y se carga el formulario del centro.
- Entonces la exclusión se rechaza y el formulario no muestra la sección de exclusiones.

### TF-TCE-22 — Restricción activada

- Dado `$tipoCss` con `permite_restriccion_en_centro` = true.
- Cuando `$directoraNorte` excluye `$p1`; e intenta excluir `$p3` (no está en `$tipoCss`).
- Entonces `$cssNorte` tiene solo `$p2`; `$cssSur` sigue con `$p1` y `$p2`; la exclusión de `$p3` se rechaza. Al desactivar el indicador, `$cssNorte` vuelve a tener `$p1` y `$p2` y la exclusión sigue guardada.

### TF-TCE-23 — Lo nuevo del tipo llega a todos

- Dado `$tipoCss` con restricción activada y `$p1` excluida en `$cssNorte`.
- Cuando se añade `$p4` a `$tipoCss` desde la prestación.
- Entonces `$cssNorte` y `$cssSur` tienen `$p4`. Se ha generado una versión de `$p4` cuyo snapshot no incluía `$tipoCss`.

### TF-TCE-24 — Tipos de plan por tipo de centro [negativo]

- Dado una ciudadana con Historia Social.
- Cuando se intenta abrir un PIA en `$cssNorte` y un PISO en `$ciamVallecas`, llamando directamente al servicio; y se abre un PIA en `$ciamVallecas`.
- Entonces los dos primeros se rechazan; el tercero se crea. El selector de `$cssNorte` solo ofrece PISO.

### TF-TCE-25 — Fichas de valoración

- Dado `$tipoCiam` con solo `$fichaVG` y `$tipoCss` sin fichas asociadas.
- Cuando se piden las fichas efectivas de `$ciamVallecas` y `$cssNorte`.
- Entonces `$ciamVallecas`: solo `$fichaVG`. `$cssNorte`: todas las fichas activas.

### TF-TCE-26 — Mover una prestación de tipo no toca lo existente

- Dado una prescripción activa de `$p2` en `$cssNorte`.
- Cuando `$p2` se quita de `$tipoCss` y se añade a `$tipoCiam`.
- Entonces la prescripción sigue igual, en `$cssNorte`. `$p2` ya no está entre las prestaciones efectivas de `$cssNorte` y sí en `$ciamVallecas`.

## Grupo E — Público destinatario

### TF-TCE-27 — Segmentos solo en el tipo

- Dado `$tipoCiam` con segmento vg.
- Cuando se piden los segmentos de `$ciamVallecas`; y `$admin` intenta guardar segmentos en el formulario del centro.
- Entonces son vg; el formulario del centro no permite editarlos y una petición que los incluya los ignora o se rechaza.

## Grupo F — Reglas de asignación

### TF-TCE-28 — Las reglas se leen del tipo

- Dado `$tipoCss` con `inscripcion_libre` = false.
- Cuando `$admin` lo cambia a true.
- Entonces `inscripcionLibre()` es true para `$cssNorte` y `$cssSur`, y `AsignacionCentroService::asignarPorEleccion()` acepta `$cssNorte`. (Rama A; en rama B, solo se comprueba `inscripcionLibre()`.)

### TF-TCE-29 — Cambiar la regla no reasigna [negativo]

- Dado 5 historias en `$cssNorte` con referencia asignada por sorteo.
- Cuando `$admin` cambia `modo_asignacion_referencia` de `$tipoCss` a `quien_abre`.
- Entonces las 5 asignaciones vigentes no cambian (ni `fecha_fin` ni profesional). La siguiente historia abierta en `$cssNorte` usa `quien_abre`.

### TF-TCE-30 — Migración de la configuración de asignación (solo rama A)

- Dado dos centros de `css_general` con `modo_asignacion_referencia` distinto en `centros`.
- Cuando se ejecuta la migración de datos del paso 3.
- Entonces no aborta; `$tipoCss` queda con el valor de la addenda §6.1 (`quien_abre`); el informe de descartes nombra el centro, la propiedad y el valor descartado.

## Grupo G — Permisos

### TF-TCE-31 — Tipos, solo administrador [negativo]

- Dado `$directoraNorte`.
- Cuando intenta crear un tipo, editar `$tipoCss` y dar de baja `$tipoVacio`, por Filament y por servicio.
- Entonces las tres se rechazan. `$admin` puede hacerlas.

### TF-TCE-32 — La directora edita lo suyo y solo lo suyo [negativo]

- Dado `$directoraNorte`.
- Cuando cambia el teléfono, el horario propio, un cierre propio y el protocolo de `$cssNorte`; después intenta, en una petición que llega al servidor, cambiar nombre, `inscripcion_libre` o `tipo_centro_id` de `$cssNorte`; y cambiar el teléfono de `$cssSur`.
- Entonces lo primero se guarda; lo segundo se rechaza sin cambiar nada; lo tercero se rechaza.

### TF-TCE-33 — Alta y baja de centros, solo administrador

- Dado `$directoraNorte` y `$admin`.
- Cuando cada uno intenta crear un centro de `$tipoCss` y dar de baja `$cssSur`.
- Entonces `$directoraNorte` recibe acceso denegado en las dos; `$admin` puede.

## Grupo H — Migración

### TF-TCE-34 — Estado tras la migración

- Dado una base con el catálogo `centro.tipo` (`css_general`, `centro_dia`), centros de seeders con su tipo deducible y filas en `prestacion_tipo_centro` por clave.
- Cuando se ejecutan las migraciones y seeders de la fase.
- Entonces existe un `TipoCentro` por clave, con una denominación vigente; todos los centros tienen `tipo_centro_id`; todas las filas de `prestacion_tipo_centro` tienen `tipo_centro_id` coherente con su clave; las entradas del grupo `centro.tipo` siguen existiendo con activo = false; los `HorarioCentro` existentes tienen `hereda_horario_tipo` = false y su horario no ha cambiado. Las columnas y tablas de la addenda §6.2 ya no existen; `centros.horario` se sigue pudiendo leer como accesor y coincide con el horario efectivo.

## Grupo I — Estructura, ámbito y centro de trabajo (addenda D1 y D2)

### TF-TCE-35 — Ámbito funcional [negativo]

- Dado una historia y un ciudadano de `$ciamVallecas`.
- Cuando `$coordIgualdad` los consulta (scope y policy).
- Entonces los ve, aunque `$uoCiamVallecas` no desciende orgánicamente de `$uoIgualdad`.

### TF-TCE-36 — Ámbito orgánico sin cambios

- Dado el mismo caso.
- Cuando `$jefaJuntaVallecas` los consulta.
- Entonces los ve; y no ve los de `$ciamUsera`.

### TF-TCE-37 — Fuera de ámbito [negativo]

- Dado el mismo caso.
- Cuando `$coordPrimaria` los consulta.
- Entonces no los ve (salvo acceso aprobado, como hoy).

### TF-TCE-38 — El ámbito sigue a la UO funcional del tipo

- Dado `$tipoCiam` con UO funcional `$uoIgualdad`.
- Cuando `$admin` la cambia a `$uoPrimaria` y se hace una petición nueva.
- Entonces `$coordPrimaria` ve los datos de `$ciamVallecas` y `$coordIgualdad` deja de verlos.

### TF-TCE-39 — Sin centro no se atiende [negativo]

- Dado `$tecnicaArea`.
- Cuando intenta abrir una Historia Social, dar una cita y registrar una atención, llamando a los servicios y a las acciones Livewire.
- Entonces las tres se rechazan con el mensaje de RN-15. `centros()` está vacío y `centroActivo()` es `null`. Puede consultar a un ciudadano dentro de su ámbito según su rol.

### TF-TCE-40 — Centro activo por defecto del multicentro

- Dado `$psicologa`.
- Cuando se pide `centroActivo()` el lunes 2026-11-02 y el miércoles 2026-11-04.
- Entonces es `$ciamVallecas` y `$ciamUsera` respectivamente.

### TF-TCE-41 — Elegir centro [negativo]

- Dado `$psicologa` el lunes.
- Cuando elige `$ciamUsera`; y después intenta elegir `$cssNorte`.
- Entonces `centroActivo()` pasa a `$ciamUsera` para esa sesión; la segunda elección se rechaza.

### TF-TCE-42 — El contexto manda

- Dado `$psicologa` con centro activo `$ciamVallecas` y una cita suya en un slot de `$ciamUsera`.
- Cuando atiende la cita y registra el apunte.
- Entonces la operación se hace en `$ciamUsera` (centro de la cita), sin cambiar su centro activo.

### TF-TCE-43 — Barra superior

- Dado `$psicologa`, una usuaria adscrita solo a `$ciamVallecas` y `$tecnicaArea`.
- Cuando cargan una pantalla operativa.
- Entonces la primera ve «CIAM · Puente de Vallecas» (el lunes) con selector de dos centros; la segunda, el mismo texto sin selector; la tercera, el nombre de `$uoArea` sin selector.

### TF-TCE-44 — Pantallas de equipo con el centro activo

- Dado una supervisora adscrita a `$uoCiamVallecas` y a `$uoCiamUsera`.
- Cuando abre Mi equipo con cada centro activo.
- Entonces ve el equipo del centro activo en cada caso; `$psicologa` aparece en los dos.

### TF-TCE-45 — Integridad UO–centro [negativo]

- Dado `$uoCssNorte`.
- Cuando se intenta crear otro centro con esa UO; y crear una UO con `parent_id = $uoCssNorte`.
- Entonces las dos se rechazan.

### TF-TCE-46 — Supervisión funcional y centros dependientes

- Dado `$coordIgualdad`.
- Cuando se evalúa `supervisa()` sobre `$ciamVallecas`, `$ciamUsera` y `$cssNorte`; y se pide `$uoArea->centrosDependientes('funcional')`, `('organica')` y `('ambas')`.
- Entonces `supervisa()` es verdadero para los dos CIAM y falso para `$cssNorte`. `$uoArea` tiene los cuatro centros por la funcional, ninguno por la orgánica y los cuatro por ambas.

### TF-TCE-47 — El centro no fija lo que es del tipo [negativo]

- Dado `$admin`.
- Cuando envía al servicio de centros y al formulario de `CentroResource` una petición con `inscripcion_libre`, `modo_asignacion_referencia` o segmentos para `$cssNorte`.
- Entonces se rechaza (o se ignora con aviso) y la configuración efectiva de `$cssNorte` sigue siendo la de `$tipoCss`.

## Grupo J — Personas protegidas (addenda D4)

### TF-TCE-48 — La UO superior no ve a la persona protegida [negativo]

Una persona protegida con historia en `$ciamVallecas`. `$coordIgualdad` (funcional) y `$jefaJuntaVallecas` (orgánica) la ven como «Persona con protección especial», sin nombre, documento ni enlace, por scope, policy y buscador. Una persona no protegida del mismo centro sí la ven (TF-TCE-35 y 36 siguen en verde).

### TF-TCE-49 — El centro sí la ve

Una profesional de `intervencion` y la directora, adscritas a `$uoCiamVallecas`, la ven con nivel 1.

### TF-TCE-50 — Concede el director del centro [negativo]

`$coordIgualdad` solicita acceso con motivo. Lo intentan resolver `$coordIgualdad`, `$jefaJuntaVallecas` y la directora de `$ciamVallecas`. Solo la directora puede; tras aprobarlo, `$coordIgualdad` la ve hasta la caducidad y su acceso se audita como `acceso_restringido`.

### TF-TCE-51 — Sin director, la supervisión del centro

Sin `DirectorCentro` vigente en `$ciamVallecas`, resuelve quien tiene `supervision` adscrito a `$uoCiamVallecas`, y no `$coordIgualdad`.

### TF-TCE-52 — Nadie resuelve lo suyo

La directora solicita acceso a una persona protegida de otro centro del que también es supervisora por ámbito superior; no puede resolverlo ella.

### TF-TCE-53 — Bandeja de asignaciones [negativo]

Una entrada de una persona protegida de `$ciamVallecas`. La supervisión adscrita al centro la ve con identidad y la resuelve; `$coordIgualdad` la ve sin identidad y la acción de resolver se rechaza en el servidor.

### TF-TCE-54 — El aviso de solicitud va al director

Al crear una solicitud desde el buscador, la alerta llega a la directora de `$ciamVallecas` y no a `$coordIgualdad` ni a `$jefaJuntaVallecas`.

### TF-TCE-60 — Salvedad del rol `intervencion` en UO superior [negativo]

una persona protegida con historia en `$ciamVallecas`. `$tecnicaIgualdad` (`intervencion`, adscrita a `$uoIgualdad`, que tiene `$ciamVallecas` en su ámbito funcional) accede a su historia con identidad (nivel 1), por scope, policy y buscador. Con la historia en un centro fuera de su ámbito (`$cssNorte`), no. Quitarle el rol `intervencion` la deja sin acceso. `$coordIgualdad` (solo `supervision`, misma UO) sigue sin verla (TF-TCE-48).

## Grupo K — Bajas de profesionales (addenda D5)

### TF-TCE-55 — No se borra [negativo]

`$admin` intenta borrar un usuario y un profesional por Filament y por servicio; se rechaza y siguen existiendo sin `deleted_at`. «Desactivar» sí funciona y el usuario no puede iniciar sesión.

### TF-TCE-56 — Salida bloqueada con casos [negativo]

`$psicologa` es referencia de 3 casos en `$ciamVallecas`. Cerrar su adscripción a `$uoCiamVallecas` se rechaza con el recuento y el enlace al reparto. Sigue adscrita.

### TF-TCE-57 — Salida tras el reparto

Tras confirmar el reparto de sus 3 casos y no tener citas futuras en `$ciamVallecas`, la adscripción se cierra. Sus casos tienen nueva referencia en `$ciamVallecas`; su adscripción a `$uoCiamUsera` y sus casos allí no cambian.

### TF-TCE-58 — Salida bloqueada con citas

Sin casos, pero con una cita confirmada la semana próxima en un slot de `$ciamVallecas`. La salida se rechaza hasta reasignar la cita.

### TF-TCE-59 — Los casos no viajan

`$psicologa` se adscribe a un tercer centro. Sus asignaciones en `$ciamVallecas` y `$ciamUsera` no cambian y no aparecen en el nuevo centro.

## Tabla de estado (rellenar al implementar)

| Grupo | Tests | Estado |
|---|---|---|
| A — Tipo de centro | TF-TCE-01..06 |  |
| B — Centro y tipo | TF-TCE-07..11 |  |
| C — Horario y calendario | TF-TCE-12..19 |  |
| D — Catálogo | TF-TCE-20..26 |  |
| E — Público destinatario | TF-TCE-27 |  |
| F — Reglas de asignación | TF-TCE-28..30 |  |
| G — Permisos | TF-TCE-31..33 |  |
| H — Migración | TF-TCE-34 |  |
| I — Estructura, ámbito y centro de trabajo | TF-TCE-35..47 |  |
| J — Personas protegidas | TF-TCE-48..54 y 60 |  |
| K — Bajas de profesionales | TF-TCE-55..59 |  |
