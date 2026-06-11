<?php

declare(strict_types=1);

namespace OCA\Earmark\Exception;

/**
 * Thrown when a MusicBrainz / ListenBrainz metadata lookup fails or returns
 * an unexpected response.
 */
class MusicBrainzException extends \RuntimeException
{
}
