# Plan de cobertura PHPDoc

Registro paralelo para cerrar las carencias detectadas en `docs/codigo-phpdoc.md`.

## Linea base

- Fecha base: 2026-06-16.
- Ambito: `vida/app` y `vida/Modules/*/app`.
- Alertas iniciales: 955.
- Cabeceras sin PHPDoc: 136.
- Metodos publicos sin PHPDoc: 361.
- PHPDoc incompletos: 458.

## Batches

### Batch 1 - Nucleo App

- Estado: completado.
- Alcance: observers, policy, services, traits y job de geocodificacion del namespace `App`.
- Ficheros tocados:
  - `vida/app/Observers/AuditObserver.php`
  - `vida/app/Observers/DireccionObserver.php`
  - `vida/app/Policies/CiudadanoPolicy.php`
  - `vida/app/Services/Api/RevelacionIdentidadService.php`
  - `vida/app/Services/AuditService.php`
  - `vida/app/Services/CiudadanoService.php`
  - `vida/app/Services/Geocodificacion/GeocodificadorService.php`
  - `vida/app/Services/Geocodificacion/ResultadoGeocodificacion.php`
  - `vida/app/Services/HistoriaSocialService.php`
  - `vida/app/Traits/Auditable.php`
  - `vida/app/Traits/TieneDireccion.php`
  - `vida/app/Jobs/NormalizarDireccionJob.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 955.
- Alertas despues: 925.
- Alertas resueltas: 30.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 2 - App soporte HTTP y bootstrap

- Estado: completado.
- Alcance: comando de geocodificacion, providers, query object, enums de soporte, controladores de autenticacion y middleware sin rol.
- Ficheros tocados:
  - `vida/app/Console/Commands/NormalizarDirecciones.php`
  - `vida/app/Providers/AppServiceProvider.php`
  - `vida/app/Providers/GeocodificacionServiceProvider.php`
  - `vida/app/Queries/AccesosExpedienteQuery.php`
  - `vida/app/Enums/AccionAuditEnum.php`
  - `vida/app/Enums/OrigenDireccion.php`
  - `vida/app/Http/Controllers/Auth/LoginController.php`
  - `vida/app/Http/Controllers/Auth/OnboardingController.php`
  - `vida/app/Http/Middleware/EnsureTieneRol.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 925.
- Alertas despues: 905.
- Alertas resueltas: 20.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 3 - Modelos App y DTO geocodificacion

- Estado: completado.
- Alcance: modelos base del namespace `App`, scope de UO y DTO `ResultadoGeocodificacion`.
- Ficheros tocados:
  - `vida/app/Models/Api/PerfilAnonimizacion.php`
  - `vida/app/Models/Audit.php`
  - `vida/app/Models/CatalogoSistema.php`
  - `vida/app/Models/Ciudadano.php`
  - `vida/app/Models/HistoriaSocial.php`
  - `vida/app/Models/Scopes/AmbitoUoScope.php`
  - `vida/app/Models/UnidadOrganizativa.php`
  - `vida/app/Models/User.php`
  - `vida/app/Models/Version.php`
  - `vida/app/Services/Geocodificacion/ResultadoGeocodificacion.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 905.
- Alertas despues: 871.
- Alertas resueltas: 34.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 4 - Modules Usuarios autorizacion

- Estado: completado.
- Alcance: provider, traits y policies de autorizacion del modulo Usuarios.
- Ficheros tocados:
  - `vida/Modules/Usuarios/app/Providers/UsuariosServiceProvider.php`
  - `vida/Modules/Usuarios/app/Traits/TieneRoles.php`
  - `vida/Modules/Usuarios/app/Traits/TieneUO.php`
  - `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php`
  - `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 871.
- Alertas despues: 838.
- Alertas resueltas: 33.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 5 - App Filament Resources

- Estado: completado.
- Alcance: recursos Filament de App con metodos de autorizacion, infolists y normalizacion de schema.
- Ficheros tocados:
  - `vida/app/Filament/Resources/AuditResource.php`
  - `vida/app/Filament/Resources/CentroResource.php`
  - `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource.php`
  - `vida/app/Filament/Resources/DocumentoResource.php`
  - `vida/app/Filament/Resources/EstiloInformeResource.php`
  - `vida/app/Filament/Resources/InformeResource.php`
  - `vida/app/Filament/Resources/LogAlertasResource.php`
  - `vida/app/Filament/Resources/PlantillaInformeResource.php`
  - `vida/app/Filament/Resources/ProfesionalResource.php`
  - `vida/app/Filament/Resources/RedResource.php`
  - `vida/app/Filament/Resources/TipoFichaResource.php`
  - `vida/app/Filament/Resources/UsuarioResource.php`
  - `vida/app/Filament/Resources/UsuarioRolResource.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 838.
- Alertas despues: 811.
- Alertas resueltas: 27.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 6 - App Filament Resources metodos publicos iniciales

- Estado: completado.
- Alcance: metodos publicos sin docblock en recursos Filament iniciales (`AuditResource`, `CentroResource`, `ConfiguracionHorarioLaboralResource`, `DocumentoResource`, `EstiloInformeResource`, `InformeResource` y `LogAlertasResource`).
- Ficheros tocados:
  - `vida/app/Filament/Resources/AuditResource.php`
  - `vida/app/Filament/Resources/CentroResource.php`
  - `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource.php`
  - `vida/app/Filament/Resources/DocumentoResource.php`
  - `vida/app/Filament/Resources/EstiloInformeResource.php`
  - `vida/app/Filament/Resources/InformeResource.php`
  - `vida/app/Filament/Resources/LogAlertasResource.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 811.
