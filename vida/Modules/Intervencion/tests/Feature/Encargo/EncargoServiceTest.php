<?php

namespace Modules\Intervencion\Tests\Feature\Encargo;

use App\Models\UsuarioUo;
use App\Services\AmbitoUsuario;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\ModalidadDerivacion;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\DerivacionService;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\EncargoTestSetup;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Modelo y servicio del encargo profesional (TF-ENC-01 a 13,
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * El encargo es una derivación sin plan especializado (EP-1): la crea el TSR, la asigna la
 * dirección o supervisión del centro de destino a un profesional con
 * `derivacion.registrar_resultado` y la cierra ese profesional con su resultado. Los
 * negativos fallan si se quita la restricción que protegen.
 */
class EncargoServiceTest extends TestCase
{
    use EncargoTestSetup;
    use RefreshDatabase;

    private EncargoService $servicio;

    /**
     * Escenario del encargo profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEncargo();
        $this->servicio = app(EncargoService::class);
    }

    /** TF-ENC-01 — Encargar desde la Historia Social: pendiente, sin plan; evento, apunte y alerta anónima. */
    #[Test]
    public function tf_enc_01_encargar_desde_la_historia_social(): void
    {
        $encargo = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);

        $this->assertSame(ModalidadDerivacion::Encargo, $encargo->modalidad);
        $this->assertSame(EstadoDerivacion::Pendiente, $encargo->estado);
        $this->assertNull($encargo->plan_origen_id);
        $this->assertNull($encargo->prestacion_id);
        $this->assertNull($encargo->tipo_plan_id);
        $this->assertSame(1, $encargo->eventos()->count());
        $this->assertTrue(Apunte::withoutGlobalScopes()->where('historia_id', $this->historiaAna->id)
            ->where('tipo', TipoApunte::Derivacion)->where('apuntable_id', $encargo->id)->exists());

        $alerta = Alerta::pendientesPara($this->directoraVallecas)
            ->where('origen_type', Derivacion::class)->where('origen_id', $encargo->id)->sole();
        $this->assertStringNotContainsString($this->ana->nombre, $alerta->titulo.$alerta->cuerpo);
        $this->assertStringNotContainsString($this->ana->apellido1, $alerta->titulo.$alerta->cuerpo);
    }

    /** TF-ENC-02 — Encargar desde el plan principal y desde un objetivo. */
    #[Test]
    public function tf_enc_02_encargar_desde_el_plan_y_un_objetivo(): void
    {
        $desdePlan = $this->servicio->encargar($this->historiaAna, $this->pisoAna, null, $this->ciamVallecas, $this->areaJuridica, 'Orientación jurídica', [], $this->tsNorte);
        $this->assertSame($this->pisoAna->id, $desdePlan->plan_origen_id);
        $this->assertNull($desdePlan->plan_objetivo_id);

        $desdeObjetivo = $this->servicio->encargar($this->historiaAna, $this->pisoAna, $this->objetivoAna1, $this->ciamUsera, $this->areaJuridica, 'Orientación jurídica', [], $this->tsNorte);
        $this->assertSame([$this->pisoAna->id, $this->objetivoAna1->id], [$desdeObjetivo->plan_origen_id, $desdeObjetivo->plan_objetivo_id]);

        // Un objetivo de otro plan, o un objetivo sin plan, no vale.
        $ajeno = $this->objetivoConIndicador($this->pisoMarta, 'Otro', 'si_no', 1);
        $this->assertRechazado(fn () => $this->servicio->encargar($this->historiaAna, $this->pisoAna, $ajeno, $this->cssNorte, $this->areaJuridica, 'Motivo', [], $this->tsNorte));
        $this->assertRechazado(fn () => $this->servicio->encargar($this->historiaAna, null, $this->objetivoAna1, $this->cssNorte, $this->areaJuridica, 'Motivo', [], $this->tsNorte));
    }

    /** TF-ENC-03 — Quién encarga: quien no gestiona la historia ni edita el plan, o sin centro (RN-15). [negativo] */
    #[Test]
    public function tf_enc_03_quien_encarga(): void
    {
        // Sin gestión de la historia ni edición del plan principal.
        foreach ([$this->psicologaVallecas, $this->abogadaVallecas, $this->supNorte] as $actor) {
            $this->assertRechazado(fn () => $this->servicio->encargar($this->historiaAna, null, null, $this->ciamVallecas, $this->areaJuridica, 'Motivo', [], $actor));
            $this->assertRechazado(fn () => $this->servicio->encargar($this->historiaAna, $this->pisoAna, null, $this->ciamVallecas, $this->areaJuridica, 'Motivo', [], $actor));
        }

        // Gestiona la historia desde una UO superior, pero no está adscrita a ningún centro (RN-15).
        $this->assertTrue(Gate::forUser($this->tecnicaArea)->allows('update', $this->historiaAna));
        $this->assertRechazado(fn () => $this->servicio->encargar($this->historiaAna, null, null, $this->ciamVallecas, $this->areaJuridica, 'Motivo', [], $this->tecnicaArea), 'adscrit');

        $this->assertSame(0, Derivacion::count());
    }

    /** TF-ENC-04 — Sin duplicados en curso al mismo centro; a otro centro y tras cerrarse, sí. */
    #[Test]
    public function tf_enc_04_sin_duplicados(): void
    {
        $primero = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);

        $this->assertRechazado(fn () => $this->encargoPendiente($this->historiaAna, $this->ciamVallecas));
        $this->assertSame($this->ciamUsera->id, $this->encargoPendiente($this->historiaAna, $this->ciamUsera)->centro_destino_id);

        // Aceptado sigue en curso.
        $this->servicio->aceptar($primero, $this->abogadaVallecas, $this->directoraVallecas);
        $this->assertRechazado(fn () => $this->encargoPendiente($this->historiaAna, $this->ciamVallecas));

        $this->servicio->cerrar($primero->refresh(), 'Hecho', $this->abogadaVallecas);
        $this->assertSame(EstadoDerivacion::Pendiente, $this->encargoPendiente($this->historiaAna, $this->ciamVallecas)->estado);
    }

    /** TF-ENC-05 — Motivo obligatorio. [negativo] */
    #[Test]
    public function tf_enc_05_motivo_obligatorio(): void
    {
        $this->assertRechazado(fn () => $this->servicio->encargar($this->historiaAna, null, null, $this->ciamVallecas, $this->areaJuridica, "   \n ", [], $this->tsNorte));
        $this->assertSame(0, Derivacion::count());
    }

    /** TF-ENC-06 — Aceptar: aceptada, sin plan especializado, con profesional; alertas al TSR y al profesional. */
    #[Test]
    public function tf_enc_06_aceptar(): void
    {
        $encargo = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);
        $planesAntes = PlanDeIntervencion::withoutGlobalScopes()->count();

        $this->servicio->aceptar($encargo, $this->abogadaVallecas, $this->directoraVallecas);

        $encargo->refresh();
        $this->assertSame(EstadoDerivacion::Aceptada, $encargo->estado);
        $this->assertSame($this->abogadaVallecas->id, $encargo->profesional_responsable_id);
        $this->assertNull($encargo->plan_especializado_id);
        $this->assertSame($planesAntes, PlanDeIntervencion::withoutGlobalScopes()->count());
        $this->assertSame(2, $encargo->eventos()->count());
        foreach ([$this->tsNorte, $this->abogadaVallecas] as $destinatario) {
            $this->assertTrue(Alerta::pendientesPara($destinatario)->where('origen_type', Derivacion::class)->where('origen_id', $encargo->id)->exists());
        }
    }

    /** TF-ENC-07 — Quién acepta y a quién se asigna. [negativo] */
    #[Test]
    public function tf_enc_07_quien_acepta_y_a_quien(): void
    {
        $encargo = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);

        // La abogada, el TSR y la supervisión de UO superior no aceptan.
        foreach ([$this->abogadaVallecas, $this->tsNorte, $this->coordIgualdad] as $actor) {
            $this->assertRechazado(fn () => $this->servicio->aceptar($encargo, $this->abogadaVallecas, $actor));
        }

        // No adscrito al centro de destino, o sin `derivacion.registrar_resultado`.
        $educadorNorte = $this->profesionalCon('educador.norte', 'consulta_profesional', $this->cssNorte, 'Educador/a social');
        $this->assertRechazado(fn () => $this->servicio->aceptar($encargo, $educadorNorte, $this->directoraVallecas));
        $this->assertFalse($this->psicologaVallecas->can(EncargoService::PERMISO_PROFESIONAL));
        $this->assertRechazado(fn () => $this->servicio->aceptar($encargo, $this->psicologaVallecas, $this->directoraVallecas));

        $this->assertSame(EstadoDerivacion::Pendiente, $encargo->fresh()->estado);
        $this->assertNull($encargo->fresh()->profesional_responsable_id);
    }

    /** TF-ENC-08 — Las modalidades no se cruzan al aceptar. [negativo] */
    #[Test]
    public function tf_enc_08_las_modalidades_no_se_cruzan(): void
    {
        $encargo = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);
        $derivacion = app(DerivacionService::class)->derivar($this->pisoAna, $this->derivPia, $this->ciamVallecas, 'Atención especializada', null, $this->tsNorte);

        $this->assertRechazado(fn () => app(DerivacionService::class)->aceptar($encargo, $this->psicologaVallecas, $this->directoraVallecas));
        $this->assertRechazado(fn () => $this->servicio->aceptar($derivacion, $this->abogadaVallecas, $this->directoraVallecas));

        $this->assertSame(EstadoDerivacion::Pendiente, $encargo->fresh()->estado);
        $this->assertSame(EstadoDerivacion::Pendiente, $derivacion->fresh()->estado);
        $this->assertNull($encargo->fresh()->plan_especializado_id);
    }

    /** TF-ENC-09 — Cerrar: cerrada con resultado; apunte `encargo` para profesionales; alerta al TSR. */
    #[Test]
    public function tf_enc_09_cerrar(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);

        $this->servicio->cerrar($encargo, '  Se informa de los recursos de defensa.  ', $this->abogadaVallecas);

        $encargo->refresh();
        $this->assertSame(EstadoDerivacion::Cerrada, $encargo->estado);
        $this->assertSame('Se informa de los recursos de defensa.', $encargo->resultado);
        $this->assertNotNull($encargo->cerrada_en);
        $this->assertSame($this->abogadaVallecas->id, $encargo->cerrada_por_id);

        $apunte = Apunte::withoutGlobalScopes()->where('historia_id', $this->historiaAna->id)
            ->where('tipo', TipoApunte::Encargo)->where('apuntable_id', $encargo->id)->sole();
        $this->assertSame(VisibilidadApunte::Profesionales, $apunte->visibilidad);
        $this->assertStringContainsString('Se informa de los recursos de defensa.', $apunte->contenido);

        $this->assertTrue(Alerta::pendientesPara($this->tsNorte)->where('origen_type', Derivacion::class)
            ->where('origen_id', $encargo->id)->where('titulo', 'Encargo profesional cerrado')->exists());
    }

    /** TF-ENC-10 — Quién cierra: otro profesional, sin resultado, pendiente o sin adscripción (RN-15). [negativo] */
    #[Test]
    public function tf_enc_10_quien_cierra(): void
    {
        $pendiente = $this->encargoPendiente($this->historiaAna, $this->ciamUsera);
        $this->assertRechazado(fn () => $this->servicio->cerrar($pendiente, 'Resultado', $this->abogadaVallecas));

        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $this->assertRechazado(fn () => $this->servicio->cerrar($encargo, 'Resultado', $this->educadorMulti));
        $this->assertRechazado(fn () => $this->servicio->cerrar($encargo, '   ', $this->abogadaVallecas));

        // Deja de estar adscrita al centro del encargo.
        UsuarioUo::where('usuario_id', $this->abogadaVallecas->id)
            ->where('unidad_organizativa_id', $this->ciamVallecas->unidad_organizativa_id)
            ->update(['fecha_fin' => now()->subDay()->toDateString()]);
        app(AmbitoUsuario::class)->olvidar();
        $this->assertRechazado(fn () => $this->servicio->cerrar($encargo, 'Resultado', $this->abogadaVallecas->fresh()));

        $this->assertSame(EstadoDerivacion::Aceptada, $encargo->fresh()->estado);
        $this->assertFalse(Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Encargo)->exists());
    }

    /** TF-ENC-11 — Rechazar y anular un encargo pendiente; anular uno aceptado, no. */
    #[Test]
    public function tf_enc_11_rechazar_y_anular(): void
    {
        $derivaciones = app(DerivacionService::class);

        $rechazado = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);
        $derivaciones->rechazar($rechazado, 'No es competencia del centro', $this->directoraVallecas);
        $this->assertSame(EstadoDerivacion::Rechazada, $rechazado->fresh()->estado);
        $this->assertSame('No es competencia del centro', $rechazado->fresh()->motivo_rechazo);

        $anulado = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);
        $this->assertRechazado(fn () => $derivaciones->anular($anulado, 'Ya no procede', $this->tsNorte2));
        $derivaciones->anular($anulado, 'Ya no procede', $this->tsNorte);
        $this->assertSame(EstadoDerivacion::Anulada, $anulado->fresh()->estado);

        $aceptado = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $this->assertRechazado(fn () => $derivaciones->anular($aceptado, 'Ya no procede', $this->tsNorte));
        $this->assertSame(EstadoDerivacion::Aceptada, $aceptado->fresh()->estado);
    }

    /** TF-ENC-12 — El CHECK exige plan, prestación y tipo en las derivaciones de plan especializado. [negativo] */
    #[Test]
    public function tf_enc_12_check_de_plan_especializado(): void
    {
        $base = [
            'historia_id' => $this->historiaAna->id,
            'centro_destino_id' => $this->ciamVallecas->id,
            'motivo' => 'Motivo',
            'estado' => 'pendiente',
            'derivada_por_id' => $this->tsNorte->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $completa = ['plan_origen_id' => $this->pisoAna->id, 'prestacion_id' => $this->derivPia->id, 'tipo_plan_id' => $this->planPia->id];

        foreach (array_keys($completa) as $falta) {
            try {
                DB::transaction(fn () => DB::table('derivaciones')->insert($base + ['modalidad' => 'plan_especializado'] + array_diff_key($completa, [$falta => 1])));
                $this->fail("El CHECK debía exigir {$falta}");
            } catch (QueryException $e) {
                $this->assertStringContainsString('derivaciones_plan_especializado_check', $e->getMessage());
            }
        }

        // Un encargo no los necesita; una modalidad desconocida no entra.
        DB::table('derivaciones')->insert($base + ['modalidad' => 'encargo']);
        try {
            DB::transaction(fn () => DB::table('derivaciones')->insert($base + ['modalidad' => 'otra']));
            $this->fail('El CHECK debía rechazar la modalidad');
        } catch (QueryException $e) {
            $this->assertStringContainsString('derivaciones_modalidad_check', $e->getMessage());
        }
    }

    /** TF-ENC-13 — Documentos al encargar: los de la carpeta de la persona; uno de otra persona no. [negativo] */
    #[Test]
    public function tf_enc_13_documentos_al_encargar(): void
    {
        $informe = $this->documentoEnCarpeta($this->ana, 'Informe médico');
        $contrato = $this->documentoEnCarpeta($this->ana, 'Contrato de alquiler');
        $deMarta = $this->documentoEnCarpeta($this->marta, 'Informe de Marta');

        $this->assertRechazado(fn () => $this->encargoPendiente($this->historiaAna, $this->ciamVallecas, [$informe->id, $deMarta->id]));
        $this->assertSame(0, Derivacion::count());

        $encargo = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas, [$informe->id, $contrato->id]);

        $vinculados = DocumentoVinculo::query()->activos()
            ->where('vinculable_type', $encargo->getMorphClass())->where('vinculable_id', $encargo->id)
            ->pluck('documento_id')->all();
        $this->assertEqualsCanonicalizing([$informe->id, $contrato->id], $vinculados);
    }

    /**
     * Comprueba que la operación lanza DomainException (y, si se indica, con ese texto).
     *
     * @param callable $operacion
     * @param string|null $contiene Fragmento esperado del mensaje.
     *
     * @return void
     */
    private function assertRechazado(callable $operacion, ?string $contiene = null): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba DomainException');
        } catch (DomainException $e) {
            if ($contiene !== null) {
                $this->assertStringContainsString($contiene, $e->getMessage());
            } else {
                $this->addToAssertionCount(1);
            }
        }
    }
}
