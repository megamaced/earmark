<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Listen>
 */
class ListenMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'earmark_listens', Listen::class);
    }

    /**
     * Insert a listen unless an identical play already exists for the user.
     * Relies on the unique (user_id, dedup_hash) index, catching the
     * constraint violation rather than pre-checking, so concurrent imports
     * stay race-free.
     *
     * @return bool true if inserted, false if it was a duplicate
     */
    public function createIfNew(Listen $listen): bool
    {
        try {
            $this->insert($listen);
            return true;
        } catch (Exception $e) {
            if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Most recent listens for a user, newest first, optionally bounded to a
     * [from, to) listened-at window.
     *
     * @return Listen[]
     */
    public function findRecent(
        string $userId,
        int $limit = 50,
        int $offset = 0,
        ?int $from = null,
        ?int $to = null,
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('listened_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        $this->applyWindow($qb, $from, $to);

        return $this->findEntities($qb);
    }

    /**
     * Listens in a given resolution state, oldest id first — the work queue
     * for the MusicBrainz resolver job.
     *
     * @return Listen[]
     */
    public function findByState(string $state, int $limit = 200): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('resolution_state', $qb->createNamedParameter($state)))
            ->orderBy('id', 'ASC')
            ->setMaxResults($limit);

        return $this->findEntities($qb);
    }

    /**
     * Newest listen timestamp for a user, or null if they have none. Used to
     * resume incremental imports from where the last run left off.
     */
    public function getNewestListenedAt(string $userId): ?int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->max('listened_at'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        $result = $qb->executeQuery();
        $value  = $result->fetchOne();
        $result->closeCursor();

        return ($value === false || $value === null) ? null : (int) $value;
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

    /** Number of distinct artists a user has listened to. */
    public function countDistinctArtists(string $userId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(DISTINCT ' . $qb->getColumnName('artist') . ')'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        $result = $qb->executeQuery();
        $value  = $result->fetchOne();
        $result->closeCursor();

        return (int) ($value ?? 0);
    }

    /**
     * Delete every listen for a user (the bulk-wipe primitive). The caller is
     * responsible for advancing the user's `wipedAt` marker so clients
     * full-resync.
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

    /**
     * Apply a resolved MusicBrainz mapping to every listen sharing a content
     * key (so one lookup updates all plays of the same track at once).
     *
     * @return int rows updated
     */
    public function applyResolution(
        string $contentKey,
        ?string $artistMbid,
        ?string $recordingMbid,
        ?string $releaseMbid,
        string $state,
        int $resolvedAt,
    ): int {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('artist_mbid', $qb->createNamedParameter($artistMbid))
            ->set('recording_mbid', $qb->createNamedParameter($recordingMbid))
            ->set('release_mbid', $qb->createNamedParameter($releaseMbid))
            ->set('resolution_state', $qb->createNamedParameter($state))
            ->set('resolved_at', $qb->createNamedParameter($resolvedAt, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('content_key', $qb->createNamedParameter($contentKey)));

        return $qb->executeStatement();
    }

    /**
     * Top artists by play count.
     *
     * @return list<array{name: string, count: int}>
     */
    public function topArtists(string $userId, ?int $from, ?int $to, int $limit, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('artist')
            ->selectAlias($qb->func()->count('*'), 'cnt')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->groupBy('artist')
            ->orderBy('cnt', 'DESC')
            ->addOrderBy('artist', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        $this->applyWindow($qb, $from, $to);

        return array_map(
            static fn (array $row): array => ['name' => (string) $row['artist'], 'count' => (int) $row['cnt']],
            $this->fetchAll($qb),
        );
    }

    /**
     * Top tracks by play count.
     *
     * @return list<array{artist: string, track: string, count: int}>
     */
    public function topTracks(string $userId, ?int $from, ?int $to, int $limit, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('artist', 'track')
            ->selectAlias($qb->func()->count('*'), 'cnt')
            ->selectAlias($qb->func()->max('release_mbid'), 'rel_mbid')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->groupBy('artist', 'track')
            ->orderBy('cnt', 'DESC')
            ->addOrderBy('artist', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        $this->applyWindow($qb, $from, $to);

        return array_map(
            static fn (array $row): array => [
                'artist'      => (string) $row['artist'],
                'track'       => (string) $row['track'],
                'count'       => (int) $row['cnt'],
                'releaseMbid' => $row['rel_mbid'] !== null ? (string) $row['rel_mbid'] : null,
            ],
            $this->fetchAll($qb),
        );
    }

    /**
     * Top albums by play count (rows without an album are excluded).
     *
     * @return list<array{artist: string, album: string, count: int}>
     */
    public function topAlbums(string $userId, ?int $from, ?int $to, int $limit, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('artist', 'album')
            ->selectAlias($qb->func()->count('*'), 'cnt')
            ->selectAlias($qb->func()->max('release_mbid'), 'rel_mbid')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNotNull('album'))
            ->groupBy('artist', 'album')
            ->orderBy('cnt', 'DESC')
            ->addOrderBy('artist', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        $this->applyWindow($qb, $from, $to);

        return array_map(
            static fn (array $row): array => [
                'artist'      => (string) $row['artist'],
                'album'       => (string) $row['album'],
                'count'       => (int) $row['cnt'],
                'releaseMbid' => $row['rel_mbid'] !== null ? (string) $row['rel_mbid'] : null,
            ],
            $this->fetchAll($qb),
        );
    }

    /**
     * Raw `listened_at` timestamps for a user since `from`, capped to bound
     * memory — used to bucket the listening clock in PHP (portable across DBs).
     *
     * @return list<int>
     */
    public function listenedAtSince(string $userId, ?int $from, ?int $to = null, int $limit = 200000): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('listened_at')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->setMaxResults($limit);
        $this->applyWindow($qb, $from, $to);

        return array_map(static fn (array $row): int => (int) $row['listened_at'], $this->fetchAll($qb));
    }

    /** Oldest listen timestamp for a user, or null if they have none. */
    public function getOldestListenedAt(string $userId): ?int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->min('listened_at'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        $result = $qb->executeQuery();
        $value  = $result->fetchOne();
        $result->closeCursor();

        return ($value === false || $value === null) ? null : (int) $value;
    }

    /** Constrain a query to a [from, to) listened-at window; null bounds are open. */
    private function applyWindow(IQueryBuilder $qb, ?int $from, ?int $to): void
    {
        if ($from !== null) {
            $qb->andWhere($qb->expr()->gte('listened_at', $qb->createNamedParameter($from, IQueryBuilder::PARAM_INT)));
        }
        if ($to !== null) {
            $qb->andWhere($qb->expr()->lt('listened_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_INT)));
        }
    }

    /** @return list<array<string, mixed>> */
    private function fetchAll(IQueryBuilder $qb): array
    {
        $result = $qb->executeQuery();
        $rows   = $result->fetchAll();
        $result->closeCursor();
        return $rows;
    }
}
