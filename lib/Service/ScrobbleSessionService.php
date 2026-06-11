<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\Db\ScrobbleSession;
use OCA\Earmark\Db\ScrobbleSessionMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Security\ISecureRandom;

/**
 * Issues and resolves AudioScrobbler 1.2 handshake sessions. Sessions are
 * created after a successful handshake and looked up by id on each
 * now-playing / submission request.
 */
class ScrobbleSessionService
{
    private const SESSION_ID_LENGTH = 32;

    public function __construct(
        private readonly ScrobbleSessionMapper $mapper,
        private readonly ISecureRandom $secureRandom,
        private readonly ITimeFactory $timeFactory,
    ) {
    }

    /** Create a new session for a user and return its opaque id. */
    public function create(string $userId): string
    {
        $sessionId = $this->secureRandom->generate(self::SESSION_ID_LENGTH, ISecureRandom::CHAR_ALPHANUMERIC);

        $entity = new ScrobbleSession();
        $entity->setSessionId($sessionId);
        $entity->setUserId($userId);
        $entity->setCreatedAt($this->timeFactory->getTime());
        $this->mapper->insert($entity);

        return $sessionId;
    }

    /** Resolve a session id to its owning user, or null if unknown. */
    public function resolve(string $sessionId): ?string
    {
        if ($sessionId === '') {
            return null;
        }
        return $this->mapper->findBySessionId($sessionId)?->getUserId();
    }
}
