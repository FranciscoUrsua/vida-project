Instrucciones de implementación — Tipo de Centro

Módulos: Centro, Organizacion, Prestaciones, Agenda, Intervencion, Usuarios. Tipo de trabajo: evolución de módulos existentes. Diseño funcional: docs/modulo-tipo-centro.md. Tests asociados: docs/instrucciones-cli/2026-10-tipo-centro-tests.md (TF-TCE-01 a TF-TCE-34). Orden respecto a Asignación: ver paso 0.2. Esta fase puede ir antes o después de 2026-09-asignacion-implementacion.md; el paso 0 determina la rama.

Antes de empezar

Lee, en este orden:

CLAUDE.md, SESSION.md, docs/principios-vida360.md, docs/decisiones-tecnicas.md.
docs/modulo-tipo-centro.md completo. Es el qué y el por qué; este documento es el cómo.
docs/modulo-centros.md (§2, §4.1, §7), docs/modulo-asignacion.md (§1, §3, §8), docs/modulo-agenda.md (§2.1 y generación de cuadrantes), docs/modulo-prestaciones.md (prestacion_tipo_centro, catálogos, versionado), docs/modulo-intervencion.md (apertura de planes), docs/modulo-usuarios-permisos.md.
El código actual de: Centro, CentroResource, AmbitoTerritorial, HorarioCentro, HorarioCentroResource, la generación de cuadrantes y slots de Agenda, Prestacion, PrestacionResource, CatalogoSistema, el trait Versionable, UnidadOrganizativa, DirectorCentro, el modelo de plan de intervención y TipoFicha.
docs/design-system/SKILL.md y docs/instrucciones-cli/2026-09-bootstrap-unico.md antes de cualquier Blade o Filament.
El documento de tests.

Regla de trabajo: si encuentras un caso no cubierto aquí o un conflicto con el código existente, detente y pregunta. No improvises decisiones de diseño. Lo marcado como fuera de alcance no se implementa.

1. Qué cambia
Hoy	Después de esta fase
El tipo de centro es una clave de catalogos_sistema (centro.tipo)	Entidad TipoCentro con UO funcional, configuración y denominaciones con vigencia
prestacion_tipo_centro.tipo_centro es un varchar	FK tipo_centro_id
El centro no tiene tipo (o lo tiene como varchar, si asignación está hecha)	centros.tipo_centro_id obligatorio e inmutable, y centro_antecesor_id
inscripcion_libre, segmentos de población y prestaciones se configuran por centro	Se configuran en el tipo; el centro las hereda
Cada HorarioCentro define su horario	Puede heredar el horario del tipo
No hay cierres de calendario de centro	Cierres de tipo y de centro, acumulables, usados por Agenda
Cualquier tipo de plan en cualquier centro	Solo los tipos de plan del tipo del centro

Principios que no se negocian:

