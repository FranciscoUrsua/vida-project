<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder de roles del sistema VIDA 360.
 *
 * Crea los 7 roles iniciales y les asigna los permisos atómicos
 * correspondientes según la especificación del módulo de usuarios
 * (docs/modulo-usuarios-permisos.md sección 2).
 *
 * Los roles son configurables desde el backoffice: el administrador
 * puede añadir o quitar permisos de un rol sin necesidad de desarrollo.
 * Lo que sí requiere código es crear un nuevo permiso atómico.
 *
 * Debe ejecutarse después de PermisosSeeder.
 *
 * @see PermisosSeeder
 */
class RolesSeeder extends Seeder
{
    /**
     * Definición de roles y sus permisos.
     * Clave: nombre del rol (guard web). Valor: lista de permisos atómicos.
     *
     * @var array<string, list<string>>
     */
    private const ROLES = [
        // -----------------------------------------------------------------
        // ROL 1: Administración del Sistema
        // Acceso global, configuración de toda la aplicación.
        // Nota: adm_sistema NO tiene acceso privilegiado sobre datos de ciudadanos;
        // también debe pasar los filtros de UO y colectivo protegido (principio 3.3).
        // -----------------------------------------------------------------
        'adm_sistema' => [
            'ciudadano.ver_ficha',
            'ciudadano.ver_datos_contacto',
            'ciudadano.leer',
            'ciudadano.crear',
            'ciudadano.editar',
            'ciudadano.eliminar',
            'historia.leer',
            'historia.abrir',
            'historia.crear',
            'historia.editar',
            'historia.cerrar',
            'historia.eliminar',
            'apunte.crear',
            'apunte.leer',
            'apunte.leer_propio',
            'apunte.leer_ajeno',
            'apunte.editar',
            'apunte.eliminar',
            'plan.leer',
            'plan.crear',
            'plan.editar',
            'plan.eliminar',
            'prestacion.asignar',
            'usuario.crear',
            'usuario.editar',
            'usuario.dar_baja',
            'centro.gestionar',
            'configuracion.acceder',
            'trazabilidad.consultar',
            'colectivo_protegido.solicitar_acceso',
            'colectivo_protegido.aprobar_acceso',
            'documento.ver_carpeta',
            'documento.subir',
        ],

        // -----------------------------------------------------------------
        // ROL 2: Supervisión
        // Lectura de su ámbito, auditoría, aprobación de accesos protegidos.
        // Solo lectura sobre datos de ciudadanos — nunca escritura.
        // -----------------------------------------------------------------
        'supervision' => [
            'solicitud.crear',
            'solicitud.ver',
            'solicitud.tramitar',
            'solicitud.registrar_manual',
            'prestacion_persona.gestionar',
            'ciudadano.ver_ficha',
            'ciudadano.ver_datos_contacto',
            'ciudadano.leer',
            'historia.leer',
            'apunte.leer',
            'apunte.leer_ajeno',
            // Sin `plan.leer` (fase 3, decisión del 14 nov 2026): supervision no lee ni edita
            // planes; quien los necesite tiene además el rol `intervencion`.
            'trazabilidad.consultar',
            'colectivo_protegido.aprobar_acceso',
            'atencion.leer',
            'atencion.leer_ajeno',
            'citas.solicitar',
            'citas.gestionar',
            'citas.atender',
            'citas.supervisar',
            'historia.ver_estado',
            'cita.ver_historial',
            'documento.ver_carpeta',
        ],

        // -----------------------------------------------------------------
        // ROL 3: Administración de Usuarios
        // Gestión de usuarios en su UO (no configura permisos por rol).
        // -----------------------------------------------------------------
        'adm_usuarios' => [
            'usuario.crear',
            'usuario.editar',
            'usuario.dar_baja',
        ],

        // -----------------------------------------------------------------
        // ROL 4: Intervención
        // Gestión completa de Historias en su UO; consulta libre fuera.
        // -----------------------------------------------------------------
        'intervencion' => [
            'solicitud.crear',
            'solicitud.ver',
            'prestacion_persona.gestionar',
            'ciudadano.ver_ficha',
            'ciudadano.ver_datos_contacto',
            'ciudadano.leer',
            'ciudadano.crear',
            'ciudadano.editar',
            'historia.leer',
            'historia.abrir',
            'historia.crear',
            'historia.editar',
            'historia.cerrar',
            'apunte.crear',
            'apunte.leer',
            'apunte.leer_propio',
            'apunte.leer_ajeno',
            'apunte.editar',
            'apunte.eliminar',
            'plan.leer',
            'plan.crear',
            'plan.editar',
            'prestacion.asignar',
            'colectivo_protegido.solicitar_acceso',
            'atencion.crear',
            'atencion.leer',
            'atencion.leer_ajeno',
            'citas.solicitar',
            'citas.atender',
            'ciudadano.editar_relaciones',
            'historia.ver_estado',
            'cita.ver_historial',
            'documento.ver_carpeta',
            'documento.subir',
        ],

        // -----------------------------------------------------------------
        // ROL 5: Tramitación
        // Apertura/cierre de Historias, gestión administrativa.
        // Sin acceso a contenido clínico/social ni apuntes profesionales.
        // -----------------------------------------------------------------
        'tramitacion' => [
            'solicitud.tramitar',
            'solicitud.registrar_manual',
            'prestacion_persona.gestionar',
            'ciudadano.ver_ficha',
            'ciudadano.ver_datos_contacto',
            'ciudadano.leer',
            'ciudadano.crear',
            'ciudadano.editar',
            'historia.abrir',
            'historia.crear',
            'historia.cerrar',
            'prestacion.asignar',
            'atencion.leer',
            'ciudadano.editar_relaciones',
            'historia.ver_estado',
            'cita.ver_historial',
            'citas.solicitar',
            'citas.gestionar',
            'documento.subir',
            'documento.gestionar_propios',
        ],

        // -----------------------------------------------------------------
        // ROL 6: Consulta Profesional Puntual
        // Acceso acotado a los ciudadanos y procesos asignados explícitamente.
        // -----------------------------------------------------------------
        'consulta_profesional' => [
            'ciudadano.ver_ficha',
            'ciudadano.leer',
            'historia.leer',
            'apunte.crear',      // Registro de resultado de cita como intervención puntual
            'apunte.leer',
            'apunte.leer_propio',
            'plan.leer',
            'documento.ver_carpeta',
        ],

        // -----------------------------------------------------------------
        // ROL 7: Consulta y Gestión Básica
        // Ficha del ciudadano y citas. Sin acceso a Historia ni apuntes.
        // -----------------------------------------------------------------
        'consulta_basica' => [
            'ciudadano.ver_ficha',
            'ciudadano.ver_datos_contacto',
            'ciudadano.leer',
            'ciudadano.editar',  // Modificar datos de identificación básicos
            'atencion.crear',
            'atencion.leer',
            'citas.solicitar',
            'citas.gestionar',
            'ciudadano.crear',
            'ciudadano.editar_relaciones',
            'historia.ver_estado',
            'cita.ver_historial',
            'documento.subir',
            'documento.gestionar_propios',
        ],
    ];

    /**
     * Crea los roles y les añade los permisos que les falten.
     *
     * Es aditivo (fase 4, TF-CBA-29): re-ejecutarlo no quita los permisos que se hayan
     * configurado en el backoffice. Retirar un permiso de un rol se hace con una
     * migración de datos, como la que quitó `plan.leer` a `supervision`.
     *
     * @return void
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ROLES as $nombreRol => $permisos) {
            $rol = Role::firstOrCreate(
                ['name' => $nombreRol, 'guard_name' => 'web'],
            );

            // givePermissionTo no duplica los que ya tiene; solo se pasan los que faltan
            $faltan = array_values(array_diff($permisos, $rol->permissions()->pluck('name')->all()));
            if ($faltan !== []) {
                $rol->givePermissionTo($faltan);
            }
        }

        $this->command->info('✓ '.count(self::ROLES).' roles creados o actualizados con sus permisos.');
    }
}