- Alertas despues: 786.
- Alertas resueltas: 25.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 7 - App Filament Resources metodos publicos catalogo y permisos

- Estado: completado.
- Alcance: metodos publicos sin docblock en recursos Filament de plantillas, profesionales, redes, roles, segmentos de poblacion y servicios de emergencia.
- Ficheros tocados:
  - `vida/app/Filament/Resources/PlantillaInformeResource.php`
  - `vida/app/Filament/Resources/ProfesionalResource.php`
  - `vida/app/Filament/Resources/RedResource.php`
  - `vida/app/Filament/Resources/RolResource.php`
  - `vida/app/Filament/Resources/SegmentoPoblacionResource.php`
  - `vida/app/Filament/Resources/ServicioEmergenciaResource.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 786.
- Alertas despues: 760.
- Alertas resueltas: 26.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 8 - App Filament Resources agenda y configuracion

- Estado: completado.
- Alcance: metodos publicos sin docblock en recursos Filament de configuracion de roles, cuadrantes, distritos, excepciones, horarios y perfiles horarios, incluyendo relation managers de cuadrantes y tipos de slot.
- Ficheros tocados:
  - `vida/app/Filament/Resources/ConfiguracionRolResource.php`
  - `vida/app/Filament/Resources/CuadranteMesResource.php`
  - `vida/app/Filament/Resources/CuadranteMesResource/RelationManagers/LineasCuadranteRelationManager.php`
  - `vida/app/Filament/Resources/DistritoResource.php`
  - `vida/app/Filament/Resources/ExcepcionProfesionalResource.php`
  - `vida/app/Filament/Resources/HorarioCentroResource.php`
  - `vida/app/Filament/Resources/HorarioCentroResource/RelationManagers/TiposSlotsRelationManager.php`
  - `vida/app/Filament/Resources/PerfilHorarioProfesionalResource.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 760.
- Alertas despues: 736.
- Alertas resueltas: 24.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 9 - App Filament Resources prestaciones y tipos

- Estado: completado.
- Alcance: metodos publicos sin docblock en recursos Filament de prestaciones, versiones, tipos de actividad/escala/espacio/ficha/relacion/slot y titulaciones.
- Ficheros tocados:
  - `vida/app/Filament/Resources/PrestacionResource.php`
  - `vida/app/Filament/Resources/PrestacionResource/RelationManagers/VersionesRelationManager.php`
  - `vida/app/Filament/Resources/TipoActividadResource.php`
  - `vida/app/Filament/Resources/TipoEscalaResource.php`
  - `vida/app/Filament/Resources/TipoEspacioResource.php`
  - `vida/app/Filament/Resources/TipoFichaResource.php`
  - `vida/app/Filament/Resources/TipoRelacionProfesionalResource.php`
  - `vida/app/Filament/Resources/TipoSlotResource.php`
  - `vida/app/Filament/Resources/TitulacionResource.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 736.
- Alertas despues: 709.
- Alertas resueltas: 27.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.

### Batch 10 - App Filament Resources organizacion y usuarios

- Estado: completado.
- Alcance: metodos publicos sin docblock en recursos Filament de unidades organizativas, usuarios, asignaciones de rol y zonas.
- Ficheros tocados:
  - `vida/app/Filament/Resources/UnidadOrganizativaResource.php`
  - `vida/app/Filament/Resources/UsuarioResource.php`
  - `vida/app/Filament/Resources/UsuarioRolResource.php`
  - `vida/app/Filament/Resources/ZonaResource.php`
- Validacion: `php -l` en todos los ficheros tocados y regeneracion de `docs/codigo-phpdoc.md`.
- Alertas antes: 709.
- Alertas despues: 694.
- Alertas resueltas: 15.
- Observaciones: solo se han modificado docblocks; no se han cambiado firmas ni logica.