Un único punto de lectura. Toda la configuración de un centro que puede venir del tipo se lee a través de ConfiguracionEfectivaCentro. Nada fuera de ese servicio lee los campos del tipo o los equivalentes del centro para decidir comportamiento.
El tipo de un centro no se cambia. Ni por UI, ni por API, ni por código: el modelo lo impide.
Nada se borra. Tipos y centros se dan de baja. Las denominaciones y horarios se cierran con fecha.
Ningún cambio de configuración reasigna personas, casos ni citas.
Permisos en el servidor. Las restricciones del director se validan en policies y servicios, no solo ocultando campos en Filament.
2. Fuera de alcance (no construir)
Subtipos o jerarquía de tipos. Centros con más de un tipo. Entidad "sede".
Operación de "cambiar el tipo de un centro".
Traslado en bloque de personas o casos entre centros o tipos.
Validaciones nuevas de cobertura o solapamiento de áreas. Las de modulo-asignacion.md §3.3, si ya existen, no se tocan.
Ampliación del ámbito de permisos por la UO funcional.
Canales de cita por tipo. Cargos habituales por tipo. Modelos de informe por tipo.
Personas protegidas por defecto y derivaciones permitidas en el tipo.
Cancelación automática de citas o slots por un cierre de calendario.
Paso 0 — Verificación del entorno
git pull origin master y php artisan test --filter=Centro, --filter=Agenda, --filter=Prestacion, --filter=Intervencion. Anota los fallos preexistentes.
Estado de la fase de asignación. Comprueba si existen centros.tipo_centro, centros.modo_asignacion_referencia, centros.ventana_reparto_meses, centros.meses_inactividad_caso y la tabla asignaciones_centro.
Rama A (asignación implementada): esos campos se migran al tipo (paso 3).
Rama B (asignación no implementada): esos campos se crean directamente en el tipo y nunca en centros. Anota en SESSION.md y en BACKLOG.md que el paso 2 de 2026-09-asignacion-implementacion.md debe ajustarse antes de ejecutarlo. No lo modifiques tú.
Si el estado es parcial (unos campos sí y otros no), detente y pregunta.
Catálogo centro.tipo. Lista sus claves activas e inactivas y todos sus usos (grep -rn "centro.tipo\|tipo_centro"). Lista los centros existentes y el tipo que les corresponde según sus seeders o mundos demo. Si algún centro no tiene un tipo deducible sin ambigüedad, detente y pide el mapeo. Si conviven claves equivalentes (p. ej. css y css_general), detente y pregunta cuál se conserva.
UO funcional. Comprueba cómo se modelan las UO (UnidadOrganizativa, jerarquía). La UO funcional de cada tipo es un dato que no se deduce: pídela para los datos reales. En seeders y mundos demo, usa la UO raíz del organigrama sembrado y anótalo en el CHANGELOG.
Lectores a sustituir. Informa, con fichero y línea, de todos los lugares que leen:
centros.inscripcion_libre;
centro_prestacion y centro_segmento_poblacion;
centros.horario;
los campos de horario de horarios_centro (dias_laborables, hora_apertura, hora_cierre, hora_inicio_atencion, hora_fin_atencion);
en rama A, los campos de asignación del punto 2.
Generación de Agenda. Identifica el punto o puntos donde se generan cuadrantes y se materializan slots a partir del horario del centro. Si hay más de uno y no comparten una función de "horario del día", detente y pregunta antes del paso 5.
Permisos. Averigua cómo se identifica hoy al administrador del sistema (rol) y al director de un centro (DirectorCentro vigente, rol supervision del centro, o ambos). Si no hay una forma inequívoca de saber si un usuario es director de un centro concreto, detente y pregunta.
Catálogos de Intervención. Comprueba cómo se modelan los tipos de plan (catálogo, enum o tabla) y si TipoFicha tiene hoy alguna relación con centros. Informa antes del paso 6.
Calendario. Comprueba que no existe ya una entidad de festivos o cierres de centro. Si existe, detente y pregunta.
Paso 1 — TipoCentro y denominaciones

En el módulo Centro.

tipos_centro
Campo	Tipo	Notas
id	bigint PK	
codigo	varchar(100)	Unique. Inmutable una vez creado
nombre	varchar(200)	Caché de la denominación vigente (ver abajo)
siglas	varchar(20)	Caché de la denominación vigente
unidad_organizativa_id	FK	UO funcional. Obligatoria
inscripcion_libre	boolean	Por defecto false
modo_asignacion_referencia	enum ModoAsignacionReferenciaCentro	sorteo / libre_eleccion / quien_abre. Reutiliza el enum de asignación si existe
ventana_reparto_meses	int	Por defecto 12
meses_inactividad_caso	int	Por defecto 6
permite_restriccion_en_centro	boolean	Por defecto false
protocolo	text nullable	
activo	boolean	
fecha_alta	date	
fecha_baja	date nullable	
timestamps		

Sin soft deletes: la baja es lógica (activo, fecha_baja).

TipoCentro usa el trait Versionable. El modelo lanza LogicException si se intenta cambiar codigo en un registro existente, o dar de baja un tipo con centros activos (RN-11).

denominaciones

Tabla polimórfica para tipos y centros.

