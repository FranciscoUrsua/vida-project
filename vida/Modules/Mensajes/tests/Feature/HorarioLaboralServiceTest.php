<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\CatalogoSistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mensajes\Services\HorarioLaboralService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del HorarioLaboralService.
 *
 * Verifican el cálculo correcto de vencimientos en horas laborales,
 * incluyendo saltos de fin de jornada y fines de semana.
 */
class HorarioLaboralServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearHorarioDefecto(
        string $inicio = '08:00',
        string $fin    = '17:00',
        array  $dias   = [1, 2, 3, 4, 5]
    ): void {
        CatalogoSistema::create([
            'grupo'    => 'sistema.configuracion',
            'clave'    => 'horario_laboral_defecto',
            'etiqueta' => 'Horario laboral por defecto',
            'valor'    => json_encode([
                'inicio'      => $inicio,
                'fin'         => $fin,
                'dias_semana' => $dias,
            ]),
            'orden'  => 1,
            'activo' => true,
        ]);
    }

    #[Test]
    public function calcular_expiracion_en_medio_de_jornada_devuelve_mismo_dia(): void
    {
        $this->crearHorarioDefecto();

        $servicio = new HorarioLaboralService();

        // Lunes 10:00 + 4h laborales = Lunes 14:00
        $desde   = now()->startOfWeek()->setTime(10, 0); // Lunes
        $resultado = $servicio->calcularExpiracion($desde);

        $this->assertEquals(14, $resultado->hour);
        $this->assertEquals(0, $resultado->minute);
        $this->assertEquals($desde->toDateString(), $resultado->toDateString());
    }

    #[Test]
    public function calcular_expiracion_a_las_14_devuelve_09_del_dia_siguiente(): void
    {
        // Nota: 14:00 + 4h laborales con horario 08:00-17:00:
        // - Quedan 3h hoy (14:00 → 17:00)
        // - Faltan 1h para mañana: 08:00 + 1h = 09:00
        $this->crearHorarioDefecto();

        $servicio = new HorarioLaboralService();

        $desde   = now()->startOfWeek()->setTime(14, 0); // Lunes 14:00
        $resultado = $servicio->calcularExpiracion($desde);

        $esperado = $desde->copy()->addDay()->setTime(9, 0); // Martes 09:00

        $this->assertEquals($esperado->toDateTimeString(), $resultado->toDateTimeString());
    }

    #[Test]
    public function calcular_expiracion_fuera_de_horario_inicia_desde_inicio_del_siguiente_dia(): void
    {
        // 17:30 es posterior al fin de jornada (17:00).
        // Las 4h se cuentan íntegramente desde 08:00 del día siguiente: 08:00 + 4h = 12:00
        $this->crearHorarioDefecto();

        $servicio = new HorarioLaboralService();

        $desde   = now()->startOfWeek()->setTime(17, 30); // Lunes 17:30 (pasado el fin)
        $resultado = $servicio->calcularExpiracion($desde);

        $esperado = $desde->copy()->addDay()->setTime(12, 0); // Martes 12:00

        $this->assertEquals($esperado->toDateTimeString(), $resultado->toDateTimeString());
    }

    #[Test]
    public function calcular_expiracion_a_las_14_del_viernes_devuelve_lunes(): void
    {
        // Viernes 14:00 + 4h laborales:
        // - Quedan 3h el viernes (14:00 → 17:00)
        // - Falta 1h el lunes: 08:00 + 1h = 09:00
        $this->crearHorarioDefecto();

        $servicio = new HorarioLaboralService();

        // Obtener el próximo viernes
        $viernes = now()->startOfWeek()->addDays(4)->setTime(14, 0); // Viernes 14:00
        $resultado  = $servicio->calcularExpiracion($viernes);

        // Debe ser el lunes siguiente a las 09:00
        $lunesSiguiente = $viernes->copy()->addDays(3)->setTime(9, 0);

        $this->assertEquals($lunesSiguiente->toDateTimeString(), $resultado->toDateTimeString());
    }

    #[Test]
    public function usa_horario_defecto_si_no_hay_entrada_en_catalogos_sistema(): void
    {
        // Sin entrada en catalogos_sistema, usa 08:00-17:00 L-V

        $servicio = new HorarioLaboralService();

        $desde   = now()->startOfWeek()->setTime(10, 0); // Lunes 10:00
        $resultado = $servicio->calcularExpiracion($desde);

        $this->assertEquals(14, $resultado->hour);
        $this->assertEquals(0, $resultado->minute);
    }

    #[Test]
    public function respeta_horario_personalizado_de_catalogos_sistema(): void
    {
        // Horario especial: 09:00-14:00 (jornada de 5h)
        $this->crearHorarioDefecto('09:00', '14:00');

        $servicio = new HorarioLaboralService();

        // Lunes 10:00 + 4h laborales = 14:00 (justo al límite)
        $desde   = now()->startOfWeek()->setTime(10, 0);
        $resultado = $servicio->calcularExpiracion($desde);

        $this->assertEquals(14, $resultado->hour);
        $this->assertEquals(0, $resultado->minute);
        $this->assertEquals($desde->toDateString(), $resultado->toDateString());
    }
}
