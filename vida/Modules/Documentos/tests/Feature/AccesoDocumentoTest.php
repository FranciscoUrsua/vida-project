<?php

namespace Modules\Documentos\Tests\Feature;

use App\Models\AccesoProtegido;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo G — Acceso y auditoría (TF-DOC-74 a 78).
 *
 * Regla de acceso decidida el 2026-09-25: la de la ficha del ciudadano
 * (CiudadanoPolicy::view). Lectura amplia para cualquier profesional con
 * `ciudadano.leer`, salvo colectivos protegidos sin acceso aprobado. Por eso
 * TF-DOC-75 usa una persona protegida y un usuario sin permiso de lectura en
 * lugar de un ciudadano no protegido de otra UO, que sí es consultable.
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md
 */
class AccesoDocumentoTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    /**
     * Prepara actores y tipos comunes, autenticado como profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDocumentos();
        $this->actingAs($this->profesional);
    }

    /**
     * Ciudadano de colectivo especialmente protegido.
     *
     * @return Ciudadano
     */
    private function ciudadanoProtegido(): Ciudadano
    {
        return Ciudadano::factory()->create(['colectivo_extra_protegido' => true]);
    }

    /**
     * Pide el documento por la ruta de la aplicación con una URL firmada.
     *
     * @param User $usuario Usuario que lo pide.
     * @param Documento $documento Documento.
     * @param string $ruta documentos.ver o documentos.descargar.
     *
     * @return TestResponse
     */
    private function pedir(User $usuario, Documento $documento, string $ruta = 'documentos.descargar'): TestResponse
    {
        $url = URL::temporarySignedRoute($ruta, now()->addMinutes(5), ['documento' => $documento->id]);

        return $this->actingAs($usuario)->get($url);
    }

    /**
     * Comprueba que se deniega sin descifrar nada ni registrar accesos.
     *
     * @param User $usuario Usuario que lo pide.
     * @param Documento $documento Documento.
     *
     * @return void
     */
    private function assertDenegadoSinDescifrar(User $usuario, Documento $documento): void
    {
        $lectura = $this->spy(LecturaDocumentoService::class);

        $this->pedir($usuario, $documento)->assertForbidden();

        $lectura->shouldNotHaveReceived('contenido');
        $this->assertSame(0, Audit::whereIn('accion', ['ver', 'exportar'])->count());
        $this->app->forgetInstance(LecturaDocumentoService::class);
    }

    #[Test]
    public function tf_doc_74_un_profesional_con_acceso_a_una_persona_vinculada_puede_descargar(): void
    {
        $documento = $this->alta([$this->ana, $this->luis, $this->eva, $this->pablo], $this->tipoEmpadronamiento, datos: [
            'nombreOriginal' => 'empadronamiento_familia_garcia.pdf',
        ]);
        $version = $documento->versionVigente;

        $respuesta = $this->pedir($this->profesional, $documento);

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($version->hash_sha256, hash('sha256', $respuesta->getContent()));

        $disposicion = (string) $respuesta->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposicion);
        $this->assertStringContainsString('certificado_empadronamiento-'.$version->fecha_captura->format('Y-m-d').'.pdf', $disposicion);
        $this->assertStringNotContainsString('garcia', $disposicion);
    }

    #[Test]
    public function tf_doc_75_sin_acceso_a_ninguna_persona_vinculada_no_se_puede_descargar(): void
    {
        $protegida = $this->ciudadanoProtegido();
        $documento = $this->alta([$protegida]);

        $this->assertDenegadoSinDescifrar($this->profesional, $documento);

        // Un usuario sin permiso de lectura de ciudadanos tampoco, aunque la persona no esté protegida.
        $sinPermiso = User::factory()->create();
        $this->assertDenegadoSinDescifrar($sinPermiso, $this->alta([$this->ana]));

        // Negativo (regla provisional «al menos una persona»): vinculado también a Ana, se descarga.
        $documento->vinculos()->create(['vinculable_type' => $this->ana->getMorphClass(), 'vinculable_id' => $this->ana->id, 'creado_por' => $this->admin->id]);
        $this->pedir($this->profesional, $documento)->assertOk();

        // Lectura amplia: un ciudadano no protegido de otra UO también es consultable, como su ficha.
        $this->pedir($this->profesional, $this->alta([$this->ciudadanoAjeno]))->assertOk();
    }

    #[Test]
    public function tf_doc_76_un_ciudadano_de_colectivo_protegido_restringe_el_acceso_a_sus_documentos(): void
    {
        $protegida = $this->ciudadanoProtegido();
        $documento = $this->alta([$protegida]);

        // La misma decisión que para su ficha (CiudadanoPolicy::view).
        $this->assertFalse(Gate::forUser($this->profesional)->allows('view', $protegida));
        $this->assertDenegadoSinDescifrar($this->profesional, $documento);

        // Negativo: con un acceso protegido aprobado y vigente, sí.
        AccesoProtegido::create([
            'usuario_id' => $this->profesional->id,
            'ciudadano_id' => $protegida->id,
            'solicitante_id' => $this->profesional->id,
            'justificacion' => 'Intervención conjunta',
            'estado' => 'aprobado',
            'aprobado_por' => $this->admin->id,
            'fecha_resolucion' => now(),
            'acceso_valido_hasta' => now()->addDay(),
        ]);
        $this->assertTrue(Gate::forUser($this->profesional)->allows('view', $protegida));
        $this->pedir($this->profesional, $documento)->assertOk();
    }

    #[Test]
    public function tf_doc_77_no_existe_acceso_directo_al_fichero_almacenado(): void
    {
        $documento = $this->alta([$this->ana]);
        $clave = $documento->versionVigente->clave_almacenamiento;

        // El disco no tiene URL pública ni se sirve desde la aplicación.
        $disco = config('filesystems.disks.documentos');
        $this->assertArrayNotHasKey('url', $disco);
        $this->assertEmpty($disco['serve'] ?? false);

        // Ninguna ruta de la aplicación, salvo las del controlador y el backoffice, menciona documentos.
        $ajenas = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($ruta): string => $ruta->uri())
            ->filter(fn (string $uri): bool => str_contains($uri, 'documentos'))
            ->reject(fn (string $uri): bool => in_array($uri, ['documentos/{documento}/ver', 'documentos/{documento}/descargar'], true)
                || str_starts_with($uri, 'admin/'))
            ->values()
            ->all();
        $this->assertSame([], $ajenas);

        // Laravel sirve los discos con «serve» (storage/{path}); ninguno puede contener el de documentos.
        $raizDocumentos = rtrim($disco['root'], '/').'/';
        foreach (config('filesystems.disks') as $nombre => $otro) {
            if ($nombre === 'documentos' || empty($otro['serve']) || ! isset($otro['root'])) {
                continue;
            }
            $this->assertStringStartsNotWith(rtrim($otro['root'], '/').'/', $raizDocumentos, "El disco servido «{$nombre}» contiene el de documentos.");
        }

        // Las URL generadas apuntan al controlador, nunca a la clave de almacenamiento.
        $lectura = app(LecturaDocumentoService::class);
        foreach ([$lectura->urlTemporal($documento), $lectura->urlDescarga($documento)] as $url) {
            $this->assertMatchesRegularExpression('#/documentos/'.$documento->id.'/(ver|descargar)\?#', $url);
            $this->assertStringNotContainsString($clave, $url);
        }
        $this->get(str_replace('signature=', 'signature=x', $lectura->urlTemporal($documento)))->assertForbidden();
    }

    #[Test]
    public function tf_doc_78_visualizar_y_descargar_quedan_auditados(): void
    {
        $documento = $this->alta([$this->ana, $this->luis, $this->eva, $this->pablo], $this->tipoEmpadronamiento);
        $version = $documento->versionVigente;

        $this->pedir($this->profesional, $documento, 'documentos.ver')->assertOk();
        $this->pedir($this->profesional, $documento, 'documentos.descargar')->assertOk();

        $accesos = Audit::where('auditable_type', Documento::class)->where('auditable_id', $documento->id)
            ->whereIn('accion', ['ver', 'exportar'])->orderBy('id')->get();

        $this->assertSame(['ver', 'exportar'], $accesos->pluck('accion')->map(fn ($a) => $a instanceof \BackedEnum ? $a->value : $a)->all());
        foreach ($accesos as $acceso) {
            $this->assertSame($this->profesional->id, $acceso->user_id);
            $this->assertSame($documento->id, $acceso->contexto['documento_id']);
            $this->assertSame($version->id, $acceso->contexto['documento_version_id']);
            $this->assertSame($this->ana->id, $acceso->ciudadano_id);
            $this->assertEqualsCanonicalizing(
                [$this->ana->id, $this->luis->id, $this->eva->id, $this->pablo->id],
                $acceso->contexto['ciudadanos_vinculados'],
            );
        }

        // Sin tabla de accesos propia del módulo.
        $tablas = collect(DB::select("select tablename from pg_tables where schemaname = 'public' and tablename like 'documento%'"))->pluck('tablename');
        $this->assertSame([], $tablas->filter(fn (string $t): bool => str_contains($t, 'acceso'))->values()->all());
    }
}
