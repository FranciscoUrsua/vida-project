<?php

namespace Modules\Agenda\Services;

use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Data\HorarioEfectivo;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Perfil horario por defecto de los profesionales de un centro.
 *
 * Ningún profesional debe quedar sin horario en el centro al que está adscrito:
 * sin perfil no aparece en el cuadrante ni entra en el sorteo de referencias.
 * Al adscribirse a la UO del centro recibe un perfil con el horario del centro,
 * marcado como «horario no personalizado» (`pendiente_verificar`) hasta que el
 * supervisor lo revisa y lo guarda, y la supervisión del centro recibe un aviso.
 *
 * Solo para usuarios con ficha de profesional: los perfiles técnicos no tienen
 * horario. Si el centro no tiene horario vigente no se crea nada.
 *
 * @see docs/modulo-agenda.md §2.3
 */
class PerfilHorarioPorDefectoService
{
    public function __construct(
        private readonly AlertaService $alertas,
        private readonly ConfiguracionEfectivaCentro $configuracion,
    ) {}

    /**
     * Da el horario del centro a quien acaba de adscribirse a su UO y avisa a
     * la supervisión del centro.
     *
     * @return Collection<int, PerfilHorarioProfesional> Perfiles creados.
     */
    public function alAdscribir(UsuarioUo $adscripcion): Collection
    {
        if ($adscripcion->fecha_fin !== null && $adscripcion->fecha_fin->isPast()) {
            return collect();
        }

        $usuario = $adscripcion->usuario;
        if ($usuario === null) {
            return collect();
        }

        // El pasado es inmutable: una adscripción con fecha anterior no reescribe cuadrantes pasados
        $desde = Carbon::parse($adscripcion->fecha_inicio)->max(today());

        return Centro::where('unidad_organizativa_id', $adscripcion->unidad_organizativa_id)->get()
            ->map(fn (Centro $centro) => $this->asignar($usuario, $centro, $desde))
            ->filter()
            ->each(fn (PerfilHorarioProfesional $perfil) => $this->avisar(
                $perfil->centro,
                $perfil,
                'Nuevo profesional en el centro: verifica su horario',
                "{$usuario->nombre_completo} se ha incorporado a {$perfil->centro->nombre} con el horario del centro. "
                    .'Revisa su perfil horario en Mi equipo y guárdalo para confirmarlo.',
            ))
            ->values();
    }

    /**
     * Da el horario del centro a una cuenta ya adscrita cuando se vincula a su
     * ficha de profesional (hasta entonces era un perfil técnico sin horario).
     *
     * @return Collection<int, PerfilHorarioProfesional> Perfiles creados.
     */
    public function alVincularProfesional(User $usuario): Collection
    {
        return $usuario->adscripcionesVigentes()->get()
            ->flatMap(fn (UsuarioUo $adscripcion) => $this->alAdscribir($adscripcion))
            ->values();
    }

    /**
     * Da el horario del centro a todos los profesionales adscritos que no
     * tienen perfil activo en él. Un único aviso a la supervisión si crea alguno.
     *
     * @return Collection<int, PerfilHorarioProfesional> Perfiles creados.
     */
    public function completarCentro(Centro $centro): Collection
    {
        if ($centro->unidad_organizativa_id === null) {
            return collect();
        }

        $creados = User::whereNotNull('profesional_id')
            ->whereHas('adscripcionesVigentes', fn ($q) => $q->where('unidad_organizativa_id', $centro->unidad_organizativa_id))
            ->orderBy('id')
            ->get()
            ->map(fn (User $usuario) => $this->asignar($usuario, $centro, today()))
            ->filter()
            ->values();

        if ($creados->isNotEmpty()) {
            $this->avisar(
                $centro,
                $centro,
                'Horarios por defecto: verifícalos',
                "{$creados->count()} profesionales de {$centro->nombre} no tenían horario y se les ha asignado el del centro. "
                    .'Revisa su perfil horario en Mi equipo y guárdalo para confirmarlo.',
            );
        }

        return $creados;
    }

    /**
     * Crea el perfil con el horario del centro si el profesional no tiene uno activo.
     *
     * @param Carbon $desde Fecha desde la que rige el perfil.
     *
     * @return PerfilHorarioProfesional|null El perfil creado, o null si no procede.
     */
    public function asignar(User $usuario, Centro $centro, Carbon $desde): ?PerfilHorarioProfesional
    {
        if ($usuario->profesional_id === null) {
            return null;
        }

        $tieneActivo = PerfilHorarioProfesional::activos()
            ->delCentro($centro->id)
            ->where('usuario_id', $usuario->id)
            ->exists();

        // Sin agenda configurada en el centro no hay perfil; el horario semanal es el efectivo (propio o del tipo)
        $efectivo = $this->horarioVigente($centro, $desde) === null ? null : $this->configuracion->horario($centro, $desde);

        if ($tieneActivo || $efectivo === null) {
            return null;
        }

        $franjas = $this->horarioHabitual($efectivo);

        return PerfilHorarioProfesional::create([
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'jornada_semanal_horas' => $this->jornada($franjas),
            'horario_habitual' => $franjas,
            'vigente_desde' => $desde->toDateString(),
            'vigente_hasta' => null,
            'activo' => true,
            'pendiente_verificar' => true,
        ]);
    }

    /**
     * Horario del centro vigente en una fecha (el más reciente si hay varios).
     */
    private function horarioVigente(Centro $centro, Carbon $fecha): ?HorarioCentro
    {
        $dia = $fecha->toDateString();

        return HorarioCentro::activos()
            ->delCentro($centro->id)
            ->whereDate('vigente_desde', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia))
            ->orderByDesc('vigente_desde')
            ->first();
    }

    /**
     * Franjas del perfil: una por día laborable del horario efectivo, de apertura a cierre.
     *
     * @return array<string, list<array{inicio: string, fin: string}>>
     */
    private function horarioHabitual(HorarioEfectivo $horario): array
    {
        $franja = ['inicio' => $horario->apertura, 'fin' => $horario->cierre];

        return collect($horario->diasLaborables)
            ->mapWithKeys(fn ($dia) => [(string) $dia => [$franja]])
            ->all();
    }

    /**
     * Horas semanales de las franjas.
     *
     * @param array<string, list<array{inicio: string, fin: string}>> $franjas
     */
    private function jornada(array $franjas): float
    {
        $minutos = collect($franjas)->flatten(1)->sum(
            fn (array $f) => Carbon::parse($f['inicio'])->diffInMinutes(Carbon::parse($f['fin']))
        );

        return round($minutos / 60, 2);
    }

    /**
     * Aviso a la supervisión de la UO del centro.
     */
    private function avisar(Centro $centro, Model $origen, string $titulo, string $cuerpo): void
    {
        $this->alertas->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => $origen::class,
            'origen_id' => $origen->getKey(),
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => 'supervision',
            'destinatario_uo_id' => $centro->unidad_organizativa_id,
        ]);
    }
}
