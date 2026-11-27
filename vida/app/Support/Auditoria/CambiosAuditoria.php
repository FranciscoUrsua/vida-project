<?php

namespace App\Support\Auditoria;

use App\Models\Audit;
use App\Support\Cifrado\OriginalSinDescifrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Cambios campo a campo de un registro de auditoría, con nombres en español, para el detalle
 * de «Últimos accesos» (U3, U3.2).
 *
 * Reglas: solo se enseñan los campos con etiqueta en `lang/es/auditoria.php` (`campos`). De los
 * `campos_protegidos` y de los atributos cifrados del modelo se dice que cambiaron, sin el
 * valor: pueden ser el contenido de un apunte privado o un dato personal cifrado. Los
 * `campos_ocultos` no se enseñan nunca. Un campo sin clasificar no se enseña y deja un aviso en
 * el log fuera de producción, para completar las etiquetas.
 */
final class CambiosAuditoria
{
    /**
     * Filas de cambios del registro.
     *
     * @param Audit $audit
     *
     * @return list<array{campo: string, etiqueta: string, antes: string, despues: string, protegido: bool}>
     */
    public static function de(Audit $audit): array
    {
        $antes = is_array($audit->datos_antes) ? $audit->datos_antes : [];
        $despues = is_array($audit->datos_despues) ? $audit->datos_despues : [];

        $etiquetas = (array) __('auditoria.campos');
        $protegidos = (array) __('auditoria.campos_protegidos');
        $ocultos = array_flip((array) __('auditoria.campos_ocultos'));
        $cifrados = array_flip(self::cifradosDe($audit->auditable_type));

        $filas = [];
        foreach (array_unique([...array_keys($antes), ...array_keys($despues)]) as $campo) {
            if (isset($ocultos[$campo])) {
                continue;
            }

            if (isset($protegidos[$campo]) || (isset($cifrados[$campo]) && isset($etiquetas[$campo]))) {
                $filas[] = [
                    'campo' => $campo,
                    'etiqueta' => $protegidos[$campo] ?? $etiquetas[$campo],
                    'antes' => '',
                    'despues' => '',
                    'protegido' => true,
                ];

                continue;
            }

            if (! isset($etiquetas[$campo])) {
                if (! app()->isProduction()) {
                    Log::warning('Campo de auditoría sin etiqueta en lang/es/auditoria.php', ['modelo' => $audit->auditable_type, 'campo' => $campo]);
                }

                continue;
            }

            $filas[] = [
                'campo' => $campo,
                'etiqueta' => $etiquetas[$campo],
                'antes' => self::valor($campo, $antes[$campo] ?? null),
                'despues' => self::valor($campo, $despues[$campo] ?? null),
                'protegido' => false,
            ];
        }

        return $filas;
    }

    /**
     * Valor legible de un campo: etiqueta del valor si la hay, Sí/No, fecha en formato español
     * o el valor tal cual.
     *
     * @param string $campo
     * @param mixed $valor
     *
     * @return string
     */
    private static function valor(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_array($valor)) {
            return '…';
        }

        $etiqueta = ((array) __('auditoria.valores'))[$campo][(string) $valor] ?? null;
        if ($etiqueta !== null) {
            return $etiqueta;
        }

        // Fechas serializadas por el observer (Y-m-d o Y-m-d H:i:s)
        if (is_string($valor) && preg_match('/^(\d{4})-(\d{2})-(\d{2})( 00:00:00)?$/', $valor, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        return (string) $valor;
    }

    /**
     * Atributos cifrados del modelo auditado (si la clase sigue existiendo).
     *
     * @param string|null $clase
     *
     * @return list<string>
     */
    private static function cifradosDe(?string $clase): array
    {
        if ($clase === null || ! class_exists($clase) || ! is_subclass_of($clase, Model::class)) {
            return [];
        }

        return OriginalSinDescifrar::atributosCifrados(new $clase);
    }
}