Campo	Tipo	Notas
id	bigint PK	
denominable_type / denominable_id	morph	TipoCentro o Centro
nombre	varchar(200)	
nombre_corto	varchar(50)	Siglas del tipo o nombre corto del centro
vigente_desde	date	
vigente_hasta	date nullable	
creado_por_id	FK	
timestamps		
Validación en el modelo: las vigencias de un mismo denominable no se solapan. Sin update de nombre ni nombre_corto en una denominación ya vigente o pasada: se cierra y se crea otra.
Trait TieneDenominaciones para TipoCentro y Centro: denominacionEn(Carbon $fecha), denominacionVigente(), programarDenominacion(string $nombre, string $corto, Carbon $desde, User $actor). Este último cierra la vigente el día anterior a $desde y crea la nueva.
Caché: tipos_centro.nombre/siglas y centros.nombre/nombre_corto guardan la denominación vigente hoy, porque muchas consultas y pantallas los leen. Se actualizan al crear una denominación con vigente_desde <= hoy y con el comando diario denominaciones:aplicar, que aplica las denominaciones programadas que entran en vigor. Regístralo en el scheduler.
Migración de datos: una denominación inicial por cada tipo y centro existente, con vigente_desde = fecha_alta (o la fecha de creación del registro si no hay fecha_alta).
Pivotes del tipo
tipo_centro_segmento_poblacion: misma referencia de segmento que usa hoy centro_segmento_poblacion.
tipo_centro_tipo_plan: referencia según lo averiguado en el paso 0.8.
tipo_centro_tipo_ficha: tipo_ficha_id. Sin filas = todas las fichas disponibles.

Unique en cada pivote.

Paso 2 — Tipo en centros y prestaciones
centros
tipo_centro_id FK. Se crea nullable, se rellena con el mapeo del paso 0.3 y se pasa a NOT NULL en una segunda migración. El modelo lanza LogicException si cambia en un registro existente.
centro_antecesor_id FK nullable a centros.
No se permite crear un centro en un tipo con activo = false.
Rama A: después de migrar, centros.tipo_centro (varchar) deja de leerse. Pregunta antes de eliminar la columna.
prestacion_tipo_centro
Nueva columna tipo_centro_id FK, rellenada por codigo desde tipo_centro. Unique (prestacion_id, tipo_centro_id). Después, NOT NULL.
La columna varchar deja de leerse. Pregunta antes de eliminarla.
PrestacionResource: la sección Tipos de centro pasa a seleccionar de tipos_centro activos. El snapshot de versiones de Prestacion debe seguir incluyendo los tipos asociados (ahora por id y código).
asignaciones_centro (solo rama A)

Añade tipo_centro_id, rellénalo desde el varchar y rehace el índice único parcial (ciudadano_id, tipo_centro_id) WHERE fecha_fin IS NULL AND deleted_at IS NULL. El varchar deja de leerse; pregunta antes de eliminarlo.

Catálogo centro.tipo

Seeder que crea un TipoCentro por cada clave activa del grupo (codigo = clave, nombre = etiqueta) y el tipo ciam si no existe. Después, las entradas del grupo pasan a activo = false. No las borres.

Actualiza los seeders de centros y los mundos demo (incluido "Prueba CIAM") para que creen tipos y asignen tipo_centro_id.

Paso 3 — Configuración que pasa del centro al tipo
Del centro	Al tipo
inscripcion_libre	tipos_centro.inscripcion_libre
centro_segmento_poblacion	tipo_centro_segmento_poblacion
Rama A: modo_asignacion_referencia, ventana_reparto_meses, meses_inactividad_caso	Mismos campos en tipos_centro

Migración de datos: para cada tipo, todos sus centros deben tener el mismo valor. Si dos centros del mismo tipo difieren, la migración aborta con un informe de las diferencias (no elige un valor). En rama A, el valor quien_abre con que se migraron los centros existentes se conserva en el tipo.

Para los segmentos, el tipo recibe la unión de los segmentos de sus centros solo si todos los centros tienen el mismo conjunto; si no, aborta con informe.

Después de migrar:

Sustituye cada lectura del paso 0.5 por ConfiguracionEfectivaCentro (paso 4).
Las columnas y tablas del centro dejan de escribirse y de leerse. Pregunta antes de eliminarlas.
Paso 4 — ConfiguracionEfectivaCentro

Servicio en Modules/Centro/app/Services/, único punto de lectura de la configuración heredable. Cachea por petición.

