#!/usr/bin/env bash
# Genera los PDF de prueba de la custodia v2 (TF-DOC-26 a 78) con Ghostscript.
# Uso: bash Modules/Documentos/tests/fixtures/generar-fixtures.sh
# Los ficheros resultantes se versionan; este script sirve para regenerarlos.
set -euo pipefail
cd "$(dirname "$0")"

pagina() { echo "/Helvetica findfont 18 scalefont setfont 72 700 moveto ($1) show showpage"; }

# valido.pdf: 2 páginas.
gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -o valido.pdf \
  -c "$(pagina 'Documento de prueba VIDA - pagina 1') $(pagina 'Documento de prueba VIDA - pagina 2')"

# largo.pdf: 60 páginas.
gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -o largo.pdf \
  -c "/Helvetica findfont 18 scalefont setfont 1 1 60 { 72 700 moveto 10 string cvs show showpage } for"

# comprimido.zip: contenedor, debe rechazarse.
printf 'contenido' > /tmp/vida-fixture.txt
(cd /tmp && zip -q -j - vida-fixture.txt) > comprimido.zip
rm -f /tmp/vida-fixture.txt

# zip-disfrazado.pdf: zip con extensión .pdf (la detección es por contenido).
cp comprimido.zip zip-disfrazado.pdf

# pdf-disfrazado.jpg: PDF con extensión .jpg.
cp valido.pdf pdf-disfrazado.jpg

# protegido.pdf: requiere contraseña para abrirse.
qpdf --encrypt vida-usuario vida-propietario 256 -- valido.pdf protegido.pdf

# con-adjunto.pdf: lleva un fichero incrustado.
printf 'adjunto de prueba' > /tmp/vida-adjunto.txt
qpdf --add-attachment /tmp/vida-adjunto.txt --key=adjunto.txt -- valido.pdf con-adjunto.pdf
rm -f /tmp/vida-adjunto.txt

# con-javascript.pdf: JavaScript al abrir (OpenAction y árbol de nombres) y un enlace /Launch.
python3 - <<'PY'
objetos = [
    b"<< /Type /Catalog /Pages 2 0 R /OpenAction 5 0 R /Names << /JavaScript << /Names [(inicio) 5 0 R] >> >> >>",
    b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
    b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R"
    b" /Resources << /Font << /F1 7 0 R >> >> /Annots [6 0 R] /AA << /O 5 0 R >> >>",
    None,
    b"<< /Type /Action /S /JavaScript /JS (app.alert\\('VIDA'\\);) >>",
    b"<< /Type /Annot /Subtype /Link /Rect [72 600 300 630] /A << /S /Launch /F (calc.exe) >> >>",
    b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
]
contenido = b"BT /F1 18 Tf 72 700 Td (Documento con JavaScript) Tj ET"
objetos[3] = b"<< /Length %d >>\nstream\n" % len(contenido) + contenido + b"\nendstream"
salida = bytearray(b"%PDF-1.7\n")
posiciones = []
for numero, cuerpo in enumerate(objetos, start=1):
    posiciones.append(len(salida))
    salida += b"%d 0 obj\n" % numero + cuerpo + b"\nendobj\n"
xref = len(salida)
salida += b"xref\n0 %d\n0000000000 65535 f \n" % (len(objetos) + 1)
for posicion in posiciones:
    salida += b"%010d 00000 n \n" % posicion
salida += b"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n" % (len(objetos) + 1, xref)
open("con-javascript.pdf", "wb").write(salida)
PY

# foto.jpg y foto.png: primera página de valido.pdf rasterizada.
gs -q -dNOPAUSE -dBATCH -sDEVICE=jpeg -r40 -dFirstPage=1 -dLastPage=1 -o foto.jpg valido.pdf
gs -q -dNOPAUSE -dBATCH -sDEVICE=png16m -r40 -dFirstPage=1 -dLastPage=1 -o foto.png valido.pdf

# documento.docx y con-macros.docm: paquetes OOXML mínimos (el .docm lleva vbaProject.bin).
python3 - <<'PY'
import zipfile

rels = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        '</Relationships>')
documento = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
             '<w:body><w:p><w:r><w:t>Documento de prueba VIDA</w:t></w:r></w:p></w:body></w:document>')

def paquete(nombre, tipo_principal, macros):
    tipos = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
             '<Default Extension="xml" ContentType="application/xml"/>'
             + ('<Default Extension="bin" ContentType="application/vnd.ms-office.vbaProject"/>' if macros else '')
             + '<Override PartName="/word/document.xml" ContentType="' + tipo_principal + '"/>'
             '</Types>')
    with zipfile.ZipFile(nombre, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml", tipos)
        z.writestr("_rels/.rels", rels)
        z.writestr("word/document.xml", documento)
        if macros:
            z.writestr("word/vbaProject.bin", b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1" + b"\0" * 64)

paquete("documento.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml", False)
paquete("con-macros.docm", "application/vnd.ms-word.document.macroEnabled.main+xml", True)
PY

# ejecutable.exe: solo la cabecera MZ/PE de un ejecutable de Windows, no ejecuta nada.
python3 -c "
import struct
cabecera = bytearray(b'MZ' + b'\0' * 126)
struct.pack_into('<I', cabecera, 0x3c, 0x80)
open('ejecutable.exe', 'wb').write(bytes(cabecera) + b'PE\0\0' + b'\0' * 256)
"

# La firma de prueba EICAR no se versiona (los antivirus de los equipos la pondrían
# en cuarentena): el test TF-DOC-51 la construye en tiempo de ejecución.
