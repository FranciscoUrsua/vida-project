<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

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
 * @see \Database\Seeders\RolesSeeder
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

        // -----------------------------------------------------------------
        // Historia Social
        // -----------------------------------------------------------------
        ['historia.leer',   'Consultar el contenido de una Historia Social'],
        ['historia.abrir',  'Abrir una nueva Historia Social'],
        ['historia.crear',  'Crear una nueva Historia Social (alias de historia.abrir para Policies de seguridad)'],
        ['historia.editar', 'Editar el contenido de una Historia Social asignada'],
        ['historia.cerrar', 'Cerrar o reactivar una Historia Social'],
        ['historia.eliminar', 'Eliminar (baja lógica) una Historia Social'],

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
    ];

    /**
     * Crea todos los permisos atómicos en la base de datos.
     * Si ya existen, los omite sin error (idempotente).
     *
     * @return void
     */
    public function run(): void
    {
        // Spatie recomienda limpiar la caché antes de seedear permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISOS as [$nombre, $descripcion]) {
            Permission::firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'web'],
            );
        }

        $this->command->info('✓ ' . count(self::PERMISOS) . ' permisos atómicos creados o verificados.');
    }
}
