<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\Entity;

/**
 * An ephemeral AudioScrobbler 1.2 session, created at handshake and presented
 * by the client (as `s=`) on subsequent now-playing / submission requests.
 * `createdAt` is a Unix timestamp (seconds).
 *
 * @method string getSessionId()
 * @method void setSessionId(string $sessionId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class ScrobbleSession extends Entity
{
    protected string $sessionId = '';
    protected string $userId = '';
    protected int $createdAt = 0;

    public function __construct()
    {
        $this->addType('createdAt', 'integer');
    }
}
