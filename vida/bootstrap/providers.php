<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\GeocodificacionServiceProvider;
use Modules\Agenda\Providers\AgendaServiceProvider;
use Modules\Centro\Providers\CentroServiceProvider;
use Modules\Ciudadania\Providers\CiudadaniaServiceProvider;
use Modules\Documentos\Providers\DocumentosServiceProvider;
use Modules\Escalas\Providers\EscalasServiceProvider;
use Modules\Atencion\Providers\AtencionServiceProvider;
use Modules\Intervencion\Providers\IntervencionServiceProvider;
use Modules\Mensajes\Providers\MensajesServiceProvider;
use Modules\Organizacion\Providers\OrganizacionServiceProvider;
use Modules\Prestaciones\Providers\PrestacionesServiceProvider;
use Modules\Usuarios\Providers\UsuariosServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    OrganizacionServiceProvider::class,
    UsuariosServiceProvider::class,
    CentroServiceProvider::class,
    PrestacionesServiceProvider::class,
    MensajesServiceProvider::class,
    AgendaServiceProvider::class,
    DocumentosServiceProvider::class,
    CiudadaniaServiceProvider::class,
    IntervencionServiceProvider::class,
    AtencionServiceProvider::class,
    EscalasServiceProvider::class,
    GeocodificacionServiceProvider::class,
];
