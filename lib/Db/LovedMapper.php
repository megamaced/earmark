<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Loved>
 */
class LovedMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'earmark_loved', Loved::class);
    }

    /**
     * Insert a loved track unless the user already has it. Relies on the
     * unique (user_id, content_key) index, catching the constraint violation
     * so re-imports stay race-free.
     *
     * @return bool true if inserted, false if it was already present
     */
    public function createIfNew(Loved $loved): bool
    {
        try {
            $this->insert($loved);
            return true;
        } catch (Exception $e) {
            if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * A user's loved tracks, most-recently-loved first.
     *
     * @return Loved[]
     */
    public function findForUser(string $userId, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('loved_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $this->findEntities($qb);
    }

    public function countForUser(string $userId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        $result = $qb->executeQuery();
        $value  = $result->fetchOne();
        $result->closeCursor();

        return (int) ($value ?? 0);
    }

    /**
     * Delete every loved track for a user.
     *
     * @return int rows deleted
     */
    public function deleteAllForUser(string $userId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $qb->executeStatement();
    }
}
