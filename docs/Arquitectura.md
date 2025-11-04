# Arquitectura de VIDA

## Introducción

La arquitectura de **VIDA: Visión Integral de Derechos y Atención Social** sigue un modelo cliente-servidor modular, diseñado para garantizar escalabilidad, seguridad y facilidad de mantenimiento en el contexto de la gestión de servicios sociales. El backend, basado en Laravel, actúa como núcleo central manejando la lógica de negocio sensible (como valoraciones de casos y prestaciones), mientras que la base de datos PostgreSQL asegura el almacenamiento robusto y compliant con normativas como RGPD. El frontend en Angular (en fase de desarrollo) proporcionará interfaces intuitivas para trabajadores sociales, fomentando una interacción proactiva y centrada en el usuario. Esta estructura permite integraciones futuras con sistemas externos (e.g., APIs autonómicas) y soporta despliegues en entornos cloud o locales. A continuación, se presenta un diagrama de alto nivel que ilustra las componentes clave y sus interacciones.

```mermaid
graph TD
    %% Definir nodos con estilos
    A[Usuario / Trabajador Social<br/>Dispositivo]:::user
    B[Frontend: Angular<br/>Dashboards & Formularios]:::frontend
    C[API Gateway / Laravel Backend<br/>Endpoints RESTful<br/>Autenticación Sanctum]:::backend
    D[Base de Datos: PostgreSQL<br/>Casos Sociales, Prestaciones<br/>Migraciones Eloquent]:::db
    E[Integraciones Externas<br/>e.g., APIs de CCAA o RGPD]:::external

    %% Flujos de conexión
    A -->|"HTTPS Requests"| B
    B -->|"API Calls (JSON)"| C
    C -->|"Queries / Inserts"| D
    C -->|"Opcional: Webhooks"| E
    D -->|"Responses"| C

    %% Estilos CSS para nodos
    classDef user fill:#e1f5fe,stroke:#01579b,stroke-width:2px,color:#000
    classDef frontend fill:#f3e5f5,stroke:#4a148c,stroke-width:2px,color:#000
    classDef backend fill:#e8f5e8,stroke:#1b5e20,stroke-width:2px,color:#000
    classDef db fill:#fff3e0,stroke:#e65100,stroke-width:2px,color:#000
    classDef external fill:#fce4ec,stroke:#880e4f,stroke-width:2px,color:#000
```

Documentación del Modelo de Datos para VIDA: Visión Integral de Derechos y Atención Social
Introducción
Este documento describe el modelo de datos central del proyecto VIDA, diseñado para gestionar de forma eficiente y segura las intervenciones sociales en el contexto de los Servicios Sociales del Ayuntamiento de Madrid. El modelo se inspira en el Plan Estratégico de Servicios Sociales 2023-2027 (que enfatiza la personalización de planes operativos y el seguimiento para promover la autonomía, págs. 37-46 y 59) y la Guía de Prestaciones de Servicios Sociales y Educativos 2024 (que agrupa 112 prestaciones en áreas como familia, vivienda y economía, con un enfoque en simplificar el acceso y reducir la burocracia, págs. 2-3).
El núcleo del modelo es la entidad Historia (anteriormente "Caso"), que representa el hilo completo de una intervención individualizada para un beneficiario o familia. Este se relaciona con Valoraciones (iniciales y sucesivas), Fichas (detalladas por tipo, como familiar o económica), Planes de Intervención (PISO, simples o especializados) y Seguimientos (periódicos para revisión).
El diseño es relacional, optimizado para PostgreSQL y Laravel (con Eloquent ORM para relaciones), priorizando escalabilidad (para decenas de entidades), seguridad (encriptación de datos sensibles per GDPR y Decreto 51/2023) y flexibilidad (para integrar IA en fichas o generación de PDFs). Se usan tablas auxiliares (ej: tipos de fichas) para lookups eficientes y polimorfismos para derivaciones especializadas.
Entidades Principales y Relaciones
A continuación, se detalla cada entidad clave, sus atributos principales (sugeridos para el esquema) y relaciones. Las relaciones siguen convenciones Eloquent (hasMany, belongsTo, etc.), con foreign keys indexadas para rendimiento. Se incluyen mejoras para compliance y usabilidad, como estados enum y campos auditables.









































