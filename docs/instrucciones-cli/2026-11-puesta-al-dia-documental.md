Instrucciones CLI — Puesta al día documental (noviembre 2026)

Encargo para Claude CLI: alinear la documentación del repositorio con lo implementado hasta el 2 de octubre y con las decisiones de análisis tomadas después.

Solo documentación. No cambiar código, migraciones, seeders, tests ni configuración. No lanzar migraciones, demo:* ni la suite de tests (la BD local es la de staging). Se permite leer código y hacer grep para verificar hechos.

Commit de partida: 0b97bf4 (2 octubre 2026). Origen: revisión documental del 10 de noviembre (docs/estado-proyecto-2026-11-10.md, que se incorpora en el paso 1).

Regla de trabajo: si un documento de análisis contradice el código o el CHANGELOG de una forma que estas instrucciones no prevén, no elijas: anótalo en la lista de dudas (paso 8) y sigue con lo demás. No reescribas decisiones de diseño. No mejores redacción fuera de los puntos indicados.

Paso 0 — Ficheros que aporta el desarrollador

Antes de lanzar la sesión, el desarrollador copia en el repositorio estos ficheros desde el proyecto de Claude «VIDA 360»:

Fichero del proyecto	Destino en el repo	Acción
docs/modulo-citas.md	docs/modulo-citas.md	Sustituye al actual
docs/roles-consulta-basica-tramitacion.md	docs/roles-consulta-basica-tramitacion.md	Nuevo
docs/modulo-tipo-centro.md	docs/modulo-tipo-centro.md	Nuevo
docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md	mismo	Nuevo
docs/instrucciones-cli/2026-10-tipo-centro-tests.md	mismo	Nuevo
docs/propuesta-requisitos-y-tareas-guiadas.md	mismo	Nuevo
docs/estado-proyecto-2026-11-10.md	mismo	Nuevo
este fichero	docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md	Nuevo

Comprobación: git status debe mostrar exactamente esos ficheros. Si falta alguno, detente y pídelo. No uses docs/modulo-asignacion.md del proyecto: la copia del repositorio es la buena.

Lee después CLAUDE.md, SESSION.md, BACKLOG.md, CHANGELOG-092026.md, CHANGELOG-102026.md y los ficheros nuevos.

Paso 1 — modulo-citas.md: revisión posterior a la implementación

La versión que se implementó el 30 de septiembre es la anterior. La nueva añade, entre otras cosas, RN-05 ter, §6 bis, la columna tramitacion (⏸) en §7 y filas nuevas en §10.

git diff docs/modulo-citas.md e informa en el CHANGELOG de qué reglas o secciones son nuevas o cambian.
Verifica en el código, con fichero y línea:
RN-05 ter: ¿CitaPolicy, SolicitudCitaPolicy o los servicios de Modules/Agenda/app/Services/Citas/ limitan dar, reprogramar y cancelar a slots de centros de la UO del usuario?
§6 bis: ¿muestra FichaCiudadanoPage un historial de citas (pasadas y programadas, de cualquier centro) a consulta_basica y tramitacion?
Cualquier otra regla nueva del diff.
Cambia la línea Estado por:

Estado: diseño funcional v1 cerrado. Implementado el 2026-09-30 sobre la revisión anterior de este documento. Pendiente de implementar lo añadido el 30 sep por la tarde: [lista de lo que el punto 2 haya confirmado como no implementado]. Ver BACKLOG.md.

Añade al BACKLOG la entrada «Citas: delta de la revisión del 30 sep» con lo no implementado y las referencias del punto 2.
Paso 2 — Estados de los documentos de módulo
Documento	Cambio
docs/modulo-asignacion.md	Mantén el estado. Añade al principio de §8 la nota: «Cuando se implemente docs/modulo-tipo-centro.md, la adscripción, el modo de referencia, la ventana de reparto y los meses de inactividad pasan del centro al tipo (ver su §7).»
docs/modulo-agenda.md	El estado dice «interfaz Livewire/Filament pendiente». Comprueba qué pantallas existen (cuadrante y ausencias del supervisor, agenda del profesional, citación, recursos de Filament) y reescribe la línea Estado con lo que hay y lo que falta. Revisa también §8 si sigue listando tests como bloqueados
docs/modulo-tipo-centro.md	En §11, la frase «Si la fase de asignación no está implementada, sus instrucciones deben ajustarse antes de ejecutarla» pasa a: «La asignación está implementada (2026-09-30): al ejecutar esta fase se sigue la rama A de las instrucciones.» Estado: sin cambios («sin implementar»)
docs/roles-consulta-basica-tramitacion.md	Sin cambios
docs/propuesta-requisitos-y-tareas-guiadas.md	Sin cambios

