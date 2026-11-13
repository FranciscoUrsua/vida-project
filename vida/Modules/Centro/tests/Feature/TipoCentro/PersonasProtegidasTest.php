<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Enums\AccionAuditEnum;
use App\Models\AccesoProtegido;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use App\Services\AccesoExpediente;
use App\Services\AmbitoUsuario;
use App\Services\PresentacionPersona;
use App\Services\ResolucionAccesoProtegido;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Mensajes\Models\Alerta;
use Modules\Supervision\Http\Livewire\AsignacionesPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo J — Personas protegidas (TF-TCE-48 a 54 y 60; addenda D4, RN-17 a 19).
 *
 * Restricción de dominio crítica (CLAUDE.md §3): una persona protegida solo la
 * gestiona el personal adscrito al centro de su historia (y, por la salvedad del
 * 12 nov, `intervencion` de una UO superior con el centro en su ámbito); el resto
 * necesita un acceso concedido por el director del centro.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class PersonasProtegidasTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    private Ciudadano $protegida;

    private HistoriaSocial $historia;

    private User $directoraVallecas;

    private User $tsVallecas;

    /**
     * Escenario común con una persona protegida atendida en el CIAM de Vallecas,
     * su directora y una trabajadora social del centro.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();

        [$this->protegida, $this->historia] = $this->personaEn($this->uoCiamVallecas->id, protegida: true, documento: '11111111H');
        $this->directoraVallecas = $this->directora('directora.vallecas', $this->ciamVallecas);
        $this->tsVallecas = $this->usuario('ts.vallecas', 'intervencion', $this->uoCiamVallecas);
    }

    /**
     * Persona con historia abierta en una UO y un documento para el buscador.
     *
     * @param int $uoId
     * @param bool $protegida
     * @param string $documento
     *
     * @return array{0: Ciudadano, 1: HistoriaSocial}
     */
    private function personaEn(int $uoId, bool $protegida, string $documento): array
    {
        $persona = Ciudadano::factory()->create(['colectivo_extra_protegido' => $protegida]);
        $historia = HistoriaSocial::create(['ciudadano_id' => $persona->id, 'unidad_organizativa_id' => $uoId, 'estado' => 'abierta', 'ciudadano_protegido' => $protegida]);
        CiudadanoIdentificador::create([
            'ciudadano_id' => $persona->id, 'tipo' => 'nif', 'valor' => $documento,
            'fecha_inicio' => '2020-01-01', 'verificado' => false, 'fuente' => 'manual',
        ]);

        return [$persona, $historia];
    }

    /**
     * Lo que ve el usuario de una persona: [scope de historia, scope de persona,
     * policy de persona, policy de historia, nombre en listados, nivel en el buscador].
     *
     * @param User $usuario
     * @param Ciudadano $persona
     * @param HistoriaSocial $historia
     * @param string $documento
     *
     * @return array{0: bool, 1: bool, 2: bool, 3: bool, 4: string, 5: int|null}
     */
    private function loQueVe(User $usuario, Ciudadano $persona, HistoriaSocial $historia, string $documento): array
    {
        $this->actingAs($usuario);
        app(AmbitoUsuario::class)->olvidar();

        $resultados = Livewire::test(BuscarCiudadanoPage::class)
            ->set('campoBusqueda', 'doc')->set('query', $documento)->call('buscar')->get('resultados');

        return [
            HistoriaSocial::whereKey($historia->id)->exists(),
            Ciudadano::whereKey($persona->id)->exists(),
            Gate::forUser($usuario)->allows('view', $persona),
            Gate::forUser($usuario)->allows('view', $historia),
            PresentacionPersona::paraListado($persona, $usuario)['nombre'],
            $resultados[0]['nivel'] ?? null,
        ];
    }

    /** TF-TCE-48 — La UO superior (funcional y orgánica) no ve a la persona protegida; a la no protegida, sí. */
    #[Test]
    public function tf_tce_48_la_uo_superior_no_ve_a_la_persona_protegida(): void
    {
        [$libre, $historiaLibre] = $this->personaEn($this->uoCiamVallecas->id, protegida: false, documento: '22222222J');

        foreach ([$this->coordIgualdad, $this->jefaJuntaVallecas] as $usuario) {
            $this->assertSame(
                [false, false, false, false, PresentacionPersona::NOMBRE_PROTEGIDO, 3],
                $this->loQueVe($usuario, $this->protegida, $this->historia, '11111111H'),
                "{$usuario->email} no debía ver a la persona protegida",
            );
            [$scopeHistoria, $scopePersona, $policy] = $this->loQueVe($usuario, $libre, $historiaLibre, '22222222J');
            $this->assertTrue($scopeHistoria && $scopePersona && $policy, "{$usuario->email} debía ver a la persona no protegida");
        }
    }

    /** TF-TCE-49 — El personal adscrito al centro la ve con nivel 1. */
    #[Test]
    public function tf_tce_49_el_centro_si_la_ve(): void
    {
        foreach ([$this->tsVallecas, $this->directoraVallecas] as $usuario) {
            [$scopeHistoria, $scopePersona, $policyPersona, $policyHistoria, $nombre, $nivel] = $this->loQueVe($usuario, $this->protegida, $this->historia, '11111111H');
            $this->assertTrue($scopeHistoria && $scopePersona && $policyPersona && $policyHistoria, "{$usuario->email} debía verla");
            $this->assertNotSame(PresentacionPersona::NOMBRE_PROTEGIDO, $nombre);
            $this->assertSame(1, $nivel);
        }
    }

    /** TF-TCE-50 — Solo la directora concede; el acceso dura hasta la caducidad y se audita. */
    #[Test]
    public function tf_tce_50_concede_el_director_del_centro(): void
    {
        $acceso = $this->solicitud($this->coordIgualdad);
        $servicio = app(ResolucionAccesoProtegido::class);

        foreach ([$this->coordIgualdad, $this->jefaJuntaVallecas] as $usuario) {
            try {
                $servicio->aprobar($acceso, $usuario);
                $this->fail("{$usuario->email} no debía poder resolver");
            } catch (AuthorizationException) {
            }
        }
        $this->assertSame('pendiente', $acceso->fresh()->estado);

        $servicio->aprobar($acceso, $this->directoraVallecas, Carbon::parse('2026-11-20'));
        $this->assertSame('aprobado', $acceso->fresh()->estado);
        $this->assertSame($this->directoraVallecas->id, $acceso->fresh()->aprobado_por);
        $this->assertTrue(Alerta::where('origen_type', AccesoProtegido::class)->where('origen_id', $acceso->id)->where('destinatario_usuario_id', $this->coordIgualdad->id)->exists());

        // La ve hasta la caducidad, y su lectura queda como acceso restringido
        $this->assertTrue($this->loQueVe($this->coordIgualdad, $this->protegida, $this->historia, '11111111H')[2]);
        app(AccesoExpediente::class)->historia($this->coordIgualdad, $this->historia->id);
        $this->assertTrue(Audit::where('user_id', $this->coordIgualdad->id)->where('accion', AccionAuditEnum::AccesoRestringido->value)->where('ciudadano_id', $this->protegida->id)->exists());

        $this->travelTo(Carbon::parse('2026-11-21 09:00'));
        $this->assertFalse(Gate::forUser($this->coordIgualdad)->allows('view', $this->protegida));
    }

    /** TF-TCE-50 (denegar) — Denegar exige motivo y lo guarda. */
    #[Test]
    public function tf_tce_50_denegar_con_motivo(): void
    {
        $acceso = $this->solicitud($this->coordIgualdad);
        $servicio = app(ResolucionAccesoProtegido::class);

        try {
            $servicio->denegar($acceso, $this->directoraVallecas, '  ');
            $this->fail('Denegar sin motivo debía rechazarse');
        } catch (\DomainException) {
        }

        $servicio->denegar($acceso, $this->directoraVallecas, 'No consta intervención en curso');
        $this->assertSame('denegado', $acceso->fresh()->estado);
        $this->assertSame('No consta intervención en curso', $acceso->fresh()->motivo_resolucion);
        $this->assertFalse(Gate::forUser($this->coordIgualdad)->allows('view', $this->protegida));
    }

    /** TF-TCE-51 — Sin director, resuelve la supervisión adscrita al centro, no la funcional. */
    #[Test]
    public function tf_tce_51_sin_director_la_supervision_del_centro(): void
    {
        $this->ciamVallecas->directorActivo()?->update(['fecha_fin' => today()->subDay()]);
        $supervisoraCentro = $this->usuario('supervisora.vallecas', 'supervision', $this->uoCiamVallecas);
        $acceso = $this->solicitud($this->tecnicaArea);
        $servicio = app(ResolucionAccesoProtegido::class);

        $this->assertFalse($servicio->puedeResolver($this->coordIgualdad, $acceso));
        // Sin director, resuelve cualquiera con supervisión adscrita al centro (también la antigua directora)
        $this->assertTrue($servicio->puedeResolver($supervisoraCentro, $acceso));
        $this->assertTrue($servicio->puedeResolver($this->directoraVallecas, $acceso));
    }

    /** TF-TCE-52 — Nadie resuelve su propia solicitud. */
    #[Test]
    public function tf_tce_52_nadie_resuelve_lo_suyo(): void
    {
        // La directora de Vallecas pide acceso a una persona protegida de Usera; aunque
        // también resolviera allí (directora de Usera), no resuelve la suya
        [$otra] = $this->personaEn($this->uoCiamUsera->id, protegida: true, documento: '33333333P');
        $this->ciamUsera->nombrarDirector(['profesional_id' => $this->directoraVallecas->profesional_id, 'fecha_inicio' => '2026-01-01']);
        $acceso = AccesoProtegido::create(['usuario_id' => $this->directoraVallecas->id, 'solicitante_id' => $this->directoraVallecas->id, 'ciudadano_id' => $otra->id, 'justificacion' => 'Coordinación de un caso compartido entre centros', 'estado' => 'pendiente']);

        $this->assertTrue(app(ResolucionAccesoProtegido::class)->resolutores($otra->id)->contains('id', $this->directoraVallecas->id));
        $this->expectException(AuthorizationException::class);
        app(ResolucionAccesoProtegido::class)->aprobar($acceso, $this->directoraVallecas);
    }

    /** TF-TCE-53 — Bandeja: la supervisión del centro la ve y la resuelve; la funcional, sin identidad y sin resolver. */
    #[Test]
    public function tf_tce_53_bandeja_de_asignaciones(): void
    {
        $pendiente = AsignacionPendiente::create([
            'ciudadano_id' => $this->protegida->id, 'tipo' => TipoAsignacionPendiente::SinCentro, 'tipo_centro' => 'ciam',
            'centros_candidatos' => [$this->ciamVallecas->id], 'estado' => EstadoAsignacionPendiente::Pendiente, 'motivo' => MotivoAsignacionPendiente::SinCobertura,
        ]);

        // Coordinación funcional: «Persona con protección especial» y la resolución se rechaza
        $this->actingAs($this->coordIgualdad);
        Livewire::test(AsignacionesPage::class)
            ->assertSee(PresentacionPersona::NOMBRE_PROTEGIDO)
            ->call('iniciar', $pendiente->id, 'descartar')
            ->set('motivo', 'La atiende otro recurso')
            ->call('resolver')
            ->assertHasErrors('motivo');
        $this->assertSame(EstadoAsignacionPendiente::Pendiente, $pendiente->fresh()->estado);

        // Supervisión adscrita al centro (la directora): con identidad, y resuelve
        $this->actingAs($this->directoraVallecas);
        app(AmbitoUsuario::class)->olvidar();
        Livewire::test(AsignacionesPage::class)
            ->assertDontSee(PresentacionPersona::NOMBRE_PROTEGIDO)
            ->call('iniciar', $pendiente->id, 'descartar')
            ->set('motivo', 'La atiende otro recurso')
            ->call('resolver')
            ->assertHasNoErrors();
        $this->assertNotSame(EstadoAsignacionPendiente::Pendiente, $pendiente->fresh()->estado);
    }

    /** TF-TCE-54 — El aviso de una solicitud nueva va a la directora, no a la supervisión superior. */
    #[Test]
    public function tf_tce_54_el_aviso_de_solicitud_va_al_director(): void
    {
        $this->actingAs($this->coordIgualdad);
        Livewire::test(BuscarCiudadanoPage::class)
            ->call('solicitarAcceso', $this->protegida->id, 'Seguimiento del protocolo de coordinación');

        $acceso = AccesoProtegido::where('ciudadano_id', $this->protegida->id)->firstOrFail();
        $destinatarios = Alerta::where('origen_type', AccesoProtegido::class)->where('origen_id', $acceso->id)->pluck('destinatario_usuario_id')->all();

        $this->assertSame([$this->directoraVallecas->id], $destinatarios);
    }

    /** TF-TCE-60 — Salvedad: `intervencion` de una UO superior con el centro en su ámbito accede; sin el rol, no. */
    #[Test]
    public function tf_tce_60_salvedad_del_rol_intervencion_en_uo_superior(): void
    {
        [$scopeHistoria, $scopePersona, $policyPersona, $policyHistoria, $nombre, $nivel] = $this->loQueVe($this->tecnicaIgualdad, $this->protegida, $this->historia, '11111111H');
        $this->assertTrue($scopeHistoria && $scopePersona && $policyPersona && $policyHistoria);
        $this->assertNotSame(PresentacionPersona::NOMBRE_PROTEGIDO, $nombre);
        $this->assertSame(1, $nivel);

        // Fuera de su ámbito (CSS Norte), no
        [$norte, $historiaNorte] = $this->personaEn($this->uoCssNorte->id, protegida: true, documento: '44444444A');
        $this->assertSame([false, false, false, false], array_slice($this->loQueVe($this->tecnicaIgualdad, $norte, $historiaNorte, '44444444A'), 0, 4));

        // Sin el rol intervencion, tampoco
        $this->tecnicaIgualdad->syncRoles(['supervision']);
        $this->assertSame([false, false, false, false], array_slice($this->loQueVe($this->tecnicaIgualdad->fresh(), $this->protegida, $this->historia, '11111111H'), 0, 4));

        // La coordinación (solo supervision, misma UO) sigue sin verla
        $this->assertFalse($this->loQueVe($this->coordIgualdad, $this->protegida, $this->historia, '11111111H')[2]);
    }

    /**
     * Solicitud de acceso pendiente a la persona protegida.
     *
     * @param User $usuario
     *
     * @return AccesoProtegido
     */
    private function solicitud(User $usuario): AccesoProtegido
    {
        return AccesoProtegido::create([
            'usuario_id' => $usuario->id, 'solicitante_id' => $usuario->id, 'ciudadano_id' => $this->protegida->id,
            'justificacion' => 'Seguimiento del protocolo de coordinación', 'estado' => 'pendiente',
        ]);
    }
}