Entidad PrincipalAtributos ClaveRelaciones ClaveNotas/Mejoras SugeridasHistoria (raíz del ciclo de intervención: apertura de un nuevo acceso a servicios)- id (PK)
- beneficiario_id (FK a Beneficiario)
- estado (enum: 'abierto', 'seguimiento', 'alta')
- fecha_apertura
- centro_servicios_id (FK)
- created_at, updated_at- hasMany: Valoraciones
- hasMany: PlanesDeIntervencion (o morphMany para especializados)
- morphMany: Documentos (polimórfico para PDFs como PISO)
- belongsTo: Beneficiario (entidad auxiliar para persona/familia)- Centraliza el lifecycle (alineado con "atención centrada en la persona", Guía pág. 3).
- Auto-pull de datos iniciales del Registro Único de Usuarios (Plan pág. 9).
- Campo JSON para metadatos (ej: distritos de Madrid).Valoracion (evaluación inicial o de seguimiento, compuesta por fichas detalladas)- id (PK)
- historia_id (FK)
- trabajador_social_id (FK)
- fecha_realizacion
- tipo (enum: 'inicial', 'sucesiva')
- resumen (texto, opcional para IA)
- created_at, updated_at- belongsTo: Historia
- hasMany: Fichas
- belongsTo: TipoFicha (tabla auxiliar: id, nombre ej: 'familia', descripcion, schema JSON para campos)
- belongsTo: TrabajadorSocial- Permite evoluciones temporales (Plan pág. 59: seguimiento y evaluación).
- Campo JSON resumen_ia para extracciones futuras de texto libre.
- Validaciones por ficha para completitud (ej: campos obligatorios HSU).Ficha (módulos granulares de la valoración, por área vital)- id (PK)
- valoracion_id (FK)
- tipo_ficha_id (FK)
- datos (JSON para campos dinámicos: ej: {ingresos: 800, hijos: 2})
- notas (texto libre)
- created_at, updated_at- belongsTo: Valoracion
- belongsTo: TipoFicha
- morphMany: Campos (opcional, si subcampos dinámicos por tipo)- Tabla auxiliar tipos_fichas con schema JSON para guiado UX (ej: campos obligatorios por 'económica').
- Soft deletes para revisiones históricas.
- Integra con wizards modulares para simplificar rellenado (evita tedio en áreas como vivienda).PlanDeIntervencion (PISO: documento formal con prestaciones y compromisos)- id (PK)
- historia_id (FK)
- tipo (enum: 'general', 'especializado' ej: familia)
- compromisos_ciudadano (JSON array: ej: ['curso FP'])
- prestaciones (JSON o many-to-many con tabla Prestaciones)
- fecha_firma
- estado (enum: 'activo', 'pausado', 'cerrado')
- created_at, updated_at- belongsTo: Historia (o morphTo para subtipos especializados)
- hasMany: Seguimientos
- belongsTo: Valoracion (última como base)
- morphMany: Documentos (para PDF generado)- Captura bidireccionalidad (prestaciones de Guía + compromisos, Plan pág. 35: promoción de capacidades).
- Relación con 112 prestaciones de Guía via pivot (compromisos recíprocos).
- Soporte para derivaciones (ej: plan específico para género).Seguimiento (revisiones periódicas del plan, con mini-evaluaciones)- id (PK)
- plan_id (FK)
- trabajador_social_id (FK)
- fecha_seguimiento
- notas_evolucion (texto, con opción IA)
- avances (JSON: progreso en compromisos)
- created_at, updated_at- belongsTo: PlanDeIntervencion
- belongsTo: TrabajadorSocial
- hasOne: Valoracion (opcional, para fichas de revisión)- Cronológico para trazabilidad (Plan pág. 3: prevención de enquistamiento).
- Campo alertas (JSON) para gaps detectados (ej: no avances en ingresos).
- Relación inversa a Alta (entidad separada para cierre, con motivos).
Notas generales sobre el modelo

Entidades auxiliares: Incluye Beneficiario (id, nombre, dni_hash para privacidad, direccion via Base de Datos Ciudad), TrabajadorSocial (id, rol) y Prestacion (de Guía: id, tipo, descripcion) para completitud.
Seguridad y compliance: Todos los campos sensibles (ej: datos en Fichas) encriptados (pgcrypto en PostgreSQL). Auditoría via timestamps y created_by. Retención automática post-alta (6 meses, per protocolo ético).
Escalabilidad: ~7 tablas principales; usa índices en FKs para queries como "seguimientos pendientes por Historia". Para casos especiales (sin hogar, menores), subtipos via tipo enum o herencia polimórfica.
Integraciones: Soporte para IA (ej: estructuración de notas en Fichas), generación de PDFs (via DomPDF, como discutido) y flujos guiados (wizards en frontend).

