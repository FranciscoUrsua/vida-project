Estado del proyecto VIDA 360 — Revisión documental

Fecha: 10 noviembre 2026 Alcance: documentos del proyecto (análisis funcional, instrucciones CLI, tests, propuesta de requisitos y tareas guiadas) cruzados con el repositorio vida-project (rama master): SESSION.md, BACKLOG.md, CHANGELOG-*.md, CLAUDE.md y docs/. Último commit en master: 2 octubre 2026 (refactor(usuarios): una sola pantalla de roles y permisos; retirada de zonas). SESSION.md también es del 2 de octubre.

Desde el 2 de octubre no hay registro de desarrollo. Todo lo posterior (Tipo de Centro, propuesta de requisitos y tareas guiadas) existe solo como análisis en el proyecto.

1. Situación por módulo
Implementado, con pendientes
Módulo	Fecha	Pendientes principales
Asignación de centro y referencia	30 sep	Adaptador real BDC (hoy solo mock); cambiar la referencia de un caso ya asignado desde la interfaz; autorización por centro dentro de los servicios; centros reales de staging sin configurar (todos en quien_abre, sin tipo); decisiones §11 (PSH, zonas por profesional, cambio a petición, fórmulas mixtas, revisión de secciones)
Citas	30 sep	Paso 10 (citas en mundos demo) pospuesto; contrato con Cita Previa; el cuadrante del supervisor no enlaza con el detalle de la cita; tipo de slot genérico en modo basico inexistente; cambios del diseño posteriores a la implementación (ver §3.B)
Acceso y auditoría de expedientes	2 oct	Personas relacionadas protegidas visibles en ficha y expediente; VerFichaPage no comprueba que la ficha sea de la historia; búsqueda por documento en BuscarCiudadanoPage; bandeja de asignaciones con protegidos no identificables; guardar() de la ficha decide por rol, no por policy
Documentos (custodia v2)	25 sep	Sin UI operativa de informes ni PISO; sin variables auxiliares; hito que crea retención intervencion_cerrada; proveedor de clave maestra en producción; documentos compartidos protegido/no protegido; destrucción no programada en el scheduler
Mensajes y alertas	26–27 sep	Paso 6 de sus instrucciones; alertas «a cualquiera del colectivo»; avisos por cargo; supervisor de UO padre; enlace al origen en la bandeja
Usuarios y roles	25 sep – 2 oct	Cuatro ojos en aprobación de roles; destinatario de la alerta (UO propia o superior); borrar usuario con casos vigentes
Agenda	jun–oct	Perfil horario por defecto (45 h en el CIAM, sin pausa); varias UO con varios centros
Intervención	jun–sep	PlanPage guarda siempre tipo = general_asp: un PIA creado desde la UI se trata como PISO; no hay asociación tipo de plan ↔ centro; tabla derivaciones inexistente
Escalas	may	Fase 2 (aplicación del pase en la Historia Social) sin punto de entrada definido; licencias Zarit y GDS
Actividades grupales	jun	Solo alta: faltan edición, baja, sesiones e inscripción de ciudadanos desde Intervención
Diseño cerrado, sin implementar
Documento	Situación
modulo-tipo-centro.md + instrucciones + 34 tests (TF-TCE)	Listo para ejecutar. Los tres documentos no están en el repositorio ni en la tabla de CLAUDE.md §6. Corresponde la rama A (la asignación ya está implementada)
roles-consulta-basica-tramitacion.md — parte consulta_basica	Diseño cerrado. Sin instrucciones CLI ni tests. No está en el repositorio
modulo-citas.md, revisión del 30 sep por la tarde	RN-05 ter (gestión limitada a la UO del usuario) y §6 bis (historial de citas en la ficha para consulta_basica/tramitacion) no se implementaron
Diferido
tramitacion (cinco cuestiones abiertas en roles-consulta-basica-tramitacion.md §4.2).
Integraciones reales: Cita Previa, BDC, HSU (todo con mock).
Notificaciones y comunicaciones con el ciudadano.
Indicadores y analítica (citas, asignación, panel de mando).
Importación de datos históricos del CIAM (proyecto de migración aparte).
En análisis inicial
Requisitos de acción y Tareas guiadas (propuesta-requisitos-y-tareas-guiadas.md): notas de partida, con bastante por decidir (ámbitos, precedencia, catálogos de acciones y condiciones, pasos condicionales, versionado de tareas, migración de los «Procesos»).
Señal visual del contexto del profesional: decidido mostrar tipo y nombre del centro en la barra superior; colores aplazados.
Grupos multifuncionales: sin documento propio; se tratan como caso de uso de las tareas guiadas.
2. Backlog relevante, priorizado
A. Bloquean o condicionan el trabajo con el CIAM
Creación de PIA desde la interfaz (BACKLOG 24 sep): PlanPage::crearNuevoPlan() fija tipo = general_asp. Un PIA creado a mano queda como plan general y pisoActivo()/MisCasos lo tratan como el PISO. Falta además el flujo de planes especializados (derivación desde PISO y entrada directa). Tipo de Centro filtra qué tipos de plan se ofrecen, pero no corrige esto.
Tipo de plan por centro (BACKLOG 24 sep): lo resuelve Tipo de Centro (tiposPlan() por tipo). El BACKLOG no lo refleja.
Varias UO con varios centros (BACKLOG 1–2 oct, «el desarrollador lo está pensando»): el código supone un centro por UO (Centro::where('unidad_organizativa_id', …)->first(), CentroDeUsuario) en Mi equipo, cuadrante, asignación y perfil por defecto. La propuesta de noviembre (§3, profesional asignado a una UO superior a varios centros) y la regla «qué tipo de centro aplica» de los requisitos dependen de resolverlo.
Prueba en staging de asignación y citas por el usuario: primer paso recomendado en SESSION.md desde el 1 de octubre; no hay constancia de que se haya hecho.
B. Seguridad y privacidad
Pendientes de acceso a expedientes (ver §1).
Base de datos local = staging (vida@127.0.0.1): cualquier migración o demo:* en local se aplica en staging. Recomendado crear vida_dev.
Documento compartido entre persona protegida y no protegida: se abre por la no protegida (regla provisional).
Cuatro ojos en la aprobación de roles.
Pendientes legales de anonimización (K, colectivos protegidos en extracciones, PSH, validación del DPD) antes de cualquier publicación de datos.
C. Calidad y estabilidad
CI «Calidad de código» en rojo (PHPStan sin memoria; ~195 errores; baseline de 772).
Modules/Supervision/tests fuera de phpunit.xml (3 fallos conocidos que la suite completa nunca ejecuta).
Fallos previos conocidos: TF-AUTH-16/17 (User::booted() pisa name), AutorizacionDatosTest, Ciudadanía «Ver historia social» (2).
Rendimiento de CiudadanoPage (1.131 líneas) y plan-page (~66 KB); polling de toasts en todas las pantallas.
Retirada de los datos TEST_CIAM: no hay comando de purga.
D. Decisiones funcionales pendientes con impacto próximo
Borrar un usuario que es referencia de casos: avisar, bloquear o lanzar el reparto.
Perfil horario por defecto (apertura a cierre = 45 h en el CIAM): descontar pausa o usar jornada estándar.
Bandeja de asignaciones: cómo resuelve el supervisor una entrada de una persona protegida que no puede identificar.
Tipo de Centro §13: UO funcional y permisos, canales de cita por tipo, traslado en bloque, cargos por tipo, modelos de informe por tipo, protocolos estructurados.
3. Incoherencias entre análisis y registros de desarrollo
A. Estados de los documentos desactualizados
Documento	Dice	Realidad
modulo-asignacion.md (proyecto)	Sin implementar	Implementado el 30 sep (la copia del repo sí lo dice)
modulo-citas.md (repo)	Sin implementar	Implementado el 30 sep
modulo-agenda.md (repo)	Interfaz Livewire/Filament pendiente	Hay interfaz: cuadrante del supervisor, agenda del profesional, citación, ausencias
docs/decisiones-pendientes.md (repo)	Actualizado en junio 2026	No recoge nada de septiembre a noviembre; los totales no cuadran (32 en cabecera, 33 en el resumen)
B. Dos versiones de modulo-citas.md
La del repositorio es la versión con la que se implementó (30 sep por la mañana).
La del proyecto es posterior (30 sep, 14:40) y añade RN-05 ter, §6 bis, la columna de tramitacion diferida y las decisiones de ámbito por UO.
No hay en el código historial de citas en la ficha del ciudadano para consulta_basica, y no consta en el CHANGELOG que la gestión se limite a la UO del usuario. Hace falta un delta de implementación de citas.
C. Roles consulta_basica / tramitacion: decisiones no propagadas

