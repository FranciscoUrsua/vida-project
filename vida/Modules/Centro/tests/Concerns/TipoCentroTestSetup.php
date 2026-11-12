<?php

namespace Modules\Centro\Tests\Concerns;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\SegmentoPoblacion;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Services\TipoCentro\CentroService;
use Modules\Centro\Services\TipoCentro\TipoCentroService;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Datos de los tests de Tipo de Centro (docs/instrucciones-cli/2026-10-tipo-centro-tests.md,
 * «Actores y datos reutilizados», con la estructura de la addenda D1 §4.1).
 *
 * Organigrama: `$uoArea` con `$uoPrimaria` y `$uoIgualdad` (UO funcionales de los
 * tipos), y las juntas `$uoJuntaNorte`, `$uoJuntaVallecas` y `$uoJuntaUsera`
 * (orgánicas, sin centro). Cada centro tiene su UO instrumental, hija de su junta.
 */
trait TipoCentroTestSetup
{
    protected User $admin;

    protected UnidadOrganizativa $uoArea;

    protected UnidadOrganizativa $uoPrimaria;

    protected UnidadOrganizativa $uoIgualdad;

    protected UnidadOrganizativa $uoJuntaNorte;

    protected UnidadOrganizativa $uoJuntaVallecas;

    protected UnidadOrganizativa $uoJuntaUsera;

    protected UnidadOrganizativa $uoCssNorte;

    protected UnidadOrganizativa $uoCssSur;

    protected UnidadOrganizativa $uoCiamVallecas;

    protected UnidadOrganizativa $uoCiamUsera;

    protected TipoCentro $tipoCss;

    protected TipoCentro $tipoCiam;

    /** Tipo sin centros. */
    protected TipoCentro $tipoVacio;

    protected Centro $cssNorte;

    protected Centro $cssSur;

    protected Centro $ciamVallecas;

    protected Centro $ciamUsera;

    protected TipoPlan $planPiso;

    protected TipoPlan $planPia;

    /** Prestaciones de `$tipoCss`. */
    protected Prestacion $p1;

    protected Prestacion $p2;

    /** Prestación de `$tipoCiam`. */
    protected Prestacion $p3;

    protected TipoFicha $fichaVivienda;

    protected TipoFicha $fichaVG;

    protected User $directoraNorte;

    protected User $directoraSur;

    protected User $coordIgualdad;

    protected User $jefaJuntaVallecas;

    protected User $coordPrimaria;

    /** `intervencion` en los dos CIAM (L-M Vallecas, X-J Usera). */
    protected User $psicologa;

    /** `intervencion` en `$uoArea`, sin centro. */
    protected User $tecnicaArea;

    /** `intervencion` en `$uoIgualdad`. */
    protected User $tecnicaIgualdad;

