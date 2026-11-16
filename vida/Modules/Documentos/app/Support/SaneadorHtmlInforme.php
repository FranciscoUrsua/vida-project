<?php

namespace Modules\Documentos\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanea el HTML de las secciones de texto libre de un informe.
 *
 * El texto libre llega de un editor `contenteditable` del navegador y acaba en el PDF
 * sin escapar (para conservar negritas, cursivas y listas). Solo se admiten esas
 * etiquetas de formato, sin atributos: cualquier otra cosa (scripts, estilos, enlaces,
 * imágenes) se elimina antes de guardar y otra vez antes de pintar el PDF.
 */
class SaneadorHtmlInforme
{
    /** Etiquetas de formato admitidas en el texto libre. */
    private const ETIQUETAS = ['p', 'br', 'div', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'];

    /** Longitud máxima, en caracteres, del HTML de una sección. */
    public const MAX_CARACTERES = 50000;

    /**
     * Devuelve el HTML con solo las etiquetas de formato admitidas.
     *
     * @param string|null $html HTML recibido del editor.
     *
     * @return string
     */
    public function sanear(?string $html): string
    {
        $config = (new HtmlSanitizerConfig)->withMaxInputLength(self::MAX_CARACTERES);

        foreach (self::ETIQUETAS as $etiqueta) {
            $config = $config->allowElement($etiqueta);
        }

        return trim((new HtmlSanitizer($config))->sanitize((string) $html));
    }

    /**
     * Indica si el HTML no tiene texto visible (una sección obligatoria «vacía»).
     *
     * @param string|null $html HTML de la sección.
     *
     * @return bool
     */
    public function estaVacio(?string $html): bool
    {
        $texto = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $texto)) === '';
    }
}
