# Instrucciones CLI — Reorganización de navegación en el backoffice Filament

> Leer este fichero íntegramente antes de tocar cualquier fichero.
> No hay cambios de lógica de negocio ni migraciones en esta sesión.

---

## Contexto y alcance

Esta sesión reorganiza únicamente la navegación del panel Filament: grupos de navegación,
orden de items y la incorporación de `ServicioResource` al menú.

**Ficheros afectados:** exclusivamente los ficheros de Resource de Filament en
`app/Filament/Resources/`. Solo se modifican las propiedades de navegación:
`$navigationGroup`, `$navigationSort`, `$navigationIcon` y `$navigationLabel`
(cuando el nombre de clase no coincida con el label deseado).

**No se toca:** lógica, formularios, tablas, relaciones, tests, modelos, migraciones.

**Una excepción:** `ServicioResource` necesita que se le asigne grupo y orden, ya que
actualmente carece de `$navigationGroup`. No requiere ningún otro cambio.

---

## Estructura objetivo

```
Panel principal

ORGANIZACIÓN
  Unidades organizativas
  Distritos
  Zonas

CENTROS Y SERVICIOS
  Prestaciones
  Centros
  Redes
  Servicios

CATÁLOGOS
  Segmentos de población
  Colectivos protegidos
  Servicios de emergencia
  Tipos de espacio
  Tipos de actividad
  Cargos
  Titulaciones
  Tipos de relación

INFORMES Y PLANTILLAS
  Plantillas de informe
  Estilos de informe
  Informes
  Documentos
  Tipos de escala

USUARIOS Y PROFESIONALES
  Profesionales
  Usuarios
  Roles y permisos
  Supervisión de roles
  Historial de roles

SISTEMA
  Configuración
  Horarios de centro       ← antes en grupo separado, ahora aquí
  Perfiles horarios        ← antes en grupo separado, ahora aquí
  Horario laboral          ← antes en grupo separado, ahora aquí
  Log de alertas
```

---

## Tabla de cambios por Resource

Aplicar exactamente los valores indicados. El campo `$navigationSort` controla el orden
dentro del grupo; usar los valores de la tabla para preservar el orden deseado.

### Grupo: Organización

| Resource | `$navigationGroup` | `$navigationSort` | Notas |
|---|---|---|---|
| `UnidadOrganizativaResource` | `'Organización'` | `1` | Sin cambio de grupo, ajustar sort |
| `DistritoResource` | `'Organización'` | `2` | Antes en «Catálogos» |
| `ZonaResource` | `'Organización'` | `3` | Antes en «Catálogos» |

### Grupo: Centros y Servicios

| Resource | `$navigationGroup` | `$navigationSort` | Notas |
|---|---|---|---|
| `PrestacionResource` | `'Centros y Servicios'` | `1` | Antes en «Catálogos» |
| `CentroResource` | `'Centros y Servicios'` | `2` | Sin cambio de grupo |
| `RedResource` | `'Centros y Servicios'` | `3` | Sin cambio de grupo |
| `ServicioResource` | `'Centros y Servicios'` | `4` | **Sin grupo previo — añadir** |

### Grupo: Catálogos

| Resource | `$navigationGroup` | `$navigationSort` | Notas |
|---|---|---|---|
| `SegmentoPoblacionResource` | `'Catálogos'` | `1` | Sin cambio de grupo |
| `ColectivoProtegidoResource` | `'Catálogos'` | `2` | Sin cambio de grupo |
| `ServicioEmergenciaResource` | `'Catálogos'` | `3` | Antes en «Organización» |
| `TipoEspacioResource` | `'Catálogos'` | `4` | Sin cambio de grupo |
| `TipoActividadResource` | `'Catálogos'` | `5` | Sin cambio de grupo |
| `CargoResource` | `'Catálogos'` | `6` | Antes en «Profesionales» |
| `TitulacionResource` | `'Catálogos'` | `7` | Antes en «Profesionales» |
| `TipoRelacionProfesionalResource` | `'Catálogos'` | `8` | Antes en «Profesionales» |

### Grupo: Informes y Plantillas

| Resource | `$navigationGroup` | `$navigationSort` | Notas |
|---|---|---|---|
| `PlantillaInformeResource` | `'Informes y Plantillas'` | `1` | Sin cambio de grupo |
| `EstiloInformeResource` | `'Informes y Plantillas'` | `2` | Sin cambio de grupo |
| `InformeResource` | `'Informes y Plantillas'` | `3` | Sin cambio de grupo |
| `DocumentoResource` | `'Informes y Plantillas'` | `4` | Sin cambio de grupo |
| `TipoEscalaResource` | `'Informes y Plantillas'` | `5` | Antes en «Catálogos» |

### Grupo: Usuarios y Profesionales

