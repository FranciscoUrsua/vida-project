<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\GeocodificacionServiceProvider;
use Modules\Agenda\Providers\AgendaServiceProvider;
use Modules\Centro\Providers\CentroServiceProvider;
use Modules\Documentos\Providers\DocumentosServiceProvider;
use Modules\Escalas\Providers\EscalasServiceProvider;
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
    IntervencionServiceProvider::class,
    EscalasServiceProvider::class,
    GeocodificacionServiceProvider::class,
];
