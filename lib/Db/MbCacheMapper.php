<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<MbCacheEntry>
 */
class MbCacheMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'earmark_mb_cache', MbCacheEntry::class);
    }

    /** Look up a cached resolution by content key, or null if not cached yet. */
    public function findByKey(string $cacheKey): ?MbCacheEntry
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('cache_key', $qb->createNamedParameter($cacheKey)))
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }
}
