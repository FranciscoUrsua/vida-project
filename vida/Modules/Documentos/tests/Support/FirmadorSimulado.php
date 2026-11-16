<?php

namespace Modules\Documentos\Tests\Support;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use RuntimeException;

/**
 * Firmador de PDF solo para tests (fase 5): hace lo que haría el profesional con
 * AutoFirma o con su certificado, sin dependencias externas.
 *
 * Genera en el propio test un certificado autofirmado (no se versionan certificados)
 * y añade al PDF una firma PAdES básica (`adbe.pkcs7.detached`) por actualización
 * incremental: un diccionario de firma con su `/ByteRange`, un campo de firma
 * invisible y el catálogo con `/AcroForm`. El CMS se calcula con `openssl_cms_sign`
 * sobre los bytes cubiertos. `pdfsig` la da por íntegra («Signature is Valid»).
 *
 * Pensado para los PDF que genera dompdf (tabla xref clásica, un único trailer).
 */
class FirmadorSimulado
{
    /** Huecos reservados para el CMS en hexadecimal. */
    private const HUECO_CONTENIDO = 16384;

    private OpenSSLAsymmetricKey $clave;

    private OpenSSLCertificate $certificado;

    /**
     * Crea el firmador con un certificado autofirmado de prueba.
     *
     * @param string $titular Nombre común (CN) del certificado.
     */
    public function __construct(public readonly string $titular = 'PRUEBA PROFESIONAL - 00000000T')
    {
        $clave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => $titular, 'organizationName' => 'VIDA tests'], $clave, ['digest_alg' => 'sha256']);
        $certificado = openssl_csr_sign($csr, null, $clave, 1, ['digest_alg' => 'sha256']);

        if ($clave === false || $certificado === false) {
            throw new RuntimeException('No se ha podido generar el certificado de prueba: '.openssl_error_string());
        }

        $this->clave = $clave;
        $this->certificado = $certificado;
    }

    /**
     * Devuelve el PDF con una firma íntegra añadida.
     *
     * @param string $pdf PDF sin firmar (contenido binario).
     *
     * @return string PDF firmado.
     */
    public function firmar(string $pdf): string
    {
        preg_match_all('/startxref\s+(\d+)/', $pdf, $xref);
        preg_match_all('/\/Root\s+(\d+)\s+0\s+R/', $pdf, $root);
        preg_match_all('/\/Size\s+(\d+)/', $pdf, $size);

        if ($xref[1] === [] || $root[1] === [] || $size[1] === []) {
            throw new RuntimeException('El PDF no tiene la estructura esperada (xref clásica).');
        }

        $xrefAnterior = (int) end($xref[1]);
        $raiz = (int) end($root[1]);
        $tamano = (int) end($size[1]);

        preg_match_all('/(?<![0-9])'.$raiz.'\s+0\s+obj\s*(<<.*?>>)\s*endobj/s', $pdf, $catalogos);
        $catalogo = (string) end($catalogos[1]);

        [$firma, $campo, $formulario] = [$tamano, $tamano + 1, $tamano + 2];
        $hueco = str_repeat('0', self::HUECO_CONTENIDO);
        $objetos = [
            $firma => '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached '
                .'/ByteRange [0 ########## ########## ##########] /Contents <'.$hueco.'> '
                .'/M (D:'.gmdate('YmdHis').'Z) /Name ('.$this->titular.') >>',
            $campo => "<< /FT /Sig /Type /Annot /Subtype /Widget /Rect [0 0 0 0] /F 132 /T (Firma1) /V {$firma} 0 R >>",
            $formulario => "<< /Fields [{$campo} 0 R] /SigFlags 3 >>",
            $raiz => (string) preg_replace('/>>\s*$/', " /AcroForm {$formulario} 0 R >>", $catalogo),
        ];

        $salida = str_ends_with($pdf, "\n") ? $pdf : $pdf."\n";
        $posiciones = [];
        foreach ($objetos as $numero => $cuerpo) {
            $posiciones[$numero] = strlen($salida);
            $salida .= "{$numero} 0 obj\n{$cuerpo}\nendobj\n";
        }

        $posicionXref = strlen($salida);
        ksort($posiciones);
        $salida .= "xref\n";
        foreach ($posiciones as $numero => $posicion) {
            $salida .= "{$numero} 1\n".sprintf('%010d 00000 n ', $posicion)."\n";
        }
        $salida .= 'trailer'."\n".'<< /Size '.($tamano + 3)." /Root {$raiz} 0 R /Prev {$xrefAnterior} >>\nstartxref\n{$posicionXref}\n%%EOF\n";

        // ByteRange: todo el fichero salvo el valor de /Contents (incluidos sus < >).
        $inicio = strpos($salida, '/Contents <'.$hueco) + strlen('/Contents ');
        $fin = $inicio + self::HUECO_CONTENIDO + 2;
        $rango = sprintf('[0 %010d %010d %010d]', $inicio, $fin, strlen($salida) - $fin);
        $salida = (string) preg_replace('/\[0 ########## ########## ##########\]/', $rango, $salida, 1);

        $hex = bin2hex($this->cms(substr($salida, 0, $inicio).substr($salida, $fin)));

        return substr($salida, 0, $inicio + 1)
            .str_pad($hex, self::HUECO_CONTENIDO, '0')
            .substr($salida, $inicio + 1 + self::HUECO_CONTENIDO);
    }

    /**
     * Firma CMS separada (DER) de los bytes cubiertos.
     *
     * @param string $datos Bytes cubiertos por el ByteRange.
     *
     * @return string
     */
    private function cms(string $datos): string
    {
        $entrada = tempnam(sys_get_temp_dir(), 'vida-firma-in');
        $salida = tempnam(sys_get_temp_dir(), 'vida-firma-out');

        try {
            file_put_contents($entrada, $datos);

            if (! openssl_cms_sign($entrada, $salida, $this->certificado, $this->clave, [], OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER)) {
                throw new RuntimeException('openssl_cms_sign ha fallado: '.openssl_error_string());
            }

            return (string) file_get_contents($salida);
        } finally {
            @unlink($entrada);
            @unlink($salida);
        }
    }
}
