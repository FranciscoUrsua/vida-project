# Seeders — VIDA 360

Inventario completo de seeders del proyecto, con descripción de los datos que genera cada uno,
el orden de ejecución y el estado de integración en el flujo principal.

---

## Flujo principal — `DatabaseSeeder`

`DatabaseSeeder` es el punto de entrada único. Se ejecuta con `php artisan db:seed` desde `vida/`.
Invoca los seeders en el siguiente orden:

```
1. PermisosSeeder
2. RolesSeeder
3. UoSeeder
4. OrganizacionSeeder  →  ConfiguracionSeeder
                       →  ColectivosProtegidosSeeder
                       →  ServiciosEmergenciaSeeder
                       →  DistritosSeeder
5. AgendaSeeder
6. CargosSeeder
7. TitulacionesSeeder
8. TiposRelacionProfesionalSeeder
9. [Crea usuario admin directamente]
```

⚠️ **`PrestacionesSeeder`, `CatalogosSistemaSeeder` y `DocumentosSeeder` no están integrados
en `DatabaseSeeder`** — deben ejecutarse manualmente hasta que se decida incluirlos.

---

## Seeders del core (`database/seeders/`)

### `PermisosSeeder`
Crea los **22 permisos atómicos** del sistema en formato `recurso.accion`.
Todos los permisos tienen correspondencia con una verificación en código (Policy, middleware o Gate).
Idempotente: usa `firstOrCreate`.

Permisos por área:

| Área | Permisos |
|---|---|
| Ciudadano | `ciudadano.ver_ficha`, `ciudadano.ver_datos_contacto`, `ciudadano.crear`, `ciudadano.editar` |
| Historia Social | `historia.leer`, `historia.abrir`, `historia.editar`, `historia.cerrar` |
| Apunte | `apunte.crear`, `apunte.leer_propio`, `apunte.leer_ajeno` |
| Plan de Intervención | `plan.crear`, `plan.editar` |
| Prestaciones | `prestacion.asignar` |
| Usuarios | `usuario.crear`, `usuario.editar`, `usuario.dar_baja` |
| Centros | `centro.gestionar` |
| Configuración | `configuracion.acceder` |
| Trazabilidad | `trazabilidad.consultar` |
| Colectivos protegidos | `colectivo_protegido.solicitar_acceso`, `colectivo_protegido.aprobar_acceso` |

---

### `RolesSeeder`
Crea los **7 roles** del sistema y asigna sus permisos mediante `syncPermissions` (idempotente).
Debe ejecutarse después de `PermisosSeeder`.

| Rol | Descripción |
|---|---|
| `adm_sistema` | Acceso global. Todos los permisos. |
| `supervision` | Lectura de su ámbito, auditoría y aprobación de accesos protegidos. |
| `adm_usuarios` | Gestión de usuarios en su UO. |
| `intervencion` | Gestión completa de Historias Sociales en su UO. |
| `tramitacion` | Apertura/cierre de Historias y gestión administrativa. Sin acceso a contenido clínico. |
| `consulta_profesional` | Acceso acotado a ciudadanos y procesos asignados explícitamente. |
| `consulta_basica` | Ficha del ciudadano y citas. Sin acceso a Historia ni apuntes. |

---

### `UoSeeder`
Crea el **catálogo de 5 tipos de Unidad Organizativa** y una **jerarquía de ejemplo** de 6 nodos.
Idempotente: usa `firstOrCreate` y `updateOrInsert`.

```
Ayuntamiento de Madrid
└── Área de Gobierno de Políticas Sociales, Familia e Igualdad
    └── Dirección General de Servicios Sociales
        └── Departamento de Atención Primaria
            ├── CSS Arganzuela
            └── CSS Retiro
```

---

### `CargosSeeder`
Crea el **catálogo de 13 cargos profesionales** habituales en servicios sociales municipales.
Idempotente: `firstOrCreate` por nombre.

Trabajador/a Social · Psicólogo/a · Educador/a Social · Terapeuta Ocupacional ·
Auxiliar de Servicios Sociales · Abogado/a · Técnico/a de Integración Social ·
Mediador/a Social · Coordinador/a de Centro · Técnico/a de Acogida ·
Administrativo/a · Auxiliar Administrativo/a · Ordenanza.

---

### `TitulacionesSeeder`
Crea el **catálogo de 14 titulaciones académicas** relevantes para el sector.
Idempotente: `firstOrCreate` por nombre.

Grado en Trabajo Social · Psicología · Educación Social · Pedagogía · Derecho ·
Terapia Ocupacional · Sociología · Integración Social · CFGS Integración Social ·
CFGM Atención Sociosanitaria · CFGM Auxiliar de Enfermería ·
Bachillerato · ESO · Certificado de Escolaridad.

---

### `TiposRelacionProfesionalSeeder`
Crea el **catálogo de 7 tipos de relación laboral** (campo `es_externo` distingue
personal propio de externa).
Idempotente: `firstOrCreate` por nombre.

Funcionario/a de carrera · Personal laboral fijo/a · Personal interino/a ·
Personal eventual · Contratado/a laboral temporal ·
Empresa externa (contrata pública) · Voluntario/a.

