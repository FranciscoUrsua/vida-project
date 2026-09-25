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
