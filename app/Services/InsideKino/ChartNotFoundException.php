<?php

namespace App\Services\InsideKino;

use RuntimeException;

/**
 * Für das Jahr gibt es bei insidekino.de keine Jahrescharts (HTTP 404).
 */
class ChartNotFoundException extends RuntimeException {}