Para el resto de docs/modulo-*.md, comprueba que la línea de estado (si la tienen) no contradice el CHANGELOG. Si alguna lo hace, corrígela solo si la evidencia es inequívoca; si no, a la lista de dudas.

Paso 3 — Propagar las decisiones de roles-consulta-basica-tramitacion.md

Aplica su §6. Todo lo que añadas sobre consulta_basica se marca como diseño cerrado, sin implementar; lo de tramitacion, como implantación diferida.

docs/modulo-usuarios-permisos.md
ROL 5 (tramitacion): añade bajo el título «Implantación diferida. Lo decidido y lo pendiente está en docs/roles-consulta-basica-tramitacion.md §4.» y, en capacidades, que gestiona citas con los permisos de docs/modulo-citas.md §7. No borres las capacidades actuales: quedan como alcance previsto.
ROL 7 (consulta_basica): sustituye «Capacidades» por un resumen de §3.4 del documento de roles (incluidos el alta de ciudadanos y la edición de relaciones) con remisión a §3. En «Ámbito por UO»: «Limita la operación (citas, documentos, modificaciones), no la consulta: puede abrir la ficha de cualquier ciudadano (ver documento de roles §2).»
§4.4: añade los permisos nuevos de §5 del documento de roles (historia.ver_estado, cita.ver_historial, documento.gestionar_propios), marcados como no implementados.
docs/modulo-ciudadania.md
§2, Capa 1: la ficha es accesible sin ámbito de UO para consulta_basica y tramitacion.
§3.3 (Relaciones): donde diga «rol tramitación incluido», añade consulta_basica.
§4 (alta): consulta_basica puede dar altas.
Colectivos protegidos (§6.2 o donde se trate): la ficha de identificación no muestra el banner de protegido; el banner es solo para intervencion al acceder a datos sensibles.
Donde se describa FichaCiudadanoPage (probablemente §7.4): bloques visibles por rol según §3.1 del documento de roles, widget de prestaciones oculto para consulta_basica, bloques de historial de citas y de estado de la Historia Social con profesional de referencia.
docs/modulo-documentos.md
§1: la disponibilidad «para cualquier profesional con acceso al expediente» tiene la excepción de consulta_basica (solo sus documentos, el día de la subida).
§2.1: anota como pendiente que Documento necesita retirada lógica (quién, cuándo, motivo), versiones del fichero al sustituir y vínculo con una solicitud administrativa. Antes de escribirlo, comprueba qué ya existe en la custodia v2 (versiones y vínculos existen): anota solo lo que falte.
docs/design-system/SKILL.md y README.md
En SKILL.md, sustituye «Protected records (menores, VG): show the protected banner; never hide the status.» por:

Protected records (menores, VG): show the protected banner only to intervencion when they try to open sensitive data (social situation, health, Historia Social content). Never show it on identification/contact views or to roles without access to sensitive data (consulta_basica, tramitacion): the banner itself would reveal the protection.

En README.md, junto a la línea de «Colectivos especialmente protegidos», añade la misma aclaración en una frase.
Paso 4 — docs/decisiones-pendientes.md

Está sin actualizar desde junio y los totales no cuadran (32 en la cabecera, 33 en el resumen).