roles-consulta-basica-tramitacion.md §6 enumera cambios en otros documentos que no se han hecho:

modulo-usuarios-permisos.md ROL 5 sigue con todas las capacidades de tramitación, sin marcarlo como diferido.
ROL 7 dice «Ámbito: limitado a su UO» (contradice que el ámbito limita la operación, no la consulta) y no incluye el alta de ciudadanos ni la edición de relaciones.
docs/design-system/SKILL.md: «Protected records: show the protected banner; never hide the status» contradice la decisión de no mostrar el banner a consulta_basica. Claude CLI lee este fichero antes de cualquier interfaz: si no se corrige, reintroducirá el banner.
modulo-ciudadania.md y modulo-documentos.md sin los cambios (bloques de la ficha por rol, documentos propios del día, retirada lógica y versiones).
D. Tipo de Centro frente a lo ya construido
modulo-tipo-centro.md §11 dice «si la fase de asignación no está implementada…»: ya lo está; las instrucciones prevén bien la rama A.
Riesgo: el paso 3 aborta si dos centros del mismo tipo tienen distinto modo de asignación, ventana o segmentos. Si para probar el sorteo se configura un CSS en sorteo y el resto sigue en quien_abre, la migración se detendrá. Conviene decidir antes los valores por tipo o probar el sorteo con todos los centros del tipo a la vez.
modulo-asignacion.md §8 sigue describiendo la configuración por centro, que Tipo de Centro mueve al tipo.
El BACKLOG («No existe asociación tipo de plan ↔ centro») no remite a Tipo de Centro.
E. Propuesta de requisitos y tareas guiadas frente al código
Habla de sustituir los «Procesos» programados en el código. En master no hay nada con ese nombre; hay que precisar a qué se refiere (¿las herramientas fijas del expediente, $herramientas en CiudadanoPage?).
Su ejemplo central (inscribir a una menor en una actividad del CIAM) depende de la inscripción de ciudadanos en actividades desde Intervención, que no existe (BACKLOG «Actividades grupales»).
El ámbito «tipo de centro» de los requisitos y la barra de contexto dependen de la entidad TipoCentro (sin implementar) y de resolver varios centros por UO.
F. BACKLOG con entradas probablemente obsoletas (verificar y cerrar)
Entrada	Por qué parece resuelta
Suite de Agenda rota por tipos_slot.horario_centro_id (24 sep)	Paso 0 de citas, 30 sep: Agenda 173 passed
Servicios pendientes de Agenda, 30 tests bloqueados (18 may)	Citas usa GestionAusenciaService, cuadrantes y slots; Agenda en verde
Integración real de auditoría en BuscarCiudadanoPage nivel 2 (1 jun)	2 oct: sin Log::info ni TODO
Tabla ciudadano_identificadores pendiente (10 jun) y DNI en cabecera	La tabla existe (el buscador de citación busca por hash)
«Suite completa con 75 fallos» (25 sep)	La mayoría eran de Agenda, ya corregidos
CI de calidad en rojo	Duplicada (29 sep y 1 oct)
UC en UI de intervención (16 jun)	Hay modal de UC y buscador en el expediente
Seguimiento del plan — UI (18 jun)	La ficha programa el siguiente seguimiento (con solicitud de cita)
Instalar dompdf (18 jun)	Probable; el PDF del plan está en uso
4. Orden de trabajo sugerido
Puesta al día documental (encargo corto a Claude CLI): subir al repo modulo-citas.md revisado, roles-consulta-basica-tramitacion.md y los tres documentos de Tipo de Centro; actualizar estados; aplicar §6 de roles (incluido el design system); limpiar el BACKLOG; actualizar decisiones-pendientes.md.
Decidir varios centros por UO y los valores de asignación por tipo antes de ejecutar Tipo de Centro.
Ejecutar Tipo de Centro (rama A): es requisito de CIAM, de los requisitos de acción y de la barra de contexto.
Corregir la creación de planes especializados (PIA), imprescindible para el CIAM.
Delta de citas + consulta_basica en un mismo juego de instrucciones y tests (RN-05 ter, §6 bis, ficha por rol, documentos propios del día).
Seguir con requisitos de acción y tareas guiadas cuando lo anterior esté en verde.

