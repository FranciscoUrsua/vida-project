<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    Modules\Organizacion\Providers\OrganizacionServiceProvider::class,
    Modules\Usuarios\Providers\UsuariosServiceProvider::class,
    Modules\Centro\Providers\CentroServiceProvider::class,
    Modules\Prestaciones\Providers\PrestacionesServiceProvider::class,
    Modules\Mensajes\Providers\MensajesServiceProvider::class,
    Modules\Agenda\Providers\AgendaServiceProvider::class,
    Modules\Documentos\Providers\DocumentosServiceProvider::class,
    Modules\Intervencion\Providers\IntervencionServiceProvider::class,
    Modules\Escalas\Providers\EscalasServiceProvider::class,
    App\Providers\GeocodificacionServiceProvider::class,
];