Para cada decisión, busca en CHANGELOG-*.md, BACKLOG.md y los docs/modulo-*.md si se resolvió. Si consta resuelta, quítala del fichero y anótala en el CHANGELOG de esta sesión («Resuelto en [fecha]: [id] — [dónde consta]»), como pide el propio documento. Si hay indicios pero no constancia, déjala y añádela a la lista de dudas.
Añade una sección «Decisiones pendientes registradas en otros documentos» que remita, sin copiarlas, a: modulo-asignacion.md §11, modulo-citas.md §11, modulo-tipo-centro.md §13, roles-consulta-basica-tramitacion.md §4.2 y propuesta-requisitos-y-tareas-guiadas.md («Por decidir»).
Recalcula los totales y la fecha de actualización.
Paso 5 — Limpieza del BACKLOG
5.1 Entradas que parecen resueltas

Verifica cada una con evidencia (fichero y línea, o la entrada del CHANGELOG que la resuelve). Si se confirma, márcala como las ya cerradas: **✅ CERRADO 2026-11-xx — ~~título~~** y una línea con la evidencia. Si no se confirma, déjala y explica en una línea qué falta.

Entrada	Indicio
Suite de Modules/Agenda rota: tipos_slot.horario_centro_id (2026-09-24)	Paso 0 de citas (CHANGELOG 2026-09-30); Agenda 173 passed el 2026-10-01
Implementar servicios pendientes del módulo Agenda, 30 tests bloqueados (2026-05-18)	Citas usa GestionAusenciaService, cuadrantes y slots; comprueba cada servicio de la lista y los markTestIncomplete
Integración real de auditoría en BuscarCiudadanoPage (nivel 2) (2026-06-01)	CHANGELOG 2026-10-02: sin Log::info ni TODO
Tabla ciudadano_identificadores pendiente de crear (2026-06-10)	El buscador de citación busca por hash. Deja abierta, si sigue así, la búsqueda por documento de BuscarCiudadanoPage (ya está en «Acceso a expedientes: pendientes»)
TODOs activos en CiudadanoPage (2026-06-15)	Revisa cada viñeta por separado (centroActivo(), DNI en cabecera, menú ⋯, statPrestaciones())
Suite completa con 75 fallos fuera de Documentos (2026-09-25)	Los de Agenda están corregidos; deja solo los que sigan (TF-AUTH-16/17, AutorizacionDatosTest, Ciudadanía «Ver historia social»), remitiendo a sus entradas
UC en UI de intervención (2026-06-16)	Modal de UC y buscador de UC en el expediente; comprueba el botón «Ver ficha» del bloque UC
Seguimiento del plan — UI (2026-06-18)	La ficha programa el siguiente seguimiento; comprueba la evaluación de objetivos
Instalar barryvdh/laravel-dompdf (2026-06-18)	composer.json
Añadir TipoRelacionSeeder al DatabaseSeeder raíz (2026-06-16)	Comprueba los seeders
[Demo] Citas en escenarios de demo (2026-06-03)	Duplica la viñeta de «Citas: pendientes de la fase»: ciérrala remitiendo a ella
5.2 Duplicados
Las dos entradas «CI "Calidad de código" (PHPStan + Pint) en rojo» (2026-09-29 y 2026-10-01): fúndelas en una, con las dos fechas y todo el contenido.
5.3 Entradas a actualizar
«No existe asociación tipo de plan ↔ centro / UO / servicio» (2026-09-24): añade «Resuelto en diseño por docs/modulo-tipo-centro.md (tipos de plan por tipo de centro, §6.2). Se cierra al implementar esa fase.»
«La interfaz no permite crear planes especializados; PlanPage fija siempre tipo = general_asp» (2026-09-24): añade «Tipo de Centro no lo resuelve: solo filtra los tipos de plan ofrecidos. Prioritario para el CIAM.»
«Asignación de centro y referencia: pendientes», viñeta «Configurar los centros reales en staging»: añade «Aviso: el paso 3 de 2026-10-tipo-centro-implementacion.md aborta si dos centros del mismo tipo tienen distinto modo de asignación, ventana o segmentos. Al configurar centros para probar el sorteo, configurar igual todos los del tipo.»
5.4 Entradas nuevas
Citas: delta de la revisión del 30 sep (paso 1).
consulta_basica: diseño cerrado sin instrucciones de implementación — docs/roles-consulta-basica-tramitacion.md §3; faltan instrucciones CLI y tests. Incluye la ficha por rol, el bloque de estado de la Historia Social, el historial de citas (§6 bis de citas) y los documentos propios del día.
Tipo de Centro: listo para ejecutar — diseño, instrucciones y TF-TCE-01 a 34 en el repo; rama A. Depende de la decisión sobre varias UO con varios centros (ver «Perfil horario por defecto: pendientes»).
Requisitos de acción y tareas guiadas — en análisis (docs/propuesta-requisitos-y-tareas-guiadas.md). Dependencias: TipoCentro, varios centros por UO, inscripción de ciudadanos en actividades (ver «Actividades grupales — gestión completa»).
Paso 6 — CLAUDE.md

