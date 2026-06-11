<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ScrobbleSession>
 */
class ScrobbleSessionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'earmark_scrobble_sessions', ScrobbleSession::class);
    }

    public function findBySessionId(string $sessionId): ?ScrobbleSession
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)))
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }
}