| Resource | `$navigationGroup` | `$navigationSort` | Notas |
|---|---|---|---|
| `ProfesionalResource` | `'Usuarios y Profesionales'` | `1` | Antes en «Profesionales» |
| `UsuarioResource` | `'Usuarios y Profesionales'` | `2` | Antes en «Organización» |
| `ConfiguracionRolResource` | `'Usuarios y Profesionales'` | `3` | Label: «Roles y permisos» |
| `UsuarioRolResource` | `'Usuarios y Profesionales'` | `4` | Label: «Supervisión de roles» |
| `HistorialRolResource` | `'Usuarios y Profesionales'` | `5` | Label: «Historial de roles» |

> **Nota sobre labels:** si el nombre visible en el menú debe diferir del nombre de clase,
> usar `$navigationLabel` estático en la clase. Por ejemplo, si `ConfiguracionRolResource`
> muestra «Configuración de roles» en lugar de «Roles y permisos», añadir:
> `protected static ?string $navigationLabel = 'Roles y permisos';`

### Grupo: Sistema

| Resource / Page | `$navigationGroup` | `$navigationSort` | Notas |
|---|---|---|---|
| `ConfiguracionResource` (o page) | `'Sistema'` | `1` | Sin cambio de grupo |
| `HorarioCentroResource` | `'Sistema'` | `2` | Antes en «Configuración» o similar |
| `PerfilHorarioProfesionalResource` | `'Sistema'` | `3` | Antes en «Configuración» o similar |
| `HorarioLaboralResource` (o page) | `'Sistema'` | `4` | Antes en «Configuración» o similar |
| `AlertaLogResource` (o similar) | `'Sistema'` | `5` | Label: «Log de alertas» |

> **Nota sobre horarios:** los resources de Agenda (HorarioCentro, PerfilHorario) estaban
> documentados en el grupo «Agenda — Configuración». Al no existir un grupo «Agenda» en la
> nueva estructura, se trasladan a «Sistema» donde viven las configuraciones técnicas globales.
> Esto es coherente: son configuraciones que el administrador toca puntualmente, no flujos
> operativos diarios.

---

## Procedimiento de aplicación

Para cada Resource en la tabla:

1. Abrir el fichero del Resource en `app/Filament/Resources/`.
2. Localizar las propiedades estáticas de navegación (suelen estar al inicio de la clase,
   después de `$model`).
3. Actualizar `$navigationGroup`, `$navigationSort` y si aplica `$navigationLabel`.
4. No modificar ninguna otra propiedad ni método.

Ejemplo de cómo debe quedar un Resource tras el cambio:

```php
class DistritoResource extends Resource
{
    protected static ?string $model = Distrito::class;
    protected static ?string $navigationGroup = 'Organización';
    protected static ?string $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    // ... resto sin cambios
}
```

---

## ServicioResource: caso especial

`ServicioResource` no tiene `$navigationGroup` asignado actualmente. Además de añadir
el grupo y el sort, verificar que tiene un icono coherente con el resto del grupo
«Centros y Servicios». Si no tiene icono, asignar `heroicon-o-building-office`.

No hacer ningún otro cambio en este Resource. Su formulario, tabla y lógica se abordan
en la sesión de implementación del módulo Centros cuando esté completamente diseñado.

---

## Verificación

No hay tests para cambios de navegación. La verificación es manual:

1. Ejecutar `php artisan serve` y abrir el panel Filament.
2. Recorrer el menú lateral y confirmar que los seis grupos aparecen con los nombres exactos
   y en el orden correcto: Organización, Centros y Servicios, Catálogos, Informes y Plantillas,
   Usuarios y Profesionales, Sistema.
3. Expandir cada grupo y confirmar que los items están en el orden de la tabla.
4. Confirmar que «Servicios» aparece en «Centros y Servicios».
5. Confirmar que ya no existe ningún grupo llamado «Catálogos» que contenga Distritos,
   Zonas o Prestaciones, ni ningún grupo llamado «Profesionales» como grupo independiente.

Ejecutar también el conjunto completo de tests para confirmar que ningún cambio de
navegación ha roto accesos por policy o middleware:

```bash
php artisan test
```

Todos los tests deben seguir pasando.

---

## Cierre de sesión

Seguir el protocolo estándar de `CLAUDE.md` sección 4.

**CHANGELOG.md** — añadir entrada con:
- Fecha de la sesión
- Módulo: Backoffice / Navegación
- Cambios: reorganización de grupos de navegación en 6 grupos conceptuales;
  `ServicioResource` incorporado al menú bajo «Centros y Servicios»;
  items de Agenda (HorarioCentro, PerfilHorario, HorarioLaboral) movidos a «Sistema»
- Decisiones de implementación tomadas que no estaban en las instrucciones

**SESSION.md** — actualizar con:
- Tarea completada: «Navegación Filament reorganizada en 6 grupos conceptuales»
- Siguiente paso recomendado: el que indique el estado actual del proyecto

**Commit:**
```bash
git add -A
git commit -m "chore(filament): reorganización de navegación en 6 grupos conceptuales"
git push origin main
```

---

*Instrucciones preparadas: mayo 2026.*
