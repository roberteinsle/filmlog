<?php

namespace App\Services\Tmdb;

use RuntimeException;

/**
 * TMDB ist nicht erreichbar, nicht konfiguriert oder hat einen Fehler geliefert.
 */
class TmdbException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('TMDB_TOKEN ist nicht gesetzt.');
    }
}
