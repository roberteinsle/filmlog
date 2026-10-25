<?php

namespace App\Services\Tmdb;

/**
 * TMDB kennt die angefragte Ressource nicht (HTTP 404).
 */
class MovieNotFoundException extends TmdbException {}