Seguimiento

Puesta al día documental del 2026-11-10 (docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md; detalle en CHANGELOG-112026.md). Una línea por punto de §3:

3.A Estados desactualizados — Resuelto. modulo-citas.md y modulo-agenda.md reescriben su línea Estado; también se corrigen modulo-auditoria.md y modulo-escala.md, que decían «pendiente de implementación». decisiones-pendientes.md actualizado (34 decisiones, totales recalculados). modulo-asignacion.md del repo ya decía «implementado».
3.B Dos versiones de modulo-citas.md — Resuelto en documentación: la revisión de la tarde sustituye a la del repo. RN-05 ter ya la cumple el código (CitaPolicy, SolicitudCitaPolicy y AlcanceCentro). §6 bis no está implementado: BACKLOG «Citas: delta de la revisión del 30 sep».
3.C Decisiones de roles no propagadas — Resuelto: modulo-usuarios-permisos.md (ROL 5, ROL 7, §4.4), modulo-ciudadania.md, modulo-documentos.md, docs/design-system/SKILL.md y README.md. Implementación pendiente: BACKLOG «consulta_basica: diseño cerrado sin instrucciones de implementación».
3.D Tipo de Centro frente a lo construido — Resuelto en documentación: modulo-tipo-centro.md §11 (rama A), nota al principio de modulo-asignacion.md §8, aviso del paso 3 en BACKLOG («Configurar los centros reales en staging») y remisión a Tipo de Centro en «No existe asociación tipo de plan ↔ centro». Nueva entrada «Tipo de Centro: listo para ejecutar».
3.E Propuesta de requisitos y tareas guiadas — Sin resolver: a qué se refieren los «Procesos» lo decide el desarrollador (candidatos en el informe final de la sesión, no anotados). Dependencias registradas en BACKLOG «Requisitos de acción y tareas guiadas — en análisis».
3.F BACKLOG con entradas obsoletas — Resuelto: cerradas con evidencia Agenda rota, servicios pendientes de Agenda, auditoría nivel 2, ciudadano_identificadores, UC en intervención, dompdf, TipoRelacionSeeder y [Demo] citas (duplicada); CI de calidad fundida en una entrada. Siguen abiertas, con nota: «Suite completa con 75 fallos» (solo los fallos que quedan), «Seguimiento del plan — UI» (falta la evaluación de objetivos) y, dentro de «TODOs activos en CiudadanoPage», centroActivo(), el menú ⋯ y statPrestaciones().
