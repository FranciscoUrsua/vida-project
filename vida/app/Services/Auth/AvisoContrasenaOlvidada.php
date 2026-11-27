<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Recuperación de contraseña sin correo (27 nov 2026): quien la ha olvidado lo indica en la
 * pantalla de acceso y VIDA avisa a quien puede ayudarle. Atajo mientras la aplicación no envía
 * correos (BACKLOG).
 *
 * Destinatarios del aviso: la supervisión de las UO donde el usuario está adscrito (comprueba en
 * persona que es quien dice) y `adm_usuarios` cuyo ámbito incluye alguna de esas UO (le asigna
 * una contraseña nueva en el backoffice). Si no hay ninguno, `adm_sistema`. Nunca el propio
 * usuario. Decisión del desarrollador: **solo se avisa**; la supervisión no restablece
 * contraseñas.
 *
 * No revela si el correo existe: quien llama da siempre la misma respuesta. Las cuentas
 * desactivadas (RN-20) no generan aviso, y tampoco se repite un aviso de la misma cuenta en
 * menos de una hora.
 */
class AvisoContrasenaOlvidada
{
    /** Título del aviso: identifica también los avisos ya enviados de una cuenta. */
    public const TITULO = 'Usuario bloqueado: no recuerda su contraseña';

    /** Minutos durante los que no se repite el aviso de una misma cuenta. */
    private const MINUTOS_SIN_REPETIR = 60;

    /**
     * @param AlertaService $alertas
     */
    public function __construct(private readonly AlertaService $alertas) {}

    /**
     * Avisa a la supervisión y a la administración de usuarios de la cuenta con ese correo, si
     * existe, está activa y no se avisó hace poco.
     *
     * @param string $email Correo escrito en la pantalla de acceso.
     *
     * @return int Avisos creados (0 si no hay cuenta, está desactivada o ya se avisó): solo para tests y registro, nunca para la respuesta.
     */
    public function avisar(string $email): int
    {
        $usuario = User::query()->noDesactivados()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

        if ($usuario === null || $this->avisadoHacePoco($usuario)) {
            return 0;
        }

        $destinatarios = $this->destinatarios($usuario);
        if ($destinatarios->isEmpty()) {
            Log::warning('Contraseña olvidada sin nadie a quien avisar', ['usuario_id' => $usuario->id]);

            return 0;
        }

        foreach ($destinatarios as $destinatario) {
            $this->alertas->crear([
                'tipo' => TipoAlerta::Aviso,
                'origen_type' => User::class,
                'origen_id' => $usuario->id,
                'titulo' => self::TITULO,
                'cuerpo' => $this->cuerpo($usuario),
                'destinatario_type' => DestinatarioType::Usuario,
                'destinatario_usuario_id' => $destinatario->id,
            ]);
        }

        return $destinatarios->count();
    }

    /**
     * Supervisión de sus UO y adm_usuarios de su ámbito; si no hay ninguno, adm_sistema.
     *
     * @param User $usuario Cuenta bloqueada.
     *
     * @return Collection<int, User>
     */
    private function destinatarios(User $usuario): Collection
    {
        $uoIds = $usuario->adscripcionesVigentes()->pluck('unidad_organizativa_id')->all();

        // Adscritos ahora a alguna de sus UO
        $adscritos = UsuarioUo::query()->vigentes()->whereIn('unidad_organizativa_id', $uoIds)->pluck('usuario_id');
        $supervision = User::query()->noDesactivados()->role('supervision')->whereIn('id', $adscritos)->get();

        // adm_usuarios gestiona su UO y las que cuelgan de ella (AmbitoGestionUsuarios)
        $admUsuarios = User::query()->noDesactivados()->role('adm_usuarios')->get()
            ->filter(fn (User $adm) => array_intersect($adm->uoSubtreeIds(), $uoIds) !== []);

        $destinatarios = $supervision->concat($admUsuarios);
        if ($destinatarios->isEmpty()) {
            $destinatarios = User::query()->noDesactivados()->role('adm_sistema')->get();
        }

        return $destinatarios->reject(fn (User $u) => $u->id === $usuario->id)->unique('id')->values();
    }

    /**
     * Si ya se avisó de esta cuenta hace menos de una hora.
     *
     * @param User $usuario Cuenta bloqueada.
     *
     * @return bool
     */
    private function avisadoHacePoco(User $usuario): bool
    {
        return Alerta::query()
            ->where('origen_type', User::class)
            ->where('origen_id', $usuario->id)
            ->where('titulo', self::TITULO)
            ->where('created_at', '>=', now()->subMinutes(self::MINUTOS_SIN_REPETIR))
            ->exists();
    }

    /**
     * Texto del aviso: quién es y qué hacer, sin pedir ni enviar contraseñas por mensaje.
     *
     * @param User $usuario Cuenta bloqueada.
     *
     * @return string
     */
    private function cuerpo(User $usuario): string
    {
        $puesto = $usuario->profesional?->cargo?->nombre;
        $quien = $usuario->nombreVisible().($puesto ? " ({$puesto})" : '').' · '.$usuario->email;

        return "{$quien} ha indicado en la pantalla de acceso que no recuerda su contraseña y no puede entrar en VIDA.\n\n"
            .'Comprueba en persona que es quien dice. Después, la administración de usuarios le asigna una contraseña nueva '
            .'en el backoffice (Usuarios → editar el usuario → Contraseña), y la persona la cambia al entrar desde su menú '
            ."(«Cambiar contraseña»).\n\nNo pidas ni envíes contraseñas por mensaje.";
    }
}
