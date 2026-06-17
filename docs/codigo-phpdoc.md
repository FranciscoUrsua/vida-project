# Documentacion de codigo PHP

Generado el 2026-06-17 11:28:17 UTC a partir de los docblocks PHPDoc compatibles con PHODoc.

## Resumen

- Ambito: `vida/app` y `vida/Modules/*/app`.
- Simbolos escaneados: 371.
- Cabeceras documentadas: 251/371.
- Metodos publicos documentados: 589/991.
- Alertas de comentarios: 670.

## Alertas

### PHPDoc incompleto (196)

- `Modules\Agenda\Enums\EstadoCita::label()` en `vida/Modules/Agenda/app/Enums/EstadoCita.php:20`: Falta @return.
- `Modules\Agenda\Enums\EstadoCuadrante::label()` en `vida/Modules/Agenda/app/Enums/EstadoCuadrante.php:17`: Falta @return.
- `Modules\Agenda\Enums\EstadoSlot::label()` en `vida/Modules/Agenda/app/Enums/EstadoSlot.php:21`: Falta @return.
- `Modules\Agenda\Enums\ModoAgenda::label()` en `vida/Modules/Agenda/app/Enums/ModoAgenda.php:17`: Falta @return.
- `Modules\Agenda\Enums\MotivoReasignacion::label()` en `vida/Modules/Agenda/app/Enums/MotivoReasignacion.php:18`: Falta @return.
- `Modules\Agenda\Enums\OrigenCita::label()` en `vida/Modules/Agenda/app/Enums/OrigenCita.php:16`: Falta @return.
- `Modules\Agenda\Enums\OrigenExcepcion::label()` en `vida/Modules/Agenda/app/Enums/OrigenExcepcion.php:16`: Falta @return.
- `Modules\Agenda\Enums\OrigenPermitidoSlot::label()` en `vida/Modules/Agenda/app/Enums/OrigenPermitidoSlot.php:17`: Falta @return.
- `Modules\Agenda\Enums\TipoExcepcion::label()` en `vida/Modules/Agenda/app/Enums/TipoExcepcion.php:21`: Falta @return.
- `Modules\Documentos\Enums\EstadoInforme::label()` en `vida/Modules/Documentos/app/Enums/EstadoInforme.php:17`: Falta @return.
- `Modules\Documentos\Enums\MetodoConformidadCiudadano::label()` en `vida/Modules/Documentos/app/Enums/MetodoConformidadCiudadano.php:15`: Falta @return.
- `Modules\Documentos\Enums\MetodoFirma::label()` en `vida/Modules/Documentos/app/Enums/MetodoFirma.php:15`: Falta @return.
- `Modules\Documentos\Enums\OrigenDocumento::label()` en `vida/Modules/Documentos/app/Enums/OrigenDocumento.php:16`: Falta @return.
- `Modules\Documentos\Enums\TipoInforme::label()` en `vida/Modules/Documentos/app/Enums/TipoInforme.php:18`: Falta @return.
- `Modules\Intervencion\Enums\ClasificacionSia::label()` en `vida/Modules/Intervencion/app/Enums/ClasificacionSia.php:17`: Falta @return.
- `Modules\Intervencion\Enums\EstadoPlan::label()` en `vida/Modules/Intervencion/app/Enums/EstadoPlan.php:18`: Falta @return.
- `Modules\Intervencion\Enums\EstadoValoracion::label()` en `vida/Modules/Intervencion/app/Enums/EstadoValoracion.php:17`: Falta @return.
- `Modules\Intervencion\Enums\MotivoCierre::label()` en `vida/Modules/Intervencion/app/Enums/MotivoCierre.php:19`: Falta @return.
- `Modules\Intervencion\Enums\TipoApunte::label()` en `vida/Modules/Intervencion/app/Enums/TipoApunte.php:24`: Falta @return.
- `Modules\Organizacion\Models\Configuracion::valorCasteado()` en `vida/Modules/Organizacion/app/Models/Configuracion.php:43`: Falta @return.
- `Modules\Organizacion\Models\Configuracion::scopeTipo()` en `vida/Modules/Organizacion/app/Models/Configuracion.php:60`: Falta @param $tipo.
- `Modules\Organizacion\Providers\OrganizacionServiceProvider::register()` en `vida/Modules/Organizacion/app/Providers/OrganizacionServiceProvider.php:24`: Falta @return.
- `Modules\Organizacion\Providers\OrganizacionServiceProvider::boot()` en `vida/Modules/Organizacion/app/Providers/OrganizacionServiceProvider.php:32`: Falta @return.
- `Modules\Organizacion\Services\ConfiguracionService::get()` en `vida/Modules/Organizacion/app/Services/ConfiguracionService.php:42`: Falta @return.
- `Modules\Organizacion\Services\ConfiguracionService::set()` en `vida/Modules/Organizacion/app/Services/ConfiguracionService.php:62`: Falta @return.
- `Modules\Organizacion\Services\ConfiguracionService::limpiarCache()` en `vida/Modules/Organizacion/app/Services/ConfiguracionService.php:81`: Falta @return.
- `Modules\Usuarios\Console\Commands\ReconciliarRoles::handle()` en `vida/Modules/Usuarios/app/Console/Commands/ReconciliarRoles.php:33`: Falta @return.
- `Modules\Usuarios\Models\Profesional::getNombreCompletoAttribute()` en `vida/Modules/Usuarios/app/Models/Profesional.php:136`: Falta @return.
- `Modules\Usuarios\Observers\UsuarioRolObserver::created()` en `vida/Modules/Usuarios/app/Observers/UsuarioRolObserver.php:33`: Falta @param $usuarioRol.
- `Modules\Usuarios\Observers\UsuarioRolObserver::created()` en `vida/Modules/Usuarios/app/Observers/UsuarioRolObserver.php:33`: Falta @return.
- `Modules\Usuarios\Observers\UsuarioRolObserver::updated()` en `vida/Modules/Usuarios/app/Observers/UsuarioRolObserver.php:52`: Falta @param $usuarioRol.
- `Modules\Usuarios\Observers\UsuarioRolObserver::updated()` en `vida/Modules/Usuarios/app/Observers/UsuarioRolObserver.php:52`: Falta @return.
- `Modules\Usuarios\Policies\ApuntePolicy::viewAny()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:48`: Falta @param $usuario.
- `Modules\Usuarios\Policies\ApuntePolicy::viewAny()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:48`: Falta @return.
- `Modules\Usuarios\Policies\ApuntePolicy::view()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:59`: Falta @param $usuario.
- `Modules\Usuarios\Policies\ApuntePolicy::view()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:59`: Falta @param $apunte.
- `Modules\Usuarios\Policies\ApuntePolicy::view()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:59`: Falta @return.
- `Modules\Usuarios\Policies\ApuntePolicy::create()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:80`: Falta @param $usuario.
- `Modules\Usuarios\Policies\ApuntePolicy::create()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:80`: Falta @return.
- `Modules\Usuarios\Policies\ApuntePolicy::update()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:96`: Falta @param $usuario.
- `Modules\Usuarios\Policies\ApuntePolicy::update()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:96`: Falta @param $apunte.
- `Modules\Usuarios\Policies\ApuntePolicy::update()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:96`: Falta @return.
- `Modules\Usuarios\Policies\ApuntePolicy::delete()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:118`: Falta @param $usuario.
- `Modules\Usuarios\Policies\ApuntePolicy::delete()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:118`: Falta @param $apunte.
- `Modules\Usuarios\Policies\ApuntePolicy::delete()` en `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:118`: Falta @return.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::viewAny()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:53`: Falta @param $usuario.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::viewAny()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:53`: Falta @return.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::view()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:68`: Falta @param $usuario.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::view()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:68`: Falta @param $historia.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::view()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:68`: Falta @return.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::create()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:94`: Falta @param $usuario.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::create()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:94`: Falta @return.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::update()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:110`: Falta @param $usuario.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::update()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:110`: Falta @param $historia.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::update()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:110`: Falta @return.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::delete()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:137`: Falta @param $usuario.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::delete()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:137`: Falta @param $historia.
- `Modules\Usuarios\Policies\HistoriaSocialPolicy::delete()` en `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:137`: Falta @return.
- `Modules\Usuarios\Providers\UsuariosServiceProvider::register()` en `vida/Modules/Usuarios/app/Providers/UsuariosServiceProvider.php:30`: Falta @return.
- `Modules\Usuarios\Providers\UsuariosServiceProvider::boot()` en `vida/Modules/Usuarios/app/Providers/UsuariosServiceProvider.php:38`: Falta @return.
- `Modules\Usuarios\Traits\TieneRoles::tieneRolVigente()` en `vida/Modules/Usuarios/app/Traits/TieneRoles.php:67`: Falta @return.
- `Modules\Usuarios\Traits\TieneRoles::tienePermiso()` en `vida/Modules/Usuarios/app/Traits/TieneRoles.php:89`: Falta @return.
- `Modules\Usuarios\Traits\TieneUO::perteneceAUo()` en `vida/Modules/Usuarios/app/Traits/TieneUO.php:88`: Falta @return.
- `Modules\Usuarios\Traits\TieneUO::tieneAccesoGestionA()` en `vida/Modules/Usuarios/app/Traits/TieneUO.php:104`: Falta @return.
- `Modules\Usuarios\Traits\TieneUO::tieneAccesoConsultaA()` en `vida/Modules/Usuarios/app/Traits/TieneUO.php:152`: Falta @return.
- `App\Console\Commands\NormalizarDirecciones::handle()` en `vida/app/Console/Commands/NormalizarDirecciones.php:47`: Falta @param $geocodificador.
- `App\Console\Commands\NormalizarDirecciones::handle()` en `vida/app/Console/Commands/NormalizarDirecciones.php:47`: Falta @return.
- `App\Enums\AccionAuditEnum::etiqueta()` en `vida/app/Enums/AccionAuditEnum.php:21`: Falta @return.
- `App\Enums\AccionAuditEnum::color()` en `vida/app/Enums/AccionAuditEnum.php:35`: Falta @return.
- `App\Enums\OrigenDireccion::label()` en `vida/app/Enums/OrigenDireccion.php:20`: Falta @return.
- `App\Filament\Resources\AuditResource::canAccess()` en `vida/app/Filament/Resources/AuditResource.php:167`: Falta @return.
- `App\Filament\Resources\AuditResource::canCreate()` en `vida/app/Filament/Resources/AuditResource.php:175`: Falta @return.
- `App\Filament\Resources\AuditResource::canEdit()` en `vida/app/Filament/Resources/AuditResource.php:181`: Falta @return.
- `App\Filament\Resources\AuditResource::canDelete()` en `vida/app/Filament/Resources/AuditResource.php:187`: Falta @return.
- `App\Filament\Resources\CentroResource::canViewAny()` en `vida/app/Filament/Resources/CentroResource.php:235`: Falta @return.
- `App\Filament\Resources\ConfiguracionHorarioLaboralResource::getEloquentQuery()` en `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource.php:101`: Falta @return.
- `App\Filament\Resources\DocumentoResource::infolist()` en `vida/app/Filament/Resources/DocumentoResource.php:50`: Falta @param $schema.
- `App\Filament\Resources\DocumentoResource::infolist()` en `vida/app/Filament/Resources/DocumentoResource.php:50`: Falta @return.
- `App\Filament\Resources\DocumentoResource::canViewAny()` en `vida/app/Filament/Resources/DocumentoResource.php:115`: Falta @return.
- `App\Filament\Resources\EstiloInformeResource::canViewAny()` en `vida/app/Filament/Resources/EstiloInformeResource.php:157`: Falta @return.
- `App\Filament\Resources\EstiloInformeResource::canEdit()` en `vida/app/Filament/Resources/EstiloInformeResource.php:163`: Falta @param $record.
- `App\Filament\Resources\EstiloInformeResource::canEdit()` en `vida/app/Filament/Resources/EstiloInformeResource.php:163`: Falta @return.
- `App\Filament\Resources\InformeResource::infolist()` en `vida/app/Filament/Resources/InformeResource.php:53`: Falta @param $schema.
- `App\Filament\Resources\InformeResource::infolist()` en `vida/app/Filament/Resources/InformeResource.php:53`: Falta @return.
- `App\Filament\Resources\InformeResource::canViewAny()` en `vida/app/Filament/Resources/InformeResource.php:147`: Falta @return.
- `App\Filament\Resources\LogAlertasResource::canViewAny()` en `vida/app/Filament/Resources/LogAlertasResource.php:127`: Falta @return.
- `App\Filament\Resources\PlantillaInformeResource::canViewAny()` en `vida/app/Filament/Resources/PlantillaInformeResource.php:265`: Falta @return.
- `App\Filament\Resources\ProfesionalResource::canViewAny()` en `vida/app/Filament/Resources/ProfesionalResource.php:230`: Falta @return.
- `App\Filament\Resources\ProfesionalResource::canDelete()` en `vida/app/Filament/Resources/ProfesionalResource.php:246`: Falta @param $record.
- `App\Filament\Resources\ProfesionalResource::canDelete()` en `vida/app/Filament/Resources/ProfesionalResource.php:246`: Falta @return.
- `App\Filament\Resources\RedResource::canViewAny()` en `vida/app/Filament/Resources/RedResource.php:82`: Falta @return.
- `App\Filament\Resources\TipoFichaResource::convertirSchemaBlocks()` en `vida/app/Filament/Resources/TipoFichaResource.php:292`: Falta @param $state.
- `App\Filament\Resources\TipoFichaResource::convertirSchemaBlocks()` en `vida/app/Filament/Resources/TipoFichaResource.php:292`: Falta @return.
- `App\Filament\Resources\UsuarioResource::canDelete()` en `vida/app/Filament/Resources/UsuarioResource.php:228`: Falta @param $record.
- `App\Filament\Resources\UsuarioResource::canDelete()` en `vida/app/Filament/Resources/UsuarioResource.php:228`: Falta @return.
- `App\Filament\Resources\UsuarioRolResource::canEdit()` en `vida/app/Filament/Resources/UsuarioRolResource.php:161`: Falta @param $record.
- `App\Filament\Resources\UsuarioRolResource::canEdit()` en `vida/app/Filament/Resources/UsuarioRolResource.php:161`: Falta @return.
- `App\Http\Controllers\Auth\LoginController::mostrar()` en `vida/app/Http/Controllers/Auth/LoginController.php:19`: Falta @return.
- `App\Http\Controllers\Auth\LoginController::autenticar()` en `vida/app/Http/Controllers/Auth/LoginController.php:29`: Falta @param $request.
- `App\Http\Controllers\Auth\LoginController::autenticar()` en `vida/app/Http/Controllers/Auth/LoginController.php:29`: Falta @return.
- `App\Http\Controllers\Auth\LoginController::cerrarSesion()` en `vida/app/Http/Controllers/Auth/LoginController.php:86`: Falta @param $request.
- `App\Http\Controllers\Auth\LoginController::cerrarSesion()` en `vida/app/Http/Controllers/Auth/LoginController.php:86`: Falta @return.
- `App\Http\Controllers\Auth\OnboardingController::mostrar()` en `vida/app/Http/Controllers/Auth/OnboardingController.php:18`: Falta @return.
- `App\Http\Controllers\Auth\OnboardingController::completar()` en `vida/app/Http/Controllers/Auth/OnboardingController.php:31`: Falta @param $request.
- `App\Http\Controllers\Auth\OnboardingController::completar()` en `vida/app/Http/Controllers/Auth/OnboardingController.php:31`: Falta @return.
- `App\Http\Middleware\EnsureTieneRol::handle()` en `vida/app/Http/Middleware/EnsureTieneRol.php:23`: Falta @param $request.
- `App\Http\Middleware\EnsureTieneRol::handle()` en `vida/app/Http/Middleware/EnsureTieneRol.php:23`: Falta @return.
- `App\Jobs\NormalizarDireccionJob::handle()` en `vida/app/Jobs/NormalizarDireccionJob.php:72`: Falta @param $geocodificador.
- `App\Jobs\NormalizarDireccionJob::handle()` en `vida/app/Jobs/NormalizarDireccionJob.php:72`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::mount()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:63`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::abrirFormularioCreacion()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:131`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::abrirFormularioEdicion()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:142`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::guardar()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:157`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::desactivar()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:181`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::reactivar()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:194`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::cancelar()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:205`: Falta @return.
- `App\Livewire\Admin\GestorUnidadesOrganizativas::render()` en `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:218`: Falta @return.
- `App\Livewire\Centros\SelectorPrestacionesCentro::mount()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:48`: Falta @param $centroId.
- `App\Livewire\Centros\SelectorPrestacionesCentro::mount()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:48`: Falta @return.
- `App\Livewire\Centros\SelectorPrestacionesCentro::togglePrestacion()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:119`: Falta @param $prestacionId.
- `App\Livewire\Centros\SelectorPrestacionesCentro::togglePrestacion()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:119`: Falta @return.
- `App\Livewire\Centros\SelectorPrestacionesCentro::deseleccionar()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:133`: Falta @param $prestacionId.
- `App\Livewire\Centros\SelectorPrestacionesCentro::deseleccionar()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:133`: Falta @return.
- `App\Livewire\Centros\SelectorPrestacionesCentro::setSegmento()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:143`: Falta @param $segmento.
- `App\Livewire\Centros\SelectorPrestacionesCentro::setSegmento()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:143`: Falta @return.
- `App\Livewire\Centros\SelectorPrestacionesCentro::verDetalle()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:151`: Falta @param $prestacionId.
- `App\Livewire\Centros\SelectorPrestacionesCentro::verDetalle()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:151`: Falta @return.
- `App\Livewire\Centros\SelectorPrestacionesCentro::cerrarDetalle()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:159`: Falta @return.
- `App\Livewire\Centros\SelectorPrestacionesCentro::guardar()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:168`: Falta @return.
- `App\Models\Api\PerfilAnonimizacion::delete()` en `vida/app/Models/Api/PerfilAnonimizacion.php:90`: Falta @return.
- `App\Models\Audit::update()` en `vida/app/Models/Audit.php:74`: Falta @param $attributes.
- `App\Models\Audit::update()` en `vida/app/Models/Audit.php:74`: Falta @param $options.
- `App\Models\Audit::update()` en `vida/app/Models/Audit.php:74`: Falta @return.
- `App\Models\Audit::delete()` en `vida/app/Models/Audit.php:82`: Falta @return.
- `App\Models\CatalogoSistema::opcionesParaSelect()` en `vida/app/Models/CatalogoSistema.php:58`: Falta @param $grupo.
- `App\Models\CatalogoSistema::valor()` en `vida/app/Models/CatalogoSistema.php:70`: Falta @return.
- `App\Models\CatalogoSistema::opcionesParaSelectConPrefijo()` en `vida/app/Models/CatalogoSistema.php:84`: Falta @param $grupo.
- `App\Models\CatalogoSistema::opcionesParaSelectConPrefijo()` en `vida/app/Models/CatalogoSistema.php:84`: Falta @param $prefijo.
- `App\Models\Ciudadano::getNombreCompletoAttribute()` en `vida/app/Models/Ciudadano.php:155`: Falta @return.
- `App\Models\Ciudadano::getCiudadanoId()` en `vida/app/Models/Ciudadano.php:171`: Falta @return.
- `App\Models\Ciudadano::tieneResidenciaVerificada()` en `vida/app/Models/Ciudadano.php:223`: Falta @return.
- `App\Models\HistoriaSocial::getCiudadanoId()` en `vida/app/Models/HistoriaSocial.php:91`: Falta @return.
- `App\Models\Scopes\AmbitoUoScope::apply()` en `vida/app/Models/Scopes/AmbitoUoScope.php:69`: Falta @param $model.
- `App\Models\Scopes\AmbitoUoScope::apply()` en `vida/app/Models/Scopes/AmbitoUoScope.php:69`: Falta @return.
- `App\Models\UnidadOrganizativa::isDescendantOf()` en `vida/app/Models/UnidadOrganizativa.php:124`: Falta @return.
- `App\Models\User::canAccessPanel()` en `vida/app/Models/User.php:112`: Falta @param $panel.
- `App\Models\User::canAccessPanel()` en `vida/app/Models/User.php:112`: Falta @return.
- `App\Models\Version::versionable()` en `vida/app/Models/Version.php:61`: Falta @return.
- `App\Observers\AuditObserver::created()` en `vida/app/Observers/AuditObserver.php:33`: Falta @return.
- `App\Observers\AuditObserver::updated()` en `vida/app/Observers/AuditObserver.php:52`: Falta @return.
- `App\Observers\AuditObserver::deleted()` en `vida/app/Observers/AuditObserver.php:81`: Falta @return.
- `App\Observers\DireccionObserver::__construct()` en `vida/app/Observers/DireccionObserver.php:29`: Falta @param $geocodificador.
- `App\Observers\DireccionObserver::creating()` en `vida/app/Observers/DireccionObserver.php:39`: Falta @param $model.
- `App\Observers\DireccionObserver::creating()` en `vida/app/Observers/DireccionObserver.php:39`: Falta @return.
- `App\Observers\DireccionObserver::created()` en `vida/app/Observers/DireccionObserver.php:50`: Falta @param $model.
- `App\Observers\DireccionObserver::created()` en `vida/app/Observers/DireccionObserver.php:50`: Falta @return.
- `App\Observers\DireccionObserver::updating()` en `vida/app/Observers/DireccionObserver.php:60`: Falta @param $model.
- `App\Observers\DireccionObserver::updating()` en `vida/app/Observers/DireccionObserver.php:60`: Falta @return.
- `App\Observers\DireccionObserver::updated()` en `vida/app/Observers/DireccionObserver.php:71`: Falta @param $model.
- `App\Observers\DireccionObserver::updated()` en `vida/app/Observers/DireccionObserver.php:71`: Falta @return.
- `App\Policies\CiudadanoPolicy::viewAny()` en `vida/app/Policies/CiudadanoPolicy.php:48`: Falta @param $usuario.
- `App\Policies\CiudadanoPolicy::viewAny()` en `vida/app/Policies/CiudadanoPolicy.php:48`: Falta @return.
- `App\Policies\CiudadanoPolicy::view()` en `vida/app/Policies/CiudadanoPolicy.php:58`: Falta @param $usuario.
- `App\Policies\CiudadanoPolicy::view()` en `vida/app/Policies/CiudadanoPolicy.php:58`: Falta @param $ciudadano.
- `App\Policies\CiudadanoPolicy::view()` en `vida/app/Policies/CiudadanoPolicy.php:58`: Falta @return.
- `App\Policies\CiudadanoPolicy::create()` en `vida/app/Policies/CiudadanoPolicy.php:83`: Falta @param $usuario.
- `App\Policies\CiudadanoPolicy::create()` en `vida/app/Policies/CiudadanoPolicy.php:83`: Falta @return.
- `App\Policies\CiudadanoPolicy::update()` en `vida/app/Policies/CiudadanoPolicy.php:99`: Falta @param $usuario.
- `App\Policies\CiudadanoPolicy::update()` en `vida/app/Policies/CiudadanoPolicy.php:99`: Falta @param $ciudadano.
- `App\Policies\CiudadanoPolicy::update()` en `vida/app/Policies/CiudadanoPolicy.php:99`: Falta @return.
- `App\Policies\CiudadanoPolicy::delete()` en `vida/app/Policies/CiudadanoPolicy.php:128`: Falta @param $usuario.
- `App\Policies\CiudadanoPolicy::delete()` en `vida/app/Policies/CiudadanoPolicy.php:128`: Falta @param $ciudadano.
- `App\Policies\CiudadanoPolicy::delete()` en `vida/app/Policies/CiudadanoPolicy.php:128`: Falta @return.
- `App\Providers\AppServiceProvider::register()` en `vida/app/Providers/AppServiceProvider.php:19`: Falta @return.
- `App\Providers\AppServiceProvider::boot()` en `vida/app/Providers/AppServiceProvider.php:27`: Falta @return.
- `App\Providers\GeocodificacionServiceProvider::register()` en `vida/app/Providers/GeocodificacionServiceProvider.php:27`: Falta @return.
- `App\Providers\GeocodificacionServiceProvider::boot()` en `vida/app/Providers/GeocodificacionServiceProvider.php:35`: Falta @return.
- `App\Queries\AccesosExpedienteQuery::puedeVerTodos()` en `vida/app/Queries/AccesosExpedienteQuery.php:69`: Falta @param $user.
- `App\Queries\AccesosExpedienteQuery::puedeVerTodos()` en `vida/app/Queries/AccesosExpedienteQuery.php:69`: Falta @param $historia.
- `App\Queries\AccesosExpedienteQuery::puedeVerTodos()` en `vida/app/Queries/AccesosExpedienteQuery.php:69`: Falta @return.
- `App\Services\Api\RevelacionIdentidadService::revelarPorAlias()` en `vida/app/Services/Api/RevelacionIdentidadService.php:36`: Falta @return.
- `App\Services\AuditService::registrarAcceso()` en `vida/app/Services/AuditService.php:44`: Falta @return.
- `App\Services\CiudadanoService::crear()` en `vida/app/Services/CiudadanoService.php:41`: Falta @return.
- `App\Services\CiudadanoService::actualizar()` en `vida/app/Services/CiudadanoService.php:62`: Falta @return.
- `App\Services\CiudadanoService::eliminar()` en `vida/app/Services/CiudadanoService.php:84`: Falta @return.
- `App\Services\Geocodificacion\GeocodificadorService::normalizar()` en `vida/app/Services/Geocodificacion/GeocodificadorService.php:39`: Falta @return.
- `App\Services\Geocodificacion\ResultadoGeocodificacion::fallo()` en `vida/app/Services/Geocodificacion/ResultadoGeocodificacion.php:58`: Falta @return.
- `App\Services\HistoriaSocialService::obtenerEntradas()` en `vida/app/Services/HistoriaSocialService.php:35`: Falta @param $ciudadano.
- `App\Services\HistoriaSocialService::obtenerEntradas()` en `vida/app/Services/HistoriaSocialService.php:35`: Falta @param $profesional.
- `App\Services\HistoriaSocialService::esTsr()` en `vida/app/Services/HistoriaSocialService.php:70`: Falta @param $usuario.
- `App\Services\HistoriaSocialService::esTsr()` en `vida/app/Services/HistoriaSocialService.php:70`: Falta @param $ciudadano.
- `App\Services\HistoriaSocialService::esTsr()` en `vida/app/Services/HistoriaSocialService.php:70`: Falta @return.
- `App\Traits\Auditable::bootAuditable()` en `vida/app/Traits/Auditable.php:27`: Falta @return.
- `App\Traits\Auditable::getCiudadanoId()` en `vida/app/Traits/Auditable.php:62`: Falta @return.
- `App\Traits\TieneDireccion::initializeTieneDireccion()` en `vida/app/Traits/TieneDireccion.php:44`: Falta @return.
- `App\Traits\TieneDireccion::direccionFormateada()` en `vida/app/Traits/TieneDireccion.php:65`: Falta @return.

### Método público sin PHPDoc (354)

- `Modules\Agenda\Models\Cita::slot()` en `vida/Modules/Agenda/app/Models/Cita.php:69`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::ciudadano()` en `vida/Modules/Agenda/app/Models/Cita.php:74`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::profesional()` en `vida/Modules/Agenda/app/Models/Cita.php:79`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::tipoSlot()` en `vida/Modules/Agenda/app/Models/Cita.php:84`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::centro()` en `vida/Modules/Agenda/app/Models/Cita.php:89`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::creadoPor()` en `vida/Modules/Agenda/app/Models/Cita.php:94`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::canceladoPor()` en `vida/Modules/Agenda/app/Models/Cita.php:99`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::reasignacion()` en `vida/Modules/Agenda/app/Models/Cita.php:104`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::scopeConfirmadas()` en `vida/Modules/Agenda/app/Models/Cita.php:109`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::scopeDelDia()` en `vida/Modules/Agenda/app/Models/Cita.php:114`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::scopeDelProfesional()` en `vida/Modules/Agenda/app/Models/Cita.php:119`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::scopeDelCiudadano()` en `vida/Modules/Agenda/app/Models/Cita.php:124`: Falta docblock de método público.
- `Modules\Agenda\Models\Cita::scopePendientesReasignacion()` en `vida/Modules/Agenda/app/Models/Cita.php:129`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::centro()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:57`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::publicadoPor()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:62`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::lineas()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:67`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::slots()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:72`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::scopePublicados()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:77`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::scopeBorradores()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:82`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::scopeDelMes()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:87`: Falta docblock de método público.
- `Modules\Agenda\Models\CuadranteMes::scopeDelCentro()` en `vida/Modules/Agenda/app/Models/CuadranteMes.php:92`: Falta docblock de método público.
- `Modules\Agenda\Models\EventoAgenda::centro()` en `vida/Modules/Agenda/app/Models/EventoAgenda.php:60`: Falta docblock de método público.
- `Modules\Agenda\Models\EventoAgenda::espacio()` en `vida/Modules/Agenda/app/Models/EventoAgenda.php:65`: Falta docblock de método público.
- `Modules\Agenda\Models\EventoAgenda::creadoPor()` en `vida/Modules/Agenda/app/Models/EventoAgenda.php:70`: Falta docblock de método público.
- `Modules\Agenda\Models\EventoAgenda::profesionales()` en `vida/Modules/Agenda/app/Models/EventoAgenda.php:75`: Falta docblock de método público.
- `Modules\Agenda\Models\EventoAgenda::scopeDelDia()` en `vida/Modules/Agenda/app/Models/EventoAgenda.php:82`: Falta docblock de método público.
- `Modules\Agenda\Models\EventoAgenda::scopeDelCentro()` en `vida/Modules/Agenda/app/Models/EventoAgenda.php:87`: Falta docblock de método público.
- `Modules\Agenda\Models\EventoAgenda::scopeDelProfesional()` en `vida/Modules/Agenda/app/Models/EventoAgenda.php:92`: Falta docblock de método público.
- `Modules\Agenda\Models\ExcepcionProfesional::usuario()` en `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:57`: Falta docblock de método público.
- `Modules\Agenda\Models\ExcepcionProfesional::centro()` en `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:62`: Falta docblock de método público.
- `Modules\Agenda\Models\ExcepcionProfesional::creadoPor()` en `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:67`: Falta docblock de método público.
- `Modules\Agenda\Models\ExcepcionProfesional::scopeVigentes()` en `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:72`: Falta docblock de método público.
- `Modules\Agenda\Models\ExcepcionProfesional::scopeQueAfectanDisponibilidad()` en `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:77`: Falta docblock de método público.
- `Modules\Agenda\Models\ExcepcionProfesional::scopeDelProfesional()` en `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:82`: Falta docblock de método público.
- `Modules\Agenda\Models\ExcepcionProfesional::scopeEnPeriodo()` en `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:87`: Falta docblock de método público.
- `Modules\Agenda\Models\HorarioCentro::centro()` en `vida/Modules/Agenda/app/Models/HorarioCentro.php:63`: Falta docblock de método público.
- `Modules\Agenda\Models\HorarioCentro::tiposSlot()` en `vida/Modules/Agenda/app/Models/HorarioCentro.php:68`: Falta docblock de método público.
- `Modules\Agenda\Models\HorarioCentro::scopeActivos()` en `vida/Modules/Agenda/app/Models/HorarioCentro.php:73`: Falta docblock de método público.
- `Modules\Agenda\Models\HorarioCentro::scopeVigentes()` en `vida/Modules/Agenda/app/Models/HorarioCentro.php:78`: Falta docblock de método público.
- `Modules\Agenda\Models\HorarioCentro::scopeDelCentro()` en `vida/Modules/Agenda/app/Models/HorarioCentro.php:88`: Falta docblock de método público.
- `Modules\Agenda\Models\HorarioCentro::esModoBasico()` en `vida/Modules/Agenda/app/Models/HorarioCentro.php:93`: Falta docblock de método público.
- `Modules\Agenda\Models\HorarioCentro::esModoAvanzado()` en `vida/Modules/Agenda/app/Models/HorarioCentro.php:98`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::cuadranteMes()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:50`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::usuario()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:55`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::centro()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:60`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::slots()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:65`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::excepcion()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:70`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::scopeActivas()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:75`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::scopeDelDia()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:80`: Falta docblock de método público.
- `Modules\Agenda\Models\LineaCuadrante::scopeDelProfesional()` en `vida/Modules/Agenda/app/Models/LineaCuadrante.php:85`: Falta docblock de método público.
- `Modules\Agenda\Models\PerfilHorarioProfesional::usuario()` en `vida/Modules/Agenda/app/Models/PerfilHorarioProfesional.php:84`: Falta docblock de método público.
- `Modules\Agenda\Models\PerfilHorarioProfesional::centro()` en `vida/Modules/Agenda/app/Models/PerfilHorarioProfesional.php:89`: Falta docblock de método público.
- `Modules\Agenda\Models\PerfilHorarioProfesional::lineasCuadrante()` en `vida/Modules/Agenda/app/Models/PerfilHorarioProfesional.php:94`: Falta docblock de método público.
- `Modules\Agenda\Models\PerfilHorarioProfesional::scopeActivos()` en `vida/Modules/Agenda/app/Models/PerfilHorarioProfesional.php:99`: Falta docblock de método público.
- `Modules\Agenda\Models\PerfilHorarioProfesional::scopeDelCentro()` en `vida/Modules/Agenda/app/Models/PerfilHorarioProfesional.php:104`: Falta docblock de método público.
- `Modules\Agenda\Models\PerfilHorarioProfesional::scopeVigentes()` en `vida/Modules/Agenda/app/Models/PerfilHorarioProfesional.php:109`: Falta docblock de método público.
- `Modules\Agenda\Models\ReasignacionCita::cita()` en `vida/Modules/Agenda/app/Models/ReasignacionCita.php:37`: Falta docblock de método público.
- `Modules\Agenda\Models\ReasignacionCita::slotOriginal()` en `vida/Modules/Agenda/app/Models/ReasignacionCita.php:42`: Falta docblock de método público.
- `Modules\Agenda\Models\ReasignacionCita::slotNuevo()` en `vida/Modules/Agenda/app/Models/ReasignacionCita.php:47`: Falta docblock de método público.
- `Modules\Agenda\Models\ReasignacionCita::profesionalOriginal()` en `vida/Modules/Agenda/app/Models/ReasignacionCita.php:52`: Falta docblock de método público.
- `Modules\Agenda\Models\ReasignacionCita::profesionalNuevo()` en `vida/Modules/Agenda/app/Models/ReasignacionCita.php:57`: Falta docblock de método público.
- `Modules\Agenda\Models\ReasignacionCita::realizadaPor()` en `vida/Modules/Agenda/app/Models/ReasignacionCita.php:62`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::lineaCuadrante()` en `vida/Modules/Agenda/app/Models/Slot.php:53`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::usuario()` en `vida/Modules/Agenda/app/Models/Slot.php:58`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::centro()` en `vida/Modules/Agenda/app/Models/Slot.php:63`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::tipoSlot()` en `vida/Modules/Agenda/app/Models/Slot.php:68`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::espacio()` en `vida/Modules/Agenda/app/Models/Slot.php:73`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::cita()` en `vida/Modules/Agenda/app/Models/Slot.php:78`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::scopeDisponibles()` en `vida/Modules/Agenda/app/Models/Slot.php:83`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::scopeUrgencias()` en `vida/Modules/Agenda/app/Models/Slot.php:88`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::scopeAnulados()` en `vida/Modules/Agenda/app/Models/Slot.php:93`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::scopeDelDia()` en `vida/Modules/Agenda/app/Models/Slot.php:98`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::scopeDelProfesional()` en `vida/Modules/Agenda/app/Models/Slot.php:103`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::scopeDelCentro()` en `vida/Modules/Agenda/app/Models/Slot.php:108`: Falta docblock de método público.
- `Modules\Agenda\Models\Slot::scopeDeEstado()` en `vida/Modules/Agenda/app/Models/Slot.php:113`: Falta docblock de método público.
- `Modules\Agenda\Models\TipoSlot::horarioCentro()` en `vida/Modules/Agenda/app/Models/TipoSlot.php:52`: Falta docblock de método público.
- `Modules\Agenda\Models\TipoSlot::slots()` en `vida/Modules/Agenda/app/Models/TipoSlot.php:57`: Falta docblock de método público.
- `Modules\Agenda\Models\TipoSlot::scopeActivos()` en `vida/Modules/Agenda/app/Models/TipoSlot.php:62`: Falta docblock de método público.
- `Modules\Agenda\Models\TipoSlot::scopeQueAdmitenApiExterna()` en `vida/Modules/Agenda/app/Models/TipoSlot.php:67`: Falta docblock de método público.
- `Modules\Agenda\Providers\AgendaServiceProvider::register()` en `vida/Modules/Agenda/app/Providers/AgendaServiceProvider.php:20`: Falta docblock de método público.
- `Modules\Agenda\Providers\AgendaServiceProvider::boot()` en `vida/Modules/Agenda/app/Providers/AgendaServiceProvider.php:25`: Falta docblock de método público.
- `Modules\Centro\Models\AmbitoTerritorial::centro()` en `vida/Modules/Centro/app/Models/AmbitoTerritorial.php:43`: Falta docblock de método público.
- `Modules\Centro\Providers\CentroServiceProvider::register()` en `vida/Modules/Centro/app/Providers/CentroServiceProvider.php:17`: Falta docblock de método público.
- `Modules\Centro\Providers\CentroServiceProvider::boot()` en `vida/Modules/Centro/app/Providers/CentroServiceProvider.php:19`: Falta docblock de método público.
- `Modules\Ciudadania\Http\Livewire\AltaCiudadano::render()` en `vida/Modules/Ciudadania/app/Http/Livewire/AltaCiudadano.php:428`: Falta docblock de método público.
- `Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage::ciudadanoSeleccionadoRelacion()` en `vida/Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php:442`: Falta docblock de método público.
- `Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage::render()` en `vida/Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php:810`: Falta docblock de método público.
- `Modules\Ciudadania\Models\CiudadanoIdentificador::ciudadano()` en `vida/Modules/Ciudadania/app/Models/CiudadanoIdentificador.php:83`: Falta docblock de método público.
- `Modules\Ciudadania\Models\CiudadanoRelacion::ciudadano()` en `vida/Modules/Ciudadania/app/Models/CiudadanoRelacion.php:74`: Falta docblock de método público.
- `Modules\Ciudadania\Models\CiudadanoRelacion::ciudadanoRelacionado()` en `vida/Modules/Ciudadania/app/Models/CiudadanoRelacion.php:79`: Falta docblock de método público.
- `Modules\Ciudadania\Models\CiudadanoRelacion::tipoRelacion()` en `vida/Modules/Ciudadania/app/Models/CiudadanoRelacion.php:84`: Falta docblock de método público.
- `Modules\Ciudadania\Providers\CiudadaniaServiceProvider::register()` en `vida/Modules/Ciudadania/app/Providers/CiudadaniaServiceProvider.php:26`: Falta docblock de método público.
- `Modules\Ciudadania\Providers\CiudadaniaServiceProvider::boot()` en `vida/Modules/Ciudadania/app/Providers/CiudadaniaServiceProvider.php:32`: Falta docblock de método público.
- `Modules\Documentos\Models\Documento::documentable()` en `vida/Modules/Documentos/app/Models/Documento.php:48`: Falta docblock de método público.
- `Modules\Documentos\Models\Documento::tipo()` en `vida/Modules/Documentos/app/Models/Documento.php:53`: Falta docblock de método público.
- `Modules\Documentos\Models\Documento::subidoPor()` en `vida/Modules/Documentos/app/Models/Documento.php:58`: Falta docblock de método público.
- `Modules\Documentos\Models\Documento::informe()` en `vida/Modules/Documentos/app/Models/Documento.php:63`: Falta docblock de método público.
- `Modules\Documentos\Models\Documento::scopeExternos()` en `vida/Modules/Documentos/app/Models/Documento.php:68`: Falta docblock de método público.
- `Modules\Documentos\Models\Documento::scopeGenerados()` en `vida/Modules/Documentos/app/Models/Documento.php:73`: Falta docblock de método público.
- `Modules\Documentos\Models\EstiloInforme::unidadOrganizativa()` en `vida/Modules/Documentos/app/Models/EstiloInforme.php:34`: Falta docblock de método público.
- `Modules\Documentos\Models\EstiloInforme::creadoPor()` en `vida/Modules/Documentos/app/Models/EstiloInforme.php:39`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::plantilla()` en `vida/Modules/Documentos/app/Models/Informe.php:59`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::historiaSocial()` en `vida/Modules/Documentos/app/Models/Informe.php:64`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::ciudadano()` en `vida/Modules/Documentos/app/Models/Informe.php:79`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::autor()` en `vida/Modules/Documentos/app/Models/Informe.php:84`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::documento()` en `vida/Modules/Documentos/app/Models/Informe.php:89`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::scopeBorradores()` en `vida/Modules/Documentos/app/Models/Informe.php:94`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::scopeFirmados()` en `vida/Modules/Documentos/app/Models/Informe.php:99`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::scopeDeAutor()` en `vida/Modules/Documentos/app/Models/Informe.php:104`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::estaFirmado()` en `vida/Modules/Documentos/app/Models/Informe.php:109`: Falta docblock de método público.
- `Modules\Documentos\Models\Informe::estaAnulado()` en `vida/Modules/Documentos/app/Models/Informe.php:114`: Falta docblock de método público.
- `Modules\Documentos\Models\PisoFirmado::planDeIntervencion()` en `vida/Modules/Documentos/app/Models/PisoFirmado.php:37`: Falta docblock de método público.
- `Modules\Documentos\Models\PisoFirmado::documento()` en `vida/Modules/Documentos/app/Models/PisoFirmado.php:44`: Falta docblock de método público.
- `Modules\Documentos\Models\PisoFirmado::subidoPor()` en `vida/Modules/Documentos/app/Models/PisoFirmado.php:49`: Falta docblock de método público.
- `Modules\Documentos\Models\PlantillaInforme::unidadOrganizativa()` en `vida/Modules/Documentos/app/Models/PlantillaInforme.php:43`: Falta docblock de método público.
- `Modules\Documentos\Models\PlantillaInforme::creadaPor()` en `vida/Modules/Documentos/app/Models/PlantillaInforme.php:48`: Falta docblock de método público.
- `Modules\Documentos\Models\PlantillaInforme::informes()` en `vida/Modules/Documentos/app/Models/PlantillaInforme.php:53`: Falta docblock de método público.
- `Modules\Documentos\Observers\EstiloInformeObserver::__construct()` en `vida/Modules/Documentos/app/Observers/EstiloInformeObserver.php:16`: Falta docblock de método público.
- `Modules\Documentos\Observers\EstiloInformeObserver::saved()` en `vida/Modules/Documentos/app/Observers/EstiloInformeObserver.php:18`: Falta docblock de método público.
- `Modules\Documentos\Observers\EstiloInformeObserver::deleted()` en `vida/Modules/Documentos/app/Observers/EstiloInformeObserver.php:23`: Falta docblock de método público.
- `Modules\Documentos\Providers\DocumentosServiceProvider::register()` en `vida/Modules/Documentos/app/Providers/DocumentosServiceProvider.php:19`: Falta docblock de método público.
- `Modules\Documentos\Providers\DocumentosServiceProvider::boot()` en `vida/Modules/Documentos/app/Providers/DocumentosServiceProvider.php:29`: Falta docblock de método público.
- `Modules\Documentos\Services\ResolverEstiloInforme::resolver()` en `vida/Modules/Documentos/app/Services/ResolverEstiloInforme.php:31`: Falta docblock de método público.
- `Modules\Documentos\Services\ResolverEstiloInforme::resolverSinCache()` en `vida/Modules/Documentos/app/Services/ResolverEstiloInforme.php:40`: Falta docblock de método público.
- `Modules\Documentos\Services\ServicioFirmaInforme::__construct()` en `vida/Modules/Documentos/app/Services/ServicioFirmaInforme.php:22`: Falta docblock de método público.
- `Modules\Documentos\Services\ServicioGeneracionPDF::__construct()` en `vida/Modules/Documentos/app/Services/ServicioGeneracionPDF.php:21`: Falta docblock de método público.
- `Modules\Escalas\Models\PaseEscala::historia()` en `vida/Modules/Escalas/app/Models/PaseEscala.php:208`: Falta docblock de método público.
- `Modules\Escalas\Providers\EscalasServiceProvider::register()` en `vida/Modules/Escalas/app/Providers/EscalasServiceProvider.php:16`: Falta docblock de método público.
- `Modules\Escalas\Providers\EscalasServiceProvider::boot()` en `vida/Modules/Escalas/app/Providers/EscalasServiceProvider.php:18`: Falta docblock de método público.
- `Modules\Intervencion\Enums\TipoEntrevista::label()` en `vida/Modules/Intervencion/app/Enums/TipoEntrevista.php:12`: Falta docblock de método público.
- `Modules\Intervencion\Enums\TipoPlan::label()` en `vida/Modules/Intervencion/app/Enums/TipoPlan.php:10`: Falta docblock de método público.
- `Modules\Intervencion\Enums\UrgenciaSia::label()` en `vida/Modules/Intervencion/app/Enums/UrgenciaSia.php:11`: Falta docblock de método público.
- `Modules\Intervencion\Enums\VisibilidadApunte::label()` en `vida/Modules/Intervencion/app/Enums/VisibilidadApunte.php:11`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\AgendaPage::render()` en `vida/Modules/Intervencion/app/Http/Livewire/AgendaPage.php:301`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage::render()` en `vida/Modules/Intervencion/app/Http/Livewire/BuscarCiudadanoPage.php:306`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::toggleUC()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:530`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::abrirModalRelaciones()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:535`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::cerrarModalRelaciones()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:540`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::abrirModalRepresentante()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:545`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::cerrarModalRepresentante()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:550`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::toggleApunte()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:718`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::seleccionarHerramienta()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:734`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::cancelarHerramienta()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:750`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\CiudadanoPage::render()` en `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:1054`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\MisCasosPage::updatedFiltroSeguimiento()` en `vida/Modules/Intervencion/app/Http/Livewire/MisCasosPage.php:54`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\MisCasosPage::updatedFiltroPiso()` en `vida/Modules/Intervencion/app/Http/Livewire/MisCasosPage.php:59`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\MisCasosPage::updatedFiltroEsp()` en `vida/Modules/Intervencion/app/Http/Livewire/MisCasosPage.php:64`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\MisCasosPage::updatedOrdenarPor()` en `vida/Modules/Intervencion/app/Http/Livewire/MisCasosPage.php:69`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\MisCasosPage::updatedBusqueda()` en `vida/Modules/Intervencion/app/Http/Livewire/MisCasosPage.php:74`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\MisCasosPage::render()` en `vida/Modules/Intervencion/app/Http/Livewire/MisCasosPage.php:196`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\RegistrarEscalaPage::getTipoEscalaProperty()` en `vida/Modules/Intervencion/app/Http/Livewire/RegistrarEscalaPage.php:45`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\RegistrarEscalaPage::render()` en `vida/Modules/Intervencion/app/Http/Livewire/RegistrarEscalaPage.php:70`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\RegistrarValoracionPage::render()` en `vida/Modules/Intervencion/app/Http/Livewire/RegistrarValoracionPage.php:182`: Falta docblock de método público.
- `Modules\Intervencion\Http\Livewire\Sidebar::render()` en `vida/Modules/Intervencion/app/Http/Livewire/Sidebar.php:47`: Falta docblock de método público.
- `Modules\Intervencion\Models\Apunte::plan()` en `vida/Modules/Intervencion/app/Models/Apunte.php:140`: Falta docblock de método público.
- `Modules\Intervencion\Providers\IntervencionServiceProvider::register()` en `vida/Modules/Intervencion/app/Providers/IntervencionServiceProvider.php:33`: Falta docblock de método público.
- `Modules\Intervencion\Providers\IntervencionServiceProvider::boot()` en `vida/Modules/Intervencion/app/Providers/IntervencionServiceProvider.php:38`: Falta docblock de método público.
- `Modules\Mensajes\Exceptions\UnauthorizedException::noEsTsr()` en `vida/Modules/Mensajes/app/Exceptions/UnauthorizedException.php:16`: Falta docblock de método público.
- `Modules\Mensajes\Http\Livewire\BuzonPage::render()` en `vida/Modules/Mensajes/app/Http/Livewire/BuzonPage.php:352`: Falta docblock de método público.
- `Modules\Mensajes\Jobs\EscalarAlertasVencidasJob::handle()` en `vida/Modules/Mensajes/app/Jobs/EscalarAlertasVencidasJob.php:31`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BadgeNotificaciones::totalAlertas()` en `vida/Modules/Mensajes/app/Livewire/BadgeNotificaciones.php:28`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BadgeNotificaciones::totalMensajes()` en `vida/Modules/Mensajes/app/Livewire/BadgeNotificaciones.php:57`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BadgeNotificaciones::total()` en `vida/Modules/Mensajes/app/Livewire/BadgeNotificaciones.php:70`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BadgeNotificaciones::render()` en `vida/Modules/Mensajes/app/Livewire/BadgeNotificaciones.php:75`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaAlertas::mount()` en `vida/Modules/Mensajes/app/Livewire/BandejaAlertas.php:26`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaAlertas::alertas()` en `vida/Modules/Mensajes/app/Livewire/BandejaAlertas.php:32`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaAlertas::render()` en `vida/Modules/Mensajes/app/Livewire/BandejaAlertas.php:117`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaMensajes::mount()` en `vida/Modules/Mensajes/app/Livewire/BandejaMensajes.php:24`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaMensajes::hilos()` en `vida/Modules/Mensajes/app/Livewire/BandejaMensajes.php:30`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaMensajes::abrirHilo()` en `vida/Modules/Mensajes/app/Livewire/BandejaMensajes.php:43`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaMensajes::archivarHilo()` en `vida/Modules/Mensajes/app/Livewire/BandejaMensajes.php:49`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaMensajes::nuevaMensaje()` en `vida/Modules/Mensajes/app/Livewire/BandejaMensajes.php:62`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\BandejaMensajes::render()` en `vida/Modules/Mensajes/app/Livewire/BandejaMensajes.php:68`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\HiloMensajes::mount()` en `vida/Modules/Mensajes/app/Livewire/HiloMensajes.php:46`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\HiloMensajes::hilo()` en `vida/Modules/Mensajes/app/Livewire/HiloMensajes.php:58`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\HiloMensajes::enviarRespuesta()` en `vida/Modules/Mensajes/app/Livewire/HiloMensajes.php:64`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\HiloMensajes::cerrarModalHistoria()` en `vida/Modules/Mensajes/app/Livewire/HiloMensajes.php:103`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\HiloMensajes::render()` en `vida/Modules/Mensajes/app/Livewire/HiloMensajes.php:175`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\NuevoMensaje::mount()` en `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:46`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\NuevoMensaje::seleccionarDestinatario()` en `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:81`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\NuevoMensaje::limpiarDestinatario()` en `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:88`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\NuevoMensaje::agregarCiudadano()` en `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:112`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\NuevoMensaje::quitarCiudadano()` en `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:122`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\NuevoMensaje::enviar()` en `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:129`: Falta docblock de método público.
- `Modules\Mensajes\Livewire\NuevoMensaje::render()` en `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:163`: Falta docblock de método público.
- `Modules\Mensajes\Providers\MensajesServiceProvider::register()` en `vida/Modules/Mensajes/app/Providers/MensajesServiceProvider.php:29`: Falta docblock de método público.
- `Modules\Mensajes\Providers\MensajesServiceProvider::boot()` en `vida/Modules/Mensajes/app/Providers/MensajesServiceProvider.php:36`: Falta docblock de método público.
- `Modules\Mensajes\Services\AlertaService::__construct()` en `vida/Modules/Mensajes/app/Services/AlertaService.php:22`: Falta docblock de método público.
- `Modules\Mensajes\Services\HorarioLaboralService::__construct()` en `vida/Modules/Mensajes/app/Services/HorarioLaboralService.php:40`: Falta docblock de método público.
- `Modules\Prestaciones\Providers\PrestacionesServiceProvider::register()` en `vida/Modules/Prestaciones/app/Providers/PrestacionesServiceProvider.php:17`: Falta docblock de método público.
- `Modules\Prestaciones\Providers\PrestacionesServiceProvider::boot()` en `vida/Modules/Prestaciones/app/Providers/PrestacionesServiceProvider.php:19`: Falta docblock de método público.
- `App\Console\Commands\AuditPurgeCommand::handle()` en `vida/app/Console/Commands/AuditPurgeCommand.php:26`: Falta docblock de método público.
- `App\Enums\TipoNumeracion::label()` en `vida/app/Enums/TipoNumeracion.php:17`: Falta docblock de método público.
- `App\Filament\Concerns\AutorizaGestion::canViewAny()` en `vida/app/Filament/Concerns/AutorizaGestion.php:15`: Falta docblock de método público.
- `App\Filament\Concerns\AutorizaGestion::canCreate()` en `vida/app/Filament/Concerns/AutorizaGestion.php:20`: Falta docblock de método público.
- `App\Filament\Concerns\AutorizaGestion::canEdit()` en `vida/app/Filament/Concerns/AutorizaGestion.php:25`: Falta docblock de método público.
- `App\Filament\Concerns\AutorizaGestion::canDelete()` en `vida/app/Filament/Concerns/AutorizaGestion.php:30`: Falta docblock de método público.
- `App\Filament\Pages\Dashboard::getColumns()` en `vida/app/Filament/Pages/Dashboard.php:29`: Falta docblock de método público.
- `App\Filament\Pages\Dashboard::getWidgets()` en `vida/app/Filament/Pages/Dashboard.php:34`: Falta docblock de método público.
- `App\Filament\Resources\AuditResource::getEloquentQuery()` en `vida/app/Filament/Resources/AuditResource.php:50`: Falta docblock de método público.
- `App\Filament\Resources\AuditResource::table()` en `vida/app/Filament/Resources/AuditResource.php:75`: Falta docblock de método público.
- `App\Filament\Resources\AuditResource::getPages()` en `vida/app/Filament/Resources/AuditResource.php:158`: Falta docblock de método público.
- `App\Filament\Resources\AuditResource\Pages\ViewAudit::infolist()` en `vida/app/Filament/Resources/AuditResource/Pages/ViewAudit.php:18`: Falta docblock de método público.
- `App\Filament\Resources\CargoResource::form()` en `vida/app/Filament/Resources/CargoResource.php:42`: Falta docblock de método público.
- `App\Filament\Resources\CargoResource::table()` en `vida/app/Filament/Resources/CargoResource.php:64`: Falta docblock de método público.
- `App\Filament\Resources\CargoResource::getPages()` en `vida/app/Filament/Resources/CargoResource.php:93`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource::form()` en `vida/app/Filament/Resources/CentroResource.php:45`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource::table()` en `vida/app/Filament/Resources/CentroResource.php:176`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource::canEdit()` en `vida/app/Filament/Resources/CentroResource.php:240`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource::canDelete()` en `vida/app/Filament/Resources/CentroResource.php:245`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource::getRelationManagers()` en `vida/app/Filament/Resources/CentroResource.php:250`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource::getPages()` en `vida/app/Filament/Resources/CentroResource.php:258`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource\RelationManagers\AmbitosTerritorialesRelationManager::form()` en `vida/app/Filament/Resources/CentroResource/RelationManagers/AmbitosTerritorialesRelationManager.php:24`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource\RelationManagers\AmbitosTerritorialesRelationManager::table()` en `vida/app/Filament/Resources/CentroResource/RelationManagers/AmbitosTerritorialesRelationManager.php:70`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource\RelationManagers\ColeccionesPlazasRelationManager::form()` en `vida/app/Filament/Resources/CentroResource/RelationManagers/ColeccionesPlazasRelationManager.php:24`: Falta docblock de método público.
- `App\Filament\Resources\CentroResource\RelationManagers\ColeccionesPlazasRelationManager::table()` en `vida/app/Filament/Resources/CentroResource/RelationManagers/ColeccionesPlazasRelationManager.php:75`: Falta docblock de método público.
- `App\Filament\Resources\ColectivoProtegidoResource::form()` en `vida/app/Filament/Resources/ColectivoProtegidoResource.php:44`: Falta docblock de método público.
- `App\Filament\Resources\ColectivoProtegidoResource::table()` en `vida/app/Filament/Resources/ColectivoProtegidoResource.php:70`: Falta docblock de método público.
- `App\Filament\Resources\ColectivoProtegidoResource::getPages()` en `vida/app/Filament/Resources/ColectivoProtegidoResource.php:100`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionHorarioLaboralResource::form()` en `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource.php:44`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionHorarioLaboralResource::table()` en `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource.php:81`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionHorarioLaboralResource::getPages()` en `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource.php:108`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionOrganizacionResource::form()` en `vida/app/Filament/Resources/ConfiguracionOrganizacionResource.php:46`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionOrganizacionResource::table()` en `vida/app/Filament/Resources/ConfiguracionOrganizacionResource.php:79`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionOrganizacionResource::getPages()` en `vida/app/Filament/Resources/ConfiguracionOrganizacionResource.php:126`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionRolResource::form()` en `vida/app/Filament/Resources/ConfiguracionRolResource.php:47`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionRolResource::table()` en `vida/app/Filament/Resources/ConfiguracionRolResource.php:71`: Falta docblock de método público.
- `App\Filament\Resources\ConfiguracionRolResource::getPages()` en `vida/app/Filament/Resources/ConfiguracionRolResource.php:99`: Falta docblock de método público.
- `App\Filament\Resources\CuadranteMesResource::form()` en `vida/app/Filament/Resources/CuadranteMesResource.php:43`: Falta docblock de método público.
- `App\Filament\Resources\CuadranteMesResource::table()` en `vida/app/Filament/Resources/CuadranteMesResource.php:102`: Falta docblock de método público.
- `App\Filament\Resources\CuadranteMesResource::getRelationManagers()` en `vida/app/Filament/Resources/CuadranteMesResource.php:191`: Falta docblock de método público.
- `App\Filament\Resources\CuadranteMesResource::getPages()` en `vida/app/Filament/Resources/CuadranteMesResource.php:198`: Falta docblock de método público.
- `App\Filament\Resources\CuadranteMesResource\RelationManagers\LineasCuadranteRelationManager::form()` en `vida/app/Filament/Resources/CuadranteMesResource/RelationManagers/LineasCuadranteRelationManager.php:17`: Falta docblock de método público.
- `App\Filament\Resources\CuadranteMesResource\RelationManagers\LineasCuadranteRelationManager::table()` en `vida/app/Filament/Resources/CuadranteMesResource/RelationManagers/LineasCuadranteRelationManager.php:23`: Falta docblock de método público.
- `App\Filament\Resources\DistritoResource::form()` en `vida/app/Filament/Resources/DistritoResource.php:41`: Falta docblock de método público.
- `App\Filament\Resources\DistritoResource::table()` en `vida/app/Filament/Resources/DistritoResource.php:71`: Falta docblock de método público.
- `App\Filament\Resources\DistritoResource::getPages()` en `vida/app/Filament/Resources/DistritoResource.php:105`: Falta docblock de método público.
- `App\Filament\Resources\DocumentoResource::table()` en `vida/app/Filament/Resources/DocumentoResource.php:120`: Falta docblock de método público.
- `App\Filament\Resources\DocumentoResource::getPages()` en `vida/app/Filament/Resources/DocumentoResource.php:205`: Falta docblock de método público.
- `App\Filament\Resources\EstiloInformeResource::form()` en `vida/app/Filament/Resources/EstiloInformeResource.php:46`: Falta docblock de método público.
- `App\Filament\Resources\EstiloInformeResource::table()` en `vida/app/Filament/Resources/EstiloInformeResource.php:103`: Falta docblock de método público.
- `App\Filament\Resources\EstiloInformeResource::getPages()` en `vida/app/Filament/Resources/EstiloInformeResource.php:147`: Falta docblock de método público.
- `App\Filament\Resources\EstiloInformeResource::canDelete()` en `vida/app/Filament/Resources/EstiloInformeResource.php:168`: Falta docblock de método público.
- `App\Filament\Resources\ExcepcionProfesionalResource::form()` en `vida/app/Filament/Resources/ExcepcionProfesionalResource.php:45`: Falta docblock de método público.
- `App\Filament\Resources\ExcepcionProfesionalResource::table()` en `vida/app/Filament/Resources/ExcepcionProfesionalResource.php:122`: Falta docblock de método público.
- `App\Filament\Resources\ExcepcionProfesionalResource::getPages()` en `vida/app/Filament/Resources/ExcepcionProfesionalResource.php:209`: Falta docblock de método público.
- `App\Filament\Resources\HorarioCentroResource::form()` en `vida/app/Filament/Resources/HorarioCentroResource.php:44`: Falta docblock de método público.
- `App\Filament\Resources\HorarioCentroResource::table()` en `vida/app/Filament/Resources/HorarioCentroResource.php:159`: Falta docblock de método público.
- `App\Filament\Resources\HorarioCentroResource::getRelationManagers()` en `vida/app/Filament/Resources/HorarioCentroResource.php:214`: Falta docblock de método público.
- `App\Filament\Resources\HorarioCentroResource::getPages()` en `vida/app/Filament/Resources/HorarioCentroResource.php:221`: Falta docblock de método público.
- `App\Filament\Resources\HorarioCentroResource\RelationManagers\TiposSlotsRelationManager::form()` en `vida/app/Filament/Resources/HorarioCentroResource/RelationManagers/TiposSlotsRelationManager.php:25`: Falta docblock de método público.
- `App\Filament\Resources\HorarioCentroResource\RelationManagers\TiposSlotsRelationManager::table()` en `vida/app/Filament/Resources/HorarioCentroResource/RelationManagers/TiposSlotsRelationManager.php:81`: Falta docblock de método público.
- `App\Filament\Resources\InformeResource::table()` en `vida/app/Filament/Resources/InformeResource.php:152`: Falta docblock de método público.
- `App\Filament\Resources\InformeResource::getPages()` en `vida/app/Filament/Resources/InformeResource.php:294`: Falta docblock de método público.
- `App\Filament\Resources\LogAlertasResource::table()` en `vida/app/Filament/Resources/LogAlertasResource.php:38`: Falta docblock de método público.
- `App\Filament\Resources\LogAlertasResource::getPages()` en `vida/app/Filament/Resources/LogAlertasResource.php:119`: Falta docblock de método público.
- `App\Filament\Resources\LogAlertasResource::canCreate()` en `vida/app/Filament/Resources/LogAlertasResource.php:133`: Falta docblock de método público.
- `App\Filament\Resources\LogAlertasResource::canEdit()` en `vida/app/Filament/Resources/LogAlertasResource.php:138`: Falta docblock de método público.
- `App\Filament\Resources\LogAlertasResource::canDelete()` en `vida/app/Filament/Resources/LogAlertasResource.php:143`: Falta docblock de método público.
- `App\Filament\Resources\PerfilHorarioProfesionalResource::form()` en `vida/app/Filament/Resources/PerfilHorarioProfesionalResource.php:41`: Falta docblock de método público.
- `App\Filament\Resources\PerfilHorarioProfesionalResource::table()` en `vida/app/Filament/Resources/PerfilHorarioProfesionalResource.php:112`: Falta docblock de método público.
- `App\Filament\Resources\PerfilHorarioProfesionalResource::getPages()` en `vida/app/Filament/Resources/PerfilHorarioProfesionalResource.php:159`: Falta docblock de método público.
- `App\Filament\Resources\PlantillaInformeResource::form()` en `vida/app/Filament/Resources/PlantillaInformeResource.php:52`: Falta docblock de método público.
- `App\Filament\Resources\PlantillaInformeResource::table()` en `vida/app/Filament/Resources/PlantillaInformeResource.php:200`: Falta docblock de método público.
- `App\Filament\Resources\PlantillaInformeResource::getPages()` en `vida/app/Filament/Resources/PlantillaInformeResource.php:255`: Falta docblock de método público.
- `App\Filament\Resources\PlantillaInformeResource::canEdit()` en `vida/app/Filament/Resources/PlantillaInformeResource.php:270`: Falta docblock de método público.
- `App\Filament\Resources\PlantillaInformeResource::canDelete()` en `vida/app/Filament/Resources/PlantillaInformeResource.php:275`: Falta docblock de método público.
- `App\Filament\Resources\PrestacionResource::form()` en `vida/app/Filament/Resources/PrestacionResource.php:42`: Falta docblock de método público.
- `App\Filament\Resources\PrestacionResource::table()` en `vida/app/Filament/Resources/PrestacionResource.php:235`: Falta docblock de método público.
- `App\Filament\Resources\PrestacionResource::getRelationManagers()` en `vida/app/Filament/Resources/PrestacionResource.php:328`: Falta docblock de método público.
- `App\Filament\Resources\PrestacionResource::getPages()` en `vida/app/Filament/Resources/PrestacionResource.php:335`: Falta docblock de método público.
- `App\Filament\Resources\PrestacionResource\RelationManagers\VersionesRelationManager::table()` en `vida/app/Filament/Resources/PrestacionResource/RelationManagers/VersionesRelationManager.php:23`: Falta docblock de método público.
- `App\Filament\Resources\PrestacionResource\RelationManagers\VersionesRelationManager::isReadOnly()` en `vida/app/Filament/Resources/PrestacionResource/RelationManagers/VersionesRelationManager.php:64`: Falta docblock de método público.
- `App\Filament\Resources\ProfesionalResource::form()` en `vida/app/Filament/Resources/ProfesionalResource.php:49`: Falta docblock de método público.
- `App\Filament\Resources\ProfesionalResource::table()` en `vida/app/Filament/Resources/ProfesionalResource.php:173`: Falta docblock de método público.
- `App\Filament\Resources\ProfesionalResource::getPages()` en `vida/app/Filament/Resources/ProfesionalResource.php:220`: Falta docblock de método público.
- `App\Filament\Resources\ProfesionalResource::canCreate()` en `vida/app/Filament/Resources/ProfesionalResource.php:235`: Falta docblock de método público.
- `App\Filament\Resources\ProfesionalResource::canEdit()` en `vida/app/Filament/Resources/ProfesionalResource.php:240`: Falta docblock de método público.
- `App\Filament\Resources\RedResource::form()` en `vida/app/Filament/Resources/RedResource.php:39`: Falta docblock de método público.
- `App\Filament\Resources\RedResource::table()` en `vida/app/Filament/Resources/RedResource.php:87`: Falta docblock de método público.
- `App\Filament\Resources\RedResource::getPages()` en `vida/app/Filament/Resources/RedResource.php:117`: Falta docblock de método público.
- `App\Filament\Resources\RolResource::form()` en `vida/app/Filament/Resources/RolResource.php:41`: Falta docblock de método público.
- `App\Filament\Resources\RolResource::table()` en `vida/app/Filament/Resources/RolResource.php:65`: Falta docblock de método público.
- `App\Filament\Resources\RolResource::getPages()` en `vida/app/Filament/Resources/RolResource.php:91`: Falta docblock de método público.
- `App\Filament\Resources\RolResource::canViewAny()` en `vida/app/Filament/Resources/RolResource.php:101`: Falta docblock de método público.
- `App\Filament\Resources\RolResource::canCreate()` en `vida/app/Filament/Resources/RolResource.php:106`: Falta docblock de método público.
- `App\Filament\Resources\RolResource::canEdit()` en `vida/app/Filament/Resources/RolResource.php:111`: Falta docblock de método público.
- `App\Filament\Resources\RolResource::canDelete()` en `vida/app/Filament/Resources/RolResource.php:116`: Falta docblock de método público.
- `App\Filament\Resources\SegmentoPoblacionResource::form()` en `vida/app/Filament/Resources/SegmentoPoblacionResource.php:37`: Falta docblock de método público.
- `App\Filament\Resources\SegmentoPoblacionResource::table()` en `vida/app/Filament/Resources/SegmentoPoblacionResource.php:60`: Falta docblock de método público.
- `App\Filament\Resources\SegmentoPoblacionResource::getPages()` en `vida/app/Filament/Resources/SegmentoPoblacionResource.php:89`: Falta docblock de método público.
- `App\Filament\Resources\ServicioEmergenciaResource::form()` en `vida/app/Filament/Resources/ServicioEmergenciaResource.php:47`: Falta docblock de método público.
- `App\Filament\Resources\ServicioEmergenciaResource::table()` en `vida/app/Filament/Resources/ServicioEmergenciaResource.php:68`: Falta docblock de método público.
- `App\Filament\Resources\ServicioEmergenciaResource::getPages()` en `vida/app/Filament/Resources/ServicioEmergenciaResource.php:97`: Falta docblock de método público.
- `App\Filament\Resources\TipoActividadResource::form()` en `vida/app/Filament/Resources/TipoActividadResource.php:37`: Falta docblock de método público.
- `App\Filament\Resources\TipoActividadResource::table()` en `vida/app/Filament/Resources/TipoActividadResource.php:59`: Falta docblock de método público.
- `App\Filament\Resources\TipoActividadResource::getPages()` en `vida/app/Filament/Resources/TipoActividadResource.php:83`: Falta docblock de método público.
- `App\Filament\Resources\TipoEscalaResource::table()` en `vida/app/Filament/Resources/TipoEscalaResource.php:51`: Falta docblock de método público.
- `App\Filament\Resources\TipoEscalaResource::form()` en `vida/app/Filament/Resources/TipoEscalaResource.php:106`: Falta docblock de método público.
- `App\Filament\Resources\TipoEscalaResource::getPages()` en `vida/app/Filament/Resources/TipoEscalaResource.php:352`: Falta docblock de método público.
- `App\Filament\Resources\TipoEspacioResource::form()` en `vida/app/Filament/Resources/TipoEspacioResource.php:37`: Falta docblock de método público.
- `App\Filament\Resources\TipoEspacioResource::table()` en `vida/app/Filament/Resources/TipoEspacioResource.php:59`: Falta docblock de método público.
- `App\Filament\Resources\TipoEspacioResource::getPages()` en `vida/app/Filament/Resources/TipoEspacioResource.php:83`: Falta docblock de método público.
- `App\Filament\Resources\TipoFichaResource::table()` en `vida/app/Filament/Resources/TipoFichaResource.php:58`: Falta docblock de método público.
- `App\Filament\Resources\TipoFichaResource::form()` en `vida/app/Filament/Resources/TipoFichaResource.php:103`: Falta docblock de método público.
- `App\Filament\Resources\TipoFichaResource::getPages()` en `vida/app/Filament/Resources/TipoFichaResource.php:272`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionProfesionalResource::form()` en `vida/app/Filament/Resources/TipoRelacionProfesionalResource.php:41`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionProfesionalResource::table()` en `vida/app/Filament/Resources/TipoRelacionProfesionalResource.php:63`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionProfesionalResource::getPages()` en `vida/app/Filament/Resources/TipoRelacionProfesionalResource.php:98`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionResource::form()` en `vida/app/Filament/Resources/TipoRelacionResource.php:47`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionResource::table()` en `vida/app/Filament/Resources/TipoRelacionResource.php:114`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionResource::canViewAny()` en `vida/app/Filament/Resources/TipoRelacionResource.php:182`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionResource::canCreate()` en `vida/app/Filament/Resources/TipoRelacionResource.php:187`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionResource::canEdit()` en `vida/app/Filament/Resources/TipoRelacionResource.php:192`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionResource::canDelete()` en `vida/app/Filament/Resources/TipoRelacionResource.php:197`: Falta docblock de método público.
- `App\Filament\Resources\TipoRelacionResource::getPages()` en `vida/app/Filament/Resources/TipoRelacionResource.php:206`: Falta docblock de método público.
- `App\Filament\Resources\TipoSlotResource::form()` en `vida/app/Filament/Resources/TipoSlotResource.php:42`: Falta docblock de método público.
- `App\Filament\Resources\TipoSlotResource::table()` en `vida/app/Filament/Resources/TipoSlotResource.php:115`: Falta docblock de método público.
- `App\Filament\Resources\TipoSlotResource::getPages()` en `vida/app/Filament/Resources/TipoSlotResource.php:176`: Falta docblock de método público.
- `App\Filament\Resources\TitulacionResource::form()` en `vida/app/Filament/Resources/TitulacionResource.php:41`: Falta docblock de método público.
- `App\Filament\Resources\TitulacionResource::table()` en `vida/app/Filament/Resources/TitulacionResource.php:58`: Falta docblock de método público.
- `App\Filament\Resources\TitulacionResource::getPages()` en `vida/app/Filament/Resources/TitulacionResource.php:87`: Falta docblock de método público.
- `App\Filament\Resources\UnidadOrganizativaResource::form()` en `vida/app/Filament/Resources/UnidadOrganizativaResource.php:43`: Falta docblock de método público.
- `App\Filament\Resources\UnidadOrganizativaResource::table()` en `vida/app/Filament/Resources/UnidadOrganizativaResource.php:99`: Falta docblock de método público.
- `App\Filament\Resources\UnidadOrganizativaResource::getPages()` en `vida/app/Filament/Resources/UnidadOrganizativaResource.php:148`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioResource::form()` en `vida/app/Filament/Resources/UsuarioResource.php:48`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioResource::table()` en `vida/app/Filament/Resources/UsuarioResource.php:150`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioResource::getPages()` en `vida/app/Filament/Resources/UsuarioResource.php:193`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioResource::canViewAny()` en `vida/app/Filament/Resources/UsuarioResource.php:202`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioResource::canCreate()` en `vida/app/Filament/Resources/UsuarioResource.php:207`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioResource::canEdit()` en `vida/app/Filament/Resources/UsuarioResource.php:212`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioRolResource::form()` en `vida/app/Filament/Resources/UsuarioRolResource.php:48`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioRolResource::table()` en `vida/app/Filament/Resources/UsuarioRolResource.php:90`: Falta docblock de método público.
- `App\Filament\Resources\UsuarioRolResource::getPages()` en `vida/app/Filament/Resources/UsuarioRolResource.php:151`: Falta docblock de método público.
- `App\Filament\Resources\ZonaResource::form()` en `vida/app/Filament/Resources/ZonaResource.php:43`: Falta docblock de método público.
- `App\Filament\Resources\ZonaResource::table()` en `vida/app/Filament/Resources/ZonaResource.php:71`: Falta docblock de método público.
- `App\Filament\Resources\ZonaResource::getPages()` en `vida/app/Filament/Resources/ZonaResource.php:108`: Falta docblock de método público.
- `App\Filament\Widgets\ActividadCatalogosWidget::canView()` en `vida/app/Filament/Widgets/ActividadCatalogosWidget.php:25`: Falta docblock de método público.
- `App\Filament\Widgets\ActividadCatalogosWidget::table()` en `vida/app/Filament/Widgets/ActividadCatalogosWidget.php:30`: Falta docblock de método público.
- `App\Filament\Widgets\AlertasSistemaWidget::canView()` en `vida/app/Filament/Widgets/AlertasSistemaWidget.php:23`: Falta docblock de método público.
- `App\Filament\Widgets\AlertasSistemaWidget::table()` en `vida/app/Filament/Widgets/AlertasSistemaWidget.php:28`: Falta docblock de método público.
- `App\Filament\Widgets\EstadoSistemaWidget::canView()` en `vida/app/Filament/Widgets/EstadoSistemaWidget.php:23`: Falta docblock de método público.
- `App\Filament\Widgets\RolesPendientesWidget::canView()` en `vida/app/Filament/Widgets/RolesPendientesWidget.php:23`: Falta docblock de método público.
- `App\Filament\Widgets\RolesPendientesWidget::table()` en `vida/app/Filament/Widgets/RolesPendientesWidget.php:28`: Falta docblock de método público.
- `App\Http\Middleware\AuditarAccesoCiudadano::__construct()` en `vida/app/Http/Middleware/AuditarAccesoCiudadano.php:31`: Falta docblock de método público.
- `App\Http\Middleware\AuditarAccesoCiudadano::handle()` en `vida/app/Http/Middleware/AuditarAccesoCiudadano.php:33`: Falta docblock de método público.
- `App\Http\Middleware\PrimerAcceso::handle()` en `vida/app/Http/Middleware/PrimerAcceso.php:14`: Falta docblock de método público.
- `App\Livewire\Centros\SelectorPrestacionesCentro::render()` en `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:179`: Falta docblock de método público.
- `App\Models\CatalogoSistema::scopeDeGrupo()` en `vida/app/Models/CatalogoSistema.php:43`: Falta docblock de método público.
- `App\Models\Ciudadano::prestacionesResumen()` en `vida/app/Models/Ciudadano.php:176`: Falta docblock de método público.
- `App\Models\HistoriaSocial::ciudadano()` en `vida/app/Models/HistoriaSocial.php:96`: Falta docblock de método público.
- `App\Observers\AuditObserver::__construct()` en `vida/app/Observers/AuditObserver.php:26`: Falta docblock de método público.
- `App\Providers\Filament\AdminPanelProvider::panel()` en `vida/app/Providers/Filament/AdminPanelProvider.php:24`: Falta docblock de método público.
- `App\Services\Geocodificacion\ResultadoGeocodificacion::__construct()` en `vida/app/Services/Geocodificacion/ResultadoGeocodificacion.php:34`: Falta docblock de método público.

### Clase sin PHPDoc (120)

- `Modules\Intervencion\Enums\TipoEntrevista` en `vida/Modules/Intervencion/app/Enums/TipoEntrevista.php:5`: Falta docblock de cabecera.
- `Modules\Intervencion\Enums\TipoPlan` en `vida/Modules/Intervencion/app/Enums/TipoPlan.php:5`: Falta docblock de cabecera.
- `Modules\Intervencion\Enums\UrgenciaSia` en `vida/Modules/Intervencion/app/Enums/UrgenciaSia.php:5`: Falta docblock de cabecera.
- `Modules\Intervencion\Enums\VisibilidadApunte` en `vida/Modules/Intervencion/app/Enums/VisibilidadApunte.php:5`: Falta docblock de cabecera.
- `App\Filament\Resources\AuditResource\Pages\ListAudits` en `vida/app/Filament/Resources/AuditResource/Pages/ListAudits.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\AuditResource\Pages\ViewAudit` en `vida/app/Filament/Resources/AuditResource/Pages/ViewAudit.php:14`: Falta docblock de cabecera.
- `App\Filament\Resources\CargoResource\Pages\CreateCargo` en `vida/app/Filament/Resources/CargoResource/Pages/CreateCargo.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\CargoResource\Pages\EditCargo` en `vida/app/Filament/Resources/CargoResource/Pages/EditCargo.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\CargoResource\Pages\ListCargos` en `vida/app/Filament/Resources/CargoResource/Pages/ListCargos.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\CentroResource` en `vida/app/Filament/Resources/CentroResource.php:27`: Falta docblock de cabecera.
- `App\Filament\Resources\CentroResource\Pages\CreateCentro` en `vida/app/Filament/Resources/CentroResource/Pages/CreateCentro.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\CentroResource\Pages\ListCentros` en `vida/app/Filament/Resources/CentroResource/Pages/ListCentros.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\CentroResource\RelationManagers\AmbitosTerritorialesRelationManager` en `vida/app/Filament/Resources/CentroResource/RelationManagers/AmbitosTerritorialesRelationManager.php:18`: Falta docblock de cabecera.
- `App\Filament\Resources\CentroResource\RelationManagers\ColeccionesPlazasRelationManager` en `vida/app/Filament/Resources/CentroResource/RelationManagers/ColeccionesPlazasRelationManager.php:18`: Falta docblock de cabecera.
- `App\Filament\Resources\ColectivoProtegidoResource\Pages\CreateColectivoProtegido` en `vida/app/Filament/Resources/ColectivoProtegidoResource/Pages/CreateColectivoProtegido.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ColectivoProtegidoResource\Pages\EditColectivoProtegido` en `vida/app/Filament/Resources/ColectivoProtegidoResource/Pages/EditColectivoProtegido.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ColectivoProtegidoResource\Pages\ListColectivosProtegidos` en `vida/app/Filament/Resources/ColectivoProtegidoResource/Pages/ListColectivosProtegidos.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionHorarioLaboralResource\Pages\EditConfiguracionHorarioLaboral` en `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource/Pages/EditConfiguracionHorarioLaboral.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionHorarioLaboralResource\Pages\ListConfiguracionHorarioLaboral` en `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource/Pages/ListConfiguracionHorarioLaboral.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionOrganizacionResource\Pages\CreateConfiguracion` en `vida/app/Filament/Resources/ConfiguracionOrganizacionResource/Pages/CreateConfiguracion.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionOrganizacionResource\Pages\EditConfiguracion` en `vida/app/Filament/Resources/ConfiguracionOrganizacionResource/Pages/EditConfiguracion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionOrganizacionResource\Pages\ListConfiguracion` en `vida/app/Filament/Resources/ConfiguracionOrganizacionResource/Pages/ListConfiguracion.php:12`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionRolResource\Pages\CreateConfiguracionRol` en `vida/app/Filament/Resources/ConfiguracionRolResource/Pages/CreateConfiguracionRol.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionRolResource\Pages\EditConfiguracionRol` en `vida/app/Filament/Resources/ConfiguracionRolResource/Pages/EditConfiguracionRol.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ConfiguracionRolResource\Pages\ListConfiguracionRol` en `vida/app/Filament/Resources/ConfiguracionRolResource/Pages/ListConfiguracionRol.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\CuadranteMesResource` en `vida/app/Filament/Resources/CuadranteMesResource.php:23`: Falta docblock de cabecera.
- `App\Filament\Resources\CuadranteMesResource\Pages\CreateCuadranteMes` en `vida/app/Filament/Resources/CuadranteMesResource/Pages/CreateCuadranteMes.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\CuadranteMesResource\Pages\EditCuadranteMes` en `vida/app/Filament/Resources/CuadranteMesResource/Pages/EditCuadranteMes.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\CuadranteMesResource\Pages\ListCuadrantesMes` en `vida/app/Filament/Resources/CuadranteMesResource/Pages/ListCuadrantesMes.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\CuadranteMesResource\RelationManagers\LineasCuadranteRelationManager` en `vida/app/Filament/Resources/CuadranteMesResource/RelationManagers/LineasCuadranteRelationManager.php:11`: Falta docblock de cabecera.
- `App\Filament\Resources\DistritoResource\Pages\CreateDistrito` en `vida/app/Filament/Resources/DistritoResource/Pages/CreateDistrito.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\DistritoResource\Pages\EditDistrito` en `vida/app/Filament/Resources/DistritoResource/Pages/EditDistrito.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\DistritoResource\Pages\ListDistritos` en `vida/app/Filament/Resources/DistritoResource/Pages/ListDistritos.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\DocumentoResource\Pages\ListDocumentos` en `vida/app/Filament/Resources/DocumentoResource/Pages/ListDocumentos.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\DocumentoResource\Pages\ViewDocumento` en `vida/app/Filament/Resources/DocumentoResource/Pages/ViewDocumento.php:11`: Falta docblock de cabecera.
- `App\Filament\Resources\EstiloInformeResource\Pages\CreateEstiloInforme` en `vida/app/Filament/Resources/EstiloInformeResource/Pages/CreateEstiloInforme.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\EstiloInformeResource\Pages\EditEstiloInforme` en `vida/app/Filament/Resources/EstiloInformeResource/Pages/EditEstiloInforme.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\EstiloInformeResource\Pages\ListEstilosInforme` en `vida/app/Filament/Resources/EstiloInformeResource/Pages/ListEstilosInforme.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ExcepcionProfesionalResource` en `vida/app/Filament/Resources/ExcepcionProfesionalResource.php:25`: Falta docblock de cabecera.
- `App\Filament\Resources\ExcepcionProfesionalResource\Pages\CreateExcepcionProfesional` en `vida/app/Filament/Resources/ExcepcionProfesionalResource/Pages/CreateExcepcionProfesional.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ExcepcionProfesionalResource\Pages\EditExcepcionProfesional` en `vida/app/Filament/Resources/ExcepcionProfesionalResource/Pages/EditExcepcionProfesional.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ExcepcionProfesionalResource\Pages\ListExcepcionesProfesional` en `vida/app/Filament/Resources/ExcepcionProfesionalResource/Pages/ListExcepcionesProfesional.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\HorarioCentroResource` en `vida/app/Filament/Resources/HorarioCentroResource.php:26`: Falta docblock de cabecera.
- `App\Filament\Resources\HorarioCentroResource\Pages\CreateHorarioCentro` en `vida/app/Filament/Resources/HorarioCentroResource/Pages/CreateHorarioCentro.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\HorarioCentroResource\Pages\EditHorarioCentro` en `vida/app/Filament/Resources/HorarioCentroResource/Pages/EditHorarioCentro.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\HorarioCentroResource\Pages\ListHorariosCentro` en `vida/app/Filament/Resources/HorarioCentroResource/Pages/ListHorariosCentro.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\HorarioCentroResource\RelationManagers\TiposSlotsRelationManager` en `vida/app/Filament/Resources/HorarioCentroResource/RelationManagers/TiposSlotsRelationManager.php:19`: Falta docblock de cabecera.
- `App\Filament\Resources\InformeResource\Pages\ListInformes` en `vida/app/Filament/Resources/InformeResource/Pages/ListInformes.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\InformeResource\Pages\ViewInforme` en `vida/app/Filament/Resources/InformeResource/Pages/ViewInforme.php:13`: Falta docblock de cabecera.
- `App\Filament\Resources\LogAlertasResource\Pages\ListLogAlertas` en `vida/app/Filament/Resources/LogAlertasResource/Pages/ListLogAlertas.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\PerfilHorarioProfesionalResource` en `vida/app/Filament/Resources/PerfilHorarioProfesionalResource.php:23`: Falta docblock de cabecera.
- `App\Filament\Resources\PerfilHorarioProfesionalResource\Pages\CreatePerfilHorarioProfesional` en `vida/app/Filament/Resources/PerfilHorarioProfesionalResource/Pages/CreatePerfilHorarioProfesional.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\PerfilHorarioProfesionalResource\Pages\EditPerfilHorarioProfesional` en `vida/app/Filament/Resources/PerfilHorarioProfesionalResource/Pages/EditPerfilHorarioProfesional.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\PerfilHorarioProfesionalResource\Pages\ListPerfilesHorarioProfesional` en `vida/app/Filament/Resources/PerfilHorarioProfesionalResource/Pages/ListPerfilesHorarioProfesional.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\PlantillaInformeResource\Pages\CreatePlantillaInforme` en `vida/app/Filament/Resources/PlantillaInformeResource/Pages/CreatePlantillaInforme.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\PlantillaInformeResource\Pages\EditPlantillaInforme` en `vida/app/Filament/Resources/PlantillaInformeResource/Pages/EditPlantillaInforme.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\PlantillaInformeResource\Pages\ListPlantillasInforme` en `vida/app/Filament/Resources/PlantillaInformeResource/Pages/ListPlantillasInforme.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\PrestacionResource` en `vida/app/Filament/Resources/PrestacionResource.php:24`: Falta docblock de cabecera.
- `App\Filament\Resources\PrestacionResource\Pages\CreatePrestacion` en `vida/app/Filament/Resources/PrestacionResource/Pages/CreatePrestacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\PrestacionResource\Pages\EditPrestacion` en `vida/app/Filament/Resources/PrestacionResource/Pages/EditPrestacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\PrestacionResource\Pages\ListPrestaciones` en `vida/app/Filament/Resources/PrestacionResource/Pages/ListPrestaciones.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ProfesionalResource\Pages\CreateProfesional` en `vida/app/Filament/Resources/ProfesionalResource/Pages/CreateProfesional.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ProfesionalResource\Pages\EditProfesional` en `vida/app/Filament/Resources/ProfesionalResource/Pages/EditProfesional.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ProfesionalResource\Pages\ListProfesionales` en `vida/app/Filament/Resources/ProfesionalResource/Pages/ListProfesionales.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\RedResource` en `vida/app/Filament/Resources/RedResource.php:21`: Falta docblock de cabecera.
- `App\Filament\Resources\RedResource\Pages\CreateRed` en `vida/app/Filament/Resources/RedResource/Pages/CreateRed.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\RedResource\Pages\EditRed` en `vida/app/Filament/Resources/RedResource/Pages/EditRed.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\RedResource\Pages\ListRedes` en `vida/app/Filament/Resources/RedResource/Pages/ListRedes.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\RolResource\Pages\CreateRol` en `vida/app/Filament/Resources/RolResource/Pages/CreateRol.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\RolResource\Pages\EditRol` en `vida/app/Filament/Resources/RolResource/Pages/EditRol.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\RolResource\Pages\ListRoles` en `vida/app/Filament/Resources/RolResource/Pages/ListRoles.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\SegmentoPoblacionResource` en `vida/app/Filament/Resources/SegmentoPoblacionResource.php:19`: Falta docblock de cabecera.
- `App\Filament\Resources\SegmentoPoblacionResource\Pages\CreateSegmentoPoblacion` en `vida/app/Filament/Resources/SegmentoPoblacionResource/Pages/CreateSegmentoPoblacion.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\SegmentoPoblacionResource\Pages\EditSegmentoPoblacion` en `vida/app/Filament/Resources/SegmentoPoblacionResource/Pages/EditSegmentoPoblacion.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\SegmentoPoblacionResource\Pages\ListSegmentosPoblacion` en `vida/app/Filament/Resources/SegmentoPoblacionResource/Pages/ListSegmentosPoblacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ServicioEmergenciaResource\Pages\CreateServicioEmergencia` en `vida/app/Filament/Resources/ServicioEmergenciaResource/Pages/CreateServicioEmergencia.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ServicioEmergenciaResource\Pages\EditServicioEmergencia` en `vida/app/Filament/Resources/ServicioEmergenciaResource/Pages/EditServicioEmergencia.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ServicioEmergenciaResource\Pages\ListServiciosEmergencia` en `vida/app/Filament/Resources/ServicioEmergenciaResource/Pages/ListServiciosEmergencia.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoActividadResource` en `vida/app/Filament/Resources/TipoActividadResource.php:19`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoActividadResource\Pages\CreateTipoActividad` en `vida/app/Filament/Resources/TipoActividadResource/Pages/CreateTipoActividad.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoActividadResource\Pages\EditTipoActividad` en `vida/app/Filament/Resources/TipoActividadResource/Pages/EditTipoActividad.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoActividadResource\Pages\ListTiposActividad` en `vida/app/Filament/Resources/TipoActividadResource/Pages/ListTiposActividad.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEscalaResource` en `vida/app/Filament/Resources/TipoEscalaResource.php:29`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEscalaResource\Pages\CreateTipoEscala` en `vida/app/Filament/Resources/TipoEscalaResource/Pages/CreateTipoEscala.php:10`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEscalaResource\Pages\EditTipoEscala` en `vida/app/Filament/Resources/TipoEscalaResource/Pages/EditTipoEscala.php:11`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEscalaResource\Pages\ListTipoEscalas` en `vida/app/Filament/Resources/TipoEscalaResource/Pages/ListTipoEscalas.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEspacioResource` en `vida/app/Filament/Resources/TipoEspacioResource.php:19`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEspacioResource\Pages\CreateTipoEspacio` en `vida/app/Filament/Resources/TipoEspacioResource/Pages/CreateTipoEspacio.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEspacioResource\Pages\EditTipoEspacio` en `vida/app/Filament/Resources/TipoEspacioResource/Pages/EditTipoEspacio.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoEspacioResource\Pages\ListTiposEspacio` en `vida/app/Filament/Resources/TipoEspacioResource/Pages/ListTiposEspacio.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoFichaResource\Pages\CreateTipoFicha` en `vida/app/Filament/Resources/TipoFichaResource/Pages/CreateTipoFicha.php:11`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoFichaResource\Pages\EditTipoFicha` en `vida/app/Filament/Resources/TipoFichaResource/Pages/EditTipoFicha.php:13`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoFichaResource\Pages\ListTipoFichas` en `vida/app/Filament/Resources/TipoFichaResource/Pages/ListTipoFichas.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoRelacionProfesionalResource\Pages\CreateTipoRelacion` en `vida/app/Filament/Resources/TipoRelacionProfesionalResource/Pages/CreateTipoRelacion.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoRelacionProfesionalResource\Pages\EditTipoRelacion` en `vida/app/Filament/Resources/TipoRelacionProfesionalResource/Pages/EditTipoRelacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoRelacionProfesionalResource\Pages\ListTiposRelacion` en `vida/app/Filament/Resources/TipoRelacionProfesionalResource/Pages/ListTiposRelacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoRelacionResource\Pages\CreateTipoRelacion` en `vida/app/Filament/Resources/TipoRelacionResource/Pages/CreateTipoRelacion.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoRelacionResource\Pages\EditTipoRelacion` en `vida/app/Filament/Resources/TipoRelacionResource/Pages/EditTipoRelacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoRelacionResource\Pages\ListTiposRelacion` en `vida/app/Filament/Resources/TipoRelacionResource/Pages/ListTiposRelacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoSlotResource` en `vida/app/Filament/Resources/TipoSlotResource.php:22`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoSlotResource\Pages\CreateTipoSlot` en `vida/app/Filament/Resources/TipoSlotResource/Pages/CreateTipoSlot.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoSlotResource\Pages\EditTipoSlot` en `vida/app/Filament/Resources/TipoSlotResource/Pages/EditTipoSlot.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TipoSlotResource\Pages\ListTiposSlot` en `vida/app/Filament/Resources/TipoSlotResource/Pages/ListTiposSlot.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TitulacionResource\Pages\CreateTitulacion` en `vida/app/Filament/Resources/TitulacionResource/Pages/CreateTitulacion.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\TitulacionResource\Pages\EditTitulacion` en `vida/app/Filament/Resources/TitulacionResource/Pages/EditTitulacion.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\TitulacionResource\Pages\ListTitulaciones` en `vida/app/Filament/Resources/TitulacionResource/Pages/ListTitulaciones.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\UnidadOrganizativaResource\Pages\CreateUnidadOrganizativa` en `vida/app/Filament/Resources/UnidadOrganizativaResource/Pages/CreateUnidadOrganizativa.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\UnidadOrganizativaResource\Pages\EditUnidadOrganizativa` en `vida/app/Filament/Resources/UnidadOrganizativaResource/Pages/EditUnidadOrganizativa.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\UnidadOrganizativaResource\Pages\ListUnidadesOrganizativas` en `vida/app/Filament/Resources/UnidadOrganizativaResource/Pages/ListUnidadesOrganizativas.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\UsuarioResource\Pages\CreateUsuario` en `vida/app/Filament/Resources/UsuarioResource/Pages/CreateUsuario.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\UsuarioResource\Pages\EditUsuario` en `vida/app/Filament/Resources/UsuarioResource/Pages/EditUsuario.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\UsuarioResource\Pages\ListUsuarios` en `vida/app/Filament/Resources/UsuarioResource/Pages/ListUsuarios.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\UsuarioRolResource\Pages\CreateUsuarioRol` en `vida/app/Filament/Resources/UsuarioRolResource/Pages/CreateUsuarioRol.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\UsuarioRolResource\Pages\EditUsuarioRol` en `vida/app/Filament/Resources/UsuarioRolResource/Pages/EditUsuarioRol.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\UsuarioRolResource\Pages\ListUsuarioRoles` en `vida/app/Filament/Resources/UsuarioRolResource/Pages/ListUsuarioRoles.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ZonaResource\Pages\CreateZona` en `vida/app/Filament/Resources/ZonaResource/Pages/CreateZona.php:8`: Falta docblock de cabecera.
- `App\Filament\Resources\ZonaResource\Pages\EditZona` en `vida/app/Filament/Resources/ZonaResource/Pages/EditZona.php:9`: Falta docblock de cabecera.
- `App\Filament\Resources\ZonaResource\Pages\ListZonas` en `vida/app/Filament/Resources/ZonaResource/Pages/ListZonas.php:9`: Falta docblock de cabecera.
- `App\Http\Controllers\Controller` en `vida/app/Http/Controllers/Controller.php:5`: Falta docblock de cabecera.
- `App\Providers\Filament\AdminPanelProvider` en `vida/app/Providers/Filament/AdminPanelProvider.php:22`: Falta docblock de cabecera.

## Referencia

### `Modules\Agenda\Enums\EstadoCita`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/EstadoCita.php:8`.
- Resumen: Estados posibles del ciclo de vida de una cita de agenda.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el estado de la cita en la interfaz.

### `Modules\Agenda\Enums\EstadoCuadrante`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/EstadoCuadrante.php:8`.
- Resumen: Estados editoriales de un cuadrante mensual de agenda.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el estado del cuadrante.

### `Modules\Agenda\Enums\EstadoSlot`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/EstadoSlot.php:8`.
- Resumen: Estados disponibles para un slot de disponibilidad en agenda.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el estado del slot.

### `Modules\Agenda\Enums\ModoAgenda`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/ModoAgenda.php:8`.
- Resumen: Modos de configuracion de la agenda por nivel de detalle.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el modo de agenda.

### `Modules\Agenda\Enums\MotivoReasignacion`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/MotivoReasignacion.php:8`.
- Resumen: Motivos normalizados para registrar la reasignacion de una cita.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el motivo de reasignacion.

### `Modules\Agenda\Enums\OrigenCita`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/OrigenCita.php:8`.
- Resumen: Origenes desde los que puede crearse una cita.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el origen de la cita.

### `Modules\Agenda\Enums\OrigenExcepcion`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/OrigenExcepcion.php:8`.
- Resumen: Origenes desde los que puede registrarse una excepcion profesional.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el origen de la excepcion.

### `Modules\Agenda\Enums\OrigenPermitidoSlot`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/OrigenPermitidoSlot.php:8`.
- Resumen: Origenes habilitados para reservar un tipo de slot.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar los origenes permitidos del slot.

### `Modules\Agenda\Enums\TipoExcepcion`

- Tipo: enum.
- Fichero: `vida/Modules/Agenda/app/Enums/TipoExcepcion.php:8`.
- Resumen: Tipos de excepcion que afectan a la disponibilidad profesional.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el tipo de excepcion.

### `Modules\Agenda\Jobs\SlotExpirationJob`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Jobs/SlotExpirationJob.php:24`.
- Resumen: Actualiza el estado de los slots cuya hora ha pasado al final del día.

- Pasa a 'expirado' los slots en estado 'bloqueado_urgencia' cuya hora ha pasado. - Pasa a 'no_ocupado' los slots en estado 'disponible' no reservados cuya hora ha pasado. - Pasa a 'no_ocupado' los slots en estado 'reservado' de fechas pasadas sin cita activa (no-shows de ciudadano cuyo slot no fue liberado a tiempo).  No realiza ninguna acción sobre profesionales ni citas. Se ejecuta diariamente al final del día laboral vía scheduler.

Metodos publicos:

- `function handle(): void`
  Transiciona los slots expirados a sus estados finales.
  `@return` void

### `Modules\Agenda\Models\Cita`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/Cita.php:48`.
- Resumen: Reserva de un slot con un ciudadano concreto.

Una cita vincula un slot con un ciudadano y tiene un ciclo de vida propio. Puede originarse en el sistema interno o vía API externa. Al crearse, el slot asociado pasa a estado 'reservado'.

Metodos publicos:

- `function slot(): BelongsTo`
  _Sin resumen PHPDoc._
- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._
- `function profesional(): BelongsTo`
  _Sin resumen PHPDoc._
- `function tipoSlot(): BelongsTo`
  _Sin resumen PHPDoc._
- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function creadoPor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function canceladoPor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function reasignacion(): HasOne`
  _Sin resumen PHPDoc._
- `function scopeConfirmadas(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelDia(Builder $query, $fecha): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelProfesional(Builder $query, int $usuarioId): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelCiudadano(Builder $query, int $ciudadanoId): Builder`
  _Sin resumen PHPDoc._
- `function scopePendientesReasignacion(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function noShowCiudadano(): void`
  Registra el no-show del ciudadano sin modificar el slot.
  `@return` void
- `function completar(): void`
  Marca la cita como completada y registra el momento exacto.
  `@return` void
- `function cancelar(User $canceladoPor, string $motivo): void`
  Cancela la cita y ajusta el estado del slot según si la franja ha pasado.
  `@return` void
- `function apuntes(): MorphMany`
  Apuntes de Historia Social vinculados a esta cita (polimórficos).
  `@return` MorphMany<Apunte>

### `Modules\Agenda\Models\CuadranteMes`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/CuadranteMes.php:35`.
- Resumen: Cuadrante mensual de un centro.

Representa la planificación de disponibilidad para todos los profesionales de un centro en un mes concreto. Ciclo de vida: borrador → revisión → publicado. Al publicarse, el SlotMaterializadorService genera los registros Slot.

Metodos publicos:

- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function publicadoPor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function lineas(): HasMany`
  _Sin resumen PHPDoc._
- `function slots(): HasManyThrough`
  _Sin resumen PHPDoc._
- `function scopePublicados(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeBorradores(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelMes(Builder $query, int $anyo, int $mes): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelCentro(Builder $query, int $centroId): Builder`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Models\EventoAgenda`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/EventoAgenda.php:42`.
- Resumen: Evento en la agenda del centro.

Bloqueo de tiempo sin ciudadano asociado (reuniones, formaciones, mesas de coordinación). No genera historia social. Puede reservar un espacio físico del centro y convocar a profesionales vía pivot evento_usuario.  El tipo_evento referencia un valor de catalogos_sistema (grupo: tipo_evento_agenda). No se modela como enum porque es puramente clasificatorio (Principio 3.10).

Metodos publicos:

- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function espacio(): BelongsTo`
  _Sin resumen PHPDoc._
- `function creadoPor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function profesionales(): BelongsToMany`
  _Sin resumen PHPDoc._
- `function scopeDelDia(Builder $query, $fecha): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelCentro(Builder $query, int $centroId): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelProfesional(Builder $query, int $usuarioId): Builder`
  _Sin resumen PHPDoc._
- `function agregarProfesionales(array $usuarioIds): array`
  Convoca a los profesionales al evento y bloquea sus slots disponibles.
  `@return` array<int, Collection<int, Cita>> Mapa usuarioId → citas en conflicto
- `function detectarConflictoEspacio(): bool`
  Comprueba si el espacio asignado al evento ya está ocupado por otro evento simultáneo.
  `@return` bool true si existe conflicto de espacio

### `Modules\Agenda\Models\ExcepcionProfesional`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/ExcepcionProfesional.php:35`.
- Resumen: Excepción en el horario de un profesional.

Registra ausencias, reducciones o modificaciones puntuales del horario. VIDA no gestiona la solicitud ni la autorización (es competencia de RRHH); el supervisor introduce el resultado en el sistema.

Metodos publicos:

- `function usuario(): BelongsTo`
  _Sin resumen PHPDoc._
- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function creadoPor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function scopeVigentes(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeQueAfectanDisponibilidad(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelProfesional(Builder $query, int $usuarioId): Builder`
  _Sin resumen PHPDoc._
- `function scopeEnPeriodo(Builder $query, $desde, $hasta): Builder`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Models\HorarioCentro`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/HorarioCentro.php:39`.
- Resumen: Horario operativo de un centro.

Define los días y horas de apertura, el horario de atención al público y el modo de agenda (básico, estándar o avanzado). Un centro puede tener múltiples horarios históricos pero solo uno vigente en cada momento.

Metodos publicos:

- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function tiposSlot(): HasMany`
  _Sin resumen PHPDoc._
- `function scopeActivos(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeVigentes(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelCentro(Builder $query, int $centroId): Builder`
  _Sin resumen PHPDoc._
- `function esModoBasico(): bool`
  _Sin resumen PHPDoc._
- `function esModoAvanzado(): bool`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Models\LineaCuadrante`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/LineaCuadrante.php:31`.
- Resumen: Línea del cuadrante: asignación de un profesional en un día concreto.

Define la franja horaria real de trabajo de un profesional en un día, incorporando las particularidades de su perfil. Las excepciones pueden anular la línea correspondiente.

Metodos publicos:

- `function cuadranteMes(): BelongsTo`
  _Sin resumen PHPDoc._
- `function usuario(): BelongsTo`
  _Sin resumen PHPDoc._
- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function slots(): HasMany`
  _Sin resumen PHPDoc._
- `function excepcion(): BelongsTo`
  _Sin resumen PHPDoc._
- `function scopeActivas(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelDia(Builder $query, $fecha): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelProfesional(Builder $query, int $usuarioId): Builder`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Models\PerfilHorarioProfesional`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/PerfilHorarioProfesional.php:34`.
- Resumen: Perfil horario de un profesional en un centro concreto.

Define la jornada semanal y las franjas habituales de trabajo. Un profesional puede tener perfiles en varios centros (profesionales itinerantes). Solo puede existir un perfil activo por combinación (usuario_id, centro_id) en cada momento; este constraint se valida en capa de aplicación, no en base de datos.

Metodos publicos:

- `function usuario(): BelongsTo`
  _Sin resumen PHPDoc._
- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function lineasCuadrante(): HasMany`
  _Sin resumen PHPDoc._
- `function scopeActivos(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelCentro(Builder $query, int $centroId): Builder`
  _Sin resumen PHPDoc._
- `function scopeVigentes(Builder $query): Builder`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Models\ReasignacionCita`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/ReasignacionCita.php:27`.
- Resumen: Historial de reasignación de una cita.

Cuando una cita pasa de un profesional a otro (por no-show, baja sobrevenida u otras causas), se crea un registro de reasignación. La reasignación siempre la realiza un supervisor; el sistema asiste mostrando los slots disponibles.

Metodos publicos:

- `function cita(): BelongsTo`
  _Sin resumen PHPDoc._
- `function slotOriginal(): BelongsTo`
  _Sin resumen PHPDoc._
- `function slotNuevo(): BelongsTo`
  _Sin resumen PHPDoc._
- `function profesionalOriginal(): BelongsTo`
  _Sin resumen PHPDoc._
- `function profesionalNuevo(): BelongsTo`
  _Sin resumen PHPDoc._
- `function realizadaPor(): BelongsTo`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Models\Slot`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/Slot.php:35`.
- Resumen: Hueco concreto disponible para reserva.

Se genera al publicar un CuadranteMes. Corresponde a un profesional, un día, una franja horaria y un tipo de slot. Los slots no se versionan; la trazabilidad se obtiene del ciclo de vida de la Cita.

Metodos publicos:

- `function lineaCuadrante(): BelongsTo`
  _Sin resumen PHPDoc._
- `function usuario(): BelongsTo`
  _Sin resumen PHPDoc._
- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function tipoSlot(): BelongsTo`
  _Sin resumen PHPDoc._
- `function espacio(): BelongsTo`
  _Sin resumen PHPDoc._
- `function cita(): HasOne`
  _Sin resumen PHPDoc._
- `function scopeDisponibles(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeUrgencias(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeAnulados(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelDia(Builder $query, $fecha): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelProfesional(Builder $query, int $usuarioId): Builder`
  _Sin resumen PHPDoc._
- `function scopeDelCentro(Builder $query, int $centroId): Builder`
  _Sin resumen PHPDoc._
- `function scopeDeEstado(Builder $query, EstadoSlot $estado): Builder`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Models\TipoSlot`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Models/TipoSlot.php:30`.
- Resumen: Tipo de atención que puede reservarse como cita en un centro.

Define la duración, reglas de uso y el porcentaje de slots reservados para urgencias. Pertenece a un HorarioCentro concreto.

Metodos publicos:

- `function horarioCentro(): BelongsTo`
  _Sin resumen PHPDoc._
- `function slots(): HasMany`
  _Sin resumen PHPDoc._
- `function scopeActivos(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeQueAdmitenApiExterna(Builder $query): Builder`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Observers\CitaObserver`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Observers/CitaObserver.php:17`.
- Resumen: Observer del modelo Cita.

Mantiene la coherencia entre el estado de la Cita y el del Slot asociado a lo largo del ciclo de vida de la reserva.

Metodos publicos:

- `function creating(Cita $cita): void`
  Impide reservar un slot de urgencia desde el canal externo.
  `@return` void
  `@throws` LogicException
- `function created(Cita $cita): void`
  Marca el slot como reservado al crear la cita.
  `@return` void

### `Modules\Agenda\Observers\ExcepcionProfesionalObserver`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Observers/ExcepcionProfesionalObserver.php:19`.
- Resumen: Observer del modelo ExcepcionProfesional.

Cuando se registra una excepción posterior a la publicación del cuadrante y afecta a la disponibilidad, propaga el efecto sobre las líneas, slots y citas ya materializadas (PF-07.5).

Metodos publicos:

- `function created(ExcepcionProfesional $excepcion): void`
  Anula las líneas y slots del cuadrante publicado afectados por la excepción.
  `@return` void

### `Modules\Agenda\Providers\AgendaServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Providers/AgendaServiceProvider.php:16`.
- Resumen: Provider del módulo Agenda.

Registra las migraciones y los servicios del módulo de citas y agendas.

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Agenda\Services\CuadranteGeneratorService`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Services/CuadranteGeneratorService.php:20`.
- Resumen: Genera el borrador de LineaCuadrante para un mes.

En modo básico, genera y publica automáticamente sin intervención del supervisor. En modo estándar/avanzado, produce un borrador para revisión antes de publicar.

Metodos publicos:

- `function generarBorrador(CuadranteMes $cuadrante): void`
  Genera las LineaCuadrante para todos los profesionales con perfil activo en el centro.
  `@return` void
- `function generarYPublicarAutomaticamente(Centro $centro, int $anyo, int $mes): CuadranteMes`
  Genera y publica automáticamente el cuadrante de un mes.
  `@return` CuadranteMes Cuadrante en estado 'publicado'

### `Modules\Agenda\Services\DisponibilidadService`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Services/DisponibilidadService.php:17`.
- Resumen: Calcula los slots disponibles para un profesional en un período.

Consulta slots materializados filtrando por estado y criterios de búsqueda. Los slots de urgencia solo se incluyen cuando el canal solicitante los permite explícitamente (canal interno, supervisores).

Metodos publicos:

- `function obtenerSlots( int $usuarioId, int $centroId, int $tipoSlotId, Carbon $desde, Carbon $hasta, bool $incluirUrgencias = false ): Collection`
  Obtiene los slots disponibles de un profesional en el período indicado.
  `@return` Collection<int, Slot>

### `Modules\Agenda\Services\GestionAusenciaService`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Services/GestionAusenciaService.php:23`.
- Resumen: Gestiona el flujo cuando un profesional no se presenta.

- Las citas confirmadas del día pasan a estado 'cancelada' con motivo descriptivo. - En modos estandar/avanzado devuelve slots de urgencia de otros profesionales como candidatos para reasignación. - En modo basico devuelve slots disponibles de otros profesionales. - La reasignación siempre la confirma un supervisor (Principio 3.9).

Metodos publicos:

- `function procesarAusencia(int $usuarioId, int $centroId, Carbon $fecha): array`
  Procesa la ausencia sobrevenida de un profesional en una fecha concreta.
  `@return` array{citas: Collection<int, Cita>, candidatos: Collection<int, Slot>}
- `function reasignar(Cita $cita, Slot $slotDestino, int $supervisorId, string $motivo): ReasignacionCita`
  Reasigna una cita a un nuevo slot elegido por el supervisor.
  `@return` ReasignacionCita Registro creado

### `Modules\Agenda\Services\SlotMaterializadorService`

- Tipo: class.
- Fichero: `vida/Modules/Agenda/app/Services/SlotMaterializadorService.php:20`.
- Resumen: Materializa los slots al publicar un CuadranteMes.

Aplica reglas de HorarioCentro (buffers, horario de atención, días laborables) y de cada TipoSlot (duración, porcentaje_urgencias). Los slots de urgencia se crean directamente en estado 'bloqueado_urgencia'.

Metodos publicos:

- `function materializar(CuadranteMes $cuadrante): int`
  Genera todos los slots de las líneas activas del cuadrante publicado.
  `@return` int Número de slots creados

### `Modules\Centro\Models\Actividad`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/Actividad.php:32`.
- Resumen: Actividad programada en un centro.

modo_acceso: libre | prescripcion | mixta mixta = hay cupo para prescripciones y cupo libre simultáneamente.

Metodos publicos:

- `function centro(): BelongsTo`
  Centro en el que se desarrolla la actividad.
  `@return` BelongsTo<Centro, self>
- `function tipoActividad(): BelongsTo`
  Tipo de actividad que clasifica esta actividad.
  `@return` BelongsTo<TipoActividad, self>
- `function sesiones(): HasMany`
  Sesiones concretas planificadas para esta actividad.
  `@return` HasMany<SesionActividad, self>
- `function verificarInscripcionCentro(int $ciudadanoId): void`
  Verifica que el ciudadano tiene inscripción activa en este centro. Solo aplica si requiere_inscripcion_centro = true.
  `@return` void
  `@throws` \InvalidArgumentException Si se requiere inscripción y el ciudadano no la tiene.
- `function scopeActivas(Builder $query): Builder`
  Filtra actividades activas y sin fecha de baja.
  `@return` Builder<static>

### `Modules\Centro\Models\AmbitoTerritorial`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/AmbitoTerritorial.php:16`.
- Resumen: Ámbito territorial de atención de un centro.

Define la población geográfica a la que atiende el centro. Un centro puede tener varios registros combinando tipos distintos. Si existe un registro de tipo 'ciudad_completa', no puede coexistir con ningún otro ámbito para ese mismo centro.

Metodos publicos:

- `function centro(): BelongsTo`
  _Sin resumen PHPDoc._

### `Modules\Centro\Models\Centro`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/Centro.php:45`.
- Resumen: Centro de servicios sociales.

Unidad operativa donde se prestan los servicios municipales. Pertenece a una UnidadOrganizativa. El ámbito territorial se modela a través de AmbitoTerritorial (tabla ambitos_territoriales).  tipo_gestion: municipal_directo | municipal_concertado | privado_concertado | privado_puro

Metodos publicos:

- `function unidadOrganizativa(): BelongsTo`
  Unidad organizativa a la que pertenece el centro.
  `@return` BelongsTo<UnidadOrganizativa, self>
- `function ambitosTeritoriales(): HasMany`
  Ámbitos territoriales de atención del centro.
  `@return` HasMany<AmbitoTerritorial, self>
- `function distrito(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<Distrito, self>
- `function coleccionesPlazas(): HasMany`
  Colecciones de plazas del centro.
  `@return` HasMany<ColeccionPlazas, self>
- `function actividades(): HasMany`
  Actividades programadas en el centro.
  `@return` HasMany<Actividad, self>
- `function directores(): HasMany`
  Historial de directores del centro.
  `@return` HasMany<DirectorCentro, self>
- `function contactos(): HasMany`
  Personas de contacto adicionales del centro.
  `@return` HasMany<ContactoCentro, self>
- `function inscripciones(): HasMany`
  Inscripciones de ciudadanos al centro.
  `@return` HasMany<InscripcionCentro, self>
- `function redes(): BelongsToMany`
  Redes a las que pertenece el centro.
  `@return` BelongsToMany<Red, self>
- `function segmentosPoblacion(): BelongsToMany`
  Segmentos de población a los que atiende el centro.
  `@return` BelongsToMany<SegmentoPoblacion, self>
- `function prestaciones(): BelongsToMany`
  Prestaciones vinculadas al centro.
  `@return` BelongsToMany<Prestacion, self>
- `function directorActivo(): ?DirectorCentro`
  Devuelve el DirectorCentro activo (fecha_fin null), o null si no lo hay.
  `@return` DirectorCentro|null
- `function nombrarDirector(array $datos): DirectorCentro`
  Nombra un nuevo director cerrando el activo actual (si existe).
  `@return` DirectorCentro
- `function scopeActivos(Builder $query): Builder`
  Filtra centros activos y sin fecha de baja.
  `@return` Builder<static>

### `Modules\Centro\Models\ColeccionPlazas`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/ColeccionPlazas.php:32`.
- Resumen: Colección de plazas de un centro.

Agrupa plazas del mismo tipo y modo de acceso. Una colección puede tener varios espacios, cada uno con varias plazas.  tipo_plaza:  pernocta | dia modo_acceso: libre | prescripcion_directa | prescripcion_lista_espera

Metodos publicos:

- `function centro(): BelongsTo`
  Centro al que pertenece la colección de plazas.
  `@return` BelongsTo<Centro, self>
- `function espacios(): HasMany`
  Espacios físicos que componen esta colección.
  `@return` HasMany<Espacio, self>
- `function plazas(): HasManyThrough`
  Plazas individuales de todos los espacios de la colección.
  `@return` HasManyThrough<Plaza, Espacio, self>
- `function prescripciones(): HasMany`
  Prescripciones dirigidas a esta colección de plazas.
  `@return` HasMany<Prescripcion, self>
- `function listaEspera(): HasOne`
  Lista de espera asociada a esta colección de plazas.
  `@return` HasOne<ListaEspera, self>
- `function plazasDisponibles(): int`
  Número de plazas con estado 'libre' en esta colección. Devuelve 0 si la colección está inactiva.
  `@return` int
- `function getPlazasDisponiblesAttribute(): int`
  _Sin resumen PHPDoc._
  `@return` int
- `function scopeActivas(Builder $query): Builder`
  Filtra colecciones de plazas activas.
  `@return` Builder<static>

### `Modules\Centro\Models\ContactoCentro`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/ContactoCentro.php:23`.
- Resumen: Persona de contacto adicional de un centro.

Complementa los datos de contacto del propio centro con personas responsables de áreas específicas (coordinación, admisiones, etc.).

Metodos publicos:

- `function centro(): BelongsTo`
  Centro al que pertenece este contacto.
  `@return` BelongsTo<Centro, self>

### `Modules\Centro\Models\DirectorCentro`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/DirectorCentro.php:29`.
- Resumen: Director o responsable de un centro en un periodo dado.

El director puede ser interno (profesional_id relleno) o externo (campos nombre/telefono/email rellenos). Ambas opciones son mutuamente excluyentes — se valida en booted(). El registro activo es el que tiene fecha_fin = null.

Metodos publicos:

- `function centro(): BelongsTo`
  Centro que dirige este registro.
  `@return` BelongsTo<Centro, self>
- `function profesional(): BelongsTo`
  Profesional interno vinculado como director (nullable para directores externos).
  `@return` BelongsTo<Profesional, self>
- `function scopeActivo(Builder $query): Builder`
  Filtra el director actualmente en activo (sin fecha de fin).
  `@return` Builder<static>

### `Modules\Centro\Models\Espacio`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/Espacio.php:26`.
- Resumen: Espacio físico (habitación, sala, módulo) dentro de una colección de plazas.

Metodos publicos:

- `function coleccionPlazas(): BelongsTo`
  Colección de plazas a la que pertenece este espacio.
  `@return` BelongsTo<ColeccionPlazas, self>
- `function tipoEspacio(): BelongsTo`
  Tipo de espacio que clasifica este espacio físico.
  `@return` BelongsTo<TipoEspacio, self>
- `function plazas(): HasMany`
  Plazas individuales contenidas en este espacio.
  `@return` HasMany<Plaza, self>
- `function scopeAccesibles(Builder $query): Builder`
  Filtra espacios accesibles para personas con movilidad reducida.
  `@return` Builder<static>
- `function scopePorGenero(Builder $query, string $genero): Builder`
  Filtra espacios compatibles con el género indicado (incluye mixtos y sin género).
  `@return` Builder<static>

### `Modules\Centro\Models\InscripcionCentro`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/InscripcionCentro.php:25`.
- Resumen: Inscripción de un ciudadano a un centro.

Registro acumulativo: no se eliminan, se cierran con fecha_baja. Requisito para participar en actividades con requiere_inscripcion_centro = true.

Metodos publicos:

- `function centro(): BelongsTo`
  Centro al que está inscrito el ciudadano.
  `@return` BelongsTo<Centro, self>
- `function ciudadano(): BelongsTo`
  Ciudadano inscrito en el centro.
  `@return` BelongsTo<Ciudadano, self>
- `function scopeActivas(Builder $query): Builder`
  Filtra inscripciones activas y sin fecha de baja.
  `@return` Builder<static>

### `Modules\Centro\Models\ListaEspera`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/ListaEspera.php:30`.
- Resumen: Registro de lista de espera vinculado a una prescripción.

El ámbito puede ser local (coleccion_plazas_id) o de red (red_id). Ambos campos son mutuamente excluyentes — se valida en booted().  estado: activa | asignada | cancelada

Metodos publicos:

- `function prescripcion(): BelongsTo`
  Prescripción que originó la entrada en lista de espera.
  `@return` BelongsTo<Prescripcion, self>
- `function coleccionPlazas(): BelongsTo`
  Colección de plazas de ámbito local (mutuamente excluyente con red).
  `@return` BelongsTo<ColeccionPlazas, self>
- `function red(): BelongsTo`
  Red de centros de ámbito compartido (mutuamente excluyente con coleccionPlazas).
  `@return` BelongsTo<Red, self>
- `function profesionalAlerta(): BelongsTo`
  Profesional al que se notificará cuando haya plaza disponible.
  `@return` BelongsTo<Profesional, self>
- `function scopeActivas(Builder $query): Builder`
  Filtra registros de lista de espera con estado activa.
  `@return` Builder<static>

### `Modules\Centro\Models\Plaza`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/Plaza.php:27`.
- Resumen: Plaza individual dentro de un espacio.

Unidad mínima asignable a una persona. El estado se desnormaliza para consultas rápidas; la ocupación efectiva se rastrea a través de la Prescripcion activa que apunta a esta plaza.  estado: libre | ocupada | reservada | mantenimiento

Metodos publicos:

- `function espacio(): BelongsTo`
  Espacio físico en el que se encuentra la plaza.
  `@return` BelongsTo<Espacio, self>
- `function prescripcion(): HasOne`
  Prescripción activa en esta plaza.
  `@return` HasOne<Prescripcion, self>
- `function scopeLibres(Builder $query): Builder`
  Filtra plazas con estado libre.
  `@return` Builder<static>
- `function scopeOcupadas(Builder $query): Builder`
  Filtra plazas con estado ocupada.
  `@return` Builder<static>

### `Modules\Centro\Models\Prescripcion`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/Prescripcion.php:36`.
- Resumen: Prescripción de un recurso (plaza o sesión de actividad) para un ciudadano.

tipo_destino + destino_id implementan una relación polimórfica manual: - 'coleccion_plazas' → destino_id apunta a colecciones_plazas.id - 'sesion_actividad'  → destino_id apunta a sesiones_actividad.id  estado: pendiente | en_lista_espera | asignada | activa | finalizada | cancelada

Metodos publicos:

- `function profesional(): BelongsTo`
  Profesional que emite la prescripción.
  `@return` BelongsTo<Profesional, self>
- `function ciudadano(): BelongsTo`
  Ciudadano beneficiario de la prescripción.
  `@return` BelongsTo<Ciudadano, self>
- `function plaza(): BelongsTo`
  Plaza concreta asignada (nullable hasta la asignación efectiva).
  `@return` BelongsTo<Plaza, self>
- `function listaEspera(): HasOne`
  Registro de lista de espera generado por esta prescripción.
  `@return` HasOne<ListaEspera, self>
- `function destino(): ColeccionPlazas|SesionActividad|null`
  Resuelve el destino de la prescripción según tipo_destino. Devuelve la ColeccionPlazas o la SesionActividad correspondiente.
  `@return` ColeccionPlazas|SesionActividad|null
- `function scopeActivas(Builder $query): Builder`
  Filtra prescripciones con estado activa.
  `@return` Builder<static>
- `function scopePendientes(Builder $query): Builder`
  Filtra prescripciones con estado pendiente.
  `@return` Builder<static>

### `Modules\Centro\Models\Red`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/Red.php:24`.
- Resumen: Red de centros con pool de plazas o lista de espera compartida.

Metodos publicos:

- `function centros(): BelongsToMany`
  Centros que pertenecen a esta red.
  `@return` BelongsToMany<Centro, self>
- `function plazasLibresTotal(): int`
  Total de plazas con estado 'libre' en todos los centros de la red. Solo cuenta colecciones activas.
  `@return` int
- `function scopeActivas(Builder $query): Builder`
  Filtra redes activas.
  `@return` Builder<static>

### `Modules\Centro\Models\ResponsableServicio`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/ResponsableServicio.php:31`.
- Resumen: Responsable de un servicio en un periodo dado.

El cargo es un atributo del servicio, no del profesional: el nombre del cargo se lee de $this->servicio->cargo_nombre. El profesional asume ese cargo al ser nombrado y lo deja al cesar.  A diferencia de DirectorCentro, no existe la figura de responsable externo: el responsable de un servicio es siempre un profesional con cuenta en VIDA 360.  El registro activo es el que tiene fecha_fin = null. Solo puede haber un responsable activo por servicio.

Metodos publicos:

- `function servicio(): BelongsTo`
  Servicio al que pertenece este registro.
  `@return` BelongsTo<Servicio, self>
- `function profesional(): BelongsTo`
  Profesional que ejerce como responsable en este período.
  `@return` BelongsTo<Profesional, self>
- `function getCargoNombreAttribute(): string`
  Nombre del cargo del responsable, tomado del servicio.
  `@return` string
- `function scopeActivo(Builder $query): Builder`
  Filtra el responsable actualmente en activo (sin fecha de fin).
  `@return` Builder<static>

### `Modules\Centro\Models\SegmentoPoblacion`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/SegmentoPoblacion.php:19`.
- Resumen: Segmento de población al que puede orientarse un centro.

Permite clasificar los centros según el perfil de los beneficiarios a los que atienden (mayores, menores, personas sin hogar, etc.).

Metodos publicos:

- `function centros(): BelongsToMany`
  Centros que atienden a este segmento de población.
  `@return` BelongsToMany<Centro, self>

### `Modules\Centro\Models\Servicio`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/Servicio.php:40`.
- Resumen: Nodo de tramitación de prestaciones sociales.

A diferencia del Centro, no tiene presencia física relevante para el ciudadano: tramita prestaciones en su nombre. La dirección de referencia se obtiene de su UO, que es obligatoria.  No tiene plazas, espacios, actividades ni inscripciones de ciudadanos.  cargo_nombre: nombre del cargo del responsable definido a nivel del servicio. El profesional asume el cargo al ser nombrado y lo deja al cesar.

Metodos publicos:

- `function unidadOrganizativa(): BelongsTo`
  Unidad organizativa a la que pertenece el servicio. La dirección de referencia se obtiene de esta UO.
  `@return` BelongsTo<UnidadOrganizativa, self>
- `function responsables(): HasMany`
  Historial de responsables del servicio.
  `@return` HasMany<ResponsableServicio, self>
- `function profesionales(): BelongsToMany`
  Profesionales actualmente asignados al servicio (sin fecha de baja).
  `@return` BelongsToMany<Profesional, self>
- `function prestaciones(): BelongsToMany`
  Prestaciones que tramita este servicio.
  `@return` BelongsToMany<Prestacion, self>
- `function solicitudes(): HasMany`
  Solicitudes de tramitación dirigidas a este servicio.
  `@return` HasMany<SolicitudServicio, self>
- `function responsableActivo(): ?ResponsableServicio`
  Devuelve el ResponsableServicio activo (fecha_fin null), o null si no hay ninguno.
  `@return` ResponsableServicio|null
- `function nombrarResponsable(Profesional $profesional, ?string $notas = null): ResponsableServicio`
  Nombra un nuevo responsable cerrando el activo actual (si existe).
  `@return` ResponsableServicio
- `function scopeActivos(Builder $query): Builder`
  Filtra servicios activos y sin fecha de baja.
  `@return` Builder<static>

### `Modules\Centro\Models\SesionActividad`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/SesionActividad.php:29`.
- Resumen: Sesión concreta de una actividad.

Los aforos de sesión sobreescriben los de la actividad cuando se informan.  estado: programada | celebrada | cancelada

Metodos publicos:

- `function actividad(): BelongsTo`
  Actividad a la que pertenece esta sesión.
  `@return` BelongsTo<Actividad, self>
- `function prescripciones(): HasMany`
  Prescripciones dirigidas a esta sesión de actividad.
  `@return` HasMany<Prescripcion, self>
- `function getAforoDisponibleAttribute(): int`
  Plazas disponibles: aforo efectivo menos prescripciones activas o asignadas. Usa el aforo de la sesión si está definido, o el de la actividad como fallback.
  `@return` int
- `function scopeProgramadas(Builder $query): Builder`
  Filtra sesiones con estado programada.
  `@return` Builder<static>

### `Modules\Centro\Models\SolicitudServicio`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/SolicitudServicio.php:40`.
- Resumen: Solicitud de tramitación de una prestación a un servicio.

Se genera cuando un TSR prescribe desde un plan de intervención una prestación asociada a un servicio. El responsable del servicio ve la solicitud en su bandeja y gestiona la tramitación.  Las anotaciones de seguimiento posteriores pertenecen al módulo de Intervención como hechos de la historia social del ciudadano, no a esta entidad.  estado: pendiente | en_tramite | resuelta | denegada | derivada_externa  Al pasar a estado 'resuelta', fecha_resolucion se registra automáticamente.

Metodos publicos:

- `function servicio(): BelongsTo`
  Servicio destinatario de la solicitud.
  `@return` BelongsTo<Servicio, self>
- `function ciudadano(): BelongsTo`
  Ciudadano beneficiario de la tramitación.
  `@return` BelongsTo<Ciudadano, self>
- `function profesional(): BelongsTo`
  TSR que generó la solicitud.
  `@return` BelongsTo<Profesional, self>
- `function planIntervencion(): BelongsTo`
  Plan de intervención en cuyo contexto se generó la solicitud.
  `@return` BelongsTo<PlanDeIntervencion, self>
- `function prestacion(): BelongsTo`
  Prestación solicitada.
  `@return` BelongsTo<Prestacion, self>
- `function scopePendientes(Builder $query): Builder`
  Filtra solicitudes pendientes de tramitar.
  `@return` Builder<static>

### `Modules\Centro\Models\TipoActividad`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/TipoActividad.php:18`.
- Resumen: Catálogo de tipos de actividad ofrecidos en los centros.

Permite clasificar y filtrar las actividades (talleres, grupos de apoyo, etc.).

Metodos publicos:

- `function actividades(): HasMany`
  Actividades que pertenecen a este tipo.
  `@return` HasMany<Actividad, self>

### `Modules\Centro\Models\TipoEspacio`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Models/TipoEspacio.php:19`.
- Resumen: Catálogo de tipos de espacio físico de un centro.

Clasifica los espacios según su naturaleza (habitación individual, habitación doble, sala común, módulo, etc.).

Metodos publicos:

- `function espacios(): HasMany`
  Espacios físicos clasificados con este tipo.
  `@return` HasMany<Espacio, self>

### `Modules\Centro\Providers\CentroServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Providers/CentroServiceProvider.php:13`.
- Resumen: Provider del módulo Centro.

Las migraciones del módulo residen en database/migrations/ (carpeta principal) por convención del proyecto, por lo que no se cargan desde aquí.

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Centro\Services\PrescripcionService`

- Tipo: class.
- Fichero: `vida/Modules/Centro/app/Services/PrescripcionService.php:21`.
- Resumen: Gestiona el ciclo de vida de las prescripciones de plazas.

Crea prescripciones asignando plaza disponible o entrando en lista de espera. Gestiona la liberación de plazas y las cancelaciones.  La asignación nunca es automática cuando se libera una plaza: el sistema actualiza el profesional de alerta y espera confirmación profesional.

Metodos publicos:

- `function __construct()`
  Función para resolver el TSR activo de un ciudadano. Se usa al liberar plazas para actualizar profesional_alerta_id.
- `function setTsrResolver(callable $resolver): void`
  Inyecta la función de resolución del TSR activo. Uso previsto: tests y adaptadores hacia el módulo Ciudadanía.
  `@return` void
- `function crear(array $datos): Prescripcion`
  Crea una prescripción hacia una colección de plazas.
  `@return` Prescripcion
  `@throws` \InvalidArgumentException Si el tipo_destino no es coleccion_plazas.
- `function liberarPlaza(Plaza $plaza): void`
  Marca una plaza como libre y actualiza el profesional de alerta en la lista de espera.
  `@return` void
- `function cancelar(Prescripcion $prescripcion, ?string $motivo = null): void`
  Cancela una prescripción y libera la plaza si tenía una asignada.
  `@return` void

### `Modules\Ciudadania\Contracts\FuenteIdentidadInterface`

- Tipo: interface.
- Fichero: `vida/Modules/Ciudadania/app/Contracts/FuenteIdentidadInterface.php:17`.
- Resumen: Contrato del servicio de consulta al padrón municipal.

Toda la aplicación interactúa con el padrón a través de esta interfaz. El adaptador concreto es intercambiable. Por defecto se usa MockFuenteIdentidad.  RESTRICCIÓN DE SEGURIDAD: Para ciudadanas en circuito VVG, esta interfaz no debe invocarse en ningún momento, ni siquiera para ignorar la respuesta. La condición se evalúa ANTES de cualquier llamada. Ver principio 4.1.  Ver docs/modulo-ciudadania.md § 7.3.

Metodos publicos:

- `function consultarDatos(string $valorDocumento): ?array`
  Consulta los datos de identidad de una persona por su documento.
  `@return` array{nombre: string, apellido1: string, apellido2: ?string, fecha_nacimiento: string, sexo: string, direccion_texto: ?string}|null

### `Modules\Ciudadania\Enums\ImplicacionFuncional`

- Tipo: enum.
- Fichero: `vida/Modules/Ciudadania/app/Enums/ImplicacionFuncional.php:13`.
- Resumen: Implicaciones funcionales de los tipos de relación entre ciudadanos.

El código evalúa este campo para tomar decisiones de negocio (consentimientos, notificaciones, representación). Nunca evalúa el slug ni la etiqueta de TipoRelacion.

Metodos publicos:

- `function etiqueta(): string`
  Etiqueta legible para mostrar en backoffice.
  `@return` string

### `Modules\Ciudadania\Http\Livewire\AltaCiudadano`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Http/Livewire/AltaCiudadano.php:28`.
- Resumen: Componente Livewire del flujo de alta de ciudadano.

Cuatro fases secuenciales: busqueda → padron → formulario → confirmacion. No hay navegación libre entre fases; se avanza al completar cada una.  Ver docs/instrucciones-cli/instrucciones-cli-alta-ciudadano.md Tarea 4. Ver docs/front/alta-ciudadano-funcional.md.

Metodos publicos:

- `function buscar(): void`
  Busca posibles duplicados usando el motor de matching. No transiciona de fase. Solo requiere al menos un criterio.
  `@return` void
- `function seleccionarExistente(int $ciudadanoId): void`
  Redirige a la ficha de un ciudadano existente.
  `@return` void
- `function continuarConNuevoAlta(): void`
  Precarga datos de búsqueda en el formulario y transiciona a la fase padron. Requiere que la búsqueda haya sido realizada.
  `@return` void
- `function consultarPadron(): void`
  Consulta el padrón y precarga los datos si la persona está empadronada.
  `@return` void
- `function seleccionarExcepcionPadron(string $excepcion): void`
  Registra la excepción de padrón y transiciona al formulario. PSH y VVG solo están disponibles para roles intervencion y supervision.
  `@return` void
- `function guardar(): void`
  Valida, normaliza, ejecuta segunda pasada de matching y guarda el ciudadano.
  `@return` void
- `function confirmarAlta(): void`
  Persiste la primera demanda y redirige según la acción elegida.
  `@return` void
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php:58`.
- Resumen: Ficha del ciudadano: vista y edición de Capa 1 (datos identificativos y de contacto).

Distinta de intervencion/ciudadano/{historia}: pivota sobre Ciudadano, no sobre HistoriaSocial. Accesible aunque el ciudadano no tenga historia social.  Se accede sin AmbitoUoScope porque un ciudadano puede no tener historia social en ninguna UO y aun así tener ficha (e.g., recién creado vía alta ciudadano).  Propiedades computadas expuestas como propiedades mágicas por Livewire 4 #[Computed]:

Metodos publicos:

- `function mount(int $ciudadano): void`
  _Sin resumen PHPDoc._
  `@return` void
- `function ciudadano(): Ciudadano`
  Ciudadano sin AmbitoUoScope — accesible aunque no tenga historia social en la UO.
  `@return` Ciudadano
- `function puedeEditar(): bool`
  El rol supervision tiene acceso de solo lectura. Todos los demás con acceso pueden editar.
  `@return` bool
- `function historiaSocial(): ?HistoriaSocial`
  Historia social sin AmbitoUoScope ni SoftDeletes — solo comprueba existencia. La historia es única y permanente: nunca se cierra.
  `@return` HistoriaSocial|null
- `function puedeVerHistoria(): bool`
  Solo el rol intervencion puede navegar a la historia social.
  `@return` bool
- `function documentos(): Collection`
  Historial completo de documentos de identidad, descendente por fecha de inicio.
  `@return` Collection<int, CiudadanoIdentificador>
- `function ucVigente(): ?UnidadConvivencia`
  UC vigente del ciudadano (primera con fecha_fin nula o futura). Sin AmbitoUoScope porque la UC no tiene ámbito UO propio.
  `@return` UnidadConvivencia|null
- `function ucMiembros(): Collection`
  Miembros activos de la UC vigente, enriquecidos con el tipo de relación si existe. Carga el ciudadano conviviente sin AmbitoUoScope porque puede pertenecer a otra UO.
  `@return` Collection<int, UnidadConvivenciaMiembro>
- `function puedeEditarRelaciones(): bool`
  Solo los roles con competencia de tramitación o intervención pueden crear o editar relaciones.
  `@return` bool
- `function prestaciones(): Collection`
  Últimas 4 prestaciones ordenadas por estado (activas primero) y fecha. Se leen desde la tabla de agregación — nunca de los módulos origen directamente.
  `@return` Collection<int, CiudadanoPrestacionResumen>
- `function puedeVerAccesos(): bool`
  El panel de accesos es visible solo para roles con competencia de intervención o supervisión. Revelar metadatos de acceso a roles sin competencia es una fuga de información sobre el caso.
  `@return` bool
- `function puedeVerTodosLosAccesos(): bool`
  Indica si el usuario ve todos los accesos (TSR/supervisor/adm) o solo los propios. Usado en la vista para mostrar u ocultar el enlace "Ver todo".
  `@return` bool
- `function actividadReciente(): Collection`
  Últimos 10 accesos al expediente de este ciudadano.
  `@return` Collection<int, Audit>
- `function tiposRelacion(): array`
  Tipos de relación activos disponibles para crear nuevas relaciones.
  `@return` array<string, string>
- `function relacionesActivas(): Collection`
  Relaciones activas salientes desde este ciudadano, con persona y tipo cargados. El eager load de ciudadanoRelacionado bypasea AmbitoUoScope: el ciudadano relacionado puede pertenecer a cualquier UO.
  `@return` Collection<int, CiudadanoRelacion>
- `function relacionesHistoricas(): Collection`
  Historial completo de relaciones del ciudadano (vigentes y cerradas).
  `@return` Collection<int, CiudadanoRelacion>
- `function relacionResultadosBusqueda(): Collection`
  Resultados de búsqueda para añadir una relación. Los nombres están cifrados, por eso se filtra en memoria siguiendo el patrón del buscador de ciudadanos.
  `@return` Collection<int, Ciudadano>
- `function ciudadanoSeleccionadoRelacion(): ?Ciudadano`
  _Sin resumen PHPDoc._
- `function activarEdicion(): void`
  Activa el modo edición simultáneo de todos los campos de Capa 1. Solo si puedeEditar — supervision no puede modificar datos.
  `@return` void
- `function cancelarEdicion(): void`
  Cancela la edición y recarga los datos desde BD.
  `@return` void
- `function guardar(): void`
  Valida, normaliza y persiste los campos de Capa 1. Solo si puedeEditar. DireccionObserver procesará geocodificación si cambia direccion_texto.
  `@return` void
  `@throws` ValidationException
- `function abrirModalNuevaRelacion(): void`
  Abre el modal para crear una nueva relación.
  `@return` void
- `function abrirModalEditarRelacion(int $relacionId): void`
  Abre el modal con los datos de una relación existente para edición.
  `@return` void
- `function cerrarModalRelacion(): void`
  Cierra el modal y limpia el estado del formulario de relación.
  `@return` void
- `function toggleHistorialRelaciones(): void`
  Alterna la visibilidad del historial de relaciones cerradas.
  `@return` void
- `function seleccionarCiudadanoRelacion(int $ciudadanoId): void`
  Registra el ciudadano seleccionado en el buscador del modal de relación.
  `@return` void
- `function guardarRelacion(): void`
  Crea o actualiza una relación entre ciudadanos. Si $relacionId es null, crea; si es int, actualiza solo las observaciones. Requiere permiso de tramitación o intervención; aborta con 403 si no.
  `@return` void
  `@throws` ValidationException
- `function cerrarRelacion(int $relacionId): void`
  Cierra una relación vigente estableciendo fecha_fin = hoy. El modelo propaga el cierre al registro recíproco automáticamente. Requiere permiso de tramitación o intervención; aborta con 403 si no.
  `@return` void
- `function abrirModalDocumento(): void`
  Abre el modal de añadir documento. Solo si puedeEditar.
  `@return` void
- `function cerrarModalDocumento(): void`
  Cierra el modal y limpia el formulario.
  `@return` void
- `function guardarDocumento(): void`
  Cierra el documento activo anterior y crea el nuevo. El historial se mantiene íntegro (principio 4.2 — el pasado es inmutable): los documentos anteriores reciben fecha_fin pero no se eliminan.
  `@return` void
  `@throws` ValidationException
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Ciudadania\Models\CiudadanoIdentificador`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Models/CiudadanoIdentificador.php:27`.
- Resumen: Documento de identidad de un ciudadano.

Un ciudadano puede tener múltiples documentos a lo largo del tiempo. El campo `valor` está cifrado; `valor_hash` permite búsqueda sin descifrar.

Metodos publicos:

- `function getCiudadanoId(): ?int`
  ID del ciudadano titular del documento.
  `@return` int|null
- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._

### `Modules\Ciudadania\Models\CiudadanoPrestacionResumen`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Models/CiudadanoPrestacionResumen.php:29`.
- Resumen: Registro de prestación o actividad del ciudadano sin historia social.

Tabla de agregación alimentada por observers de cada módulo origen. La FichaCiudadanoPage nunca consulta las tablas de módulos origen directamente.

Metodos publicos:

- `function scopeActivas(Builder $query): Builder`
  Filtra prestaciones activas o en trámite.
  `@return` Builder<self>
- `function scopeRecientes(Builder $query, int $limit = 4): Builder`
  Ordena por estado (activos primero) y fecha descendente, limitando a $limit registros.
  `@return` Builder<self>
- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<Ciudadano, CiudadanoPrestacionResumen>

### `Modules\Ciudadania\Models\CiudadanoRelacion`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Models/CiudadanoRelacion.php:25`.
- Resumen: Relación entre dos ciudadanos.

El campo tipo_relacion almacena el slug del catálogo tipos_relacion. La reciprocidad se gestiona automáticamente en los hooks de booted(): al crear un registro se crea el inverso, y al cerrar/eliminar se sincroniza.

Metodos publicos:

- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._
- `function ciudadanoRelacionado(): BelongsTo`
  _Sin resumen PHPDoc._
- `function tipoRelacion(): BelongsTo`
  _Sin resumen PHPDoc._
- `function scopeActivas(Builder $query): Builder`
  Filtra relaciones sin fecha de fin.
  `@return` Builder<self>

### `Modules\Ciudadania\Models\TipoRelacion`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Models/TipoRelacion.php:33`.
- Resumen: Tipo de relación entre ciudadanos.

Catálogo editable por el backoffice. Cada tipo define su slug (inmutable, contrato interno del código), su etiqueta visible para el TSR, y su implicación funcional (si la tiene). El código nunca evalúa slugs ni etiquetas — siempre evalúa `implicacion_funcional`.  Los tipos con `eliminable = false` son del seeder y no pueden borrarse.

Metodos publicos:

- `function scopeActivos(Builder $query): Builder`
  Filtra tipos activos.
  `@return` Builder<self>
- `function scopeConImplicacion(Builder $query, ImplicacionFuncional $implicacion): Builder`
  _Sin resumen PHPDoc._
  `@return` Builder<self>
- `function tipoRecíproco(): ?self`
  Devuelve el tipo recíproco. Para tipos simétricos devuelve $this. Para asimétricos consulta el catálogo por `slug_reciproco`.
  `@return` self|null
- `function conImplicacionFuncional(ImplicacionFuncional $implicacion): Collection`
  Tipos activos que tienen la implicación funcional indicada. El código debe usar este método, nunca comparar slugs directamente.
  `@return` Collection<int, self>
- `function existeImplicacion(ImplicacionFuncional $implicacion): bool`
  ¿Existe al menos un tipo activo con esta implicación funcional?
  `@return` bool
- `function opcionesParaSelect(): array`
  Array slug → etiqueta de tipos activos, ordenado por etiqueta. Útil para selects en formularios.
  `@return` array<string, string>

### `Modules\Ciudadania\Models\UnidadConvivencia`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Models/UnidadConvivencia.php:39`.
- Resumen: Unidad de convivencia — grupo de ciudadanos que comparten domicilio.

Entidad con identidad propia: tiene domicilio, fechas de vigencia y composición propia. Es la unidad de referencia para el cálculo de prestaciones económicas y para la intervención familiar.  El domicilio se cifra en aplicación (AES-256 vía Crypt). La UC no tiene titular; los planes y prestaciones se asignan a personas concretas o a la UC como entidad (ver docs/modulo-intervencion.md sección 5).

Metodos publicos:

- `function miembros(): HasMany`
  Todas las membresías (históricas y activas).
  `@return` HasMany<UnidadConvivenciaMiembro, self>
- `function miembrosActivos(): HasMany`
  Solo miembros activos (sin fecha_fin).
  `@return` HasMany<UnidadConvivenciaMiembro, self>
- `function miembrosVerificados(): HasMany`
  Miembros activos con residencia verificada.
  `@return` HasMany<UnidadConvivenciaMiembro, self>
- `function ciudadanos(): BelongsToMany`
  Ciudadanos que pertenecen o han pertenecido a esta UC.
  `@return` BelongsToMany<Ciudadano, self>
- `function estaDisuelta(): bool`
  Indica si la unidad está disuelta (fecha_disolucion en el pasado).
  `@return` bool
- `function agregarMiembro( int $ciudadanoId, string $fuente = 'manual', ?\DateTimeInterface $fechaInicio = null ): UnidadConvivenciaMiembro`
  Añade un ciudadano como miembro activo.
  `@return` UnidadConvivenciaMiembro
  `@throws` \LogicException Si el ciudadano ya es miembro activo.
- `function darDeBajaMiembro( int $ciudadanoId, ?\DateTimeInterface $fechaFin = null ): void`
  Da de baja a un miembro (fecha_fin = hoy o la fecha indicada).
  `@return` void
  `@throws` \LogicException Si el ciudadano no es miembro activo.

### `Modules\Ciudadania\Models\UnidadConvivenciaMiembro`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Models/UnidadConvivenciaMiembro.php:31`.
- Resumen: Membresía de un ciudadano en una unidad de convivencia.

Registra la pertenencia de un ciudadano a una UC con sus fechas de vigencia, la fuente del dato (manual / padrón / importación) y la verificación de residencia. Sin verificación, el ciudadano no puede ser perceptor de prestaciones municipales.

Metodos publicos:

- `function unidadConvivencia(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<UnidadConvivencia, self>
- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<Ciudadano, self>
- `function verificadoPor(): BelongsTo`
  Profesional que realizó la verificación manual.
  `@return` BelongsTo<User, self>
- `function verificar(User $profesional): void`
  Marca la membresía como verificada por el profesional dado. Operación idempotente: no lanza excepción si ya estaba verificada.
  `@return` void
- `function estaActiva(): bool`
  Indica si esta membresía está actualmente activa (sin fecha de fin).
  `@return` bool
- `function puedeSerPerceptorPrestaciones(): bool`
  Indica si este miembro puede ser perceptor de prestaciones municipales. Requiere membresía activa Y verificación de residencia.
  `@return` bool

### `Modules\Ciudadania\Providers\CiudadaniaServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Providers/CiudadaniaServiceProvider.php:22`.
- Resumen: Provider del módulo Ciudadanía.

Registra migraciones, vistas, rutas, servicios y componentes Livewire del módulo de alta y gestión del expediente ciudadano.  FuenteIdentidadInterface se enlaza a MockFuenteIdentidad por defecto (principio 3.6: mock activo en todos los entornos no productivos).

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Ciudadania\Services\MotorMatching`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Services/MotorMatching.php:23`.
- Resumen: Motor de detección de posibles duplicados.

Recibe datos normalizados de un ciudadano aún no guardado y devuelve una colección de ResultadoMatching ordenada por score descendente. Solo incluye resultados con score >= umbral_minimo.  Algoritmo determinista sin IA: Jaro-Winkler para similitud de cadenas. Ver docs/instrucciones-cli/instrucciones-cli-alta-ciudadano.md § Tarea 3.  TODO: reemplazar búsqueda por nombre (carga ≤ 500) por índice hash determinista cuando esté disponible.

Metodos publicos:

- `function buscar(array $datosNormalizados): Collection`
  Busca posibles duplicados del ciudadano descrito por $datosNormalizados.
  `@return` Collection<int, ResultadoMatching>

### `Modules\Ciudadania\Services\NormalizadorCiudadano`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Services/NormalizadorCiudadano.php:12`.
- Resumen: Servicio de normalización de datos de ciudadano.

Convierte los valores introducidos por el profesional a formato canónico antes de la búsqueda de duplicados y el guardado. No lanza excepciones: si el formato no es válido devuelve el valor original.

Metodos publicos:

- `function documento(string $tipo, string $valor): string`
  Normaliza un número de documento a formato canónico.
  `@return` string
- `function nombre(string $valor): string`
  Normaliza un nombre o apellido a Title Case, eliminando espacios múltiples y expandiendo abreviaturas unívocas.
  `@return` string
- `function telefono(string $valor): string`
  Normaliza un número de teléfono eliminando espacios, guiones y paréntesis. Añade prefijo +34 si empieza por 6, 7, 8 o 9 sin prefijo internacional.
  `@return` string
- `function email(string $valor): string`
  Normaliza un email a minúsculas sin espacios.
  `@return` string
- `function normalizar(array $datos): array`
  Aplica todos los normalizadores sobre un array de datos de formulario.
  `@return` array<string, mixed>

### `Modules\Ciudadania\Services\Padron\MockFuenteIdentidad`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Services/Padron/MockFuenteIdentidad.php:15`.
- Resumen: Adaptador mock del padrón municipal para entornos de desarrollo y pruebas.

Activo por defecto (principio 3.6): ningún entorno de desarrollo necesita conexión real al padrón para ejercitar el flujo completo de alta.  En tests, sustituir con $this->app->instance(FuenteIdentidadInterface::class, mock).

Metodos publicos:

- `function consultarDatos(string $valorDocumento): ?array`
  Devuelve null simulando persona no empadronada.
  `@return` array<string, mixed>|null

### `Modules\Ciudadania\Services\ResultadoMatching`

- Tipo: class.
- Fichero: `vida/Modules/Ciudadania/app/Services/ResultadoMatching.php:16`.
- Resumen: DTO inmutable que representa un candidato de posible duplicado.

Metodos publicos:

- `function __construct( public int $ciudadanoId, public string $nombreCompleto, public ?string $documento, public ?string $fechaNacimiento, public float $score, public array $camposCoincidentes, public bool $bloquea, )`
  _Sin resumen PHPDoc._

### `Modules\Documentos\Enums\EstadoInforme`

- Tipo: enum.
- Fichero: `vida/Modules/Documentos/app/Enums/EstadoInforme.php:8`.
- Resumen: Estados del ciclo de vida de un informe documental.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el estado del informe.

### `Modules\Documentos\Enums\MetodoConformidadCiudadano`

- Tipo: enum.
- Fichero: `vida/Modules/Documentos/app/Enums/MetodoConformidadCiudadano.php:8`.
- Resumen: Metodos aceptados para registrar la conformidad del ciudadano.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el metodo de conformidad.

### `Modules\Documentos\Enums\MetodoFirma`

- Tipo: enum.
- Fichero: `vida/Modules/Documentos/app/Enums/MetodoFirma.php:8`.
- Resumen: Metodos de firma admitidos para documentos generados.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el metodo de firma.

### `Modules\Documentos\Enums\OrigenDocumento`

- Tipo: enum.
- Fichero: `vida/Modules/Documentos/app/Enums/OrigenDocumento.php:8`.
- Resumen: Origenes posibles de un documento asociado al expediente.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el origen del documento.

### `Modules\Documentos\Enums\TipoInforme`

- Tipo: enum.
- Fichero: `vida/Modules/Documentos/app/Enums/TipoInforme.php:8`.
- Resumen: Tipos funcionales de informe que puede gestionar el modulo de documentos.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el tipo de informe.

### `Modules\Documentos\Models\Documento`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Models/Documento.php:37`.
- Resumen: Fichero custodiado en el sistema.

Puede ser un documento subido externamente (PDF aportado por el ciudadano o un profesional) o el PDF resultante de un informe generado y firmado.  Los ficheros nunca se sirven desde rutas públicas. El acceso siempre pasa por un controlador que verifica permisos y genera URLs firmadas temporales.

Metodos publicos:

- `function documentable(): MorphTo`
  _Sin resumen PHPDoc._
- `function tipo(): BelongsTo`
  _Sin resumen PHPDoc._
- `function subidoPor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function informe(): HasOne`
  _Sin resumen PHPDoc._
- `function scopeExternos(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeGenerados(Builder $query): Builder`
  _Sin resumen PHPDoc._

### `Modules\Documentos\Models\EstiloInforme`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Models/EstiloInforme.php:28`.
- Resumen: Estilo formal de los informes generados por una UO.

Los campos se heredan campo a campo por proximidad ascendente en la jerarquía de UOs: para cada campo, se usa el valor definido en la UO más cercana al autor que lo tenga establecido. La resolución la realiza ResolverEstiloInforme.  Una UO tiene como máximo un EstiloInforme (unique sobre unidad_organizativa_id).

Metodos publicos:

- `function unidadOrganizativa(): BelongsTo`
  _Sin resumen PHPDoc._
- `function creadoPor(): BelongsTo`
  _Sin resumen PHPDoc._

### `Modules\Documentos\Models\Informe`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Models/Informe.php:42`.
- Resumen: Instancia concreta de un informe profesional.

Ciclo de vida: borrador → firmado → (anulado). Un informe firmado es inmutable: no puede editarse ni eliminarse. Solo puede ser anulado por el autor, con motivo obligatorio. El fichero PDF firmado permanece en el sistema tras la anulación.  Las transiciones de estado se validan en ServicioFirmaInforme, no en el modelo (capa de servicio, no de persistencia).

Metodos publicos:

- `function plantilla(): BelongsTo`
  _Sin resumen PHPDoc._
- `function historiaSocial(): BelongsTo`
  _Sin resumen PHPDoc._
- `function getCiudadanoId(): ?int`
  ID del ciudadano asociado al informe.
  `@return` int|null
- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._
- `function autor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function documento(): BelongsTo`
  _Sin resumen PHPDoc._
- `function scopeBorradores(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeFirmados(Builder $query): Builder`
  _Sin resumen PHPDoc._
- `function scopeDeAutor(Builder $query, int $userId): Builder`
  _Sin resumen PHPDoc._
- `function estaFirmado(): bool`
  _Sin resumen PHPDoc._
- `function estaAnulado(): bool`
  _Sin resumen PHPDoc._

### `Modules\Documentos\Models\PisoFirmado`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Models/PisoFirmado.php:27`.
- Resumen: Custodia del Plan de Intervención (PISO) con doble firma.

En v1.0 la doble firma (profesional + ciudadano) se resuelve fuera del sistema digital: impresión, firma manuscrita de ambas partes, custodia del escaneado.  La FK a plan_de_intervencion_id no tiene constrained(): la tabla planes_de_intervencion aún no existe. Se añadirá cuando el módulo Intervención implemente esa tabla.

Metodos publicos:

- `function planDeIntervencion(): BelongsTo`
  _Sin resumen PHPDoc._
- `function documento(): BelongsTo`
  _Sin resumen PHPDoc._
- `function subidoPor(): BelongsTo`
  _Sin resumen PHPDoc._

### `Modules\Documentos\Models\PlantillaInforme`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Models/PlantillaInforme.php:31`.
- Resumen: Plantilla configurable para la generación de informes profesionales.

Define la estructura del informe (secciones automáticas y de texto libre). El aspecto formal lo aporta EstiloInforme en el momento de la generación.  Las plantillas tienen alcance jerárquico: una plantilla creada en una UO está disponible para todos los profesionales de esa UO y sus descendientes.

Metodos publicos:

- `function unidadOrganizativa(): BelongsTo`
  _Sin resumen PHPDoc._
- `function creadaPor(): BelongsTo`
  _Sin resumen PHPDoc._
- `function informes(): HasMany`
  _Sin resumen PHPDoc._
- `function scopeVisiblesParaUo(Builder $query, int $uoId): Builder`
  Plantillas activas visibles para una UO dada y todos sus ancestros.
  `@return` Builder<self>

### `Modules\Documentos\Observers\EstiloInformeObserver`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Observers/EstiloInformeObserver.php:14`.
- Resumen: Observer de EstiloInforme.

Invalida la caché de estilos de la UO afectada y todas sus descendientes cada vez que un EstiloInforme se guarda o elimina.

Metodos publicos:

- `function __construct(private ResolverEstiloInforme $resolver)`
  _Sin resumen PHPDoc._
- `function saved(EstiloInforme $estilo): void`
  _Sin resumen PHPDoc._
- `function deleted(EstiloInforme $estilo): void`
  _Sin resumen PHPDoc._

### `Modules\Documentos\Providers\DocumentosServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Providers/DocumentosServiceProvider.php:15`.
- Resumen: Provider del módulo Documentos.

Registra migraciones, configuración, servicios y observers del módulo.

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Documentos\Services\ResolverEstiloInforme`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Services/ResolverEstiloInforme.php:20`.
- Resumen: Resuelve el estilo de informe efectivo para una UO dada.

Recorre la cadena de ancestros de la UO (incluida ella misma) de más cercana a más lejana. Para cada campo visual (logo, nombre, dirección, teléfono, pie), usa el primer valor no nulo encontrado en la cadena. Si ningún ancestro define un campo, aplica el valor por defecto de config('documentos.estilo_defecto').  El resultado se cachea por UO con TTL configurable. La caché se invalida en EstiloInformeObserver al guardar o eliminar un EstiloInforme.

Metodos publicos:

- `function resolver(int $uoId): array`
  _Sin resumen PHPDoc._
- `function resolverSinCache(int $uoId): array`
  _Sin resumen PHPDoc._
- `function invalidarCacheUo(int $uoId): void`
  Invalida la caché de una UO y todas sus descendientes.
  `@return` void

### `Modules\Documentos\Services\ResolverFuentesInforme`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Services/ResolverFuentesInforme.php:24`.
- Resumen: Resuelve las fuentes de datos de las secciones de una PlantillaInforme.

Dos responsabilidades: 1. resolver(): fuentes automáticas ('automatico') — datos pre-cargados desde la Historia Social. 2. resolverMergeTags(): sustitución de variables dinámicas en secciones de texto libre.  Centraliza la lógica de extracción de datos para que las plantillas sean declarativas.

Metodos publicos:

- `function resolver(string $fuente, int $ciudadanoId): array`
  Resuelve los datos de una fuente automática para un ciudadano.
  `@return` array Datos estructurados para renderizar
- `function resolverMergeTags( string $html, int $ciudadanoId, int $profesionalId, Carbon $fechaInforme ): string`
  Sustituye los merge tags en el contenido HTML de una sección de plantilla, devolviendo el HTML con los valores reales del informe.
  `@return` string HTML con valores sustituidos

### `Modules\Documentos\Services\ServicioAlmacenamiento`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Services/ServicioAlmacenamiento.php:21`.
- Resumen: Abstracción sobre Laravel Filesystem para la custodia de documentos.

Centraliza subida, descarga y verificación de integridad. Los ficheros nunca se sirven desde rutas públicas: todo acceso genera URLs temporales.  El disco activo se configura en config('documentos.disco'). Cambiar el disco en el entorno no requiere cambios de código.

Metodos publicos:

- `function guardar( UploadedFile $fichero, string $contexto, int $subidoPor, int $tipoDocId, object $documentable ): Documento`
  Guarda un fichero PDF y retorna el Documento persistido.
  `@return` Documento
  `@throws` \InvalidArgumentException si el fichero no es un PDF
- `function guardarGenerado( string $contenidoPdf, int $subidoPor, int $tipoDocId, object $documentable, string $nombreOriginal ): Documento`
  Guarda un PDF ya generado internamente (informes firmados).
  `@return` Documento
- `function urlTemporal(Documento $documento, int $minutosExpiracion = 30): string`
  Genera una URL temporal para acceder al documento.
  `@return` string
- `function verificarIntegridad(Documento $documento): bool`
  Verifica la integridad del fichero comparando su hash SHA-256 actual con el calculado en el momento de la subida.
  `@return` bool true si el fichero no ha sido alterado
- `function eliminarFichero(Documento $documento): void`
  Elimina el fichero del disco. No elimina el registro en base de datos.
  `@return` void

### `Modules\Documentos\Services\ServicioFirmaInforme`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Services/ServicioFirmaInforme.php:20`.
- Resumen: Coordina el proceso de firma de un informe mediante AutoFirma.

Flujo: 1. El profesional genera la vista previa del PDF (ServicioGeneracionPDF::generarBorrador) 2. El cliente AutoFirma firma el PDF con el Certificado de Empleado Público 3. El componente Livewire recibe el PDF firmado en base64 y llama a este servicio 4. El servicio persiste el PDF y actualiza el estado del informe  La responsabilidad de autoría es personal: no existe fallback de firma manuscrita.

Metodos publicos:

- `function __construct(private ServicioGeneracionPDF $generacionPdf)`
  _Sin resumen PHPDoc._
- `function firmar(Informe $informe, string $pdfFirmadoBase64): Informe`
  Firma un informe en estado borrador.
  `@return` Informe Informe actualizado a estado firmado
  `@throws` \DomainException si el informe no está en estado borrador
- `function anular(Informe $informe, int $usuarioId, string $motivo): Informe`
  Anula un informe firmado. Solo puede ejecutarlo el autor.
  `@return` Informe
  `@throws` \DomainException si el informe no está firmado o el usuario no es el autor

### `Modules\Documentos\Services\ServicioGeneracionPDF`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Services/ServicioGeneracionPDF.php:19`.
- Resumen: Genera el PDF de un informe profesional.

Combina el estilo resuelto para la UO del autor con el contenido de las secciones del informe. Usa barryvdh/laravel-dompdf.  El PDF de borrador no se persiste (vista previa iterativa). El PDF final firmado se persiste vía ServicioAlmacenamiento.

Metodos publicos:

- `function __construct( private ResolverEstiloInforme $resolverEstilo, private ServicioAlmacenamiento $almacenamiento, private ResolverFuentesInforme $resolverFuentes, )`
  _Sin resumen PHPDoc._
- `function generarBorrador(Informe $informe): string`
  Genera el PDF de borrador y retorna su contenido binario.
  `@return` string Contenido binario del PDF
- `function generarFinal(Informe $informe, string $pdfFirmadoBase64): Documento`
  Persiste el PDF firmado recibido desde AutoFirma como Documento.
  `@return` Documento Documento persistido

### `Modules\Documentos\Support\MergeTagsCatalogo`

- Tipo: class.
- Fichero: `vida/Modules/Documentos/app/Support/MergeTagsCatalogo.php:13`.
- Resumen: Catálogo centralizado de merge tags disponibles en plantillas de informe. Las claves son los identificadores que se insertan en el contenido ({{ clave }}). Los valores son las etiquetas legibles que se muestran en el editor de Filament.

Al añadir un nuevo tag aquí también hay que implementar su resolución en ResolverFuentesInforme::resolverMergeTag().

Metodos publicos:

- `function todos(): array`
  Devuelve el array de merge tags en el formato que espera RichEditor::mergeTags(). Formato: ['clave' => 'Etiqueta legible'].
  `@return` array<string, string>
- `function claves(): array`
  Devuelve solo las claves, para validación.
  `@return` array<string>

### `Modules\Escalas\Enums\EstadoPase`

- Tipo: enum.
- Fichero: `vida/Modules/Escalas/app/Enums/EstadoPase.php:10`.
- Resumen: Estado de un pase de escala. El código toma decisiones sobre este valor (inmutabilidad de scores en completado, validación de respuestas completas antes de completar), por eso es enum PHP.

### `Modules\Escalas\Models\PaseEscala`

- Tipo: class.
- Fichero: `vida/Modules/Escalas/app/Models/PaseEscala.php:38`.
- Resumen: Aplicación concreta de un instrumento de escala a un ciudadano.

Cada pase es independiente e inmutable una vez en estado completado. Los scores (total y por sección) y la interpretación se calculan al cerrar y se persisten; no se recalculan en lectura. Esto garantiza fidelidad histórica aunque el schema del TipoEscala se modifique posteriormente.

Metodos publicos:

- `function calcularScores(): void`
  Suma los valores de todas las respuestas y calcula los scores por sección. No persiste; llamar a save() después si se desea guardar.
  `@return` void
- `function asignarInterpretacion(): void`
  Busca el rango de interpretación que corresponde al score_total y asigna su código. Si no encuentra ningún rango, deja interpretacion_codigo como null. No persiste.
  `@return` void
- `function completar(): void`
  Orquesta el cierre del pase: valida respuestas, calcula scores, persiste.
  `@return` void
  `@throws` \LogicException Si falta respuesta para algún ítem del schema.
- `function tipoEscala(): BelongsTo`
  Instrumento aplicado en este pase.
  `@return` BelongsTo<TipoEscala, self>
- `function getCiudadanoId(): ?int`
  ID del ciudadano titular de la historia social asociada al pase.
  `@return` int|null
- `function historia(): BelongsTo`
  _Sin resumen PHPDoc._
- `function profesional(): BelongsTo`
  Profesional que aplicó la escala.
  `@return` BelongsTo<User, self>

### `Modules\Escalas\Models\TipoEscala`

- Tipo: class.
- Fichero: `vida/Modules/Escalas/app/Models/TipoEscala.php:32`.
- Resumen: Instrumento estandarizado de valoración.

Define la estructura completa de una escala (secciones, ítems, opciones con valor numérico) y su tabla de rangos de interpretación. Gestionada exclusivamente desde el backoffice de Filament. Los profesionales aplican instancias de este instrumento mediante PaseEscala.

Metodos publicos:

- `function codigoId(string $codigo): int`
  Devuelve el id del TipoEscala con el código dado, desde caché.
  `@return` int
  `@throws` ModelNotFoundException
- `function scopeAplicables(Builder $query): Builder`
  Escalas disponibles para aplicar a un ciudadano.
  `@return` Builder<self>
- `function pases(): HasMany`
  Pases realizados de este instrumento.
  `@return` HasMany<PaseEscala, self>

### `Modules\Escalas\Providers\EscalasServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Escalas/app/Providers/EscalasServiceProvider.php:12`.
- Resumen: Provider del módulo Escalas.

Registra las migraciones del módulo.

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Enums\ClasificacionSia`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/ClasificacionSia.php:8`.
- Resumen: Clasificaciones SIA utilizadas para categorizar demandas de intervencion.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar la clasificacion SIA.

### `Modules\Intervencion\Enums\EstadoPlan`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/EstadoPlan.php:8`.
- Resumen: Estados del ciclo de vida de un plan de intervencion.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el estado del plan.

### `Modules\Intervencion\Enums\EstadoValoracion`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/EstadoValoracion.php:8`.
- Resumen: Estados de una valoracion profesional dentro de la historia social.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el estado de la valoracion.

### `Modules\Intervencion\Enums\MotivoCierre`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/MotivoCierre.php:8`.
- Resumen: Motivos normalizados para cerrar un plan o proceso de intervencion.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el motivo de cierre.

### `Modules\Intervencion\Enums\TipoApunte`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/TipoApunte.php:8`.
- Resumen: Tipos de apunte registrados en la historia social.

Metodos publicos:

- `function label(): string`
  Etiqueta legible para mostrar el tipo de apunte.

### `Modules\Intervencion\Enums\TipoEntrevista`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/TipoEntrevista.php:5`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function label(): string`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Enums\TipoPlan`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/TipoPlan.php:5`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function label(): string`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Enums\UrgenciaSia`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/UrgenciaSia.php:5`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function label(): string`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Enums\VisibilidadApunte`

- Tipo: enum.
- Fichero: `vida/Modules/Intervencion/app/Enums/VisibilidadApunte.php:5`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function label(): string`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Http\Livewire\AgendaPage`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Http/Livewire/AgendaPage.php:24`.
- Resumen: Pantalla de Agenda del interfaz operativo de Intervención.

Implementa tres vistas (día, semana, mes) con navegación de fechas. Los datos de citas se obtienen del módulo Agenda cuando esté disponible; hasta entonces se usa una fixture de desarrollo.

Metodos publicos:

- `function mount(): void`
  Inicializa la fecha ancla al día de hoy.
  `@return` void
- `function navegarAnterior(): void`
  Retrocede 1 día, semana o mes según la vista activa.
  `@return` void
- `function navegarSiguiente(): void`
  Avanza 1 día, semana o mes según la vista activa.
  `@return` void
- `function irAHoy(): void`
  Resetea la fecha ancla al día de hoy.
  `@return` void
- `function setVista(string $vista): void`
  Cambia la vista activa.
  `@return` void
- `function irADia(string $fecha): void`
  Al hacer clic en un día en la vista de mes, navega a la vista de día.
  `@return` void
- `function tituloFecha(): string`
  Título descriptivo de la fecha según la vista activa.
  `@return` string
- `function citasDia(): array`
  Citas para la vista de día: 4 columnas (ayer, hoy, mañana, pasado mañana).
  `@return` array<string, array<int, array<string, mixed>>>
- `function citasSemana(): array`
  Citas para la vista de semana: lunes a viernes de la semana del ancla.
  `@return` array<string, array<int, array<string, mixed>>>
- `function datosMes(): array`
  Datos del mes para la vista de mes: número de citas y tipos por día.
  `@return` array<int, array<string, mixed>>
- `function kpis(): array`
  KPIs para la franja superior de la vista.
  `@return` array{alertas_sin_reconocer: int, seguimientos_vencidos: int, citas: int, mensajes_sin_leer: int}
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Http/Livewire/BuscarCiudadanoPage.php:36`.
- Resumen: Pantalla de búsqueda de ciudadanos del interfaz operativo de Intervención.

Implementa tres niveles de acceso a los resultados según UO y colectivo: Nivel 1 (propio): Historia Social en la UO del profesional. Nivel 2 (otra UO): Historia Social en otra UO sin colectivo protegido. Nivel 3 (protegido): Ciudadano con colectivo_extra_protegido activo.  Nota: la búsqueda por nombre opera sobre datos cifrados. Se implementa cargando un máximo de 500 registros y filtrando en PHP — es aceptable para el volumen esperado en una UO. Un índice hash búsqueda-eficiente es la solución de producción correcta. TODO: Implementar índice hash determinista para búsqueda de nombre cifrado.

Metodos publicos:

- `function buscar(): void`
  Ejecuta la búsqueda y llena $resultados con los ciudadanos encontrados.
  `@return` void
- `function registrarAccesoNivel2(int $historiaId): void`
  Registra el acceso de nivel 2 en el log de auditoría y navega a la Historia Social. El campo audits no existe aún — se registra en el log de la aplicación.
  `@return` void
- `function abrirModalSolicitud(int $ciudadanoId): void`
  Abre el modal de solicitud de acceso para un ciudadano protegido.
  `@return` void
- `function cerrarModalSolicitud(): void`
  Cierra el modal sin enviar.
  `@return` void
- `function solicitarAcceso(int $ciudadanoId, string $justificacion): void`
  Crea la solicitud de acceso a un ciudadano protegido.
  `@return` void
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Http\Livewire\CiudadanoPage`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php:51`.
- Resumen: Pantalla principal de trabajo con el ciudadano.

Concentra el timeline de la Historia Social y las siete herramientas de registro. La ruta aplica `can:view,historia` usando HistoriaSocialPolicy.  Propiedades computadas expuestas como propiedades mágicas por Livewire 4 #[Computed]:

Metodos publicos:

- `function ciudadano(): ?Ciudadano`
  Ciudadano titular de la Historia Social.
  `@return` Ciudadano|null
- `function apuntesHS(): Collection`
  Apuntes visibles de la Historia Social, aplicando el filtro activo.
  `@return` Collection<int, Apunte>
- `function pisoActivo(): ?PlanDeIntervencion`
  Plan general ASP activo más reciente de la Historia Social.
  `@return` PlanDeIntervencion|null
- `function tiposFicha(): Collection`
  Tipos de ficha disponibles para la herramienta de Valoración.
  `@return` Collection<int, TipoFicha>
- `function tiposEscala(): Collection`
  Tipos de escala disponibles para la herramienta de Escala.
  `@return` Collection<int, TipoEscala>
- `function accesosRecientes(): Collection`
  Últimos 5 accesos al expediente filtrados según la visibilidad del usuario.
  `@return` Collection<int, Audit>
- `function puedeVerTodosLosAccesos(): bool`
  Indica si el usuario puede ver todos los accesos o únicamente los propios. Controla la visibilidad del enlace "Ver todo" en el widget.
  `@return` bool
- `function uoNombre(): ?string`
  Nombre corto (o completo) de la UO responsable de la Historia Social. Se muestra en la cabecera del ciudadano sustituyendo al ID numérico de UO.
  `@return` string|null
- `function planNombreCorto(): string`
  Nombre corto del Plan de Intervención según la UO de la Historia Social. Fallback: «Plan».
  `@return` string
- `function planNombreCompleto(): string`
  Nombre completo del Plan de Intervención según la UO de la Historia Social. Fallback: «Plan de intervención».
  `@return` string
- `function ciudadanoDocumento(): ?string`
  Documento de identidad del ciudadano (cifrado, desencriptado por el cast).
  `@return` string|null
- `function ciudadanoTelefono(): ?string`
  Teléfono de contacto del ciudadano.
  `@return` string|null
- `function ciudadanoEmail(): ?string`
  Correo electrónico de contacto del ciudadano.
  `@return` string|null
- `function statApuntes(): int`
  Total de apuntes visibles en la Historia Social (todos los filtros). Usa la colección ya cargada para evitar consulta adicional.
  `@return` int
- `function statPrestaciones(): ?string`
  Prestaciones activas del ciudadano. Pendiente de integración real con el módulo Prestaciones.
  `@return` string|null
- `function statUltimoContacto(): ?string`
  Fecha del último registro en la Historia Social formateada.
  `@return` string|null
- `function ucVigente(): ?UnidadConvivencia`
  UC vigente del ciudadano (primera activa), con miembros y ciudadanos cargados.
  `@return` UnidadConvivencia|null
- `function ucMiembrosActivos(): \Illuminate\Support\Collection`
  Miembros activos de la UC vigente con datos de ciudadano cargados.
  `@return` \Illuminate\Support\Collection<int, UnidadConvivenciaMiembro>
- `function ucResultadosBusqueda(): \Illuminate\Support\Collection`
  Ciudadanos que coinciden con la búsqueda, excluyendo miembros actuales y titular. Carga en PHP porque los campos de nombre están cifrados (mismo patrón que BuscarCiudadanoPage).
  `@return` \Illuminate\Support\Collection<int, Ciudadano>
- `function representante(): ?Ciudadano`
  Representante legal/designado del ciudadano, si existe relación activa. Busca por implicacion_funcional = 'representante', nunca por slug directamente.
  `@return` Ciudadano|null
- `function relacionesAgrupadas(): \Illuminate\Support\Collection`
  Relaciones activas del ciudadano agrupadas por tipo (para el modal completo). Excluye tipos no presentes en el catálogo activo.
  `@return` \Illuminate\Support\Collection<string, array{etiqueta: string, miembros: \Illuminate\Support\Collection}>
- `function relacionesMiembrosUc(): \Illuminate\Support\Collection`
  Tipo de relación (etiqueta) de cada miembro de la UC respecto al titular, indexado por ciudadano_id. Enriquece el widget UC.
  `@return` \Illuminate\Support\Collection<int, string|null>
- `function toggleUC(): void`
  _Sin resumen PHPDoc._
- `function abrirModalRelaciones(): void`
  _Sin resumen PHPDoc._
- `function cerrarModalRelaciones(): void`
  _Sin resumen PHPDoc._
- `function abrirModalRepresentante(): void`
  _Sin resumen PHPDoc._
- `function cerrarModalRepresentante(): void`
  _Sin resumen PHPDoc._
- `function abrirModalUc(): void`
  Abre el modal de gestión de UC y reinicia su estado interno.
  `@return` void
- `function cerrarModalUc(): void`
  Cierra el modal de gestión de UC.
  `@return` void
- `function seleccionarCiudadanoUc(int $ciudadanoId): void`
  Selecciona un ciudadano de los resultados de búsqueda para confirmar su adición.
  `@return` void
- `function confirmarAnadirMiembro(): void`
  Confirma la adición del ciudadano seleccionado a la UC vigente.
  `@return` void
- `function cancelarSeleccionUc(): void`
  Cancela la selección de ciudadano para añadir a la UC.
  `@return` void
- `function iniciarBajaMiembro(int $miembroId): void`
  Inicia el flujo de confirmación de baja de un miembro.
  `@return` void
- `function confirmarBajaMiembro(): void`
  Confirma la baja del miembro seleccionado, estableciendo su fecha_fin.
  `@return` void
- `function cancelarBajaMiembro(): void`
  Cancela el flujo de confirmación de baja.
  `@return` void
- `function verificarMiembro(int $miembroId): void`
  Verifica manualmente la residencia de un miembro en la UC vigente.
  `@return` void
- `function crearUc(): void`
  Crea la UC tomando el domicilio del ciudadano titular y lo añade como primer miembro. Solo actúa si el ciudadano no tiene UC vigente.
  `@return` void
- `function toggleApunte(int $apunteId): void`
  _Sin resumen PHPDoc._
- `function setFiltroHS(string $filtro): void`
  _Sin resumen PHPDoc._
  `@return` void
- `function seleccionarHerramienta(string $herramienta): void`
  _Sin resumen PHPDoc._
- `function cancelarHerramienta(): void`
  _Sin resumen PHPDoc._
- `function verApunte(int $apunteId): void`
  Abre el modal de detalle de un apunte en modo solo lectura. El pasado es inmutable: este modal nunca ofrece edición.
  `@return` void
- `function cerrarModalApunte(): void`
  Cierra el modal de detalle de apunte.
  `@return` void
- `function guardarEntrevista(): void`
  Guarda una entrevista y su apunte asociado.
  `@return` void
- `function guardarAnotacion(): void`
  Guarda una anotación en la Historia Social.
  `@return` void
- `function crearDerivacion(): void`
  Crea una derivación (apunte de tipo derivacion). La tabla derivaciones no existe todavía — se registra como apunte. TODO: crear modelo Derivacion y tabla derivaciones cuando esté disponible.
  `@return` void
- `function guardarGestion(): void`
  Guarda una gestión / coordinación como apunte.
  `@return` void
- `function guardarValoracion(int $tipoFichaId, array $datos, ?int $entrevistaId = null): void`
  Guarda una valoración y su apunte asociado (desde RegistrarValoracionPage).
  `@return` void
- `function guardarEscala(int $tipoEscalaId, array $respuestas): void`
  Guarda un pase de escala y su apunte asociado (desde RegistrarEscalaPage).
  `@return` void
- `function calcularScoreEscala(array $schema, array $respuestas): int`
  Calcula la puntuación total de un pase de escala. Suma valor × peso de cada ítem respondido.
  `@return` int
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Http\Livewire\MisCasosPage`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Http/Livewire/MisCasosPage.php:28`.
- Resumen: Pantalla "Mis casos" del interfaz operativo de Intervención.

Muestra la lista paginada de ciudadanos con plan general activo asignados al profesional autenticado. Incluye filtros por estado de seguimiento, plan ASP y derivaciones especializadas.

Metodos publicos:

- `function updatedFiltroSeguimiento(): void`
  _Sin resumen PHPDoc._
- `function updatedFiltroPiso(): void`
  _Sin resumen PHPDoc._
- `function updatedFiltroEsp(): void`
  _Sin resumen PHPDoc._
- `function updatedOrdenarPor(): void`
  _Sin resumen PHPDoc._
- `function updatedBusqueda(): void`
  _Sin resumen PHPDoc._
- `function nombrePlanAsp(): string`
  Etiqueta configurable del tipo de plan general (PISO o nombre alternativo). Se lee del catálogo de sistema para permitir cambio sin deploy.
  `@return` string
- `function casos(): LengthAwarePaginator`
  Lista paginada de casos asignados al profesional autenticado.
  `@return` LengthAwarePaginator
- `function ciudadanosDelPage(): Collection`
  Mapa de ciudadanos correspondientes a la página actual de casos. Carga los nombres en una sola consulta para evitar N+1 sobre datos cifrados.
  `@return` Collection<int, Ciudadano>
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Http\Livewire\RegistrarEscalaPage`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Http/Livewire/RegistrarEscalaPage.php:20`.
- Resumen: Pantalla completa para aplicar un instrumento de escala.

Carga el schema del TipoEscala y presenta las secciones con sus ítems. Al guardar, delega en CiudadanoPage::guardarEscala().

Metodos publicos:

- `function mount(HistoriaSocial $historia): void`
  Inicializa la pantalla con la historia y el tipo de escala opcional.
  `@return` void
- `function getTipoEscalaProperty(): ?TipoEscala`
  _Sin resumen PHPDoc._
- `function guardar(): void`
  Guarda el pase de escala y redirige de vuelta a la pantalla del ciudadano.
  `@return` void
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Http\Livewire\RegistrarValoracionPage`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Http/Livewire/RegistrarValoracionPage.php:24`.
- Resumen: Pantalla completa para registrar una ficha de valoración sobre la Historia Social.

Carga el schema del TipoFicha seleccionado y renderiza los campos dinámicamente según su tipo (texto, numero, select, booleano, fecha, escala). Persiste los datos en `fichas` vinculada directamente a la historia mediante historia_id (sin requerir Valoracion formal previa — TODO: vincular cuando esté completo).

Metodos publicos:

- `function mount(HistoriaSocial $historia): void`
  Inicializa la pantalla con la historia y los parámetros de ficha/entrevista.
  `@return` void
- `function tipoFicha(): ?TipoFicha`
  TipoFicha actualmente seleccionado, null si no hay selección.
  `@return` TipoFicha|null
- `function fichasDisponibles(): array`
  Fichas activas disponibles para el selector, indexadas por id.
  `@return` array<int, string>
- `function seleccionarFicha(int $id): void`
  Cambia la ficha seleccionada y reinicializa el formulario.
  `@return` void
- `function guardar(): void`
  Valida los campos obligatorios y persiste la ficha vinculada a la historia. Si ya existe una ficha para esta historia y tipo, la actualiza (idempotente).
  `@return` void
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Http\Livewire\Sidebar`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Http/Livewire/Sidebar.php:19`.
- Resumen: Sidebar del interfaz operativo de Intervención.

Muestra la navegación principal y badges de conteo. Se actualiza automáticamente cada 5 minutos mediante wire:poll.

Metodos publicos:

- `function datos(): array`
  Contadores para los badges del sidebar.
  `@return` array{alertas: int, mensajes: int, notificaciones: int, casos: int}
- `function branding(): array`
  Datos de identidad visual configurados en el backoffice. Se usa para el logotipo y nombre de aplicación en la cabecera del sidebar.
  `@return` array{logoUrl: string|null, nombreAplicacion: string|null}
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Models\Apunte`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/Apunte.php:39`.
- Resumen: Apunte asociado a un Plan de Intervención.

Nodo de conexión entre el plan y entidades heterogéneas: entrevistas, documentos, derivaciones, seguimientos o anotaciones sin entidad vinculada.  Tres niveles de visibilidad (docs/modulo-intervencion.md §7.2): - privada: solo el autor. Regla con precedencia absoluta. - profesionales: cualquier profesional con acceso a la historia. - ciudadano: visible también en la carpeta ciudadana.

Metodos publicos:

- `function getCiudadanoId(): ?int`
  ID del ciudadano titular de la historia social asociada al apunte.
  `@return` int|null
- `function plan(): BelongsTo`
  _Sin resumen PHPDoc._
- `function autor(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<User, Apunte>
- `function apuntable(): MorphTo`
  Entidad concreta vinculada (polimórfica).
  `@return` MorphTo
- `function scopeVisiblesParaUsuario(Builder $query, int $usuarioId): Builder`
  Devuelve los apuntes visibles para un usuario dado.
  `@return` Builder<self>

### `Modules\Intervencion\Models\Entrevista`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/Entrevista.php:34`.
- Resumen: Entrevista profesional-ciudadano.

Contenedor de trabajo del profesional durante y después del encuentro. Puede existir sin cita previa (visitas domiciliarias, contactos urgentes). Puede generar una Valoracion o un SeguimientoPlan según su tipo.

Metodos publicos:

- `function getCiudadanoId(): ?int`
  Devuelve el ciudadano asociado a la historia social de la entrevista.
  `@return` int|null
- `function historia(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<HistoriaSocial, Entrevista>
- `function profesional(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<User, Entrevista>
- `function planDeIntervencion(): BelongsTo`
  Plan de intervención al que está vinculada esta entrevista de seguimiento.
  `@return` BelongsTo<PlanDeIntervencion, Entrevista>
- `function valoracion(): HasOne`
  Valoración generada en esta entrevista (si la hubiera).
  `@return` HasOne<Valoracion>
- `function seguimientoPlan(): HasOne`
  Seguimiento del plan generado en esta entrevista (si la hubiera).
  `@return` HasOne<SeguimientoPlan>

### `Modules\Intervencion\Models\Ficha`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/Ficha.php:30`.
- Resumen: Ficha de datos reales de una valoración.

Cada ficha corresponde a un TipoFicha y almacena los valores introducidos por el profesional. El campo datos es un JSON libre; el campo notas permite texto sin estructura durante la entrevista.  historia_id se usa cuando la ficha se crea desde RegistrarValoracionPage antes de existir una Valoracion formal (valoracion_id nullable). TODO: vincular siempre a Valoracion cuando ese flujo esté completo.

Metodos publicos:

- `function historia(): BelongsTo`
  Historia social a la que pertenece esta ficha (flujo directo, sin valoracion formal).
  `@return` BelongsTo<HistoriaSocial, Ficha>
- `function valoracion(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<Valoracion, Ficha>
- `function tipoFicha(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<TipoFicha, Ficha>

### `Modules\Intervencion\Models\FirmaPlan`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/FirmaPlan.php:24`.
- Resumen: Firma de una versión concreta de un Plan de Intervención.

Registra las firmas del ciudadano y del profesional responsable. Cada revisión que requiere nueva firma genera un nuevo registro. Un plan no puede activarse sin firma de ambas partes.

Metodos publicos:

- `function plan(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<PlanDeIntervencion, FirmaPlan>

### `Modules\Intervencion\Models\PlanDeIntervencion`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/PlanDeIntervencion.php:46`.
- Resumen: Plan de Intervención Social (PISO).

Acuerdo formal entre el profesional y el ciudadano con objetivos, prestaciones comprometidas y compromisos del ciudadano. Requiere firma de ambas partes para activarse (ver estaFirmado()).  El versionado es no destructivo: crearNuevaVersion() genera un nuevo registro con version+1; el original pasa a estado en_revision.

Metodos publicos:

- `function getCiudadanoId(): ?int`
  Devuelve el ciudadano asociado a la historia social del plan.
  `@return` int|null
- `function historia(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<HistoriaSocial, PlanDeIntervencion>
- `function profesionalResponsable(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<User, PlanDeIntervencion>
- `function planAsp(): BelongsTo`
  Plan general ASP al que está vinculado este plan especializado.
  `@return` BelongsTo<PlanDeIntervencion, PlanDeIntervencion>
- `function planesEspecializados(): HasMany`
  Planes especializados vinculados a este plan general.
  `@return` HasMany<PlanDeIntervencion>
- `function firmas(): HasMany`
  _Sin resumen PHPDoc._
  `@return` HasMany<FirmaPlan>
- `function revisiones(): HasMany`
  _Sin resumen PHPDoc._
  `@return` HasMany<RevisionPlan>
- `function seguimientos(): HasMany`
  _Sin resumen PHPDoc._
  `@return` HasMany<SeguimientoPlan>
- `function apuntes(): HasMany`
  _Sin resumen PHPDoc._
  `@return` HasMany<Apunte>
- `function estaFirmado(): bool`
  Comprueba si la versión actual del plan tiene ambas firmas registradas.
  `@return` bool
- `function crearNuevaVersion(string $motivo, int $profesionalId, ?int $seguimientoId = null): static`
  Crea una nueva versión del plan a partir del estado actual.
  `@return` static El nuevo plan (nueva versión)
  `@throws` \DomainException Si el plan no está en estado activo
- `function scopeActivos(Builder $query): Builder`
  Solo planes activos.
  `@return` Builder<PlanDeIntervencion>

### `Modules\Intervencion\Models\RevisionPlan`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/RevisionPlan.php:25`.
- Resumen: Registro histórico de una revisión de Plan de Intervención.

Almacena qué versión fue revisada, por quién, cuándo y por qué motivo. Si la revisión tiene origen en un seguimiento concreto, se registra el vínculo.

Metodos publicos:

- `function plan(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<PlanDeIntervencion, RevisionPlan>
- `function profesional(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<User, RevisionPlan>
- `function seguimiento(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<SeguimientoPlan, RevisionPlan>

### `Modules\Intervencion\Models\SeguimientoPlan`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/SeguimientoPlan.php:31`.
- Resumen: Registro de una sesión de seguimiento del Plan de Intervención.

Se genera a partir de una entrevista de tipo seguimiento. Si requiere_revision_plan = true, el flujo posterior debe invocar PlanDeIntervencion::crearNuevaVersion() para iniciar el proceso de revisión.

Metodos publicos:

- `function plan(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<PlanDeIntervencion, SeguimientoPlan>
- `function entrevista(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<Entrevista, SeguimientoPlan>
- `function profesional(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<User, SeguimientoPlan>
- `function solicitarCitaSiguiente(): void`
  Solicita al módulo de Agenda la creación de una cita para la siguiente sesión.
  `@return` void

### `Modules\Intervencion\Models\SiaContacto`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/SiaContacto.php:35`.
- Resumen: Registro de contacto del Servicio de Información y Asesoramiento (SIA).

El SIA es opcional: si el municipio no lo tiene implementado, las funciones de alta, clasificación y determinación de urgencia recaen en el TSR. Ver docs/modulo-intervencion.md §2.

Metodos publicos:

- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<Ciudadano, SiaContacto>
- `function auxiliar(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<User, SiaContacto>
- `function scopeCompetenciaMunicipal(Builder $query): Builder`
  Solo contactos de competencia municipal.
  `@return` Builder<SiaContacto>

### `Modules\Intervencion\Models\TipoFicha`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/TipoFicha.php:30`.
- Resumen: Tipo de ficha configurable desde el backoffice.

Define la estructura de campos de una ficha de valoración: qué campos existen, su tipo, orden y reglas de visibilidad condicional. El campo schema es un array JSON con la clave raíz "campos" que contiene la lista de definiciones de campo.  La validación del schema se aplica en el evento saving para garantizar la integridad estructural independientemente del canal de entrada. Cuando existen fichas cumplimentadas, los ids y tipos de campos existentes son inmutables: solo se pueden añadir campos nuevos.

Metodos publicos:

- `function setSchemaAttribute(mixed $value): void`
  Valida el schema antes de almacenarlo.
  `@return` void
  `@throws` \InvalidArgumentException Si el string recibido no es JSON válido
- `function getSchemaAttribute(mixed $value): array`
  Devuelve el schema siempre como array PHP.
  `@return` array
- `function fichas(): HasMany`
  Fichas cumplimentadas que usan este tipo.
  `@return` HasMany<Ficha>
- `function tipoValoracionFichas(): HasMany`
  Asociaciones de este tipo de ficha con tipos de valoración.
  `@return` HasMany<TipoValoracionFicha>
- `function tieneFichasAsociadas(): bool`
  Indica si esta ficha ya tiene instancias reales de datos (fichas cumplimentadas). Cuando es true, los ids y tipos de campos existentes son inmutables.
  `@return` bool
- `function validarSchema(): void`
  Valida la estructura del schema JSON antes de persistir. Lanza ValidationException si el schema no cumple el contrato.
  `@return` void
  `@throws` ValidationException
- `function scopeActivos(Builder $query): Builder`
  Fichas activas disponibles para componer valoraciones.
  `@return` Builder<TipoFicha>

### `Modules\Intervencion\Models\TipoValoracion`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/TipoValoracion.php:23`.
- Resumen: Tipo de valoración configurable desde el backoffice.

Define qué fichas componen una valoración, en qué orden y cuáles son obligatorias. Un tipo de valoración agrupa fichas para un contexto específico (ASP, especializada_mayores, etc.).

Metodos publicos:

- `function tipoValoracionFichas(): HasMany`
  Fichas asociadas a este tipo de valoración, con metadata de orden y obligatoriedad.
  `@return` HasMany<TipoValoracionFicha>
- `function scopeActivos(Builder $query): Builder`
  Solo tipos de valoración activos.
  `@return` Builder<TipoValoracion>

### `Modules\Intervencion\Models\TipoValoracionFicha`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/TipoValoracionFicha.php:19`.
- Resumen: Pivot entre TipoValoracion y TipoFicha.

Define qué fichas componen cada tipo de valoración, en qué orden y cuáles son obligatorias.

Metodos publicos:

- `function tipoValoracion(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<TipoValoracion, TipoValoracionFicha>
- `function tipoFicha(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<TipoFicha, TipoValoracionFicha>

### `Modules\Intervencion\Models\Valoracion`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Models/Valoracion.php:32`.
- Resumen: Valoración diagnóstica estructurada del ciudadano.

Tiene ciclo de vida propio: puede completarse en varias sesiones, revisarse posteriormente y existir en múltiples versiones. No toda entrevista genera valoración.

Metodos publicos:

- `function getCiudadanoId(): ?int`
  Devuelve el ciudadano asociado a la historia social de la valoración.
  `@return` int|null
- `function historia(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<HistoriaSocial, Valoracion>
- `function entrevista(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<Entrevista, Valoracion>
- `function profesional(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<User, Valoracion>
- `function tipoValoracion(): BelongsTo`
  _Sin resumen PHPDoc._
  `@return` BelongsTo<TipoValoracion, Valoracion>
- `function fichas(): HasMany`
  _Sin resumen PHPDoc._
  `@return` HasMany<Ficha>

### `Modules\Intervencion\Policies\ApuntePolicy`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Policies/ApuntePolicy.php:21`.
- Resumen: Policy de autorización para Apunte del módulo Intervención.

Implementa las tres reglas de visibilidad descritas en docs/modulo-intervencion.md §7.2: - privada: solo el autor, sin excepción de rol ni jerarquía (precedencia absoluta) - profesionales: accesible a cualquier profesional; el autor puede editar - ciudadano: accesible a profesionales y, en el futuro, al ciudadano vía carpeta ciudadana  Regla adicional de eliminación: solo los apuntes privados pueden eliminarse (son notas personales del profesional). Los apuntes de visibilidad profesionales o ciudadano son registro permanente e ineliminable por diseño.

Metodos publicos:

- `function view(User $usuario, Apunte $apunte): bool`
  Determina si el usuario puede ver el apunte.
  `@return` bool
- `function update(User $usuario, Apunte $apunte): bool`
  Determina si el usuario puede editar el apunte.
  `@return` bool
- `function delete(User $usuario, Apunte $apunte): bool`
  Determina si el usuario puede eliminar el apunte.
  `@return` bool

### `Modules\Intervencion\Policies\PlanDeIntervencionPolicy`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Policies/PlanDeIntervencionPolicy.php:31`.
- Resumen: Policy de autorización para el Plan de Intervención.

Implementa los tres pasos estándar de seguridad en profundidad:  Paso 1 — Permiso atómico: ¿tiene el usuario el permiso Spatie requerido? Paso 2 — Ámbito de UO: ¿el plan pertenece al ámbito de UO del usuario? La UO se obtiene vía Historia Social del plan. Nivel 1 = ámbito de UO → gestión completa. Nivel 2 = fuera del ámbito → solo lectura. Paso 3 — Colectivo protegido: solo en Nivel 2, verificar aprobación vigente (delegado a HistoriaSocialPolicy vía comprobación directa).  Caso especial — supervision: solo lectura, nunca escritura. Caso especial — adm_sistema: también debe pasar los filtros de UO y colectivo protegido.

Metodos publicos:

- `function viewAny(User $usuario): bool`
  Decide si el usuario puede listar Planes de Intervención.
  `@return` bool
- `function view(User $usuario, PlanDeIntervencion $plan): bool`
  Decide si el usuario puede consultar el Plan de Intervención.
  `@return` bool
- `function create(User $usuario): bool`
  Decide si el usuario puede crear un Plan de Intervención.
  `@return` bool
- `function update(User $usuario, PlanDeIntervencion $plan): bool`
  Decide si el usuario puede editar el Plan de Intervención.
  `@return` bool
- `function delete(User $usuario, PlanDeIntervencion $plan): bool`
  Decide si el usuario puede eliminar (baja lógica) el Plan de Intervención.
  `@return` bool

### `Modules\Intervencion\Providers\IntervencionServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Providers/IntervencionServiceProvider.php:29`.
- Resumen: Provider del módulo Intervención.

Registra migraciones, policies, vistas, rutas y componentes Livewire del módulo de gestión del ciclo de intervención social.

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Intervencion\Services\ApunteService`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Services/ApunteService.php:23`.
- Resumen: Servicio de dominio para el Apunte del módulo Intervención.

Centraliza las operaciones de escritura sobre Apunte garantizando que todas las mutaciones pasen por el par (GlobalScope + Policy).  Regla crítica de dominio: las anotaciones privadas son inmutables desde el punto de vista del acceso ajeno — la Policy aplica la restricción de autor con precedencia absoluta.

Metodos publicos:

- `function crear(array $datos): Apunte`
  Crea un nuevo apunte.
  `@return` Apunte
  `@throws` AuthorizationException Si el usuario no tiene permiso de crear apuntes
- `function actualizar(int $id, array $datos): Apunte`
  Actualiza un apunte existente.
  `@return` Apunte
  `@throws` AuthorizationException Si el usuario no tiene permiso de editar
  `@throws` ModelNotFoundException Si el apunte no existe o no está en el ámbito del usuario
- `function eliminar(int $id): void`
  Elimina un apunte.
  `@return` void
  `@throws` AuthorizationException Si el usuario no tiene permiso de eliminar
  `@throws` ModelNotFoundException Si el apunte no existe o no está en el ámbito del usuario

### `Modules\Intervencion\Services\HistoriaSocialService`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Services/HistoriaSocialService.php:27`.
- Resumen: Servicio de dominio para la Historia Social.

Centraliza las operaciones de escritura sobre HistoriaSocial garantizando que todas las mutaciones pasen por el par (GlobalScope + Policy) definido en la estrategia de seguridad en profundidad.  Patrón de cada método de escritura: 1. Recuperar el registro (el GlobalScope ya filtra por ámbito de UO). 2. Verificar la Policy (Gate::authorize). 3. Ejecutar la lógica de negocio.

Metodos publicos:

- `function crear(array $datos): HistoriaSocial`
  Abre (crea) una nueva Historia Social.
  `@return` HistoriaSocial
  `@throws` AuthorizationException Si el usuario no tiene permiso de crear
- `function actualizar(int $id, array $datos): HistoriaSocial`
  Actualiza una Historia Social existente.
  `@return` HistoriaSocial
  `@throws` AuthorizationException Si el usuario no tiene permiso de editar
  `@throws` ModelNotFoundException Si la historia no existe o no está en el ámbito del usuario
- `function eliminar(int $id): void`
  Elimina (baja lógica) una Historia Social.
  `@return` void
  `@throws` AuthorizationException Si el usuario no tiene permiso de eliminar
  `@throws` ModelNotFoundException Si la historia no existe o no está en el ámbito del usuario

### `Modules\Intervencion\Services\IntervencionSidebarDataService`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Services/IntervencionSidebarDataService.php:20`.
- Resumen: Servicio de datos para el sidebar del interfaz operativo de Intervención.

Proporciona los contadores para los badges del sidebar: - Total de alertas directas pendientes de reconocimiento - Total de mensajes no leídos - Número de ciudadanos con plan activo asignados al profesional

Metodos publicos:

- `function totalAlertas(): int`
  Número de alertas directas al usuario autenticado pendientes de reconocimiento.
  `@return` int
- `function mensajesNoLeidos(): int`
  Número de mensajes no leídos del usuario autenticado.
  `@return` int
- `function totalNotificaciones(): int`
  Total de notificaciones (alertas + mensajes no leídos). Usado para el badge del ítem "Alertas y mensajes".
  `@return` int
- `function misCasosCount(): int`
  Número de historias sociales con plan activo asignadas al profesional. Usado para el badge del ítem "Mis casos".
  `@return` int
- `function getData(): array`
  Devuelve todos los datos del sidebar en un array.
  `@return` array{alertas: int, mensajes: int, notificaciones: int, casos: int}

### `Modules\Intervencion\Services\PlanDeIntervencionService`

- Tipo: class.
- Fichero: `vida/Modules/Intervencion/app/Services/PlanDeIntervencionService.php:24`.
- Resumen: Servicio de dominio para el Plan de Intervención.

Centraliza las operaciones de escritura sobre PlanDeIntervencion garantizando que todas las mutaciones pasen por el par (GlobalScope + Policy).  El versionado del plan es no destructivo: crearNuevaVersion() genera un nuevo registro. El servicio expone los métodos básicos de escritura; la lógica de negocio compleja (firma, revisión) pertenece al modelo.

Metodos publicos:

- `function crear(array $datos): PlanDeIntervencion`
  Crea un nuevo Plan de Intervención.
  `@return` PlanDeIntervencion
  `@throws` AuthorizationException Si el usuario no tiene permiso de crear planes
- `function actualizar(int $id, array $datos): PlanDeIntervencion`
  Actualiza un Plan de Intervención existente.
  `@return` PlanDeIntervencion
  `@throws` AuthorizationException Si el usuario no tiene permiso de editar
  `@throws` ModelNotFoundException Si el plan no existe o no está en el ámbito del usuario
- `function eliminar(int $id): void`
  Elimina (baja lógica) un Plan de Intervención.
  `@return` void
  `@throws` AuthorizationException Si el usuario no tiene permiso de eliminar
  `@throws` ModelNotFoundException Si el plan no existe o no está en el ámbito del usuario

### `Modules\Mensajes\Enums\DestinatarioType`

- Tipo: enum.
- Fichero: `vida/Modules/Mensajes/app/Enums/DestinatarioType.php:12`.
- Resumen: Tipo de destinatario de una alerta del sistema.

- usuario: alerta dirigida a un usuario concreto. - rol_uo: alerta dirigida a todos los usuarios con un rol determinado en una Unidad Organizativa concreta.

### `Modules\Mensajes\Enums\EstadoAlerta`

- Tipo: enum.
- Fichero: `vida/Modules/Mensajes/app/Enums/EstadoAlerta.php:13`.
- Resumen: Estado del ciclo de vida de una alerta.

pendiente  → reconocida (fin) pendiente  → escalada (si vence el plazo de reconocimiento) escalada   → reconocida (por el supervisor) escalada   → vencida (si el supervisor tampoco reconoce en plazo)

### `Modules\Mensajes\Enums\RolParticipante`

- Tipo: enum.
- Fichero: `vida/Modules/Mensajes/app/Enums/RolParticipante.php:11`.
- Resumen: Rol de un usuario dentro de un hilo de mensajes.

- remitente_inicial: usuario que creó el hilo y envió el primer mensaje. - participante: el otro interlocutor en la conversación 1 a 1.

### `Modules\Mensajes\Enums\TipoAlerta`

- Tipo: enum.
- Fichero: `vida/Modules/Mensajes/app/Enums/TipoAlerta.php:13`.
- Resumen: Nivel de gravedad de una alerta del sistema.

- aviso: notificación informativa. No requiere reconocimiento explícito ni tiene plazo de vencimiento. - alerta: requiere reconocimiento explícito en un plazo máximo de 4 horas laborales. Si vence sin ser reconocida, escala al supervisor de la UO.

### `Modules\Mensajes\Enums\TipoReconocimiento`

- Tipo: enum.
- Fichero: `vida/Modules/Mensajes/app/Enums/TipoReconocimiento.php:12`.
- Resumen: Naturaleza del reconocimiento de una alerta por parte de un usuario.

- reconocida: el destinatario original marcó la alerta como leída. - escalada: el supervisor heredó la alerta tras un vencimiento de plazo. - descartada: el destinatario descartó un aviso (sin confirmación requerida).

### `Modules\Mensajes\Enums\VisibilidadMensaje`

- Tipo: enum.
- Fichero: `vida/Modules/Mensajes/app/Enums/VisibilidadMensaje.php:15`.
- Resumen: Visibilidad de un registro de mensaje en la Historia Social.

- privada: solo visible para el profesional que lo registró. - profesionales: visible para todos los profesionales con acceso a la Historia Social del ciudadano.  Nota: la visibilidad 'ciudadano' no aplica aquí ya que se trata de comunicación interna entre profesionales.

### `Modules\Mensajes\Exceptions\UnauthorizedException`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Exceptions/UnauthorizedException.php:14`.
- Resumen: Excepción lanzada cuando un usuario intenta realizar una acción para la que no tiene autorización dentro del módulo de Mensajes.

Ejemplo: intentar registrar un mensaje en la Historia Social de un ciudadano del que no se es TSR responsable.

Metodos publicos:

- `function noEsTsr(int $usuarioId, int $ciudadanoId): self`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Http\Livewire\BuzonPage`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Http/Livewire/BuzonPage.php:33`.
- Resumen: Buzón unificado de alertas, avisos y mensajes del interfaz operativo.

Tres pestañas: - Alertas: notificaciones que requieren reconocimiento explícito. - Avisos: informativos sin plazo de reconocimiento. - Mensajes: hilos de conversación con otros profesionales.

Metodos publicos:

- `function alertas(): \Illuminate\Database\Eloquent\Collection`
  Alertas (tipo alerta) directas al usuario en estado pendiente.
  `@return` \Illuminate\Database\Eloquent\Collection<int, Alerta>
- `function avisos(): \Illuminate\Database\Eloquent\Collection`
  Avisos (tipo aviso) directos al usuario en estado pendiente.
  `@return` \Illuminate\Database\Eloquent\Collection<int, Alerta>
- `function hilos(): Collection`
  Hilos de mensajes activos en los que participa el usuario.
  `@return` Collection<int, MensajeParticipante>
- `function alertaSeleccionada(): ?Alerta`
  Alerta seleccionada actualmente en la pestaña Alertas/Avisos.
  `@return` Alerta|null
- `function hiloSeleccionado(): ?MensajeParticipante`
  Participación seleccionada en la pestaña Mensajes.
  `@return` MensajeParticipante|null
- `function cambiarPestana(string $pestana): void`
  Cambia la pestaña activa y deselecciona el ítem actual.
  `@return` void
- `function seleccionar(int $id): void`
  Selecciona un ítem de la lista.
  `@return` void
- `function reconocerAlerta(int $alertaId): void`
  Reconoce una alerta del usuario autenticado. Actualiza su estado a 'reconocida' y la retira del listado.
  `@return` void
- `function enviarRespuesta(int $hiloId, MensajeriaService $mensajeriaService): void`
  Envía una respuesta al hilo seleccionado.
  `@return` void
- `function mount(string $asunto = ''): void`
  Inicializa el componente. Si se recibe un asunto por URL, abre el modal con el asunto pre-rellenado.
  `@return` void
- `function abrirModalNuevoMensaje(): void`
  Abre el modal de redaccion de nuevo mensaje.
  `@return` void
- `function buscarDestinatario(): void`
  Busca usuarios por nombre para seleccionar como destinatario. Filtra por coincidencia ILIKE sobre nombre + apellidos del profesional.
  `@return` void
- `function seleccionarDestinatario(int $id, string $nombre): void`
  Selecciona un destinatario de los resultados de busqueda.
  `@return` void
- `function enviarMensaje(): void`
  Valida y envia el nuevo mensaje, creando el hilo y el primer mensaje. Despues de enviar, cierra el modal y navega a la pestana de mensajes.
  `@return` void
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Jobs\EscalarAlertasVencidasJob`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Jobs/EscalarAlertasVencidasJob.php:24`.
- Resumen: Job que detecta alertas con el plazo de reconocimiento vencido y ejecuta la escalada al supervisor de la UO correspondiente.

Se ejecuta cada 15 minutos via el scheduler (configurado en MensajesServiceProvider).  Requiere que el queue worker esté en ejecución: php artisan queue:work

Metodos publicos:

- `function handle(AlertaService $alertaService): void`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Livewire\BadgeNotificaciones`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Livewire/BadgeNotificaciones.php:22`.
- Resumen: Badge embebible en la barra de navegación que muestra el recuento de alertas pendientes y mensajes no leídos del usuario autenticado.

Se actualiza cada 60 segundos mediante wire:poll.  // TODO mejora futura: reemplazar polling por Laravel Echo + broadcasting para reducir carga con muchos usuarios concurrentes.

Metodos publicos:

- `function totalAlertas(): int`
  _Sin resumen PHPDoc._
- `function totalMensajes(): int`
  _Sin resumen PHPDoc._
- `function total(): int`
  _Sin resumen PHPDoc._
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Livewire\BandejaAlertas`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Livewire/BandejaAlertas.php:22`.
- Resumen: Bandeja de alertas del profesional autenticado.

Muestra alertas pendientes ordenadas por prioridad: primero alertas (requieren reconocimiento), luego avisos. Dentro de cada grupo, ordenadas por expiración ascendente.

Metodos publicos:

- `function mount(): void`
  _Sin resumen PHPDoc._
- `function alertas(): Collection`
  _Sin resumen PHPDoc._
- `function confirmarReconocimiento(int $alertaId): void`
  Solicita confirmación antes de reconocer una alerta.
  `@return` void
- `function reconocer(AlertaService $alertaService): void`
  Reconoce (o descarta) la alerta confirmada.
  `@return` void
- `function cancelarReconocimiento(): void`
  Cancela el diálogo de confirmación.
  `@return` void
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Livewire\BandejaMensajes`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Livewire/BandejaMensajes.php:18`.
- Resumen: Bandeja de mensajes del profesional autenticado.

Muestra la lista de hilos activos (no archivados) con indicador de mensajes no leídos. Al seleccionar un hilo carga HiloMensajes.

Metodos publicos:

- `function mount(): void`
  _Sin resumen PHPDoc._
- `function hilos(): Collection`
  _Sin resumen PHPDoc._
- `function abrirHilo(int $hiloId): void`
  _Sin resumen PHPDoc._
- `function archivarHilo(int $hiloId, MensajeriaService $mensajeriaService): void`
  _Sin resumen PHPDoc._
- `function nuevaMensaje(): void`
  _Sin resumen PHPDoc._
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Livewire\HiloMensajes`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Livewire/HiloMensajes.php:25`.
- Resumen: Vista detalle de un hilo de mensajes.

Muestra los mensajes en orden cronológico y permite: - Responder al hilo - Adjuntar archivos - Registrar un mensaje en la Historia Social (solo si el usuario es TSR del ciudadano referenciado)

Metodos publicos:

- `function mount(int $hiloId): void`
  _Sin resumen PHPDoc._
- `function hilo(): MensajeHilo`
  _Sin resumen PHPDoc._
- `function enviarRespuesta(MensajeriaService $mensajeriaService): void`
  _Sin resumen PHPDoc._
- `function abrirModalHistoria(int $mensajeId, int $ciudadanoId): void`
  Abre el modal para registrar un mensaje en la Historia Social.
  `@return` void
- `function cerrarModalHistoria(): void`
  _Sin resumen PHPDoc._
- `function confirmarRegistroHistoria(MensajeriaService $mensajeriaService): void`
  Confirma el registro del mensaje en la Historia Social.
  `@return` void
- `function esTsrDeCiudadano(int $ciudadanoId): bool`
  Comprueba si el usuario autenticado es TSR responsable del ciudadano.
  `@return` bool
- `function opcionesVisibilidad(): array`
  Opciones de visibilidad disponibles.
  `@return` array<string, string>
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Livewire\NuevoMensaje`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Livewire/NuevoMensaje.php:20`.
- Resumen: Formulario de redacción de un mensaje nuevo.

Permite buscar el destinatario por nombre o filtrar por rol y UO. También permite referenciar ciudadanos y adjuntar archivos.

Metodos publicos:

- `function mount(): void`
  _Sin resumen PHPDoc._
- `function resultadosDestinatario(): Collection`
  Resultados de búsqueda de destinatarios por nombre.
  `@return` Collection<int, User>
- `function seleccionarDestinatario(int $usuarioId): void`
  _Sin resumen PHPDoc._
- `function limpiarDestinatario(): void`
  _Sin resumen PHPDoc._
- `function resultadosCiudadano(): Collection`
  Resultados de búsqueda de ciudadanos para referenciar.
  `@return` Collection<int, Ciudadano>
- `function agregarCiudadano(int $ciudadanoId): void`
  _Sin resumen PHPDoc._
- `function quitarCiudadano(int $ciudadanoId): void`
  _Sin resumen PHPDoc._
- `function enviar(MensajeriaService $mensajeriaService): void`
  _Sin resumen PHPDoc._
- `function render(): View`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Models\Alerta`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Models/Alerta.php:37`.
- Resumen: Alerta del sistema.

Metodos publicos:

- `function reconocimientos(): HasMany`
  Reconocimientos registrados para esta alerta.
  `@return` HasMany<AlertaReconocimiento, self>
- `function destinatarioUsuario(): BelongsTo`
  Usuario al que va dirigida la alerta (cuando el destinatario es un usuario concreto).
  `@return` BelongsTo<User, self>
- `function destinatarioUo(): BelongsTo`
  Unidad organizativa destinataria (cuando el destinatario es un rol+UO).
  `@return` BelongsTo<UnidadOrganizativa, self>
- `function escaladaA(): BelongsTo`
  Usuario al que fue escalada la alerta tras vencer el plazo de reconocimiento.
  `@return` BelongsTo<User, self>
- `function origen(): MorphTo`
  Entidad que originó la alerta (relación polimórfica).
  `@return` MorphTo<Model, self>
- `function scopePendientes(Builder $query): Builder`
  Filtra alertas en estado pendiente.
  `@return` Builder<static>
- `function scopeVencidas(Builder $query): Builder`
  Alertas de tipo 'alerta' con el plazo de reconocimiento vencido.
  `@return` Builder<static>

### `Modules\Mensajes\Models\AlertaReconocimiento`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Models/AlertaReconocimiento.php:21`.
- Resumen: Reconocimiento individual de una alerta por un usuario.

Metodos publicos:

- `function alerta(): BelongsTo`
  Alerta a la que pertenece este reconocimiento.
  `@return` BelongsTo<Alerta, self>
- `function usuario(): BelongsTo`
  Usuario que realizó el reconocimiento.
  `@return` BelongsTo<User, self>

### `Modules\Mensajes\Models\Mensaje`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Models/Mensaje.php:27`.
- Resumen: Mensaje individual dentro de un hilo de mensajería interna.

Los adjuntos se gestionan a través de spatie/laravel-medialibrary en la colección 'adjuntos_mensaje'.

Metodos publicos:

- `function registerMediaCollections(): void`
  Registra la colección de adjuntos del mensaje en disco local.
  `@return` void
- `function registerMediaConversions(?Media $media = null): void`
  Sin conversiones de imagen para documentos adjuntos.
  `@return` void
- `function hilo(): BelongsTo`
  Hilo de conversación al que pertenece el mensaje.
  `@return` BelongsTo<MensajeHilo, self>
- `function remitente(): BelongsTo`
  Usuario que envió el mensaje.
  `@return` BelongsTo<User, self>
- `function referenciasCiudadano(): HasMany`
  Referencias a ciudadanos mencionados en el mensaje.
  `@return` HasMany<MensajeReferenciaCiudadano, self>
- `function registrosHistoria(): HasMany`
  Registros del mensaje incorporados a Historias Sociales de ciudadanos.
  `@return` HasMany<MensajeRegistroHistoria, self>

### `Modules\Mensajes\Models\MensajeHilo`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Models/MensajeHilo.php:21`.
- Resumen: Hilo de conversación entre dos profesionales.

Metodos publicos:

- `function participantes(): HasMany`
  Participantes del hilo de conversación.
  `@return` HasMany<MensajeParticipante, self>
- `function mensajes(): HasMany`
  Mensajes del hilo ordenados cronológicamente.
  `@return` HasMany<Mensaje, self>
- `function creadoPor(): BelongsTo`
  Usuario que creó el hilo de conversación.
  `@return` BelongsTo<User, self>
- `function ultimoMensaje(): HasOne`
  _Sin resumen PHPDoc._
  `@return` HasOne<Mensaje>
- `function tieneParticipante(int $usuarioId): bool`
  Comprueba si un usuario es participante activo del hilo.
  `@return` bool

### `Modules\Mensajes\Models\MensajeParticipante`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Models/MensajeParticipante.php:21`.
- Resumen: Participante de un hilo de mensajes.

Metodos publicos:

- `function hilo(): BelongsTo`
  Hilo de mensajes al que pertenece esta participación.
  `@return` BelongsTo<MensajeHilo, self>
- `function usuario(): BelongsTo`
  Usuario participante del hilo.
  `@return` BelongsTo<User, self>
- `function mensajesNoLeidos(): int`
  Número de mensajes del hilo que el participante aún no ha leído.
  `@return` int

### `Modules\Mensajes\Models\MensajeReferenciaCiudadano`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Models/MensajeReferenciaCiudadano.php:21`.
- Resumen: Referencia informativa entre un mensaje y un ciudadano.

Permite navegar desde el mensaje al expediente del ciudadano. No implica registro en la Historia Social.

Metodos publicos:

- `function mensaje(): BelongsTo`
  Mensaje que origina esta referencia.
  `@return` BelongsTo<Mensaje, self>
- `function ciudadano(): BelongsTo`
  Ciudadano referenciado en el mensaje.
  `@return` BelongsTo<Ciudadano, self>

### `Modules\Mensajes\Models\MensajeRegistroHistoria`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Models/MensajeRegistroHistoria.php:29`.
- Resumen: Registro de un mensaje en la Historia Social de un ciudadano.

Materializa la decisión explícita del TSR de incorporar un mensaje (o una versión editada) al expediente del ciudadano. Solo el TSR responsable del expediente puede crear estos registros.

Metodos publicos:

- `function mensaje(): BelongsTo`
  Mensaje original cuyo contenido se incorporó al expediente.
  `@return` BelongsTo<Mensaje, self>
- `function ciudadano(): BelongsTo`
  Ciudadano cuyo expediente recibió el registro.
  `@return` BelongsTo<Ciudadano, self>
- `function registradoPor(): BelongsTo`
  Profesional que incorporó el mensaje al expediente.
  `@return` BelongsTo<User, self>

### `Modules\Mensajes\Providers\MensajesServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Providers/MensajesServiceProvider.php:25`.
- Resumen: Provider del módulo Mensajes.

Registra las migraciones, servicios, componentes Livewire y el scheduler del job de escalada de alertas vencidas.

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Mensajes\Services\AlertaService`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Services/AlertaService.php:20`.
- Resumen: Servicio de gestión del ciclo de vida de alertas del sistema.

Metodos publicos:

- `function __construct( private readonly HorarioLaboralService $horarioLaboral )`
  _Sin resumen PHPDoc._
- `function crear(array $datos): Alerta`
  Crea una alerta y calcula su expiración si es de tipo 'alerta'.
  `@return` Alerta
- `function reconocer(Alerta $alerta, User $usuario, string $ipAddress): AlertaReconocimiento`
  Marca una alerta como reconocida por un usuario.
  `@return` AlertaReconocimiento
- `function escalar(Alerta $alerta): void`
  Ejecuta la escalada de una alerta vencida al supervisor de la UO.
  `@return` void
- `function resolverDestinatarios(Alerta $alerta): Collection`
  Resuelve qué usuarios son destinatarios reales de una alerta rol_uo.
  `@return` Collection<int, User>

### `Modules\Mensajes\Services\HorarioLaboralService`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Services/HorarioLaboralService.php:26`.
- Resumen: Servicio para calcular vencimientos de alertas en horas laborales.

Lee el horario laboral por defecto desde catalogos_sistema con la clave 'horario_laboral_defecto'. El valor esperado es un JSON:  { "inicio": "08:00", "fin": "17:00", "dias_semana": [1, 2, 3, 4, 5]   // 1=lunes, 5=viernes }  El algoritmo avanza minuto a minuto contando solo los instantes que caen dentro del horario laboral configurado.  // TODO: integrar con módulo Agenda para usar el calendario laboral oficial cuando esté disponible.

Metodos publicos:

- `function __construct()`
  _Sin resumen PHPDoc._
- `function calcularExpiracion(Carbon $desde): Carbon`
  Calcula el timestamp de vencimiento sumando HORAS_PLAZO horas laborales efectivas a $desde.
  `@return` Carbon

### `Modules\Mensajes\Services\MensajeriaService`

- Tipo: class.
- Fichero: `vida/Modules/Mensajes/app/Services/MensajeriaService.php:21`.
- Resumen: Servicio de gestión de la mensajería interna entre profesionales.

Metodos publicos:

- `function crearHilo( User $remitente, User $destinatario, string $asunto, string $cuerpo, array $ciudadanoIds = [], array $adjuntos = [] ): MensajeHilo`
  Crea un hilo nuevo y envía el primer mensaje.
  `@return` MensajeHilo
- `function responder( MensajeHilo $hilo, User $remitente, string $cuerpo, array $adjuntos = [] ): Mensaje`
  Añade un mensaje de respuesta a un hilo existente.
  `@return` Mensaje
- `function registrarEnHistoria( Mensaje $mensaje, Ciudadano $ciudadano, User $tsr, string $cuerpoEditado, string $visibilidad = 'profesionales' ): MensajeRegistroHistoria`
  Registra un mensaje en la Historia Social de un ciudadano.
  `@return` MensajeRegistroHistoria
- `function marcarComoLeido(MensajeHilo $hilo, User $usuario): void`
  Marca todos los mensajes del hilo como leídos para un usuario.
  `@return` void

### `Modules\Organizacion\Models\ColectivoProtegido`

- Tipo: class.
- Fichero: `vida/Modules/Organizacion/app/Models/ColectivoProtegido.php:26`.
- Resumen: Modelo de Colectivo Especialmente Protegido.

Define los colectivos cuyos ciudadanos requieren aprobación previa para el acceso desde fuera de la UO responsable (principio 3.6). Actualmente: menores y víctimas de violencia de género.

Metodos publicos:

- `function scopeActivos(Builder $consulta): Builder`
  Filtra únicamente los colectivos activos.
  `@return` Builder<ColectivoProtegido>
- `function scopeRequierenAprobacion(Builder $consulta): Builder`
  Filtra colectivos que requieren aprobación previa de acceso.
  `@return` Builder<ColectivoProtegido>

### `Modules\Organizacion\Models\Configuracion`

- Tipo: class.
- Fichero: `vida/Modules/Organizacion/app/Models/Configuracion.php:27`.
- Resumen: Modelo de configuración general de la organización.

Almacena pares clave-valor configurables desde el backoffice. El tipo de dato del valor determina cómo se castea al leerlo.

Metodos publicos:

- `function valorCasteado(): mixed`
  Devuelve el valor casteado según el tipo declarado.
- `function scopeTipo(Builder $consulta, string $tipo): Builder`
  Filtra por tipo de configuración.
  `@return` Builder<Configuracion>
- `function logoUrl(): ?string`
  URL pública del logotipo de la aplicación. Lee la clave «logo_path» del almacén de configuración.
  `@return` string|null URL completa o null si no hay logotipo configurado.
- `function nombreAplicacion(): ?string`
  Nombre personalizado de la aplicación. Lee la clave «nombre_aplicacion» del almacén de configuración.
  `@return` string|null Nombre configurado o null si no se ha establecido.

### `Modules\Organizacion\Models\Distrito`

- Tipo: class.
- Fichero: `vida/Modules/Organizacion/app/Models/Distrito.php:29`.
- Resumen: Modelo de Distrito municipal.

División territorial del municipio. Los 21 distritos de Madrid son los valores iniciales, adaptables a otros municipios. Un ciudadano tiene asignado un centro de servicios sociales según su distrito de residencia.

Metodos publicos:

- `function zonas(): HasMany`
  Zonas pertenecientes a este distrito.
  `@return` HasMany<Zona>
- `function scopeActivos(Builder $consulta): Builder`
  Filtra únicamente los distritos activos.
  `@return` Builder<Distrito>

### `Modules\Organizacion\Models\ServicioEmergenciaPreautorizado`

- Tipo: class.
- Fichero: `vida/Modules/Organizacion/app/Models/ServicioEmergenciaPreautorizado.php:27`.
- Resumen: Modelo de Servicio de Emergencia Preautorizado.

Los servicios listados aquí tienen acceso preautorizado en modo consulta a Historias de ciudadanos especialmente protegidos, sin necesidad de aprobación previa (principio 3.6, excepción urgencia). El acceso queda registrado en auditoría. Por defecto: SAMUR Social.

Metodos publicos:

- `function scopeActivos(Builder $consulta): Builder`
  Filtra únicamente los servicios activos.
  `@return` Builder<ServicioEmergenciaPreautorizado>

### `Modules\Organizacion\Models\Zona`

- Tipo: class.
- Fichero: `vida/Modules/Organizacion/app/Models/Zona.php:26`.
- Resumen: Modelo de Zona (subdivisión del distrito).

Agrupación configurable de unidades censales dentro de un distrito. Permite distribuir la carga de trabajo entre profesionales del mismo centro.

Metodos publicos:

- `function distrito(): BelongsTo`
  Distrito al que pertenece esta zona.
  `@return` BelongsTo<Distrito, Zona>
- `function scopeActivas(Builder $consulta): Builder`
  Filtra únicamente las zonas activas.
  `@return` Builder<Zona>

### `Modules\Organizacion\Providers\OrganizacionServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Organizacion/app/Providers/OrganizacionServiceProvider.php:14`.
- Resumen: Provider del módulo Organizacion.

Registra el ConfiguracionService como singleton para que la caché sea compartida en toda la petición.

Metodos publicos:

- `function register(): void`
  Registra los servicios del módulo en el contenedor.
- `function boot(): void`
  Arranca los servicios del módulo.

### `Modules\Organizacion\Services\ConfiguracionService`

- Tipo: class.
- Fichero: `vida/Modules/Organizacion/app/Services/ConfiguracionService.php:21`.
- Resumen: Servicio de configuración general de la organización.

Proporciona acceso a los parámetros de configuración almacenados en la tabla organizacion_configuracion, con caché para evitar consultas a BD en cada petición.  Se registra como singleton en el ServiceProvider del módulo.  Uso: app(ConfiguracionService::class)->get('nombre_organizacion'); app(ConfiguracionService::class)->set('nombre_organizacion', 'Ayuntamiento de Madrid');

Metodos publicos:

- `function get(string $clave, mixed $defecto = null): mixed`
  Obtiene el valor de una clave de configuración.
- `function set(string $clave, mixed $valor): void`
  Establece el valor de una clave de configuración.
- `function limpiarCache(): void`
  Invalida la caché de configuración.

### `Modules\Prestaciones\Models\Prestacion`

- Tipo: class.
- Fichero: `vida/Modules/Prestaciones/app/Models/Prestacion.php:55`.
- Resumen: Prestación del catálogo oficial de servicios sociales municipales.

Fuente de verdad sobre qué prestaciones existen, sus condiciones y cómo se accede a ellas. Es un módulo de catálogo de solo lectura para el resto del sistema; el mantenimiento se realiza exclusivamente desde Filament.  Los campos clasificatorios (objetivo_general, categoria_especifica, etc.) son claves de catalogos_sistema, nunca FKs (principio 3.10).  Los únicos enums son tipo_prestacion y nivel_garantia porque el código toma decisiones de lógica de negocio basándose en ellos (principio 3.10).

Metodos publicos:

- `function tiposCentro(): HasMany`
  Tipos de centro vinculados a esta prestación.
  `@return` HasMany<PrestacionTipoCentro, self>
- `function scopeActivas(Builder $query): Builder`
  Filtra prestaciones activas.
  `@return` Builder<static>
- `function scopeDeServicio(Builder $query): Builder`
  Filtra prestaciones de tipo servicio (no económicas).
  `@return` Builder<static>
- `function scopeEconomicas(Builder $query): Builder`
  Filtra prestaciones de tipo económico.
  `@return` Builder<static>

### `Modules\Prestaciones\Models\PrestacionTipoCentro`

- Tipo: class.
- Fichero: `vida/Modules/Prestaciones/app/Models/PrestacionTipoCentro.php:15`.
- Resumen: Relación entre una prestación y un tipo de centro que puede ofrecerla.

`tipo_centro` es una clave de catalogos_sistema (grupo: 'centro.tipo'), no una FK a la tabla centros. Registra qué TIPOS de centro pueden prestar cada prestación según las fichas del catálogo oficial.

Metodos publicos:

- `function prestacion(): BelongsTo`
  Prestación a la que pertenece este tipo de centro.
  `@return` BelongsTo<Prestacion, self>

### `Modules\Prestaciones\Providers\PrestacionesServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Prestaciones/app/Providers/PrestacionesServiceProvider.php:13`.
- Resumen: Provider del módulo Prestaciones.

Las migraciones del módulo residen en database/migrations/ (carpeta principal) por convención del proyecto, por lo que no se cargan desde aquí.

Metodos publicos:

- `function register(): void`
  _Sin resumen PHPDoc._
- `function boot(): void`
  _Sin resumen PHPDoc._

### `Modules\Usuarios\Console\Commands\ReconciliarRoles`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Console/Commands/ReconciliarRoles.php:22`.
- Resumen: Comando de reconciliación de roles.

Sincroniza model_has_roles de Spatie con el estado actual de usuario_rol. Debe ejecutarse al arrancar el sistema por primera vez y tras migraciones.  Paso 1: para cada UsuarioRol vigente, garantiza que el rol está en model_has_roles. Paso 2: elimina de model_has_roles cualquier rol que no tenga un UsuarioRol vigente.

Metodos publicos:

- `function handle(): int`
  Ejecuta el comando.

### `Modules\Usuarios\Models\Cargo`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Models/Cargo.php:25`.
- Resumen: Catálogo de cargos profesionales.

Configurable desde el backoffice por adm_sistema. Ejemplos: Trabajador/a Social, Psicólogo/a, Educador/a Social.

Metodos publicos:

- `function profesionales(): HasMany`
  Profesionales con este cargo.
  `@return` HasMany<Profesional>
- `function scopeActivos(Builder $consulta): Builder`
  Solo cargos activos.
  `@return` Builder<Cargo>

### `Modules\Usuarios\Models\ConfiguracionRol`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Models/ConfiguracionRol.php:31`.
- Resumen: Configuración adicional de un rol de Spatie.

Extiende los roles de Spatie sin modificar sus tablas. Actualmente almacena el nivel de supervisión requerido para que la asignación de ese rol sea efectiva.  nivel_supervision: - aprobacion_previa: la asignación no es efectiva hasta que el supervisor la aprueba explícitamente (adm_sistema, supervision). - alerta_supervisada: la asignación es inmediata, genera alerta que el supervisor debe reconocer (resto de roles).

Metodos publicos:

- `function rol(): BelongsTo`
  Rol de Spatie al que corresponde esta configuración.
  `@return` BelongsTo<Role, ConfiguracionRol>

### `Modules\Usuarios\Models\Profesional`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Models/Profesional.php:51`.
- Resumen: Perfil organizativo de una persona en el sistema.

Describe a una persona en su dimensión laboral: cargo, titulación, tipo de vínculo contractual y datos de contacto profesional. No es la cuenta del sistema (User) ni el perfil de autorización.  Profesional es la entidad raíz: existe con independencia de si tiene cuenta de acceso. Un User casi siempre tiene un Profesional detrás, salvo perfiles estrictamente técnicos (adm_sistema).  Sujeta a versionado (trait Versionable): sus datos cambian a lo largo de la vida laboral y el sistema debe poder conocer el estado en cualquier fecha pasada.

Metodos publicos:

- `function cargo(): BelongsTo`
  Cargo que ocupa este profesional.
  `@return` BelongsTo<Cargo, Profesional>
- `function titulacion(): BelongsTo`
  Titulación académica del profesional.
  `@return` BelongsTo<Titulacion, Profesional>
- `function tipoRelacion(): BelongsTo`
  Tipo de relación contractual con la organización.
  `@return` BelongsTo<TipoRelacionProfesional, Profesional>
- `function usuario(): HasOne`
  Cuenta de acceso al sistema vinculada a este profesional, si existe.
  `@return` HasOne<User>
- `function getNombreCompletoAttribute(): string`
  Nombre completo: nombre + apellido1 [+ apellido2].
- `function scopeActivos(Builder $consulta): Builder`
  Solo profesionales activos.
  `@return` Builder<Profesional>

### `Modules\Usuarios\Models\TipoRelacionProfesional`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Models/TipoRelacionProfesional.php:29`.
- Resumen: Catálogo de tipos de relación profesional con la organización.

Configurable desde el backoffice por adm_sistema. Ejemplos: funcionario/a de carrera, personal interino/a, contratado/a laboral, empresa externa, voluntario/a.  El campo `es_externo` determina si el campo `organizacion` en Profesional es relevante para el registro.

Metodos publicos:

- `function profesionales(): HasMany`
  Profesionales con este tipo de relación.
  `@return` HasMany<Profesional>
- `function scopeActivos(Builder $consulta): Builder`
  Solo tipos de relación activos.
  `@return` Builder<TipoRelacionProfesional>

### `Modules\Usuarios\Models\Titulacion`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Models/Titulacion.php:24`.
- Resumen: Catálogo de titulaciones académicas.

Configurable desde el backoffice por adm_sistema. Ejemplos: Grado en Trabajo Social, Grado en Psicología.

Metodos publicos:

- `function profesionales(): HasMany`
  Profesionales con esta titulación.
  `@return` HasMany<Profesional>
- `function scopeActivas(Builder $consulta): Builder`
  Solo titulaciones activas.
  `@return` Builder<Titulacion>

### `Modules\Usuarios\Models\UsuarioRol`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Models/UsuarioRol.php:41`.
- Resumen: Historial de roles de un usuario.

Es la FUENTE DE VERDAD para la asignación de roles. model_has_roles de Spatie es el estado derivado activo, optimizado para la evaluación rápida de can() en cada request.  El campo `estado` gestiona el flujo de aprobación previa: - pendiente_aprobacion: asignación solicitada, aún no activa en Spatie. - activo: vigente, sincronizado en model_has_roles. - inactivo: revocado, eliminado de model_has_roles.  El Observer mantiene model_has_roles sincronizado con los cambios en esta tabla.

Metodos publicos:

- `function usuario(): BelongsTo`
  Usuario al que corresponde este historial de rol.
  `@return` BelongsTo<User, UsuarioRol>
- `function rol(): BelongsTo`
  Rol de Spatie asignado.
  `@return` BelongsTo<Role, UsuarioRol>
- `function asignadoPor(): BelongsTo`
  Usuario que realizó la asignación.
  `@return` BelongsTo<User, UsuarioRol>
- `function scopeVigentes(Builder $consulta): Builder`
  Registros de rol vigentes (activos con fecha válida).
  `@return` Builder<UsuarioRol>
- `function scopePendientes(Builder $consulta): Builder`
  Registros pendientes de aprobación.
  `@return` Builder<UsuarioRol>

### `Modules\Usuarios\Observers\UsuarioRolObserver`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Observers/UsuarioRolObserver.php:25`.
- Resumen: Observer de UsuarioRol.

Mantiene sincronizados el historial VIDA (tabla usuario_rol) y la tabla model_has_roles de Spatie. Spatie es la fuente de verdad para los roles ACTIVOS; usuario_rol es la fuente de verdad para el HISTORIAL.  Reglas de sincronización: - Al crear un UsuarioRol con estado=activo y fecha vigente → assignRole. - Al aprobar una asignación (estado: pendiente_aprobacion → activo) → assignRole. - Al revocar (estado → inactivo) o caducar (fecha_fin en el pasado) → removeRole si no hay otro UsuarioRol vigente para ese rol en ese usuario.

Metodos publicos:

- `function created(UsuarioRol $usuarioRol): void`
  Sincroniza Spatie al crear un nuevo registro de rol.
- `function updated(UsuarioRol $usuarioRol): void`
  Sincroniza Spatie al actualizar un registro de rol.

### `Modules\Usuarios\Policies\ApuntePolicy`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Policies/ApuntePolicy.php:29`.
- Resumen: Policy de autorización para el Apunte (acto profesional).

Implementa los tres pasos estándar más la regla especial de anotaciones privadas (docs/modulo-usuarios-permisos.md § 4.7):  Regla absoluta: si apunte.privada === true, SOLO el autor tiene acceso. Sin excepción de rol ni jerarquía. Esta regla tiene precedencia total.  Para apuntes no privados, se aplica el modelo de tres pasos: 1. ¿Tiene el permiso atómico requerido? 2. ¿Está en el ámbito de UO del apunte? (vía HistoriaSocial) 3. Si UO diferente: ¿el ciudadano es colectivo protegido?  El rol supervision solo puede leer apuntes no privados de su ámbito de UO. El rol tramitacion no tiene acceso a apuntes.

Metodos publicos:

- `function viewAny(User $usuario): bool`
  Decide si el usuario puede listar apuntes.
- `function view(User $usuario, Apunte $apunte): bool`
  Decide si el usuario puede ver el apunte.
- `function create(User $usuario): bool`
  Decide si el usuario puede crear un nuevo apunte.
- `function update(User $usuario, Apunte $apunte): bool`
  Decide si el usuario puede editar el apunte.
- `function delete(User $usuario, Apunte $apunte): bool`
  Decide si el usuario puede eliminar el apunte.

### `Modules\Usuarios\Policies\HistoriaSocialPolicy`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Policies/HistoriaSocialPolicy.php:34`.
- Resumen: Policy de autorización para la Historia Social.

Implementa los tres niveles de acceso definidos en docs/modulo-usuarios-permisos.md sección 1.4:  Nivel 1 — Gestión completa: el profesional pertenece a la UO de la Historia o a una UO descendiente. Tiene acceso completo según su permiso. Nivel 2 — Consulta libre: cualquier profesional con permiso historia.leer puede consultar Historias fuera de su UO (solo lectura). Nivel 3 — Consulta con aprobación: ciudadanos de colectivos especialmente protegidos requieren aprobación previa del supervisor competente.  La evaluación sigue siempre el mismo orden de tres pasos: 1. ¿Tiene el permiso atómico requerido? 2. ¿Pertenece al ámbito de UO del recurso? (Nivel 1 vs Nivel 2) 3. ¿El ciudadano es colectivo protegido? (solo si Nivel 2)  Caso especial — adm_sistema: NO tiene acceso privilegiado sobre datos de ciudadanos; también debe pasar los filtros de UO y colectivo protegido. Caso especial — supervision: solo lectura en su ámbito, nunca escritura.

Metodos publicos:

- `function viewAny(User $usuario): bool`
  Decide si el usuario puede listar Historias Sociales.
- `function view(User $usuario, HistoriaSocial $historia): bool`
  Decide si el usuario puede consultar la Historia Social.
- `function create(User $usuario): bool`
  Decide si el usuario puede crear una Historia Social.
- `function update(User $usuario, HistoriaSocial $historia): bool`
  Decide si el usuario puede editar la Historia Social.
- `function delete(User $usuario, HistoriaSocial $historia): bool`
  Decide si el usuario puede eliminar (baja lógica) la Historia Social.

### `Modules\Usuarios\Providers\UsuariosServiceProvider`

- Tipo: class.
- Fichero: `vida/Modules/Usuarios/app/Providers/UsuariosServiceProvider.php:20`.
- Resumen: Provider del módulo Usuarios.

Registra las Policies de autorización y carga las migraciones del módulo.

Metodos publicos:

- `function register(): void`
  Registra servicios en el contenedor.
- `function boot(): void`
  Arranca los servicios del módulo y registra las Policies.

### `Modules\Usuarios\Traits\TieneRoles`

- Tipo: trait.
- Fichero: `vida/Modules/Usuarios/app/Traits/TieneRoles.php:18`.
- Resumen: Trait que añade al User la lógica de historial de roles.

Los roles activos se consultan a través de Spatie (HasRoles). Este trait añade la capacidad de consultar el HISTORIAL de roles y provee métodos de conveniencia que unifican ambas fuentes.

Metodos publicos:

- `function historialRoles(): HasMany`
  Todos los registros de historial de roles del usuario.
  `@return` HasMany<UsuarioRol>
- `function rolesVigentes(): HasMany`
  Únicamente los registros de rol vigentes (activos con fecha válida).
  `@return` HasMany<UsuarioRol>
- `function rolesPendientes(): HasMany`
  Registros de rol pendientes de aprobación.
  `@return` HasMany<UsuarioRol>
- `function tieneRolVigente(string $rolNombre): bool`
  Comprueba si el usuario tiene activo el rol indicado según el historial de VIDA (no solo Spatie).
- `function tienePermiso(string $permiso): bool`
  Comprueba si el usuario tiene el permiso indicado a través de alguno de sus roles vigentes en Spatie.

### `Modules\Usuarios\Traits\TieneUO`

- Tipo: trait.
- Fichero: `vida/Modules/Usuarios/app/Traits/TieneUO.php:19`.
- Resumen: Trait que añade al User la lógica de adscripción a Unidades Organizativas.

Una UO determina el ÁMBITO de datos sobre el que el usuario puede ejercer sus permisos: gestión completa en su UO, consulta libre fuera.

Metodos publicos:

- `function adscripciones(): HasMany`
  Todas las adscripciones a UO del usuario (históricas y vigentes).
  `@return` HasMany<UsuarioUo>
- `function adscripcionesVigentes(): HasMany`
  Únicamente las adscripciones vigentes (fecha_fin null o futura).
  `@return` HasMany<UsuarioUo>
- `function unidadesOrganizativas(): BelongsToMany`
  Unidades Organizativas a las que el usuario está adscrito (historial completo). Relación muchos-a-muchos a través de la tabla usuario_uo.
  `@return` BelongsToMany<UnidadOrganizativa>
- `function uosActivas(): Collection`
  Devuelve la colección de Unidades Organizativas activas a las que el usuario está actualmente adscrito.
  `@return` Collection<int, UnidadOrganizativa>
- `function perteneceAUo(UnidadOrganizativa $uo): bool`
  Indica si el usuario pertenece exactamente a la UO indicada (sin considerar la jerarquía).
- `function tieneAccesoGestionA(UnidadOrganizativa $uo): bool`
  Indica si el usuario tiene acceso de gestión sobre la UO indicada (su propia UO o una UO descendiente que gestiona).
- `function uoSubtreeIds(): array`
  Devuelve los IDs de todas las UOs gestionadas por el usuario: las suyas propias y todas sus descendientes. Útil para filtrar queries de backoffice por ámbito.
  `@return` array<int>
- `function tieneAccesoConsultaA(UnidadOrganizativa $uo): bool`
  Indica si el usuario puede acceder en consulta libre a la UO indicada.

### `App\Console\Commands\AuditPurgeCommand`

- Tipo: class.
- Fichero: `vida/app/Console/Commands/AuditPurgeCommand.php:20`.
- Resumen: Purga los registros de auditoría que superan el período de retención.

Es la única operación de DELETE legítima sobre la tabla `audits`. El período de retención se lee del catálogo de sistema con clave 'auditoria.retencion_dias'; si no existe, el defecto es 1825 (5 años).  Se ejecuta diariamente vía el scheduler de Laravel.

Metodos publicos:

- `function handle(): int`
  _Sin resumen PHPDoc._

### `App\Console\Commands\DemoResetCommand`

- Tipo: class.
- Fichero: `vida/app/Console/Commands/DemoResetCommand.php:31`.
- Resumen: Comando de reset de entorno de demo.

Destruye todos los datos de ciudadanos, historias, planes, entrevistas y seguimientos, y reconstruye el mundo desde el fichero YAML indicado.  NUNCA ejecutar en producción — el comando lo verifica y aborta.  Flujo: 1. Verificar entorno (no producción) 2. Confirmar si staging 3. Cargar y validar YAML 4. Mostrar resumen 5. TRUNCATE tablas en cascada 6. Construir mundo (UOs + profesionales) 7. Construir escenarios (ciudadanos + trayectorias) 8. Verificar invariantes 9. Commit o rollback

Metodos publicos:

- `function handle(): int`
  Ejecuta el comando de reset del mundo demo.
  `@return` int Código de salida (0 = éxito, 1 = error)

### `App\Console\Commands\DemoValidateCommand`

- Tipo: class.
- Fichero: `vida/app/Console/Commands/DemoValidateCommand.php:16`.
- Resumen: Comando de validación de mundos YAML para entornos de demo.

Valida la estructura y semántica del fichero YAML sin tocar la base de datos. Útil para verificar mundos antes de aplicarlos, o en CI/CD.  Devuelve código 0 si el YAML es válido, código 1 si hay errores de validación.

Metodos publicos:

- `function handle(): int`
  Ejecuta la validación del mundo YAML.
  `@return` int Código de salida (0 = válido, 1 = inválido)

### `App\Console\Commands\NormalizarDirecciones`

- Tipo: class.
- Fichero: `vida/app/Console/Commands/NormalizarDirecciones.php:23`.
- Resumen: Comando artisan para normalización masiva de direcciones pendientes.

Procesa en batches de 100 registros con throttling de 1 segundo entre batches para no saturar el proveedor de geocoding ni el sistema.  Uso: php artisan vida:normalizar-direcciones --entidad=ciudadano --pendientes php artisan vida:normalizar-direcciones --entidad=centro  Ver docs/geocodificacion.md § 4.3.

Metodos publicos:

- `function handle(GeocodificadorInterface $geocodificador): int`
  Ejecuta el comando.

### `App\Enums\AccionAuditEnum`

- Tipo: enum.
- Fichero: `vida/app/Enums/AccionAuditEnum.php:10`.
- Resumen: Tipos de acción registrables en la tabla de auditoría.

Metodos publicos:

- `function etiqueta(): string`
  Etiqueta en lenguaje natural para la vista del ciudadano.
- `function color(): string`
  Color semántico para el badge en Filament.

### `App\Enums\OrigenDireccion`

- Tipo: enum.
- Fichero: `vida/app/Enums/OrigenDireccion.php:11`.
- Resumen: Origen de la dirección almacenada en una entidad.

El DireccionObserver toma decisiones basadas en este valor (solo geocodifica cuando el origen es 'profesional'). Ver docs/geocodificacion.md § 3.3.

Metodos publicos:

- `function label(): string`
  Normalizada posteriormente por el job de reintento.

### `App\Enums\TipoNumeracion`

- Tipo: enum.
- Fichero: `vida/app/Enums/TipoNumeracion.php:11`.
- Resumen: Tipo de numeración de la vía en una dirección postal.

El código toma decisiones basadas en este valor — por eso es enum y no un valor de catálogo. Ver principio 3.10 de principios-vida360.md.

Metodos publicos:

- `function label(): string`
  _Sin resumen PHPDoc._

### `App\Exceptions\Anonimizacion\KAnonimatoValidacionException`

- Tipo: class.
- Fichero: `vida/app/Exceptions/Anonimizacion/KAnonimatoValidacionException.php:14`.
- Resumen: La validación final de k-anonimato falló: existen combinaciones de cuasi-identificadores con menos de K registros tras aplicar la cascada completa.

Este error bloquea la entrega del fichero de extracción. El job queda en estado 'error_k_anonimato'. Ver docs/anonimizacion.md § 6.2.

Metodos publicos:

- `function __construct(private readonly array $combinacionesProblematicas)`
  _Sin resumen PHPDoc._
- `function getCombinaciones(): array`
  Devuelve el detalle de las combinaciones que no alcanzaron K.
  `@return` array<string>

### `App\Exceptions\Anonimizacion\PerfilAnonimizacionInactivoException`

- Tipo: class.
- Fichero: `vida/app/Exceptions/Anonimizacion/PerfilAnonimizacionInactivoException.php:10`.
- Resumen: El perfil de anonimización solicitado existe pero está inactivo.

Metodos publicos:

- `function __construct(string $perfilId)`
  _Sin resumen PHPDoc._

### `App\Exceptions\Anonimizacion\PerfilAnonimizacionNotFoundException`

- Tipo: class.
- Fichero: `vida/app/Exceptions/Anonimizacion/PerfilAnonimizacionNotFoundException.php:10`.
- Resumen: El perfil de anonimización solicitado no existe en el sistema.

Metodos publicos:

- `function __construct(string $perfilId)`
  _Sin resumen PHPDoc._

### `App\Exceptions\Anonimizacion\PerfilConExtraccionesException`

- Tipo: class.
- Fichero: `vida/app/Exceptions/Anonimizacion/PerfilConExtraccionesException.php:14`.
- Resumen: Intento de eliminar un perfil de anonimización que tiene extracciones asociadas.

Un perfil con extracciones no puede eliminarse porque esas extracciones referencian la versión exacta del perfil que se les aplicó, lo que garantiza la trazabilidad de cualquier extracción pasada. Ver docs/anonimizacion.md § 6.4.

Metodos publicos:

- `function __construct(string $perfilNombre)`
  _Sin resumen PHPDoc._

### `App\Exceptions\Anonimizacion\PerfilSistemaNoEliminableException`

- Tipo: class.
- Fichero: `vida/app/Exceptions/Anonimizacion/PerfilSistemaNoEliminableException.php:13`.
- Resumen: Intento de eliminar un perfil de anonimización predefinido del sistema.

Los perfiles de sistema (es_sistema = true) son invariantes del contrato del sistema. No pueden eliminarse sin una decisión explícita documentada.

Metodos publicos:

- `function __construct(string $perfilNombre)`
  _Sin resumen PHPDoc._

### `App\Filament\Concerns\AutorizaGestion`

- Tipo: trait.
- Fichero: `vida/app/Filament/Concerns/AutorizaGestion.php:13`.
- Resumen: Autorización estándar para Resources de backoffice.

Restringe el acceso a los roles con capacidad de gestión del sistema: adm_sistema y adm_usuarios.

Metodos publicos:

- `function canViewAny(): bool`
  _Sin resumen PHPDoc._
- `function canCreate(): bool`
  _Sin resumen PHPDoc._
- `function canEdit(Model $record): bool`
  _Sin resumen PHPDoc._
- `function canDelete(Model $record): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Pages\Dashboard`

- Tipo: class.
- Fichero: `vida/app/Filament/Pages/Dashboard.php:19`.
- Resumen: Panel de inicio del backoffice de VIDA 360.

Muestra indicadores de estado del sistema de configuración: prestaciones activas, centros, profesionales y alertas operativas. No muestra métricas de actividad asistencial (→ Power BI). Ver principio 3.14 en docs/principios-vida360.md.

Metodos publicos:

- `function getColumns(): int|array`
  _Sin resumen PHPDoc._
- `function getWidgets(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Pages\DemoWorldsPage`

- Tipo: class.
- Fichero: `vida/app/Filament/Pages/DemoWorldsPage.php:28`.
- Resumen: Página de gestión de entornos de demo en el backoffice.

Permite a los administradores cargar mundos YAML predefinidos para resetear el entorno de demostración con datos ficticios pero realistas.  Solo visible en entornos no productivos (canAccess() devuelve false en producción).

Metodos publicos:

- `function canAccess(): bool`
  La página solo es accesible en entornos no productivos.
  `@return` bool True si el entorno no es producción
- `function getViewData(): array`
  Datos para la vista Blade: lista de mundos disponibles con metadatos.
  `@return` array{worlds: list<array{id: string, nombre: string, descripcion: string, centros: int, profesionales: int, ciudadanos: int, reset_cada: string}>}

### `App\Filament\Resources\AuditResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/AuditResource.php:30`.
- Resumen: Visor de registros de auditoría para supervisores y administradores.

Solo lectura: no hay CreateAction, EditAction ni DeleteAction. El scope automático de UO limita los registros visibles al supervisor a los de profesionales de su UO y descendientes. El rol adm_sistema no tiene restricción de scope.  El filtro de rango de fechas es obligatorio para evitar cargas masivas (máximo 90 días por consulta).

Metodos publicos:

- `function getEloquentQuery(): Builder`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canAccess(): bool`
  Solo accesible para roles supervision y adm_sistema.
- `function canCreate(): bool`
  Registro de auditoría — inmutable. Nunca se crean desde el backoffice.
- `function canEdit($record): bool`
  _Sin resumen PHPDoc._
- `function canDelete($record): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\AuditResource\Pages\ListAudits`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/AuditResource/Pages/ListAudits.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\AuditResource\Pages\ViewAudit`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/AuditResource/Pages/ViewAudit.php:14`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function infolist(Schema $schema): Schema`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\CargoResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CargoResource.php:24`.
- Resumen: Backoffice: gestión del catálogo de cargos profesionales.

Accesible en /admin/cargos.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\CargoResource\Pages\CreateCargo`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CargoResource/Pages/CreateCargo.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CargoResource\Pages\EditCargo`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CargoResource/Pages/EditCargo.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CargoResource\Pages\ListCargos`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CargoResource/Pages/ListCargos.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CentroResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CentroResource.php:27`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  Cualquier usuario autenticado puede consultar el catálogo de centros.
- `function canEdit(Model $record): bool`
  _Sin resumen PHPDoc._
- `function canDelete(Model $record): bool`
  _Sin resumen PHPDoc._
- `function getRelationManagers(): array`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\CentroResource\Pages\CreateCentro`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CentroResource/Pages/CreateCentro.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CentroResource\Pages\EditCentro`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CentroResource/Pages/EditCentro.php:17`.
- Resumen: Página de edición de un centro.

Añade un Action en la cabecera para gestionar las prestaciones del centro mediante un SlideOver con selector interactivo. La selección de prestaciones se gestiona en el componente Livewire SelectorPrestacionesCentro, no en el formulario principal del centro.

### `App\Filament\Resources\CentroResource\Pages\ListCentros`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CentroResource/Pages/ListCentros.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CentroResource\RelationManagers\AmbitosTerritorialesRelationManager`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CentroResource/RelationManagers/AmbitosTerritorialesRelationManager.php:18`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\CentroResource\RelationManagers\ColeccionesPlazasRelationManager`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CentroResource/RelationManagers/ColeccionesPlazasRelationManager.php:18`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ColectivoProtegidoResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ColectivoProtegidoResource.php:24`.
- Resumen: Backoffice: gestión del catálogo de colectivos especialmente protegidos.

Accesible en /admin/colectivos-protegidos.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ColectivoProtegidoResource\Pages\CreateColectivoProtegido`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ColectivoProtegidoResource/Pages/CreateColectivoProtegido.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ColectivoProtegidoResource\Pages\EditColectivoProtegido`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ColectivoProtegidoResource/Pages/EditColectivoProtegido.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ColectivoProtegidoResource\Pages\ListColectivosProtegidos`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ColectivoProtegidoResource/Pages/ListColectivosProtegidos.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionHorarioLaboralResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource.php:26`.
- Resumen: Recurso de configuración del horario laboral por defecto.

Edita el registro 'horario_laboral_defecto' en catalogos_sistema. Este horario se usa para calcular los vencimientos de alertas del sistema hasta que el módulo de Agenda esté disponible.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getEloquentQuery(): Builder`
  Solo muestra el registro del horario laboral.
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionHorarioLaboralResource\Pages\EditConfiguracionHorarioLaboral`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource/Pages/EditConfiguracionHorarioLaboral.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionHorarioLaboralResource\Pages\ListConfiguracionHorarioLaboral`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionHorarioLaboralResource/Pages/ListConfiguracionHorarioLaboral.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionOrganizacionResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionOrganizacionResource.php:26`.
- Resumen: Backoffice: gestión de la configuración general de la organización.

Almacena pares clave-valor que controlan el comportamiento de la aplicación.  Accesible en /admin/configuracion-organizacion.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionOrganizacionResource\Pages\CreateConfiguracion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionOrganizacionResource/Pages/CreateConfiguracion.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionOrganizacionResource\Pages\EditConfiguracion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionOrganizacionResource/Pages/EditConfiguracion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionOrganizacionResource\Pages\ListConfiguracion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionOrganizacionResource/Pages/ListConfiguracion.php:12`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionRolResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionRolResource.php:29`.
- Resumen: Backoffice: nivel de supervisión requerido por cada rol.

Configura si la asignación de un rol requiere aprobación previa del supervisor (adm_sistema, supervision) o solo genera una alerta supervisada (resto de roles).  Accesible en /admin/configuracion-roles.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionRolResource\Pages\CreateConfiguracionRol`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionRolResource/Pages/CreateConfiguracionRol.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionRolResource\Pages\EditConfiguracionRol`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionRolResource/Pages/EditConfiguracionRol.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ConfiguracionRolResource\Pages\ListConfiguracionRol`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ConfiguracionRolResource/Pages/ListConfiguracionRol.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CuadranteMesResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CuadranteMesResource.php:23`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getRelationManagers(): array`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\CuadranteMesResource\Pages\CreateCuadranteMes`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CuadranteMesResource/Pages/CreateCuadranteMes.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CuadranteMesResource\Pages\EditCuadranteMes`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CuadranteMesResource/Pages/EditCuadranteMes.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CuadranteMesResource\Pages\ListCuadrantesMes`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CuadranteMesResource/Pages/ListCuadrantesMes.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\CuadranteMesResource\RelationManagers\LineasCuadranteRelationManager`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/CuadranteMesResource/RelationManagers/LineasCuadranteRelationManager.php:11`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\DistritoResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/DistritoResource.php:23`.
- Resumen: Backoffice: gestión del catálogo de distritos municipales.

Accesible en /admin/distritos.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\DistritoResource\Pages\CreateDistrito`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/DistritoResource/Pages/CreateDistrito.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\DistritoResource\Pages\EditDistrito`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/DistritoResource/Pages/EditDistrito.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\DistritoResource\Pages\ListDistritos`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/DistritoResource/Pages/ListDistritos.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\DocumentoResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/DocumentoResource.php:29`.
- Resumen: Visor de documentos custodiados en el sistema.

Incluye documentos externos (PDFs aportados por ciudadanos o profesionales) y documentos generados internamente (PDFs de informes firmados).  Los ficheros nunca se sirven desde rutas públicas: el acceso siempre genera una URL firmada temporal a través de ServicioAlmacenamiento::urlTemporal().

Metodos publicos:

- `function infolist(Schema $schema): Schema`
  Los documentos se suben desde el flujo operativo, no desde el backoffice.
- `function canViewAny(): bool`
  supervision puede ver documentos de su subtree (solo lectura); adm_* puede gestionar.
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\DocumentoResource\Pages\ListDocumentos`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/DocumentoResource/Pages/ListDocumentos.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\DocumentoResource\Pages\ViewDocumento`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/DocumentoResource/Pages/ViewDocumento.php:11`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\EstiloInformeResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/EstiloInformeResource.php:28`.
- Resumen: Gestión del estilo formal de informes por Unidad Organizativa.

Los campos se heredan campo a campo por la jerarquía de UOs. Accesible solo a usuarios con rol supervisor o admin_sistema. Cada supervisor solo puede editar los estilos de su UO y descendientes.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  supervision puede ver estilos de su subtree (solo lectura); adm_* puede gestionar.
- `function canEdit(Model $record): bool`
  adm_usuarios solo gestiona estilos de su subtree de UO.
- `function canDelete(Model $record): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\EstiloInformeResource\Pages\CreateEstiloInforme`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/EstiloInformeResource/Pages/CreateEstiloInforme.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\EstiloInformeResource\Pages\EditEstiloInforme`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/EstiloInformeResource/Pages/EditEstiloInforme.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\EstiloInformeResource\Pages\ListEstilosInforme`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/EstiloInformeResource/Pages/ListEstilosInforme.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ExcepcionProfesionalResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ExcepcionProfesionalResource.php:25`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ExcepcionProfesionalResource\Pages\CreateExcepcionProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ExcepcionProfesionalResource/Pages/CreateExcepcionProfesional.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ExcepcionProfesionalResource\Pages\EditExcepcionProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ExcepcionProfesionalResource/Pages/EditExcepcionProfesional.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ExcepcionProfesionalResource\Pages\ListExcepcionesProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ExcepcionProfesionalResource/Pages/ListExcepcionesProfesional.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\HorarioCentroResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/HorarioCentroResource.php:26`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getRelationManagers(): array`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\HorarioCentroResource\Pages\CreateHorarioCentro`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/HorarioCentroResource/Pages/CreateHorarioCentro.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\HorarioCentroResource\Pages\EditHorarioCentro`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/HorarioCentroResource/Pages/EditHorarioCentro.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\HorarioCentroResource\Pages\ListHorariosCentro`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/HorarioCentroResource/Pages/ListHorariosCentro.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\HorarioCentroResource\RelationManagers\TiposSlotsRelationManager`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/HorarioCentroResource/RelationManagers/TiposSlotsRelationManager.php:19`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\InformeResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/InformeResource.php:32`.
- Resumen: Supervisión y gestión operativa de informes profesionales.

Los informes se crean desde el expediente del ciudadano (flujo Livewire, pendiente). Este recurso permite a supervisores y administradores consultar el estado de todos los informes y ejecutar la acción de anulación cuando proceda.

Metodos publicos:

- `function infolist(Schema $schema): Schema`
  Los informes se crean desde el flujo operativo (Livewire), no desde el backoffice.
- `function canViewAny(): bool`
  supervision puede ver informes de su subtree (solo lectura); adm_* puede gestionar.
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\InformeResource\Pages\ListInformes`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/InformeResource/Pages/ListInformes.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\InformeResource\Pages\ViewInforme`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/InformeResource/Pages/ViewInforme.php:13`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\LogAlertasResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/LogAlertasResource.php:22`.
- Resumen: Recurso de solo lectura para supervisión y auditoría de alertas.

Permite al administrador consultar el log completo de alertas con filtros por estado, tipo, fecha y UO. Especialmente útil para auditar alertas vencidas y escaladas.

Metodos publicos:

- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  Solo adm_sistema y supervision pueden ver el log de alertas.
- `function canCreate(): bool`
  _Sin resumen PHPDoc._
- `function canEdit(Model $record): bool`
  _Sin resumen PHPDoc._
- `function canDelete(Model $record): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\LogAlertasResource\Pages\ListLogAlertas`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/LogAlertasResource/Pages/ListLogAlertas.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\Pages\ListRecords`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/Pages/ListRecords.php:14`.
- Resumen: Clase base para páginas de listado de Resources.

Filament v5 deja authorizeAccess() vacío en ListRecords, lo que permite acceder por URL directa aunque canViewAny() devuelva false y el item de navegación esté oculto. Este override cierra esa brecha.

### `App\Filament\Resources\PerfilHorarioProfesionalResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PerfilHorarioProfesionalResource.php:23`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\PerfilHorarioProfesionalResource\Pages\CreatePerfilHorarioProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PerfilHorarioProfesionalResource/Pages/CreatePerfilHorarioProfesional.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PerfilHorarioProfesionalResource\Pages\EditPerfilHorarioProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PerfilHorarioProfesionalResource/Pages/EditPerfilHorarioProfesional.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PerfilHorarioProfesionalResource\Pages\ListPerfilesHorarioProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PerfilHorarioProfesionalResource/Pages/ListPerfilesHorarioProfesional.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PlantillaInformeResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PlantillaInformeResource.php:34`.
- Resumen: Gestión de plantillas de informe profesional.

Las plantillas tienen alcance jerárquico: se crean al nivel de UO adecuado y son visibles para todos los profesionales de esa UO y sus descendientes. Accesible solo a usuarios con rol supervisor o admin_sistema.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  supervision puede ver plantillas de su subtree (solo lectura); adm_* puede gestionar.
- `function canEdit(Model $record): bool`
  _Sin resumen PHPDoc._
- `function canDelete(Model $record): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\PlantillaInformeResource\Pages\CreatePlantillaInforme`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PlantillaInformeResource/Pages/CreatePlantillaInforme.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PlantillaInformeResource\Pages\EditPlantillaInforme`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PlantillaInformeResource/Pages/EditPlantillaInforme.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PlantillaInformeResource\Pages\ListPlantillasInforme`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PlantillaInformeResource/Pages/ListPlantillasInforme.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PrestacionResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PrestacionResource.php:24`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getRelationManagers(): array`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\PrestacionResource\Pages\CreatePrestacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PrestacionResource/Pages/CreatePrestacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PrestacionResource\Pages\EditPrestacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PrestacionResource/Pages/EditPrestacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PrestacionResource\Pages\ListPrestaciones`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PrestacionResource/Pages/ListPrestaciones.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\PrestacionResource\RelationManagers\VersionesRelationManager`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/PrestacionResource/RelationManagers/VersionesRelationManager.php:17`.
- Resumen: Historial de versiones de una prestación.

Muestra las versiones registradas en la tabla `versiones` (polimórfico). Cada versión contiene el snapshot completo del estado anterior al cambio. Solo lectura: el historial no se puede editar ni eliminar.

Metodos publicos:

- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function isReadOnly(): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ProfesionalResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ProfesionalResource.php:33`.
- Resumen: Backoffice: gestión de profesionales.

Un Profesional es la entidad raíz del sistema de usuarios: el perfil organizativo de una persona con independencia de si tiene cuenta de acceso.  Accesible en /admin/profesionales.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  Cualquier usuario autenticado puede consultar el directorio de profesionales.
- `function canCreate(): bool`
  _Sin resumen PHPDoc._
- `function canEdit(Model $record): bool`
  _Sin resumen PHPDoc._
- `function canDelete(Model $record): bool`
  Solo adm_sistema puede eliminar profesionales.

### `App\Filament\Resources\ProfesionalResource\Pages\CreateProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ProfesionalResource/Pages/CreateProfesional.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ProfesionalResource\Pages\EditProfesional`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ProfesionalResource/Pages/EditProfesional.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ProfesionalResource\Pages\ListProfesionales`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ProfesionalResource/Pages/ListProfesionales.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\RedResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RedResource.php:21`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  supervision puede ver redes cuyo ámbito incluye su subtree de UO.
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\RedResource\Pages\CreateRed`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RedResource/Pages/CreateRed.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\RedResource\Pages\EditRed`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RedResource/Pages/EditRed.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\RedResource\Pages\ListRedes`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RedResource/Pages/ListRedes.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\RolResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RolResource.php:25`.
- Resumen: Resource Filament para gestionar Roles y su matriz de permisos.

Permite visualizar y modificar qué permisos atómicos tiene cada rol. Solo el rol adm_sistema debe tener acceso a este recurso (sección 4.5).  Accesible en /admin/rols.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  _Sin resumen PHPDoc._
- `function canCreate(): bool`
  _Sin resumen PHPDoc._
- `function canEdit(Model $record): bool`
  _Sin resumen PHPDoc._
- `function canDelete(Model $record): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\RolResource\Pages\CreateRol`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RolResource/Pages/CreateRol.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\RolResource\Pages\EditRol`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RolResource/Pages/EditRol.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\RolResource\Pages\ListRoles`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/RolResource/Pages/ListRoles.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\SegmentoPoblacionResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/SegmentoPoblacionResource.php:19`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\SegmentoPoblacionResource\Pages\CreateSegmentoPoblacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/SegmentoPoblacionResource/Pages/CreateSegmentoPoblacion.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\SegmentoPoblacionResource\Pages\EditSegmentoPoblacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/SegmentoPoblacionResource/Pages/EditSegmentoPoblacion.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\SegmentoPoblacionResource\Pages\ListSegmentosPoblacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/SegmentoPoblacionResource/Pages/ListSegmentosPoblacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ServicioEmergenciaResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ServicioEmergenciaResource.php:27`.
- Resumen: Backoffice: gestión del catálogo de servicios de emergencia preautorizados.

Los servicios aquí listados tienen acceso en modo consulta a Historias de ciudadanos protegidos sin aprobación previa (excepción de urgencia).  Accesible en /admin/servicios-emergencia.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ServicioEmergenciaResource\Pages\CreateServicioEmergencia`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ServicioEmergenciaResource/Pages/CreateServicioEmergencia.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ServicioEmergenciaResource\Pages\EditServicioEmergencia`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ServicioEmergenciaResource/Pages/EditServicioEmergencia.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ServicioEmergenciaResource\Pages\ListServiciosEmergencia`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ServicioEmergenciaResource/Pages/ListServiciosEmergencia.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoActividadResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoActividadResource.php:19`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoActividadResource\Pages\CreateTipoActividad`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoActividadResource/Pages/CreateTipoActividad.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoActividadResource\Pages\EditTipoActividad`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoActividadResource/Pages/EditTipoActividad.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoActividadResource\Pages\ListTiposActividad`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoActividadResource/Pages/ListTiposActividad.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEscalaResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEscalaResource.php:29`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEscalaResource\Pages\CreateTipoEscala`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEscalaResource/Pages/CreateTipoEscala.php:10`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEscalaResource\Pages\EditTipoEscala`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEscalaResource/Pages/EditTipoEscala.php:11`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEscalaResource\Pages\ListTipoEscalas`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEscalaResource/Pages/ListTipoEscalas.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEspacioResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEspacioResource.php:19`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEspacioResource\Pages\CreateTipoEspacio`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEspacioResource/Pages/CreateTipoEspacio.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEspacioResource\Pages\EditTipoEspacio`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEspacioResource/Pages/EditTipoEspacio.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoEspacioResource\Pages\ListTiposEspacio`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoEspacioResource/Pages/ListTiposEspacio.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoFichaResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoFichaResource.php:36`.
- Resumen: Recurso Filament para la gestión de fichas de valoración configurables.

Cada TipoFicha define un formulario con campos tipados (texto, número, select, booleano, fecha, escala) que el profesional rellena durante la valoración. El schema JSON se edita mediante un Builder visual con bloques por tipo de campo.

Metodos publicos:

- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function convertirSchemaBlocks(mixed $state): array`
  Convierte el estado crudo del Builder (bloques con 'type'/'data') al formato canónico del schema del modelo ({'campos': [...]}). Si el estado ya está en formato canónico, lo devuelve sin modificar. Necesario porque en Filament 5 el valor de dehydrateStateUsing en un Builder NO se asigna automáticamente a $data en mutateFormDataBefore*.

### `App\Filament\Resources\TipoFichaResource\Pages\CreateTipoFicha`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoFichaResource/Pages/CreateTipoFicha.php:11`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoFichaResource\Pages\EditTipoFicha`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoFichaResource/Pages/EditTipoFicha.php:13`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoFichaResource\Pages\ListTipoFichas`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoFichaResource/Pages/ListTipoFichas.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionProfesionalResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionProfesionalResource.php:23`.
- Resumen: Backoffice: gestión del catálogo de tipos de relación profesional.

Accesible en /admin/tipos-relacion-profesional.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionProfesionalResource\Pages\CreateTipoRelacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionProfesionalResource/Pages/CreateTipoRelacion.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionProfesionalResource\Pages\EditTipoRelacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionProfesionalResource/Pages/EditTipoRelacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionProfesionalResource\Pages\ListTiposRelacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionProfesionalResource/Pages/ListTiposRelacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionResource.php:27`.
- Resumen: Backoffice: gestión del catálogo de tipos de relación entre ciudadanos.

Accesible en /admin/tipos-relacion. Restringido a adm_sistema. Los tipos del seeder (eliminable = false) no muestran el botón de eliminar.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  _Sin resumen PHPDoc._
- `function canCreate(): bool`
  _Sin resumen PHPDoc._
- `function canEdit($record): bool`
  _Sin resumen PHPDoc._
- `function canDelete($record): bool`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionResource\Pages\CreateTipoRelacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionResource/Pages/CreateTipoRelacion.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionResource\Pages\EditTipoRelacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionResource/Pages/EditTipoRelacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoRelacionResource\Pages\ListTiposRelacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoRelacionResource/Pages/ListTiposRelacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoSlotResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoSlotResource.php:22`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoSlotResource\Pages\CreateTipoSlot`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoSlotResource/Pages/CreateTipoSlot.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoSlotResource\Pages\EditTipoSlot`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoSlotResource/Pages/EditTipoSlot.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TipoSlotResource\Pages\ListTiposSlot`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TipoSlotResource/Pages/ListTiposSlot.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TitulacionResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TitulacionResource.php:23`.
- Resumen: Backoffice: gestión del catálogo de titulaciones académicas.

Accesible en /admin/titulaciones.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\TitulacionResource\Pages\CreateTitulacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TitulacionResource/Pages/CreateTitulacion.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TitulacionResource\Pages\EditTitulacion`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TitulacionResource/Pages/EditTitulacion.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\TitulacionResource\Pages\ListTitulaciones`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/TitulacionResource/Pages/ListTitulaciones.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UnidadOrganizativaResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UnidadOrganizativaResource.php:25`.
- Resumen: Resource Filament para gestionar Unidades Organizativas.

Accesible en /admin/unidades-organizativas.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\UnidadOrganizativaResource\Pages\CreateUnidadOrganizativa`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UnidadOrganizativaResource/Pages/CreateUnidadOrganizativa.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UnidadOrganizativaResource\Pages\EditUnidadOrganizativa`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UnidadOrganizativaResource/Pages/EditUnidadOrganizativa.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UnidadOrganizativaResource\Pages\ListUnidadesOrganizativas`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UnidadOrganizativaResource/Pages/ListUnidadesOrganizativas.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UsuarioResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioResource.php:32`.
- Resumen: Resource Filament para gestionar Usuarios del sistema.

Permite crear usuarios, asignarles roles globales y adscribirlos a Unidades Organizativas con rol y tipo de vínculo.  Accesible en /admin/usuarios.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canViewAny(): bool`
  _Sin resumen PHPDoc._
- `function canCreate(): bool`
  _Sin resumen PHPDoc._
- `function canEdit(Model $record): bool`
  _Sin resumen PHPDoc._
- `function canDelete(Model $record): bool`
  Solo adm_sistema puede eliminar usuarios.

### `App\Filament\Resources\UsuarioResource\Pages\CreateUsuario`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioResource/Pages/CreateUsuario.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UsuarioResource\Pages\EditUsuario`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioResource/Pages/EditUsuario.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UsuarioResource\Pages\ListUsuarios`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioResource/Pages/ListUsuarios.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UsuarioRolResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioRolResource.php:28`.
- Resumen: Backoffice: supervisión del historial de asignaciones de rol.

Muestra el historial completo de roles (pendientes, activos, inactivos) y permite al supervisor aprobar asignaciones pendientes.  Accesible en /admin/usuario-roles.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._
- `function canEdit(Model $record): bool`
  adm_usuarios solo gestiona asignaciones de rol de su subtree de UO.

### `App\Filament\Resources\UsuarioRolResource\Pages\CreateUsuarioRol`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioRolResource/Pages/CreateUsuarioRol.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UsuarioRolResource\Pages\EditUsuarioRol`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioRolResource/Pages/EditUsuarioRol.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\UsuarioRolResource\Pages\ListUsuarioRoles`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/UsuarioRolResource/Pages/ListUsuarioRoles.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ZonaResource`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ZonaResource.php:25`.
- Resumen: Backoffice: gestión del catálogo de zonas territoriales.

Accesible en /admin/zonas.

Metodos publicos:

- `function form(Schema $schema): Schema`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._
- `function getPages(): array`
  _Sin resumen PHPDoc._

### `App\Filament\Resources\ZonaResource\Pages\CreateZona`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ZonaResource/Pages/CreateZona.php:8`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ZonaResource\Pages\EditZona`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ZonaResource/Pages/EditZona.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Resources\ZonaResource\Pages\ListZonas`

- Tipo: class.
- Fichero: `vida/app/Filament/Resources/ZonaResource/Pages/ListZonas.php:9`.
- Resumen: _Sin resumen PHPDoc._

### `App\Filament\Widgets\ActividadCatalogosWidget`

- Tipo: class.
- Fichero: `vida/app/Filament/Widgets/ActividadCatalogosWidget.php:17`.
- Resumen: Widget de actividad reciente en catálogos (últimas modificaciones).

TODO: requiere un modelo de auditoría (App\Models\Audit o similar) que no está instalado. Activar cuando se integre owen-it/laravel-auditing o similar. Ver BACKLOG.md — módulo Sistema.

Metodos publicos:

- `function canView(): bool`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._

### `App\Filament\Widgets\AlertasSistemaWidget`

- Tipo: class.
- Fichero: `vida/app/Filament/Widgets/AlertasSistemaWidget.php:15`.
- Resumen: Widget de alertas activas del sistema dirigidas a administración. Solo alertas de ámbito de backoffice (origen sistema/permisos).

Metodos publicos:

- `function canView(): bool`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._

### `App\Filament\Widgets\EstadoSistemaWidget`

- Tipo: class.
- Fichero: `vida/app/Filament/Widgets/EstadoSistemaWidget.php:17`.
- Resumen: Widget de estado del sistema de configuración. Solo contadores de entidades de configuración — no métricas asistenciales.

Metodos publicos:

- `function canView(): bool`
  _Sin resumen PHPDoc._

### `App\Filament\Widgets\RolesPendientesWidget`

- Tipo: class.
- Fichero: `vida/app/Filament/Widgets/RolesPendientesWidget.php:15`.
- Resumen: Widget de asignaciones de rol pendientes de aprobación. Acceso directo a la gestión desde el dashboard.

Metodos publicos:

- `function canView(): bool`
  _Sin resumen PHPDoc._
- `function table(Table $table): Table`
  _Sin resumen PHPDoc._

### `App\Http\Controllers\Auth\LoginController`

- Tipo: class.
- Fichero: `vida/app/Http/Controllers/Auth/LoginController.php:14`.
- Resumen: Gestiona el acceso y la salida de la aplicación operacional. El backoffice (/admin) tiene su propia autenticación Filament — no usar este controlador allí.

Metodos publicos:

- `function mostrar()`
  Muestra el formulario de login.
- `function autenticar(Request $request)`
  Procesa el intento de autenticación.
  `@throws` ValidationException
- `function cerrarSesion(Request $request)`
  Cierra la sesión activa.

### `App\Http\Controllers\Auth\OnboardingController`

- Tipo: class.
- Fichero: `vida/app/Http/Controllers/Auth/OnboardingController.php:13`.
- Resumen: Gestiona la pantalla de primer acceso que se muestra una sola vez tras la creación de la cuenta.

Metodos publicos:

- `function mostrar()`
  Muestra la pantalla de bienvenida con el contexto del usuario.
- `function completar(Request $request)`
  Marca el onboarding como completado y redirige al destino según rol.

### `App\Http\Controllers\Controller`

- Tipo: class.
- Fichero: `vida/app/Http/Controllers/Controller.php:5`.
- Resumen: _Sin resumen PHPDoc._

### `App\Http\Middleware\AuditarAccesoCiudadano`

- Tipo: class.
- Fichero: `vida/app/Http/Middleware/AuditarAccesoCiudadano.php:29`.
- Resumen: Red de seguridad de segunda línea para auditoría de accesos a ciudadanos.

Registra un acceso 'ver' cuando se accede a una ruta con parámetro {ciudadano}. Actúa como fallback por si el componente no llamó explícitamente a AuditService::registrarAcceso(). Un acceso duplicado (middleware + componente) es un bug menor; uno no registrado es una brecha de accountability.  El middleware no sustituye la llamada explícita desde el componente — complementa. Los componentes deben seguir llamando a registrarAcceso() para incluir contexto adicional (modelo concreto consultado, etc.).

Metodos publicos:

- `function __construct(private readonly AuditService $service)`
  _Sin resumen PHPDoc._
- `function handle(Request $request, Closure $next): Response`
  _Sin resumen PHPDoc._

### `App\Http\Middleware\EnsureTieneRol`

- Tipo: class.
- Fichero: `vida/app/Http/Middleware/EnsureTieneRol.php:18`.
- Resumen: Redirige a /sin-rol si el usuario autenticado no tiene ningún rol asignado.

Solo cubre el caso de cero roles — cuando el usuario tiene un rol pero no el adecuado para la ruta, el middleware role:* de Spatie sigue devolviendo 403.  No debe aplicarse a la propia ruta /sin-rol para evitar bucles.

Metodos publicos:

- `function handle(Request $request, Closure $next): Response`
  _Sin resumen PHPDoc._

### `App\Http\Middleware\FilamentAuthenticate`

- Tipo: class.
- Fichero: `vida/app/Http/Middleware/FilamentAuthenticate.php:20`.
- Resumen: Sobrescribe el comportamiento de Filament cuando el usuario autenticado no tiene acceso al panel.

La implementación por defecto llama a abort(403), lo que impide al usuario acceder a /admin/login (la página de login redirige a /admin si hay sesión activa, generando un bucle 302 → 403). Este middleware cierra la sesión del guard de Filament y redirige al login, igual que si el usuario no estuviera autenticado.

### `App\Http\Middleware\PrimerAcceso`

- Tipo: class.
- Fichero: `vida/app/Http/Middleware/PrimerAcceso.php:12`.
- Resumen: Redirige a inicio si el usuario ya ha completado el onboarding. Solo actúa sobre rutas protegidas con este middleware.

Metodos publicos:

- `function handle(Request $request, Closure $next)`
  _Sin resumen PHPDoc._

### `App\Jobs\NormalizarDireccionJob`

- Tipo: class.
- Fichero: `vida/app/Jobs/NormalizarDireccionJob.php:33`.
- Resumen: Job de reintento de normalización de dirección.

Procesa entidades con direccion_normalizada = false intentando geocodificarlas de nuevo. Se ejecuta en cola de baja prioridad ('low') con backoff exponencial.  El job es genérico: recibe la clase del modelo y el id, no está acoplado a Ciudadano ni a Centro. Cualquier modelo que use TieneDireccion puede encolarlo.  Si el reintento tiene éxito, actualiza los campos canónicos y establece origen_direccion = geocodificacion. Si falla, el job se reencola automáticamente hasta agotar los intentos máximos.  Ver docs/geocodificacion.md § 4.2.

Metodos publicos:

- `function __construct( private readonly string $claseModelo, private readonly int|string $modeloId, )`
  _Sin resumen PHPDoc._
- `function backoff(): array`
  Segundos de espera entre reintentos — backoff exponencial.
  `@return` list<int>
- `function handle(GeocodificadorInterface $geocodificador): void`
  Procesa el reintento de normalización.

### `App\Livewire\Admin\GestorUnidadesOrganizativas`

- Tipo: class.
- Fichero: `vida/app/Livewire/Admin/GestorUnidadesOrganizativas.php:29`.
- Resumen: Componente Livewire para la gestión de Unidades Organizativas en el backoffice.

Ofrece: - Listado del árbol jerárquico de UO (usando descendants del paquete staudenmeir/laravel-adjacency-list). - Formulario de creación y edición de UO. - Acción de desactivación (soft-disable, no elimina).  Accesible únicamente para usuarios con el permiso `configuracion.acceder` (docs/modulo-usuarios-permisos.md § 4.5).

Metodos publicos:

- `function mount(): void`
  Valida que el usuario autenticado tiene el permiso requerido. Livewire llama a este método al montar el componente.
  `@throws` AuthorizationException
- `function arbolUo(): Collection`
  Devuelve el árbol de UO desde los nodos raíz. Si hay búsqueda activa, filtra por nombre en toda la jerarquía.
  `@return` Collection<int, UnidadOrganizativa>
- `function tiposDisponibles(): Collection`
  Catálogo de tipos de UO disponibles para el selector del formulario.
  `@return` Collection<int, object>
- `function uosDisponiblesComoPadre(): Collection`
  Lista de UO activas disponibles como padre (excluye la que se está editando).
  `@return` Collection<int, UnidadOrganizativa>
- `function abrirFormularioCreacion(): void`
  Abre el formulario para crear una nueva UO.
- `function abrirFormularioEdicion(int $id): void`
  Abre el formulario cargando los datos de una UO existente para editarla.
- `function guardar(): void`
  Guarda la UO (crea o actualiza según si hay editandoId).
- `function desactivar(int $id): void`
  Desactiva una UO (la marca como inactiva; no la elimina). El historial de adscripciones y la estructura quedan preservados.
- `function reactivar(int $id): void`
  Reactiva una UO previamente desactivada.
- `function cancelar(): void`
  Cierra el formulario sin guardar.
- `function render(): View`
  Renderiza el componente.

### `App\Livewire\Centros\SelectorPrestacionesCentro`

- Tipo: class.
- Fichero: `vida/app/Livewire/Centros/SelectorPrestacionesCentro.php:25`.
- Resumen: Selector interactivo de prestaciones para un centro.

Muestra el catálogo completo de prestaciones activas con filtros por segmento de población y búsqueda por texto. Las prestaciones se agrupan por objetivo general usando las etiquetas de catalogos_sistema. La selección se persiste en la tabla pivote centro_prestacion al guardar.

Metodos publicos:

- `function mount(int $centroId): void`
  Inicializa el componente cargando las prestaciones ya asociadas al centro.
- `function segmentosFiltro(): array`
  Devuelve las opciones de filtro por segmento de población. Se derivan de los segmentos actualmente asociados al centro.
  `@return` array<string, string>
- `function prestacionesFiltradas(): Collection`
  Devuelve las prestaciones filtradas y agrupadas por nombre de objetivo general. Las etiquetas de objetivo se obtienen de catalogos_sistema.
  `@return` Collection<string, Collection<int, Prestacion>>
- `function togglePrestacion(int $prestacionId): void`
  Alterna la selección de una prestación.
- `function deseleccionar(int $prestacionId): void`
  Elimina una prestación del panel de seleccionadas.
- `function setSegmento(string $segmento): void`
  Activa el filtro de segmento de población.
- `function verDetalle(int $prestacionId): void`
  Abre la ficha de detalle de una prestación.
- `function cerrarDetalle(): void`
  Cierra la ficha de detalle.
- `function guardar(): void`
  Persiste la selección de prestaciones en la tabla pivote centro_prestacion. Usa sync() para gestionar altas y bajas en una sola operación.
- `function render(): View`
  _Sin resumen PHPDoc._

### `App\Models\AccesoProtegido`

- Tipo: class.
- Fichero: `vida/app/Models/AccesoProtegido.php:36`.
- Resumen: Modelo de solicitud de acceso a ciudadano especialmente protegido.

Registra el flujo de aprobación definido en la sección 3 de docs/modulo-usuarios-permisos.md: un profesional solicita acceso a la Historia de un ciudadano protegido fuera de su UO; el supervisor competente aprueba o deniega la solicitud.  Los estados posibles son: pendiente, aprobado, denegado.

Metodos publicos:

- `function usuario(): BelongsTo`
  Profesional que solicitó el acceso.
  `@return` BelongsTo<User, AccesoProtegido>
- `function aprobador(): BelongsTo`
  Supervisor que aprobó o denegó la solicitud.
  `@return` BelongsTo<User, AccesoProtegido>

### `App\Models\Api\PerfilAnonimizacion`

- Tipo: class.
- Fichero: `vida/app/Models/Api/PerfilAnonimizacion.php:37`.
- Resumen: Perfil de anonimización.

Define, campo a campo, qué técnica se aplica a un conjunto de registros para un caso de uso concreto. Los perfiles son configurables desde el backoffice y están versionados: cada modificación de 'campos' o 'k_valor' genera un snapshot inmutable antes del cambio.  Los perfiles de sistema (es_sistema = true) son invariantes del contrato del sistema — no pueden eliminarse.  Ver docs/anonimizacion.md § 5 y docs/decisiones-tecnicas.md §§ 7 y 8.

Metodos publicos:

- `function delete(): ?bool`
  Intenta eliminar el perfil respetando las restricciones de dominio.
  `@throws` PerfilSistemaNoEliminableException Si es un perfil de sistema
  `@throws` PerfilConExtraccionesException Si tiene extracciones asociadas
- `function versiones(): HasMany`
  Historial de versiones anteriores del perfil.
  `@return` HasMany<PerfilAnonimizacionVersion>
- `function scopeActivos(Builder $query): Builder`
  Solo perfiles en estado activo.
  `@return` Builder<PerfilAnonimizacion>
- `function scopeDeSistema(Builder $query): Builder`
  Solo perfiles predefinidos del sistema.
  `@return` Builder<PerfilAnonimizacion>

### `App\Models\Api\PerfilAnonimizacionVersion`

- Tipo: class.
- Fichero: `vida/app/Models/Api/PerfilAnonimizacionVersion.php:25`.
- Resumen: Snapshot histórico de un perfil de anonimización.

Cada fila representa el estado de 'campos' y 'k_valor' ANTES de un cambio. El registro en PerfilAnonimizacion tiene siempre el estado actual.  Los snapshots son inmutables: no tienen updated_at y no deben modificarse una vez creados. Ver docs/anonimizacion.md § 6.4.

Metodos publicos:

- `function perfil(): BelongsTo`
  Perfil al que pertenece este snapshot.
  `@return` BelongsTo<PerfilAnonimizacion, PerfilAnonimizacionVersion>

### `App\Models\Apunte`

- Tipo: class.
- Fichero: `vida/app/Models/Apunte.php:39`.
- Resumen: Modelo stub de Apunte (acto profesional).

Stub mínimo para que las Policies y los tests puedan referenciar la entidad. La implementación completa corresponde al módulo Intervencion (docs/glosario.md § Apunte).  Un apunte es cualquier acto profesional registrado en la Historia Social: valoración, seguimiento, anotación, entrevista, gestión/coordinación, informe social, derivación.  Las anotaciones privadas son un subtipo especial: solo el autor puede crearlas, leerlas y eliminarlas; ni el supervisor tiene acceso (docs/modulo-usuarios-permisos.md § 4.7).  con todos los atributos, relaciones y tipos configurables.

Metodos publicos:

- `function historiaSocial(): BelongsTo`
  Historia Social a la que pertenece este apunte.
  `@return` BelongsTo<HistoriaSocial, Apunte>
- `function profesional(): BelongsTo`
  Profesional autor del apunte.
  `@return` BelongsTo<User, Apunte>

### `App\Models\Audit`

- Tipo: class.
- Fichero: `vida/app/Models/Audit.php:36`.
- Resumen: Registro de auditoría de acceso a datos de ciudadanos.

Inmutable por diseño: update() y delete() a nivel de instancia lanzan LogicException. La única operación de borrado legítima es la purga por retención ejecutada por AuditPurgeCommand mediante query builder directo.

Metodos publicos:

- `function update(array $attributes = [], array $options = []): bool`
  _Sin resumen PHPDoc._
  `@throws` LogicException Los registros de auditoría son inmutables.
- `function delete(): ?bool`
  _Sin resumen PHPDoc._
  `@throws` LogicException Use AuditPurgeCommand para purgas por retención.
- `function auditable(): MorphTo`
  Modelo afectado (polimórfico).
  `@return` MorphTo<Model, Audit>
- `function user(): BelongsTo`
  Profesional que realizó la acción.
  `@return` BelongsTo<User, Audit>
- `function ciudadano(): BelongsTo`
  Ciudadano al que pertenece el dato accedido.
  `@return` BelongsTo<Ciudadano, Audit>

### `App\Models\CatalogoSistema`

- Tipo: class.
- Fichero: `vida/app/Models/CatalogoSistema.php:31`.
- Resumen: Catálogo de valores clasificatorios configurables desde backoffice.

Implementa el principio 3.10 de principios-vida360.md: valores puramente descriptivos que un administrador funcional puede añadir, renombrar u ordenar sin necesidad de un deploy.  RESTRICCIÓN: los valores de este catálogo NUNCA deben referenciarse en lógica de negocio (match/if con comportamiento diferenciado). Si el código necesita distinguir entre dos valores para hacer algo distinto, ese catálogo debe ser un enum, no una entrada aquí.  Uso habitual desde Filament: CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general') // → ['01' => 'Acceso, información y valoración', ...]

Metodos publicos:

- `function scopeDeGrupo(Builder $query, string $grupo): Builder`
  _Sin resumen PHPDoc._
- `function opcionesParaSelect(string $grupo): array`
  Devuelve las opciones activas de un grupo en formato [clave => etiqueta] listo para usar en selects de Filament.
  `@return` array<string, string>
- `function valor(string $clave, string $defecto = ''): string`
  Devuelve la etiqueta de un valor del catálogo por clave única. Útil para parámetros de configuración global (clave única entre todos los grupos).
- `function opcionesParaSelectConPrefijo(string $grupo, string $prefijo): array`
  Devuelve las opciones de un grupo filtradas por prefijo de clave. Útil para cargar subcategorías dependientes de una categoría padre.
  `@return` array<string, string>

### `App\Models\Ciudadano`

- Tipo: class.
- Fichero: `vida/app/Models/Ciudadano.php:56`.
- Resumen: Modelo stub de Ciudadano.

Stub mínimo para que los módulos que referencian ciudadanos puedan compilar y funcionar. La implementación completa corresponde al módulo Ciudadania (docs/modulo-ciudadania.md).  Todos los campos de datos personales se cifran en la capa de aplicación con el cast 'encrypted' (AES-256). Lo que se persiste en BD es texto cifrado opaco.  Referencia definitiva: Modules\Ciudadania\Models\Ciudadano  con todos los atributos, relaciones y lógica de dominio.

Metodos publicos:

- `function getNombreCompletoAttribute(): string`
  Nombre completo del ciudadano: nombre + apellido1 [+ apellido2]. Los campos están cifrados — solo accesible mediante Eloquent ORM.
- `function getCiudadanoId(): ?int`
  El ciudadano es la entidad raíz: su propio id es el ciudadano_id.
- `function prestacionesResumen(): HasMany`
  _Sin resumen PHPDoc._
- `function membresiasUC(): HasMany`
  Todas las membresías UC del ciudadano (históricas y activas).
  `@return` HasMany<UnidadConvivenciaMiembro, self>
- `function unidadesConvivencia(): BelongsToMany`
  Unidades de convivencia a las que ha pertenecido el ciudadano.
  `@return` BelongsToMany<UnidadConvivencia, self>
- `function unidadesConvivenciaActivas(): BelongsToMany`
  Unidades de convivencia activas (puede ser más de una: custodia compartida).
  `@return` BelongsToMany<UnidadConvivencia, self>
- `function tieneResidenciaVerificada(): bool`
  Indica si el ciudadano tiene verificada su residencia en alguna UC activa. Determina si puede ser perceptor de prestaciones municipales.

### `App\Models\HistoriaSocial`

- Tipo: class.
- Fichero: `vida/app/Models/HistoriaSocial.php:41`.
- Resumen: Modelo stub de Historia Social.

Stub mínimo para que las Policies y los tests puedan referenciar la entidad. La implementación completa corresponde al módulo Intervencion (docs/glosario.md § Historia Social).  La Historia Social es el instrumento central de intervención: recoge la demanda del ciudadano, el diagnóstico, el plan y el seguimiento. Se abre cuando existe una demanda que requiere valoración, plan o seguimiento municipal (principio 3.2).  Referencia definitiva: Modules\Intervencion\Models\Historia  con todos los atributos, relaciones y lógica de dominio.

Metodos publicos:

- `function getCiudadanoId(): ?int`
  {@inheritDoc}
- `function ciudadano(): BelongsTo`
  _Sin resumen PHPDoc._
- `function unidadOrganizativa(): BelongsTo`
  UO responsable de la Historia Social.
  `@return` BelongsTo<UnidadOrganizativa, HistoriaSocial>
- `function pasesEscala(): HasMany`
  Pases de escala registrados en esta historia social. Filtrar por tipo_escala_id para obtener la serie temporal de un instrumento concreto.
  `@return` HasMany<PaseEscala, HistoriaSocial>

### `App\Models\RevelacionIdentidad`

- Tipo: class.
- Fichero: `vida/app/Models/RevelacionIdentidad.php:28`.
- Resumen: Registro de auditoría de una revelación de identidad.

Cada fila documenta que un usuario específico, con una justificación dada, resolvió un alias seudonimizado para obtener el ciudadano real. Los registros son inmutables — sin updated_at.  El requisito de justificación obligatoria y la trazabilidad completa son parte del cumplimiento RGPD para el Nivel 1 de anonimización. Ver docs/anonimizacion.md § 6.3.

Metodos publicos:

- `function usuario(): BelongsTo`
  Usuario que realizó la revelación.
  `@return` BelongsTo<User, RevelacionIdentidad>

### `App\Models\Scopes\AmbitoUoScope`

- Tipo: class.
- Fichero: `vida/app/Models/Scopes/AmbitoUoScope.php:43`.
- Resumen: Global Scope de ámbito de Unidad Organizativa para modelos sensibles.

Restringe automáticamente todas las consultas sobre modelos sensibles (HistoriaSocial, Ciudadano, Apunte, PlanDeIntervencion) para devolver únicamente los registros accesibles para el usuario autenticado.  Comportamiento por caso: - Sin usuario autenticado (consola, artisan, tests sin login): NO filtra. - Usuario con rol adm_sistema: NO filtra (acceso global de configuración). - Cualquier otro usuario autenticado: devuelve solo los registros de su UO o descendientes, MÁS los registros de otras UOs para los que tenga AccesoProtegido vigente.  El scope se desactiva con withoutGlobalScope(AmbitoUoScope::class) para auditorías, comandos de consola o consultas de supervisión global.  IMPORTANTE: Este scope filtra por la columna $uoForeignKey del modelo. Cada modelo que lo aplica debe declarar la FK adecuada: - HistoriaSocial → unidad_organizativa_id (directo) - Ciudadano → vía Historia Social (consulta subquery) - Apunte → historia_social_id → HistoriaSocial.unidad_organizativa_id - PlanDeIntervencion → historia_id → HistoriaSocial.unidad_organizativa_id

Metodos publicos:

- `function apply(Builder $builder, Model $model): void`
  Aplica el filtro de ámbito de UO a la consulta.

### `App\Models\UnidadOrganizativa`

- Tipo: class.
- Fichero: `vida/app/Models/UnidadOrganizativa.php:43`.
- Resumen: Modelo de Unidad Organizativa (UO).

Representa un nodo en la jerarquía organizativa del ayuntamiento: puede ser el propio ayuntamiento (raíz), un Área de Gobierno, una Dirección General, un Departamento, un Centro, etc.  La jerarquía es una Adjacency List (parent_id auto-referencial). Las consultas de ancestros y descendientes se ejecutan mediante CTEs recursivas nativas de PostgreSQL, gestionadas por el paquete staudenmeir/laravel-adjacency-list.  El tipo de UO es una referencia blanda al catálogo configurable `tipos_unidad_organizativa`, evitando enums cerrados (principio 4.12).

Metodos publicos:

- `function padre(): BelongsTo`
  UO padre en la jerarquía. Devuelve null para el nodo raíz (Ayuntamiento).
  `@return` BelongsTo<UnidadOrganizativa, UnidadOrganizativa>
- `function hijas(): HasMany`
  UO hijas directas (un nivel por debajo).
  `@return` HasMany<UnidadOrganizativa>
- `function usuarios(): HasMany`
  Usuarios (profesionales) actualmente adscritos a esta UO. La adscripción pasa por la tabla pivot `usuario_uo`.
  `@return` HasMany<UsuarioUo>
- `function isDescendantOf(UnidadOrganizativa $ancestor): bool`
  Comprueba si esta UO es descendiente del nodo dado. Útil para verificar ámbitos de supervisión jerárquica.
- `function getPlanNombreCompletoAttribute(): string`
  Nombre completo del plan de intervención con fallback. Permite personalizar el término por UO (p. ej. «PISO», «PIA»).
  `@return` string Nombre completo, nunca nulo.
- `function getPlanNombreCortoAttribute(): string`
  Acrónimo del plan de intervención con fallback.
  `@return` string Nombre corto, nunca nulo.
- `function scopeActivas(Builder $consulta): Builder`
  Filtra únicamente las UO marcadas como activas.
  `@return` Builder<UnidadOrganizativa>
- `function scopeRaiz(Builder $consulta): Builder`
  Filtra UO raíz (sin padre).
  `@return` Builder<UnidadOrganizativa>

### `App\Models\User`

- Tipo: class.
- Fichero: `vida/app/Models/User.php:46`.
- Resumen: Cuenta de acceso al sistema.

Representa la identidad de autenticación: las credenciales que dan acceso al sistema. Es el "quién ha hecho esto" en la auditoría.  Un User casi siempre tiene un Profesional asociado (su perfil organizativo), salvo los perfiles técnicos sin función asistencial (rol adm_sistema). La FK profesional_id es nullable por eso.  Los permisos se gestionan mediante Spatie laravel-permission (HasRoles). El ámbito de datos lo delimita la adscripción a UO — ver TieneUO. El historial de roles se gestiona mediante usuario_rol — ver TieneRoles.

Metodos publicos:

- `function canAccessPanel(Panel $panel): bool`
  Solo roles de gestión y supervisión pueden acceder al panel de administración.
- `function profesional(): BelongsTo`
  Perfil organizativo del usuario.
  `@return` BelongsTo<Profesional, User>

### `App\Models\UsuarioUo`

- Tipo: class.
- Fichero: `vida/app/Models/UsuarioUo.php:31`.
- Resumen: Adscripción de un usuario a una Unidad Organizativa.

Registra en qué UO opera el usuario y con qué tipo de vínculo laboral. Tiene fechas de vigencia para mantener el historial completo (principio 4.2: el pasado es inmutable).  Los roles del usuario son globales (Spatie model_has_roles) y determinan qué puede hacer; la adscripción determina dónde puede hacerlo con acceso completo (nivel 1). En UOs ajenas solo puede consultar (nivel 2).

Metodos publicos:

- `function usuario(): BelongsTo`
  Usuario al que corresponde esta adscripción.
  `@return` BelongsTo<User, UsuarioUo>
- `function unidadOrganizativa(): BelongsTo`
  Unidad Organizativa a la que el usuario está adscrito.
  `@return` BelongsTo<UnidadOrganizativa, UsuarioUo>
- `function scopeVigentes(Builder $consulta): Builder`
  Filtra únicamente las adscripciones vigentes: aquellas donde fecha_fin es null o todavía no ha llegado.
  `@return` Builder<UsuarioUo>

### `App\Models\Version`

- Tipo: class.
- Fichero: `vida/app/Models/Version.php:32`.
- Resumen: Snapshot histórico de un registro versionable.

Cada fila representa el estado completo de una entidad en el momento ANTERIOR a un cambio. El registro principal siempre tiene el estado actual; esta tabla guarda el historial.  Índice compuesto sobre (versionable_type, versionable_id, created_at) para consultas históricas eficientes.

Metodos publicos:

- `function versionable(): MorphTo`
  Entidad a la que pertenece esta versión.
- `function usuario(): BelongsTo`
  Usuario que realizó el cambio que generó esta versión.
  `@return` BelongsTo<User, Version>

### `App\Observers\AuditObserver`

- Tipo: class.
- Fichero: `vida/app/Observers/AuditObserver.php:24`.
- Resumen: Observer que registra automáticamente escrituras sobre modelos Auditable.

Las lecturas NO las registra este observer — deben registrarse explícitamente desde el componente Livewire / Resource Filament mediante AuditService::registrarAcceso().  El observer omite el registro si no hay usuario autenticado (procesos de consola, seeds, migraciones).

Metodos publicos:

- `function __construct(private readonly AuditService $service)`
  _Sin resumen PHPDoc._
- `function created(Model $model): void`
  Registra la creación de un modelo auditable.
- `function updated(Model $model): void`
  Registra la edición de un modelo auditable con diff de campos cambiados.
- `function deleted(Model $model): void`
  Registra la eliminación (soft o hard) de un modelo auditable.

### `App\Observers\DireccionObserver`

- Tipo: class.
- Fichero: `vida/app/Observers/DireccionObserver.php:24`.
- Resumen: Observer de dirección para modelos que usan el trait TieneDireccion.

Invoca el geocoder al guardar una entidad con dirección introducida manualmente (origen_direccion = profesional). Si el geocoder falla o supera el timeout, guarda con direccion_normalizada = false y encola un job de reintento sin bloquear el guardado.  Las direcciones procedentes del padrón (origen_direccion = padron) llegan ya estructuradas y no pasan por el geocoder.  Ver docs/geocodificacion.md § 4.1.

Metodos publicos:

- `function __construct( private readonly GeocodificadorInterface $geocodificador, )`
  Timeout en segundos para la llamada al geocoder.
- `function creating(Model $model): void`
  Intenta geocodificar antes de insertar el registro.
- `function created(Model $model): void`
  Encola el job de reintento si el guardado inicial no normalizó la dirección.
- `function updating(Model $model): void`
  Intenta geocodificar antes de actualizar el registro.
- `function updated(Model $model): void`
  Encola el job de reintento si la actualización no normalizó la dirección.

### `App\Policies\CiudadanoPolicy`

- Tipo: class.
- Fichero: `vida/app/Policies/CiudadanoPolicy.php:29`.
- Resumen: Policy de autorización para el modelo Ciudadano.

Implementa los tres pasos estándar de seguridad en profundidad:  Paso 1 — Permiso atómico: ¿tiene el usuario el permiso Spatie requerido? Paso 2 — Ámbito de UO: ¿el ciudadano tiene Historia en la UO del usuario o en una UO descendiente? (Nivel 1 = gestión, Nivel 2 = consulta) Paso 3 — Colectivo protegido: solo en Nivel 2, verificar aprobación vigente.  La UO se determina vía la Historia Social activa del ciudadano. Si el ciudadano no tiene Historia Social, el acceso corresponde a la UO del usuario activo.  Caso especial — supervision: solo lectura, nunca escritura sobre datos de ciudadanos. Caso especial — adm_sistema: también debe pasar los filtros de UO y colectivo protegido.

Metodos publicos:

- `function viewAny(User $usuario): bool`
  Decide si el usuario puede listar ciudadanos.
- `function view(User $usuario, Ciudadano $ciudadano): bool`
  Decide si el usuario puede consultar la ficha del ciudadano.
- `function create(User $usuario): bool`
  Decide si el usuario puede crear un ciudadano.
- `function update(User $usuario, Ciudadano $ciudadano): bool`
  Decide si el usuario puede editar el ciudadano.
- `function delete(User $usuario, Ciudadano $ciudadano): bool`
  Decide si el usuario puede eliminar (baja lógica) el ciudadano.

### `App\Providers\AppServiceProvider`

- Tipo: class.
- Fichero: `vida/app/Providers/AppServiceProvider.php:14`.
- Resumen: Proveedor principal de servicios de la aplicación.

Las Policies de autorización se registran en el módulo Usuarios (Modules\Usuarios\Providers\UsuariosServiceProvider). Este provider mantiene solo los registros globales de la aplicación.

Metodos publicos:

- `function register(): void`
  Registra servicios en el contenedor de la aplicación.
- `function boot(): void`
  Arranca los servicios de la aplicación.

### `App\Providers\Filament\AdminPanelProvider`

- Tipo: class.
- Fichero: `vida/app/Providers/Filament/AdminPanelProvider.php:22`.
- Resumen: _Sin resumen PHPDoc._

Metodos publicos:

- `function panel(Panel $panel): Panel`
  _Sin resumen PHPDoc._

### `App\Providers\GeocodificacionServiceProvider`

- Tipo: class.
- Fichero: `vida/app/Providers/GeocodificacionServiceProvider.php:22`.
- Resumen: Proveedor de servicios de geocodificación.

Registra el binding de GeocodificadorInterface en el contenedor y conecta el DireccionObserver a los modelos que usan TieneDireccion.  Añadir aquí cualquier modelo futuro que incorpore TieneDireccion.  Ver docs/geocodificacion.md § 2 y docs/decisiones-tecnicas.md § 9.

Metodos publicos:

- `function register(): void`
  Registra el binding de la interfaz en el contenedor.
- `function boot(): void`
  Registra el observer en los modelos con dirección.

### `App\Queries\AccesosExpedienteQuery`

- Tipo: class.
- Fichero: `vida/app/Queries/AccesosExpedienteQuery.php:25`.
- Resumen: Query object para obtener los accesos al expediente de un ciudadano filtrados por la visibilidad del usuario autenticado.

Visibilidad (spec §5 docs/modulo-auditoria.md): - adm_sistema → todos los accesos sin restricción. - TSR responsable del plan activo de la historia → todos los accesos. - Supervisor con la UO de la historia en su árbol → todos los accesos. - Cualquier otro profesional → únicamente sus propios accesos.

Metodos publicos:

- `function paraUsuario(User $user, Ciudadano $ciudadano, HistoriaSocial $historia): Builder`
  Construye el Builder de auditoría filtrado según el rol del usuario.
  `@return` Builder<Audit>
- `function puedeVerTodos(User $user, HistoriaSocial $historia): bool`
  Indica si el usuario puede ver todos los accesos o únicamente los propios.

### `App\Services\Api\AnonimizadorService`

- Tipo: class.
- Fichero: `vida/app/Services/Api/AnonimizadorService.php:22`.
- Resumen: Servicio de anonimización de registros.

Capa de transformación que actúa después del descifrado de campos sensibles y antes de serializar la respuesta o el fichero de extracción. Recibe una colección de registros (modelos Eloquent o arrays) y un perfil, y devuelve la colección transformada como arrays — nunca modelos Eloquent.  No tiene dependencias con ningún módulo funcional. Ver docs/anonimizacion.md §§ 3 y 6.1.

Metodos publicos:

- `function anonimizar(Collection $registros, string $perfilId): Collection`
  Aplica el perfil de anonimización a la colección de registros.
  `@return` Collection<int, array<string, mixed>>
  `@throws` PerfilAnonimizacionNotFoundException Si el perfil no existe
  `@throws` PerfilAnonimizacionInactivoException Si el perfil existe pero está inactivo

### `App\Services\Api\RevelacionIdentidadService`

- Tipo: class.
- Fichero: `vida/app/Services/Api/RevelacionIdentidadService.php:23`.
- Resumen: Servicio de reversión controlada de seudonimización.

Implementa la 'tabla de correspondencias' alias → ciudadano_id sin almacenarla: recorre los ciudadanos activos calculando el alias en vuelo hasta encontrar coincidencia. La operación requiere el permiso atómico 'ciudadano.revelar_identidad' y queda registrada en auditoría con justificación obligatoria.  Ver docs/anonimizacion.md § 6.3.

Metodos publicos:

- `function revelarPorAlias(string $alias, int $usuarioId, string $justificacion): Ciudadano`
  Resuelve un alias seudonimizado al ciudadano real.
  `@throws` ValidationException Si justificacion está vacía
  `@throws` AuthorizationException Si el usuario no tiene permiso ciudadano.revelar_identidad
  `@throws` ModelNotFoundException Si ningún ciudadano activo coincide con el alias

### `App\Services\Api\ValidadorKAnonimato`

- Tipo: class.
- Fichero: `vida/app/Services/Api/ValidadorKAnonimato.php:20`.
- Resumen: Validador y aplicador de k-anonimato sobre un conjunto de registros.

Garantiza que cada combinación de cuasi-identificadores aparece al menos K veces en el resultado. Cuando no se cumple, aplica una cascada de generalización en orden estricto hasta alcanzar el umbral o suprimir el registro.  Los cuasi-identificadores evaluados son: sexo, rango_edad, calle_generalizada, colectivo_principal. Ver docs/anonimizacion.md § 6.5.  Solo se usa en jobs asíncronos de extracción — nunca en tiempo real.

Metodos publicos:

- `function aplicar(Collection $registros, int $k): Collection`
  Aplica la cascada de generalización y valida k-anonimato sobre la colección.
  `@return` Collection<int, array<string, mixed>>
  `@throws` KAnonimatoValidacionException Si tras la cascada completa aún hay combinaciones < K

### `App\Services\AuditService`

- Tipo: class.
- Fichero: `vida/app/Services/AuditService.php:26`.
- Resumen: Servicio centralizado de registro de auditoría.

Es el único punto desde el que se crea un registro en `audits`. Ningún componente debe llamar directamente a Audit::create() — todo pasa por este servicio para garantizar la resolución de ciudadano_id y contexto.  Las escrituras (crear/editar/eliminar) se registran automáticamente a través de AuditObserver. Las lecturas deben registrarse explícitamente llamando a registrarAcceso() desde el componente Livewire o Resource de Filament.

Metodos publicos:

- `function registrarAcceso( User $user, Model $modelo, AccionAuditEnum|string $accion = AccionAuditEnum::Ver, ?int $ciudadanoId = null, array $contexto = [], ?array $datosAntes = null, ?array $datosDespues = null, ): void`
  Registra un acceso a datos de ciudadano.

### `App\Services\CiudadanoService`

- Tipo: class.
- Fichero: `vida/app/Services/CiudadanoService.php:32`.
- Resumen: Servicio de dominio para el Ciudadano.

Centraliza las operaciones de escritura sobre Ciudadano garantizando que todas las mutaciones pasen por el par (GlobalScope + Policy) definido en la estrategia de seguridad en profundidad.  Patrón de cada método de escritura: 1. Recuperar el registro (el GlobalScope ya filtra por ámbito de UO). 2. Verificar la Policy (Gate::authorize). 3. Ejecutar la lógica de negocio.  La implementación completa corresponde al módulo Ciudadania (pendiente). Este servicio es la capa de autorización mínima para el stub actual.

Metodos publicos:

- `function crear(array $datos): Ciudadano`
  Da de alta un nuevo ciudadano en el sistema.
  `@throws` AuthorizationException Si el usuario no tiene permiso de crear ciudadanos
- `function actualizar(int $id, array $datos): Ciudadano`
  Actualiza los datos de un ciudadano existente.
  `@throws` AuthorizationException Si el usuario no tiene permiso de editar
  `@throws` ModelNotFoundException Si el ciudadano no existe o no está en el ámbito del usuario
- `function eliminar(int $id): void`
  Elimina (baja lógica) un ciudadano del sistema.
  `@throws` AuthorizationException Si el usuario no tiene permiso de eliminar
  `@throws` ModelNotFoundException Si el ciudadano no existe o no está en el ámbito del usuario

### `App\Services\Geocodificacion\Adaptadores\MockGeocodificador`

- Tipo: class.
- Fichero: `vida/app/Services/Geocodificacion/Adaptadores/MockGeocodificador.php:22`.
- Resumen: Adaptador de geocodificación para desarrollo y pruebas.

Implementa un parser de texto libre con reglas para extraer los campos estructurados de una dirección española, más coordenadas aleatorias dentro del bounding box del municipio de Madrid.  No valida que la dirección exista realmente ni calcula coordenadas precisas. Su objetivo es que toda la lógica que consume ResultadoGeocodificacion funcione correctamente sin depender de ningún servicio externo.  Ver docs/geocodificacion.md § 5.

Metodos publicos:

- `function normalizar(string $direccionTexto): ResultadoGeocodificacion`
  Normaliza una dirección en texto libre.
  `@return` ResultadoGeocodificacion Siempre devuelve exito = true.

### `App\Services\Geocodificacion\GeocodificadorInterface`

- Tipo: interface.
- Fichero: `vida/app/Services/Geocodificacion/GeocodificadorInterface.php:14`.
- Resumen: Contrato del servicio de geocodificación.

Toda la aplicación interactúa con el geocoder a través de esta interfaz. El proveedor concreto es un detalle de infraestructura intercambiable desde el backoffice sin necesidad de código ni despliegue.  Ver docs/geocodificacion.md § 2.1.

Metodos publicos:

- `function normalizar(string $direccionTexto): ResultadoGeocodificacion`
  Normaliza una dirección en texto libre extrayendo sus campos estructurados y calculando coordenadas geográficas.
  `@return` ResultadoGeocodificacion Siempre devuelve un resultado — nunca lanza excepción.

### `App\Services\Geocodificacion\GeocodificadorService`

- Tipo: class.
- Fichero: `vida/app/Services/Geocodificacion/GeocodificadorService.php:20`.
- Resumen: Fachada del servicio de geocodificación.

Lee el proveedor activo de la configuración del sistema y delega en el adaptador correspondiente. Los módulos funcionales inyectan GeocodificadorInterface y nunca instancian adaptadores directamente.  El proveedor se configura en backoffice con la clave 'geocoder.proveedor'. Cambiar de proveedor no requiere código ni despliegue.  Ver docs/geocodificacion.md § 2.3.

Metodos publicos:

- `function normalizar(string $direccionTexto): ResultadoGeocodificacion`
  Normaliza la dirección delegando en el adaptador activo.

### `App\Services\Geocodificacion\ResultadoGeocodificacion`

- Tipo: class.
- Fichero: `vida/app/Services/Geocodificacion/ResultadoGeocodificacion.php:32`.
- Resumen: Resultado inmutable de una operación de geocodificación.

Estructura uniforme independientemente del adaptador que procesó la petición. Si $exito es false, los campos de dirección y coordenadas pueden ser null; $errorMensaje describe el motivo del fallo.  Ver docs/geocodificacion.md § 2.1.

Metodos publicos:

- `function __construct( public readonly bool $exito, public readonly ?string $tipoVia, public readonly ?string $nombreVia, public readonly ?TipoNumeracion $tipoNumeracion, public readonly ?string $numero, public readonly ?string $portal, public readonly ?string $escalera, public readonly ?string $piso, public readonly ?string $puerta, public readonly ?string $codigoPostal, public readonly ?string $municipio, public readonly ?float $latitud, public readonly ?float $longitud, public readonly string $proveedor, public readonly ?string $errorMensaje = null, )`
  _Sin resumen PHPDoc._
- `function fallo(string $proveedor, string $errorMensaje): self`
  Crea un resultado de fallo.

### `App\Services\HistoriaSocialService`

- Tipo: class.
- Fichero: `vida/app/Services/HistoriaSocialService.php:22`.
- Resumen: Servicio de Historia Social (stub).

Centraliza las consultas sobre la línea de tiempo del expediente de un ciudadano. La implementación completa corresponde al módulo Intervencion; este stub expone únicamente lo necesario para la integración con el módulo de Mensajes.  cuando el módulo de Intervención esté operativo.

Metodos publicos:

- `function obtenerEntradas(Ciudadano $ciudadano, User $profesional): Collection`
  Obtiene todas las entradas de la Historia Social de un ciudadano visibles para el profesional dado, incluyendo los mensajes que el TSR registró explícitamente en el expediente.
  `@return` Collection<int, array<string, mixed>>
- `function esTsr(User $usuario, Ciudadano $ciudadano): bool`
  Verifica si un usuario es el TSR responsable de la Historia Social de un ciudadano concreto.

### `App\Traits\Auditable`

- Tipo: trait.
- Fichero: `vida/app/Traits/Auditable.php:24`.
- Resumen: Trait para modelos Eloquent que manejan datos de ciudadanos.

Activa el AuditObserver para registrar automáticamente operaciones de escritura (crear, editar, eliminar). Las lecturas deben registrarse explícitamente mediante AuditService::registrarAcceso().  Cada modelo que use este trait debe implementar getCiudadanoId() si el ciudadano_id no es un atributo directo del modelo.

Metodos publicos:

- `function bootAuditable(): void`
  Registra el observer de auditoría al arrancar el modelo.
- `function audits(): MorphMany`
  Todos los registros de auditoría de este modelo.
  `@return` MorphMany<Audit>
- `function camposAuditables(): array`
  Campos incluidos en los snapshots datos_antes / datos_despues.
  `@return` list<string>
- `function getCiudadanoId(): ?int`
  Devuelve el ciudadano_id asociado a este registro.

### `App\Traits\TieneDireccion`

- Tipo: trait.
- Fichero: `vida/app/Traits/TieneDireccion.php:39`.
- Resumen: Trait que añade el modelo canónico de dirección a una entidad Eloquent.

Aplicable a cualquier modelo que tenga dirección postal: Ciudadano, Centro y cualquier entidad futura. Los campos se almacenan en la propia tabla de la entidad — no hay tabla centralizada de direcciones.  Las migraciones correspondientes añaden las columnas a cada tabla. Los modelos que usen este trait deben incluir los campos canónicos en su propia lista $fillable.  Ver docs/geocodificacion.md § 3.2 y docs/decisiones-tecnicas.md § 9.

Metodos publicos:

- `function initializeTieneDireccion(): void`
  Inyecta los casts de los campos de dirección al instanciar el modelo.
- `function direccionFormateada(): string`
  Devuelve la dirección estructurada como cadena legible.
- `function scopeSinNormalizar(Builder $query): Builder`
  Filtra entidades cuya dirección aún no ha sido normalizada por el geocoder.
  `@return` Builder<static>

### `App\Traits\Versionable`

- Tipo: trait.
- Fichero: `vida/app/Traits/Versionable.php:24`.
- Resumen: Trait que añade versionado automático a un modelo Eloquent.

Al actualizar un registro, guarda un snapshot completo del estado anterior en la tabla `versiones`. Esto permite consultar el estado de cualquier entidad en cualquier fecha pasada.  El snapshot guarda el estado ANTERIOR al cambio. El registro principal siempre tiene el estado actual.  Para conocer el estado en una fecha X: - Si X es posterior al último cambio → el registro actual es la respuesta. - Si no → la versión más reciente anterior a X.

Metodos publicos:

- `function versiones(): MorphMany`
  Todas las versiones históricas de este registro.
  `@return` MorphMany<Version>

