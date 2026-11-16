<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder de permisos atómicos del sistema VIDA 360.
 *
 * Cada permiso es un contrato del sistema: corresponde a una verificación
 * en algún punto del código (Policy, middleware o Gate). Crear un permiso
 * nuevo en este seeder implica implementar la verificación correspondiente.
 *
 * Los permisos siguen el formato recurso.accion, en español
 * (principio 4.4 de principios-vida360.md).
 *
 * Fuente de referencia: docs/modulo-usuarios-permisos.md sección 4.3
 *
 * @see RolesSeeder
 */
class PermisosSeeder extends Seeder
{
    /**
     * Permisos atómicos agrupados por recurso.
     * Cada entrada es [nombre, descripcion_legible].
     *
     * @var array<int, array{string, string}>
     */
    private const PERMISOS = [
        // -----------------------------------------------------------------
        // Ciudadano
        // -----------------------------------------------------------------
        ['ciudadano.ver_ficha',          'Ver la ficha básica del ciudadano (datos de identificación)'],
        ['ciudadano.ver_datos_contacto', 'Ver datos de contacto y localización del ciudadano'],
        ['ciudadano.leer',               'Leer la ficha completa del ciudadano (alias de ver_ficha para Policies de seguridad)'],
        ['ciudadano.crear',              'Dar de alta un nuevo ciudadano en el sistema'],
        ['ciudadano.editar',             'Modificar datos de identificación del ciudadano'],
        ['ciudadano.eliminar',           'Eliminar (baja lógica) un ciudadano del sistema'],
        ['ciudadano.editar_relaciones',  'Crear, editar y cerrar relaciones entre ciudadanos'],

        // -----------------------------------------------------------------
        // Historia Social
        // -----------------------------------------------------------------
        ['historia.leer',   'Consultar el contenido de una Historia Social'],
        ['historia.abrir',  'Abrir una nueva Historia Social'],
        ['historia.crear',  'Crear una nueva Historia Social (alias de historia.abrir para Policies de seguridad)'],
        ['historia.editar', 'Editar el contenido de una Historia Social asignada'],
        ['historia.cerrar', 'Cerrar o reactivar una Historia Social'],
        ['historia.eliminar', 'Eliminar (baja lógica) una Historia Social'],
        ['historia.ver_estado', 'Ver si la persona tiene Historia Social, su estado y el profesional de referencia'],

        // -----------------------------------------------------------------
        // Apunte (acto profesional)
        // -----------------------------------------------------------------
        ['apunte.crear',       'Crear apuntes en una Historia Social'],
        ['apunte.leer',        'Leer apuntes en una Historia Social (alias compuesto de leer_propio + leer_ajeno)'],
        ['apunte.leer_propio', 'Leer los propios apuntes'],
        ['apunte.leer_ajeno',  'Leer apuntes de otros profesionales en una Historia Social'],
        ['apunte.editar',      'Editar apuntes propios en una Historia Social'],
        ['apunte.eliminar',    'Eliminar apuntes propios en una Historia Social'],

        // -----------------------------------------------------------------
        // Plan de Intervención
        // -----------------------------------------------------------------
        ['plan.leer',     'Consultar un Plan de Intervención'],
        ['plan.crear',    'Crear un Plan de Intervención'],
        ['plan.editar',   'Editar un Plan de Intervención existente'],
        ['plan.eliminar', 'Eliminar (baja lógica) un Plan de Intervención'],

        // -----------------------------------------------------------------
        // Prestaciones
        // -----------------------------------------------------------------
        ['prestacion.asignar', 'Asignar prestaciones a una Historia Social'],

        // Solicitudes de prestación (fase 3 bis, docs/modulo-solicitudes-expedientes.md §5)
        ['solicitud.crear', 'Crear solicitudes de prestación y registrar prestaciones directas'],
        ['solicitud.ver', 'Ver las solicitudes y prestaciones de las personas que se atienden'],
        ['solicitud.tramitar', 'Tramitar solicitudes de la bandeja de su unidad'],
        ['solicitud.registrar_manual', 'Registrar a mano estado, incidencias y resolución de un expediente'],
        ['prestacion_persona.gestionar', 'Registrar inicio, suspensión o fin de una prestación de la persona'],

        // -----------------------------------------------------------------
        // Usuarios y administración
        // -----------------------------------------------------------------
        ['usuario.crear',    'Dar de alta usuarios en el sistema'],
        ['usuario.editar',   'Modificar datos de usuarios existentes'],
        ['usuario.dar_baja', 'Dar de baja usuarios del sistema'],

        // -----------------------------------------------------------------
        // Centros
        // -----------------------------------------------------------------
        ['centro.gestionar', 'Crear, modificar y desactivar centros de servicios sociales'],

        // -----------------------------------------------------------------
        // Configuración del sistema
        // -----------------------------------------------------------------
        ['configuracion.acceder', 'Acceder al backoffice de configuración del sistema'],

        // -----------------------------------------------------------------
        // Trazabilidad y auditoría
        // -----------------------------------------------------------------
        ['trazabilidad.consultar', 'Consultar el registro de accesos a expedientes (auditoría visible)'],

        // -----------------------------------------------------------------
        // Colectivos especialmente protegidos
        // -----------------------------------------------------------------
        ['colectivo_protegido.solicitar_acceso', 'Solicitar acceso a Historia de ciudadano especialmente protegido'],
        ['colectivo_protegido.aprobar_acceso',   'Aprobar o denegar solicitudes de acceso a ciudadanos protegidos'],

        // -----------------------------------------------------------------
        // Registro de Atención
        // -----------------------------------------------------------------
        ['atencion.crear',      'Crear un Registro de Atención'],
        ['atencion.leer',       'Consultar el historial de atenciones de un ciudadano'],
        ['atencion.leer_ajeno', 'Ver atenciones registradas por otros profesionales fuera de la propia UO'],

        // Citas (docs/modulo-citas.md §7)
        ['citas.solicitar',  'Crear solicitudes de cita'],
        ['citas.gestionar',  'Dar, reprogramar y cancelar citas; bandeja de citación'],
        ['citas.atender',    'Marcar incomparecencias, registrar acompañantes y pedir cambios en las citas propias'],
        ['citas.supervisar', 'Reasignar por ausencia, cancelación retroactiva y correcciones de marcado'],
        ['cita.ver_historial', 'Ver el historial de citas de la persona en su ficha, de cualquier centro'],

        // Documentos de la persona (fase 4, docs/roles-consulta-basica-tramitacion.md §3.3)
        ['documento.ver_carpeta', 'Ver la carpeta completa de documentos de las personas que puede consultar'],
        ['documento.subir', 'Subir documentos a la carpeta de la persona o a una solicitud'],
        ['documento.gestionar_propios', 'Ver, sustituir, recatalogar y retirar los documentos propios durante el día de la subida'],
    ];

    /**
     * Crea todos los permisos atómicos en la base de datos.
     * Si ya existen, los omite sin error (idempotente).
     */
    public function run(): void
    {
        // Spatie recomienda limpiar la caché antes de seedear permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISOS as [$nombre, $descripcion]) {
            Permission::firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'web'],
            );
        }

        $this->command->info('✓ '.count(self::PERMISOS).' permisos atómicos creados o verificados.');
    }
}