Diagrama ER (Entity-Relationship)
A continuación, un diagrama ER conceptual en formato Mermaid (renderizable en GitHub o tools como Mermaid Live). Representa entidades como rectángulos, relaciones como líneas con cardinalidad (1:1, 1:N, N:N), y atributos clave en óvalos. Enfocado en el núcleo; auxiliares como Beneficiario se muestran simplificados.
#mermaid-diagram-mermaid-qt0kt4l{font-family:"trebuchet ms",verdana,arial,sans-serif;font-size:16px;fill:#000000;}@keyframes edge-animation-frame{from{stroke-dashoffset:0;}}@keyframes dash{to{stroke-dashoffset:0;}}#mermaid-diagram-mermaid-qt0kt4l .edge-animation-slow{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 50s linear infinite;stroke-linecap:round;}#mermaid-diagram-mermaid-qt0kt4l .edge-animation-fast{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 20s linear infinite;stroke-linecap:round;}#mermaid-diagram-mermaid-qt0kt4l .error-icon{fill:#552222;}#mermaid-diagram-mermaid-qt0kt4l .error-text{fill:#552222;stroke:#552222;}#mermaid-diagram-mermaid-qt0kt4l .edge-thickness-normal{stroke-width:1px;}#mermaid-diagram-mermaid-qt0kt4l .edge-thickness-thick{stroke-width:3.5px;}#mermaid-diagram-mermaid-qt0kt4l .edge-pattern-solid{stroke-dasharray:0;}#mermaid-diagram-mermaid-qt0kt4l .edge-thickness-invisible{stroke-width:0;fill:none;}#mermaid-diagram-mermaid-qt0kt4l .edge-pattern-dashed{stroke-dasharray:3;}#mermaid-diagram-mermaid-qt0kt4l .edge-pattern-dotted{stroke-dasharray:2;}#mermaid-diagram-mermaid-qt0kt4l .marker{fill:#666;stroke:#666;}#mermaid-diagram-mermaid-qt0kt4l .marker.cross{stroke:#666;}#mermaid-diagram-mermaid-qt0kt4l svg{font-family:"trebuchet ms",verdana,arial,sans-serif;font-size:16px;}#mermaid-diagram-mermaid-qt0kt4l p{margin:0;}#mermaid-diagram-mermaid-qt0kt4l .entityBox{fill:#eee;stroke:#999;}#mermaid-diagram-mermaid-qt0kt4l .relationshipLabelBox{fill:hsl(-160, 0%, 93.3333333333%);opacity:0.7;background-color:hsl(-160, 0%, 93.3333333333%);}#mermaid-diagram-mermaid-qt0kt4l .relationshipLabelBox rect{opacity:0.5;}#mermaid-diagram-mermaid-qt0kt4l .labelBkg{background-color:rgba(237.9999999999, 237.9999999999, 237.9999999999, 0.5);}#mermaid-diagram-mermaid-qt0kt4l .edgeLabel .label{fill:#999;font-size:14px;}#mermaid-diagram-mermaid-qt0kt4l .label{font-family:"trebuchet ms",verdana,arial,sans-serif;color:#000000;}#mermaid-diagram-mermaid-qt0kt4l .edge-pattern-dashed{stroke-dasharray:8,8;}#mermaid-diagram-mermaid-qt0kt4l .node rect,#mermaid-diagram-mermaid-qt0kt4l .node circle,#mermaid-diagram-mermaid-qt0kt4l .node ellipse,#mermaid-diagram-mermaid-qt0kt4l .node polygon{fill:#eee;stroke:#999;stroke-width:1px;}#mermaid-diagram-mermaid-qt0kt4l .relationshipLine{stroke:#666;stroke-width:1;fill:none;}#mermaid-diagram-mermaid-qt0kt4l .marker{fill:none!important;stroke:#666!important;stroke-width:1;}#mermaid-diagram-mermaid-qt0kt4l :root{--mermaid-font-family:"trebuchet ms",verdana,arial,sans-serif;}pertenece acontienecompuesta porde tipoasociatienerealizada porgestionada porrealizada porgenera (polimórfico)genera (polimórfico)genera (polimórfico)HISTORIAintidPKenumestadodatetimefecha_aperturaBENEFICIARIOintidPKstringnombrestringdni_hashVALORACIONintidPKenumtipodatetimefecha_realizacionFICHAintidPKjsondatostextnotasTIPO_FICHAintidPKstringnombreej: familiajsonschemaPLAN_DE_INTERVENCIONintidPKenumtipojsoncompromisos_ciudadanodatetimefecha_firmaenumestadoSEGUIMIENTOintidPKdatetimefecha_seguimientojsonavancesTRABAJADOR_SOCIALintidPKstringrolDOCUMENTOintidPKstringpatho binariodatetimefecha
Este diagrama ilustra el flujo jerárquico: una Historia ramifica en Valoraciones/Fichas para diagnóstico, y Planes/Seguimientos para acción y revisión.
