<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ScrobbleToken>
 */
class ScrobbleTokenMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'earmark_scrobble_tokens', ScrobbleToken::class);
    }

    /**
     * Resolve an inbound scrobble request to its owner by token hash, or null
     * if the hash is unknown.
     */
    public function findByTokenHash(string $tokenHash): ?ScrobbleToken
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('token_hash', $qb->createNamedParameter($tokenHash)))
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * All tokens a user has issued, newest first — for the settings UI.
     *
     * @return ScrobbleToken[]
     */
    public function findByUser(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }
}
