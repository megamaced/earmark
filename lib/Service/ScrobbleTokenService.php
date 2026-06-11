<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\Db\ScrobbleToken;
use OCA\Earmark\Db\ScrobbleTokenMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Security\ISecureRandom;

/**
 * Issues, lists, revokes and authenticates per-user scrobble tokens. The
 * plaintext token is generated once and returned to the caller at issue
 * time; only its SHA-256 hash is persisted, so a leaked database row cannot
 * be used to scrobble.
 */
class ScrobbleTokenService
{
    private const TOKEN_LENGTH = 32;
    private const LABEL_MAX_LEN = 64;

    public function __construct(
        private readonly ScrobbleTokenMapper $mapper,
        private readonly ISecureRandom $secureRandom,
        private readonly ITimeFactory $timeFactory,
    ) {
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Issue a new token. The returned plaintext is the only time it is
     * available — thereafter only the hash exists.
     *
     * @return array{token: string, entity: ScrobbleToken}
     */
    public function issue(string $userId, ?string $label): array
    {
        $token = $this->secureRandom->generate(self::TOKEN_LENGTH, ISecureRandom::CHAR_ALPHANUMERIC);

        $label = ($label !== null && trim($label) !== '')
            ? mb_substr(trim($label), 0, self::LABEL_MAX_LEN)
            : null;

        $entity = new ScrobbleToken();
        $entity->setUserId($userId);
        $entity->setTokenHash(self::hashToken($token));
        $entity->setLabel($label);
        $entity->setCreatedAt($this->timeFactory->getTime());

        $entity = $this->mapper->insert($entity);

        return ['token' => $token, 'entity' => $entity];
    }

    /** @return ScrobbleToken[] */
    public function listForUser(string $userId): array
    {
        return $this->mapper->findByUser($userId);
    }

    /** Revoke a token by id, scoped to its owner. Returns false if not found / not theirs. */
    public function revoke(string $userId, int $id): bool
    {
        try {
            $entity = $this->mapper->find($id);
        } catch (DoesNotExistException) {
            return false;
        }

        if ($entity->getUserId() !== $userId) {
            return false;
        }

        $this->mapper->delete($entity);
        return true;
    }

    /**
     * Resolve a plaintext token to the owning user id, or null if unknown.
     * Touches `last_used_at` on success (best-effort).
     */
    public function authenticate(string $token): ?string
    {
        if ($token === '') {
            return null;
        }

        $entity = $this->mapper->findByTokenHash(self::hashToken($token));
        if ($entity === null) {
            return null;
        }

        $entity->setLastUsedAt($this->timeFactory->getTime());
        try {
            $this->mapper->update($entity);
        } catch (\Throwable) {
            // last_used_at is non-critical; don't fail auth if the update races
        }

        return $entity->getUserId();
    }
}
