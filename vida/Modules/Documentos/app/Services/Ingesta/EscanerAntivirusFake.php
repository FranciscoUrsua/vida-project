<?php

namespace Modules\Documentos\Services\Ingesta;

use Modules\Documentos\Contracts\EscanerAntivirus;
use Modules\Documentos\Exceptions\AntivirusNoDisponibleException;

/**
 * Escáner antivirus simulado para tests: limpio por defecto, configurable para
 * dar positivo o fallar. El provider solo lo registra con APP_ENV=testing.
 */
class EscanerAntivirusFake implements EscanerAntivirus
{
    private ?string $firma = null;

    private bool $falla = false;

    /** @var list<string> Contenido de cada fichero analizado, en orden. */
    public array $analizados = [];

    /**
     * Hace que todos los análisis den positivo.
     *
     * @param string $firma Nombre de la firma que se devuelve.
     *
     * @return static
     */
    public function infectado(string $firma = 'Vida-Test-Signature'): static
    {
        $this->firma = $firma;

        return $this;
    }

    /**
     * Hace que todos los análisis fallen como si clamd no respondiera.
     *
     * @return static
     */
    public function fallando(): static
    {
        $this->falla = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function escanear(string $ruta): ?string
    {
        if ($this->falla) {
            throw new AntivirusNoDisponibleException('Antivirus simulado sin servicio.');
        }

        $this->analizados[] = (string) file_get_contents($ruta);

        return $this->firma;
    }
}
