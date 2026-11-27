<?php

/*
|--------------------------------------------------------------------------
| Auditoría: textos de «Últimos accesos» y del detalle (usabilidad U3)
|--------------------------------------------------------------------------
|
| - `modelos`: nombre de cada modelo auditable con artículo, para el resumen por defecto
|   («Registró un objetivo del plan») y para «Registro» en el detalle.
| - `campos`: nombres en español de los campos que se enseñan en el detalle (antes → después).
|   Un campo sin etiqueta no se enseña y deja un aviso en el log fuera de producción.
| - `campos_protegidos`: campos cuyo cambio se indica sin enseñar el valor (contenido de
|   apuntes, notas, datos de fichas…: pueden ser privados o estar cifrados, U3.2).
| - `campos_ocultos`: identificadores y datos técnicos que no se enseñan nunca, sin aviso.
| - `valores`: etiquetas de los valores de algunos campos.
|
*/

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Models\PisoFirmado;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanActuacionCiudadano;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanFichaDiagnostico;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanParticipante;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Intervencion\Models\Valoracion;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Models\SolicitudPrestacion;

return [
    'acciones' => [
        'ver' => 'Consultó el expediente',
        'crear' => 'Registró nueva información',
        'editar' => 'Modificó información existente',
        'eliminar' => 'Eliminó un registro',
        'exportar' => 'Exportó datos',
        'imprimir' => 'Generó un documento',
        'acceso_restringido' => 'Accedió a expediente protegido',
    ],

    'modelos' => [
        Ciudadano::class => 'los datos de la persona',
        HistoriaSocial::class => 'la Historia Social',
        SolicitudCita::class => 'una solicitud de cita',
        InscripcionSesion::class => 'una inscripción en una actividad',
        Prescripcion::class => 'una prescripción',
        CiudadanoIdentificador::class => 'un documento de identidad',
        CiudadanoRelacion::class => 'una relación con otra persona',
        UnidadConvivencia::class => 'una unidad de convivencia',
        UnidadConvivenciaMiembro::class => 'un miembro de la unidad de convivencia',
        Documento::class => 'un documento',
        DocumentoVersion::class => 'una versión de un documento',
        DocumentoVinculo::class => 'un vínculo de un documento',
        Informe::class => 'un informe',
        PisoFirmado::class => 'el PISO firmado',
        PaseEscala::class => 'una escala',
        Apunte::class => 'un apunte',
        Derivacion::class => 'una derivación',
        Entrevista::class => 'una entrevista',
        Ficha::class => 'una ficha',
        PlanActuacionAyuntamiento::class => 'un compromiso del Ayuntamiento en el plan',
        PlanActuacionCiudadano::class => 'un compromiso de la persona en el plan',
        PlanDeIntervencion::class => 'un plan de intervención',
        PlanFichaDiagnostico::class => 'una ficha del diagnóstico del plan',
        PlanObjetivo::class => 'un objetivo del plan',
        PlanParticipante::class => 'un participante del plan',
        SeguimientoPlan::class => 'un seguimiento del plan',
        Valoracion::class => 'una valoración',
        PrestacionPersona::class => 'una prestación de la persona',
        SolicitudPrestacion::class => 'una solicitud de prestación',
    ],

    'campos' => [
        'estado' => 'Estado',
        'fecha' => 'Fecha',
        'fecha_hora' => 'Fecha y hora',
        'fecha_inicio' => 'Fecha de inicio',
        'fecha_fin' => 'Fecha de fin',
        'fecha_prescripcion' => 'Fecha de prescripción',
        'fecha_asignacion' => 'Fecha de asignación',
        'fecha_baja' => 'Fecha de baja',
        'fecha_firma' => 'Fecha de firma',
        'fecha_cierre' => 'Fecha de cierre',
        'fecha_concesion' => 'Fecha de concesión',
        'fecha_constitucion' => 'Fecha de constitución',
        'fecha_disolucion' => 'Fecha de disolución',
        'fecha_emision' => 'Fecha de emisión',
        'fecha_validez' => 'Fecha de validez',
        'fecha_envio' => 'Fecha de envío',
        'fecha_inicio_prevista' => 'Inicio previsto',
        'fecha_fin_prevista' => 'Fin previsto',
        'fecha_fin_real' => 'Fin real',
        'fecha_siguiente_seguimiento' => 'Siguiente seguimiento',
        'tipo' => 'Tipo',
        'tipo_destino' => 'Tipo de recurso',
        'tipo_relacion' => 'Tipo de relación',
        'visibilidad' => 'Visibilidad',
        'via' => 'Vía',
        'modalidad' => 'Modalidad',
        'nivel' => 'Nivel',
        'orden' => 'Orden',
        'rol_en_plan' => 'Papel en el plan',
        'motivo_cancelacion' => 'Motivo de la cancelación',
        'motivo_baja' => 'Motivo de la baja',
        'motivo_cierre' => 'Motivo del cierre',
        'motivo_retirada' => 'Motivo de la retirada',
        'motivo_rechazo' => 'Motivo del rechazo',
        'motivo_anulacion' => 'Motivo de la anulación',
        'motivo_suspension' => 'Motivo de la suspensión',
        'motivo_fin' => 'Motivo del fin',
        'titulo' => 'Título',
        'numero_expediente' => 'Número de expediente',
        'completada' => 'Completada',
        'no_contesta' => 'No desea contestar',
        'verificado' => 'Verificado',
        'activo' => 'Activo',
        'requiere_revision_plan' => 'Requiere revisar el plan',
        'periodicidad_seguimiento' => 'Periodicidad del seguimiento',
        'score_total' => 'Puntuación total',
        'interpretacion_codigo' => 'Interpretación',
        'version' => 'Versión',
        'urgencia' => 'Urgencia',
        'canal' => 'Canal',
        'resultado' => 'Resultado',
        'fuente' => 'Fuente',
        'sexo' => 'Sexo',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'ciudadano_protegido' => 'Persona protegida',
        'colectivo_extra_protegido' => 'Protección especial',
        'es_vvg' => 'Víctima de violencia de género',
        'es_psh' => 'Persona sin hogar',
        'nivel_identificacion' => 'Nivel de identificación',
        'visible_ciudadano' => 'Visible para la persona',
    ],

    'campos_protegidos' => [
        'contenido' => 'Contenido',
        'notas' => 'Notas',
        'notas_generales' => 'Notas',
        'datos' => 'Datos de la ficha',
        'respuestas' => 'Respuestas',
        'observaciones' => 'Observaciones',
        'avances' => 'Avances',
        'incidencias' => 'Incidencias',
        'objetivos_cumplidos' => 'Objetivos cumplidos',
        'nuevas_prestaciones' => 'Nuevas prestaciones',
        'texto' => 'Texto',
        'descripcion' => 'Descripción',
        'descripcion_especifica' => 'Descripción',
        'diagnostico_social' => 'Diagnóstico social',
        'objetivos' => 'Objetivos',
        'resumen' => 'Resumen',
        'motivo' => 'Motivo',
        'condiciones' => 'Condiciones',
        'domicilio' => 'Domicilio',
        'nombre' => 'Nombre',
        'apellido1' => 'Primer apellido',
        'apellido2' => 'Segundo apellido',
        'alias' => 'Alias',
        'telefono' => 'Teléfono',
        'email' => 'Correo electrónico',
        'direccion_texto' => 'Dirección',
        'valor' => 'Número del documento',
        'primera_demanda' => 'Primera demanda',
        'observaciones_citacion' => 'Observaciones de la citación',
        'metadatos' => 'Metadatos',
        'configuracion_snapshot' => 'Configuración',
        'schema_snapshot' => 'Estructura de la ficha',
        'scores_seccion' => 'Puntuaciones por sección',
    ],

    'campos_ocultos' => [
        'id', 'uuid', 'ciudadano_id', 'historia_id', 'plan_id', 'plan_intervencion_id', 'plan_de_intervencion_id',
        'profesional_id', 'prestacion_id', 'compromiso_id', 'destino_id', 'plaza_id', 'sesion_actividad_id',
        'prescripcion_id', 'inscrita_por_id', 'baja_por_id', 'unidad_convivencia_id', 'ciudadano_relacionado_id',
        'verificado_por', 'verificado_en', 'autor_id', 'apuntable_type', 'apuntable_id', 'cita_id', 'evento_agenda_id',
        'derivacion_id', 'tipo_escala_id', 'ficha_id', 'entrevista_id', 'tipo_ficha_id', 'valoracion_id',
        'objetivo_catalogo_id', 'responsable_id', 'user_id', 'servicio_id', 'tipo_plan_id', 'centro_id',
        'servicio_especializado_id', 'profesional_responsable_id', 'plan_asp_id', 'tipo_valoracion_id',
        'tipo_documental_id', 'created_by', 'retirado_en', 'retirado_por_id', 'organo_emisor', 'documento_id',
        'vinculable_type', 'vinculable_id', 'creado_por', 'telefono_hash', 'email_hash', 'valor_hash',
        'unidad_organizativa_id', 'origen_type', 'origen_id', 'plan_objetivo_id', 'registrada_por_id',
        'seguimiento_id', 'centro_solicitud_id', 'unidad_tramitadora_id', 'creada_por_id', 'sugerida_id',
        'estado_externo_codigo', 'estado_externo_texto', 'estado_externo_normalizado', 'ultima_actualizacion_en',
        'plan_origen_id', 'centro_destino_id', 'derivada_por_id', 'resuelta_por_id', 'plan_especializado_id',
        'resuelta_en', 'cerrada_en', 'cerrada_por_id', 'area_encargo_id', 'centro_id', 'solicitante_id',
        'tipo_cita_id', 'profesional_destino_id', 'contexto_type', 'contexto_id', 'solicitud_anterior_id',
        'gestionada_por_id', 'aviso_fuera_plazo_en', 'latitud', 'longitud', 'coordenadas_lat', 'coordenadas_lng',
        'pernocta_lat', 'pernocta_lng', 'direccion_normalizada', 'origen_direccion', 'tipo_via', 'nombre_via',
        'tipo_numeracion', 'numero', 'portal', 'escalera', 'piso', 'puerta', 'codigo_postal', 'municipio',
        'geocoder_proveedor', 'codigo_ndp', 'distrito_codigo', 'barrio_codigo', 'seccion_censal_codigo',
        'contexto_alta', 'colectivo_principal', 'zona_intervencion', 'destino', 'servicio_destino',
        'no_antes_de', 'no_despues_de', 'registrada_por_id', 'fecha_captura',
    ],

    'valores' => [
        'estado' => [
            'pendiente' => 'Pendiente',
            'en_lista_espera' => 'En lista de espera',
            'asignada' => 'Asignada',
            'activa' => 'Activa',
            'activo' => 'Activo',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            'borrador' => 'Borrador',
            'en_revision' => 'En revisión',
            'cerrado' => 'Cerrado',
            'cerrada' => 'Cerrada',
            'abierta' => 'Abierta',
            'baja' => 'Baja',
            'vigente' => 'Vigente',
            'retirado' => 'Retirado',
            'en_curso' => 'En curso',
            'completada' => 'Completada',
            'completado' => 'Completado',
        ],
        'tipo_destino' => [
            'sesion_actividad' => 'Sesión de actividad',
            'coleccion_plazas' => 'Plazas',
        ],
        'visibilidad' => [
            'privada' => 'Privada',
            'profesionales' => 'Para profesionales',
        ],
        'via' => [
            'libre' => 'Acceso libre',
            'prescripcion' => 'Prescripción',
        ],
    ],
];