En la tabla de §6 añade:

Fichero	Contenido
2026-10-tipo-centro-implementacion.md	Tipo de Centro como entidad: denominaciones con vigencia, herencia tipo → centro (ConfiguracionEfectivaCentro), horario y cierres de calendario, catálogo por tipo, evolución y bajas. Sin ejecutar; rama A
2026-10-tipo-centro-tests.md	34 tests de Tipo de Centro (TF-TCE-01 a 34). Sin implementar
2026-11-puesta-al-dia-documental.md	Alineación de la documentación con lo implementado y con las decisiones de septiembre a noviembre. Hecho el 2026-11-xx

En el mapa de §5 (docs/), añade docs/roles-consulta-basica-tramitacion.md («antes de tocar permisos, ficha del ciudadano o citas para roles administrativos») y docs/estado-proyecto-2026-11-10.md («foto del estado del proyecto a noviembre 2026»).

No cambies nada más de CLAUDE.md.

Paso 7 — docs/estado-proyecto-2026-11-10.md

No lo reescribas: es una foto fechada. Añade al final una sección «Seguimiento» con una línea por cada punto de su §3 (incoherencias) indicando si esta sesión lo ha resuelto y dónde.

Paso 8 — Cierre
CHANGELOG-112026.md nuevo (mismo formato que CHANGELOG-102026.md, con la remisión al mes anterior), con:
ficheros incorporados;
el resultado del paso 1 (diff de citas y verificación de RN-05 ter y §6 bis, con fichero y línea);
documentos modificados y qué se cambió en cada uno;
decisiones pendientes dadas por resueltas y entradas del BACKLOG cerradas, con su evidencia;
lista de dudas: contradicciones no previstas aquí, sin resolver.
SESSION.md: tarea completada (una línea), estado (sin cambios de código desde el 2026-10-02) y siguiente paso recomendado:
el desarrollador prueba en staging asignación y citas (sigue pendiente desde el 2026-10-01);
decidir el modelo de varias UO con varios centros;
ejecutar 2026-10-tipo-centro-implementacion.md (rama A);
corregir la creación de planes especializados (PIA);
instrucciones del delta de citas y de consulta_basica. Conserva la sección «Contexto para retomar sin fricción» y añade una línea: «Documentación al día a 2026-11-xx: ver docs/estado-proyecto-2026-11-10.md y su sección Seguimiento.»
Informa en tu respuesta final de qué elementos del código podrían ser los «Procesos» programados que menciona docs/propuesta-requisitos-y-tareas-guiadas.md (flujos guiados o pasos fijos en el expediente, la ficha o el alta), con fichero y línea. No los anotes en ningún documento: lo decide el desarrollador.
git add -A, git commit -m "docs: puesta al día documental (noviembre 2026)", git push origin master.
Criterios de finalización
git diff --stat 0b97bf4 solo muestra ficheros .md (y ningún fichero bajo vida/ salvo, si los hubiera, .md de documentación).
Ningún docs/modulo-*.md dice «sin implementar» de algo que el CHANGELOG da por implementado, ni al revés.
docs/design-system/SKILL.md ya no dice que el banner de protegido se muestre siempre.
Cada entrada del BACKLOG cerrada en esta sesión tiene su evidencia.
La lista de dudas está en el CHANGELOG, aunque esté vacía («Sin dudas»).
CHANGELOG, SESSION, BACKLOG y CLAUDE.md actualizados y subidos.
