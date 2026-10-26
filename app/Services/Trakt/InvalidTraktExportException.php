<?php

namespace App\Services\Trakt;

use RuntimeException;

/**
 * Die hochgeladene Datei lässt sich nicht als Trakt-Export lesen; ein neuer Versuch ändert daran nichts.
 */
class InvalidTraktExportException extends RuntimeException {}