    /**
     * Monta el escenario común con fecha 2026-11-02 (lunes).
     *
     * @return void
     */
    protected function montarTipoCentro(): void
    {
        Carbon::setTestNow('2026-11-02 10:00:00');

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uoArea = $this->uo('Área de Políticas Sociales', 'area_gobierno');
        $this->uoPrimaria = $this->uo('Departamento de Atención Primaria', 'departamento', $this->uoArea);
        $this->uoIgualdad = $this->uo('Departamento de Igualdad', 'departamento', $this->uoArea);
        $this->uoJuntaNorte = $this->uo('Junta de Distrito Norte', 'junta');
        $this->uoJuntaVallecas = $this->uo('Junta de Distrito de Puente de Vallecas', 'junta');
        $this->uoJuntaUsera = $this->uo('Junta de Distrito de Usera', 'junta');

        $this->admin = $this->usuario('admin', 'adm_sistema', $this->uoArea);

        $this->planPiso = TipoPlan::firstOrCreate(['slug' => 'piso'], ['nombre' => 'PISO', 'ambito' => 'asp', 'activo' => true]);
        $this->planPia = TipoPlan::firstOrCreate(['slug' => 'pia'], ['nombre' => 'PIA', 'ambito' => 'especializado', 'activo' => true]);
        $general = SegmentoPoblacion::firstOrCreate(['slug' => 'poblacion_general'], ['nombre' => 'Población general', 'activo' => true]);
        $vg = SegmentoPoblacion::firstOrCreate(['slug' => 'vg'], ['nombre' => 'Mujeres víctimas de violencia de género', 'activo' => true]);

        $tipos = app(TipoCentroService::class);
        $this->tipoCss = $tipos->crear([
            'codigo' => 'css_general', 'nombre' => 'Centro de Servicios Sociales', 'siglas' => 'CSS',
            'unidad_organizativa_id' => $this->uoPrimaria->id, 'inscripcion_libre' => false,
            'modo_asignacion_referencia' => 'sorteo', 'fecha_alta' => '2026-01-01',
        ], $this->admin);
        $this->tipoCss->segmentos()->attach($general->id);
        $this->tipoCss->tiposPlan()->attach($this->planPiso->id);

        $this->tipoCiam = $tipos->crear([
            'codigo' => 'ciam', 'nombre' => 'Centro Integral de Atención a la Mujer', 'siglas' => 'CIAM',
            'unidad_organizativa_id' => $this->uoIgualdad->id, 'inscripcion_libre' => true,
            'modo_asignacion_referencia' => 'libre_eleccion', 'fecha_alta' => '2026-01-01',
        ], $this->admin);
        $this->tipoCiam->segmentos()->attach($vg->id);
        $this->tipoCiam->tiposPlan()->attach($this->planPia->id);

        $this->tipoVacio = $tipos->crear([
            'codigo' => 'tipo_vacio', 'nombre' => 'Tipo sin centros', 'siglas' => 'TSC',
            'unidad_organizativa_id' => $this->uoPrimaria->id, 'fecha_alta' => '2026-01-01',
        ], $this->admin);

        $this->p1 = $this->prestacion('P1', 'Información y orientación', $this->tipoCss);
        $this->p2 = $this->prestacion('P2', 'Ayuda a domicilio', $this->tipoCss);
        $this->p3 = $this->prestacion('P3', 'Atención psicológica a víctimas', $this->tipoCiam);

        $this->fichaVivienda = TipoFicha::create(['nombre' => 'Vivienda', 'activo' => true, 'schema' => ['campos' => []]]);
        $this->fichaVG = TipoFicha::create(['nombre' => 'Violencia de género', 'activo' => true, 'schema' => ['campos' => []]]);
        $this->tipoCiam->tiposFicha()->attach($this->fichaVG->id);

        $this->uoCssNorte = $this->uo('CSS Norte', 'centro', $this->uoJuntaNorte);
        $this->uoCssSur = $this->uo('CSS Sur', 'centro', $this->uoJuntaNorte);
        $this->uoCiamVallecas = $this->uo('CIAM Puente de Vallecas', 'centro', $this->uoJuntaVallecas);
        $this->uoCiamUsera = $this->uo('CIAM Usera', 'centro', $this->uoJuntaUsera);

        $this->cssNorte = $this->centro('CSS Norte', 'Norte', $this->tipoCss, $this->uoCssNorte);
        $this->cssSur = $this->centro('CSS Sur', 'Sur', $this->tipoCss, $this->uoCssSur);
        $this->ciamVallecas = $this->centro('CIAM Puente de Vallecas', 'Puente de Vallecas', $this->tipoCiam, $this->uoCiamVallecas);
        $this->ciamUsera = $this->centro('CIAM Usera', 'Usera', $this->tipoCiam, $this->uoCiamUsera);

        $this->directoraNorte = $this->directora('directora.norte', $this->cssNorte);
        $this->directoraSur = $this->directora('directora.sur', $this->cssSur);

        $this->coordIgualdad = $this->usuario('coord.igualdad', 'supervision', $this->uoIgualdad);
        $this->jefaJuntaVallecas = $this->usuario('jefa.junta.vallecas', 'supervision', $this->uoJuntaVallecas);
        $this->coordPrimaria = $this->usuario('coord.primaria', 'supervision', $this->uoPrimaria);
        $this->psicologa = $this->usuario('psicologa', 'intervencion', $this->uoCiamVallecas);
        $this->adscribir($this->psicologa, $this->uoCiamUsera);
        $this->tecnicaArea = $this->usuario('tecnica.area', 'intervencion', $this->uoArea);
        $this->tecnicaIgualdad = $this->usuario('tecnica.igualdad', 'intervencion', $this->uoIgualdad);
    }

