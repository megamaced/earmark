<?php

declare(strict_types=1);

namespace OCA\Earmark\Exception;

/**
 * Thrown when an inbound scrobble payload is malformed. The message is safe
 * to return to the client as a 400.
 */
class InvalidPayloadException extends \RuntimeException
{
}