Método	Devuelve	Política
tipo(Centro)	TipoCentro	
uoFuncional(Centro)	UnidadOrganizativa	Solo tipo
segmentos(Centro)	Collection	Solo tipo
inscripcionLibre(Centro)	bool	Solo tipo
modoAsignacionReferencia(Centro)	enum	Solo tipo
ventanaRepartoMeses(Centro) / mesesInactividadCaso(Centro)	int	Solo tipo
horario(Centro, Carbon $fecha)	DTO HorarioEfectivo	Sobrescribible
dia(Centro, Carbon $fecha)	DTO DiaCalendario (abierto, franja de apertura, franja de atención, origen)	Acumulable + horario
protocolo(Centro)	?string	Sobrescribible
prestaciones(Centro)	Collection<Prestacion>	Restringible
tiposPlan(Centro) / tiposFicha(Centro)	Collection	Restringible
origen(Centro, string $propiedad)	'tipo' / 'centro'	Para la UI

En rama A, los servicios de asignación (ResolucionCentroService, AsignacionCentroService, PoolReferenciaService, SorteoReferenciaService, ActividadCasosService, AsignacionReferenciaService) pasan a leer de aquí.

Paso 5 — Horario y calendario
horarios_tipo_centro
Campo	Tipo
id	bigint PK
tipo_centro_id	FK
nombre	varchar
dias_laborables	json
hora_apertura / hora_cierre	time
hora_inicio_atencion / hora_fin_atencion	time
vigente_desde	date
vigente_hasta	date nullable
timestamps	

Validación: sin solape de vigencias por tipo. Versionado con Versionable.

horarios_centro (ampliación)
hereda_horario_tipo boolean. Los registros existentes: false (ya tienen su horario). Los nuevos: true por defecto.
Con hereda_horario_tipo = true, los campos de horario del registro quedan a null y no se leen; el resto de campos de agenda se mantienen.
Volver al valor del tipo = poner hereda_horario_tipo = true y vaciar los campos de horario.

ConfiguracionEfectivaCentro::horario() toma el HorarioCentro vigente del centro en la fecha; si hereda, completa los campos con el HorarioTipoCentro vigente en esa fecha. Si el tipo no tiene horario vigente y el centro hereda, lanza una excepción de configuración.

cierres_calendario
Campo	Tipo	Notas
id	bigint PK	
titular_type / titular_id	morph	TipoCentro o Centro
fecha_desde / fecha_hasta	date	
tipo	enum TipoCierre	cierre / horario_reducido
hora_inicio / hora_fin	time nullable	Obligatorias en horario_reducido
motivo	varchar(200)	
creado_por_id	FK	
timestamps		

dia() aplica: cierre del centro > cierre del tipo > horario efectivo.

Agenda
Sustituye las lecturas de los campos de horario de horarios_centro (paso 0.5) por ConfiguracionEfectivaCentro::horario() y dia().
La generación de cuadrantes y slots no genera slots en días cerrados y recorta los de días con horario reducido.
Al guardar un cierre (de tipo o de centro), la acción devuelve el número de citas confirmada afectadas por centro y lo muestra como aviso. No cancela citas ni slots.
centros.horario (JSON) pasa a ser un accesor calculado del horario efectivo. Pregunta antes de eliminar la columna.
Paso 6 — Catálogo y restricción
centro_exclusiones_catalogo
Campo	Tipo	Notas
id	bigint PK	
centro_id	FK	
elemento_tipo	enum ElementoCatalogo	prestacion / tipo_plan / tipo_ficha
elemento_id	bigint	
creado_por_id	FK	
timestamps		

Unique (centro_id, elemento_tipo, elemento_id). Validación: solo se puede excluir un elemento que esté en el catálogo vigente del tipo del centro, y solo si permite_restriccion_en_centro = true.

ConfiguracionEfectivaCentro:

prestaciones() = prestaciones activas de prestacion_tipo_centro del tipo, menos las exclusiones del centro solo si el tipo permite restricción.
tiposPlan() y tiposFicha(): igual. tiposFicha() sin filas en el pivote del tipo = todas las fichas activas.
Consumidores
Sustituye las lecturas de centro_prestacion por prestaciones(). La tabla deja de escribirse; pregunta antes de eliminarla.
Apertura de plan de intervención: solo se ofrecen y se aceptan (validación en el servicio, no solo en la UI) los tipos de plan de tiposPlan() del centro en que se abre.
Selección de ficha de valoración: solo los de tiposFicha() del centro.
Paso 7 — Evolución y bajas
TipoCentroService::darDeBaja(TipoCentro, Carbon $fecha, User): rechaza si hay centros activos.
CentroService::darDeAlta(array $datos, TipoCentro, ?Centro $antecesor, User): rechaza tipos inactivos.
CentroService::darDeBaja(Centro, Carbon $fecha, User): baja lógica.
Renombrados: programarDenominacion() del paso 1, para tipos y centros.
No hay método para cambiar el tipo de un centro.
Paso 8 — Permisos
TipoCentroPolicy: todas las acciones de escritura, solo administrador del sistema (según paso 0.7). Lectura, cualquier usuario con acceso al backoffice.
CentroPolicy:
create, darDeBaja, renombrar: solo administrador.
update: administrador; o director del centro, limitado a los campos de §9 del diseño funcional (contacto y descripción, horario, cierres propios, protocolo propio y exclusiones si el tipo lo permite).
Los servicios validan los campos que cambia el director y rechazan los demás aunque lleguen en la petición.
tipo_centro_id, inscripcion_libre y el resto de propiedades "solo tipo" no son editables desde el centro por nadie.
Paso 9 — Interfaz

Antes de cualquier Blade o Filament: design system y 2026-09-bootstrap-unico.md. Termina con npm run build y php artisan ui:auditar en verde.

TipoCentroResource (Filament, grupo de organización, solo administrador). Secciones:
Identificación: código (solo lectura tras crear), denominación vigente e historial de denominaciones, acción Programar nueva denominación.
Dependencia y público: UO funcional, segmentos.
Catálogo: prestaciones (solo lectura, con enlace a cada PrestacionResource), tipos de plan, fichas, indicador de restricción.
Horario y calendario: horarios con vigencia, cierres comunes.
Asignación: adscripción, modo de referencia, ventana y meses de inactividad.
Protocolo.
Centros del tipo: listado con estado, en lectura.
CentroResource:
Tipo de centro: obligatorio al crear, solo lectura después. Centro antecesor opcional.
Propiedades "solo tipo" en lectura, con el valor del tipo.
Horario y protocolo con insignia Heredado del tipo o Personalizado y acción Volver al valor del tipo.
Cierres: los del tipo en lectura y los propios editables.
Exclusiones de catálogo solo si el tipo lo permite.
Lo que el director no puede editar, deshabilitado.
HorarioCentroResource: interruptor Heredar horario del tipo; con él activo, los campos de horario se muestran en lectura con los valores del tipo.
Ficha del centro (Livewire, si existe vista pública del centro): denominación vigente, tipo y UO funcional.
Paso 10 — Tests, documentación y cierre
Implementa los tests de docs/instrucciones-cli/2026-10-tipo-centro-tests.md.
Ejecuta los filtros de Centro, Agenda, Prestacion, Intervencion y, en rama A, los tests de asignación (TF-ASG); después la suite completa. No des la fase por terminada con fallos nuevos.
Actualiza los documentos de modulo-tipo-centro.md §11.
docs/documentacion-proyecto.md: secciones de Centros, Agenda y Prestaciones.
CHANGELOG.md, con la rama seguida (A o B), el mapeo de tipos aplicado y las decisiones de implementación que no estaban en estas instrucciones.
BACKLOG.md: decisiones pendientes de modulo-tipo-centro.md §13; columnas y tablas retiradas pendientes de eliminar; en rama B, el ajuste pendiente de 2026-09-asignacion-implementacion.md.
Añade este fichero y el de tests a la tabla de CLAUDE.md (sección 6). Actualiza SESSION.md.
Criterios de finalización
Tests existentes de los módulos afectados sin fallos nuevos.
TF-TCE-01 a TF-TCE-34 implementados y pasando.
Suite completa sin fallos nuevos.
Ninguna lectura de centros.inscripcion_libre, centro_prestacion, centro_segmento_poblacion, centros.horario ni de los campos de horario de horarios_centro fuera de ConfiguracionEfectivaCentro y de las migraciones (verificable con grep; indica el resultado en el CHANGELOG).
Todos los centros con tipo_centro_id; ninguna fila de prestacion_tipo_centro sin tipo_centro_id.
Ningún camino de código permite cambiar centros.tipo_centro_id ni tipos_centro.codigo.
ui:auditar en verde.
Documentación, CHANGELOG, BACKLOG, CLAUDE.md y SESSION.md actualizados.