---

## Módulo Organización (`Modules/Organizacion/`)

Orquestado por `OrganizacionSeeder`, que llama a los cuatro sub-seeders en orden.

### `ConfiguracionSeeder`
Crea las **claves de configuración global** del sistema en la tabla `configuracion`.
Incluye `nombre_organizacion`, `municipio`, `provincia` y otras claves del sistema.
Idempotente: `firstOrCreate` por clave.

### `ColectivosProtegidosSeeder`
Crea los **colectivos especialmente protegidos** (tabla `colectivos_protegidos`).
Incluye: menores, víctimas de violencia de género, y otros colectivos con
`requiere_aprobacion_previa` según su nivel de protección.
Idempotente: `firstOrCreate` por nombre.

### `ServiciosEmergenciaSeeder`
Crea los **servicios de emergencia social preautorizados** (tabla `servicios_emergencia_preautorizados`).
Incluye SAMUR Social y otros servicios de guardia permanente.
Idempotente: `firstOrCreate` por nombre.

### `DistritosSeeder`
Crea los **21 distritos municipales de Madrid** con su código oficial y coordenadas geográficas centradas.
Idempotente: `firstOrCreate` por código.

Centro · Arganzuela · Retiro · Salamanca · Chamartín · Tetuán · Chamberí ·
Fuencarral-El Pardo · Moncloa-Aravaca · Latina · Carabanchel · Usera ·
Puente de Vallecas · Moratalaz · Ciudad Lineal · Hortaleza · Villaverde ·
Villa de Vallecas · Vicálvaro · San Blas-Canillejas · Barajas.

---

## Módulo Agenda (`Modules/Agenda/`)

### `AgendaSeeder`
Requiere que exista al menos un `Centro` en base de datos.
Crea sobre el primer centro disponible:
- 1 `HorarioCentro` de ejemplo ("Horario general", modo `estandar`, L-V 8:00-19:00, atención 9:00-14:00).
- 3 `TipoSlot`: Entrevista TSR (45 min) · Primera atención SIA (30 min) · Reunión de coordinación (60 min).
- Entrada en `catalogos_sistema` para el grupo `tipo_evento_agenda` (clave `reunion_equipo`).

⚠️ Si no existe ningún centro, el seeder no genera datos (no lanza error).

---

## Módulo Prestaciones (`Modules/Prestaciones/`)

⚠️ **No integrado en `DatabaseSeeder`.** Ejecutar manualmente:
```bash
php artisan db:seed --class="Modules\Prestaciones\Database\Seeders\CatalogosSistemaSeeder"
php artisan db:seed --class="Modules\Prestaciones\Database\Seeders\PrestacionesSeeder"
```
`CatalogosSistemaSeeder` debe ejecutarse antes que `PrestacionesSeeder`.

### `CatalogosSistemaSeeder`
Crea el **árbol de categorías y subcategorías** de prestaciones en `catalogos_sistema`
(grupos `prestacion.categoria` y `prestacion.subcategoria`).
8 categorías · ~35 subcategorías · tipos de modalidad de gestión.

Categorías: Acceso/información/valoración · Inclusión social y empleo ·
Familia/infancia/adolescencia · Autonomía personal · Atención a la dependencia ·
Urgencia social y VG · Desarrollo comunitario · Participación social.

### `PrestacionesSeeder`
Crea el **catálogo de prestaciones concretas** con código jerárquico (ej. `010101`).
Incluye SIVOAS, valoración social, informes, derivaciones, atención a personas
sin hogar, AES, PMC, intervención familiar, protección de menores, dependencia, etc.

---

## Módulo Documentos (`Modules/Documentos/`)

⚠️ **No integrado en `DatabaseSeeder`.** Ejecutar manualmente:
```bash
php artisan db:seed --class="Modules\Documentos\Database\Seeders\DocumentosSeeder"
```

### `DocumentosSeeder`
Crea en `catalogos_sistema` los **7 tipos de documento** (grupo `documento.tipo`):
informe médico · certificado · documento de identidad · resolución administrativa ·
informe externo · informe generado por VIDA · otro.

También crea una **plantilla de informe social de ejemplo** en `plantillas_informe`.

---

## Ejecución completa

```bash
# Desde vida/

# Flujo principal (incluye Organización y Agenda)
php artisan db:seed

# Módulos no integrados aún en DatabaseSeeder
php artisan db:seed --class="Modules\Prestaciones\Database\Seeders\CatalogosSistemaSeeder"
php artisan db:seed --class="Modules\Prestaciones\Database\Seeders\PrestacionesSeeder"
php artisan db:seed --class="Modules\Documentos\Database\Seeders\DocumentosSeeder"
```

Todos los seeders son **idempotentes**: se pueden ejecutar múltiples veces sin generar duplicados.

---

## Pendiente

- Integrar `CatalogosSistemaSeeder`, `PrestacionesSeeder` y `DocumentosSeeder` en `DatabaseSeeder`
  cuando se decida incluirlos en el flujo estándar de instalación.
- Crear un `CentroSeeder` de ejemplo para que `AgendaSeeder` siempre encuentre un centro disponible
  (actualmente depende de que exista un centro creado manualmente).