    /**
     * UO del organigrama de prueba.
     *
     * @param string $nombre Nombre.
     * @param string $tipo Tipo de UO.
     * @param UnidadOrganizativa|null $padre UO padre orgánica.
     *
     * @return UnidadOrganizativa
     */
    protected function uo(string $nombre, string $tipo, ?UnidadOrganizativa $padre = null): UnidadOrganizativa
    {
        return UnidadOrganizativa::create(['nombre' => $nombre, 'tipo' => $tipo, 'parent_id' => $padre?->id, 'activa' => true]);
    }

    /**
     * Prestación activa en el catálogo de un tipo.
     *
     * @param string $codigo Código.
     * @param string $nombre Nombre.
     * @param TipoCentro $tipo Tipo que la ofrece.
     *
     * @return Prestacion
     */
    protected function prestacion(string $codigo, string $nombre, TipoCentro $tipo): Prestacion
    {
        $prestacion = Prestacion::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo_prestacion' => 'servicio',
            'nivel_garantia' => 'garantizada',
            'activa' => true,
        ]);
        $prestacion->tiposCentro()->create(['tipo_centro_id' => $tipo->id]);

        return $prestacion;
    }

    /**
     * Centro de un tipo con su UO instrumental, dado de alta por el servicio.
     *
     * @param string $nombre Nombre.
     * @param string $corto Nombre corto.
     * @param TipoCentro $tipo Tipo.
     * @param UnidadOrganizativa $uo UO del centro.
     *
     * @return Centro
     */
    protected function centro(string $nombre, string $corto, TipoCentro $tipo, UnidadOrganizativa $uo): Centro
    {
        return app(CentroService::class)->darDeAlta([
            'nombre' => $nombre,
            'nombre_corto' => $corto,
            'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $uo->id,
            'fecha_alta' => '2026-01-01',
        ], $tipo, null, $this->admin);
    }

    /**
     * Usuario con un rol, adscrito a una UO.
     *
     * @param string $alias Prefijo del email.
     * @param string $rol Rol.
     * @param UnidadOrganizativa $uo UO de adscripción.
     * @param Profesional|null $profesional Ficha de profesional vinculada.
     *
     * @return User
     */
    protected function usuario(string $alias, string $rol, UnidadOrganizativa $uo, ?Profesional $profesional = null): User
    {
        $usuario = User::create([
            'email' => "{$alias}@vida360.test",
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
            'profesional_id' => $profesional?->id,
        ]);
        $usuario->syncRoles([$rol]);
        $this->adscribir($usuario, $uo);

        return $usuario;
    }

    /**
     * Adscripción vigente de un usuario a una UO.
     *
     * @param User $usuario Usuario.
     * @param UnidadOrganizativa $uo UO.
     *
     * @return void
     */
    protected function adscribir(User $usuario, UnidadOrganizativa $uo): void
    {
        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => '2020-01-01',
        ]);
    }

    /**
     * Directora vigente de un centro (`DirectorCentro`) con rol `supervision` en su UO.
     *
     * @param string $alias Prefijo del email.
     * @param Centro $centro Centro que dirige.
     *
     * @return User
     */
    protected function directora(string $alias, Centro $centro): User
    {
        $profesional = Profesional::create([
            'nombre' => ucfirst($alias),
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Dirección de centro'], ['activo' => true])->id,
            'tipo_relacion_id' => TipoRelacionProfesional::firstOrCreate(['nombre' => 'Funcionario/a de carrera'], ['es_externo' => false, 'activo' => true])->id,
            'fecha_inicio' => '2020-01-01',
            'activo' => true,
        ]);

        $usuario = $this->usuario($alias, 'supervision', $centro->unidadOrganizativa, $profesional);
        $centro->nombrarDirector(['profesional_id' => $profesional->id, 'fecha_inicio' => '2026-01-01']);

        return $usuario;
    }
}
