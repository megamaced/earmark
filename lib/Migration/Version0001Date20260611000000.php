<?php

declare(strict_types=1);

namespace OCA\Earmark\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial schema for the Earmark app.
 *
 * Tables:
 *   earmark_listens          — one row per recorded play, per user
 *   earmark_mb_cache         — cached MusicBrainz resolutions, keyed on track identity
 *   earmark_scrobble_tokens  — per-user credentials for the inbound scrobble API
 *
 * Timestamps (`listened_at`, `resolved_at`, `created_at`, `last_used_at`)
 * are stored as Unix seconds in BIGINT columns. Long artist/track strings
 * are kept out of indexes by hashing them into fixed-length key columns
 * (`content_key`, `dedup_hash`, `cache_key`).
 */
class Version0001Date20260611000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // ── Listens ────────────────────────────────────────────────────────────
        if (!$schema->hasTable('earmark_listens')) {
            $listens = $schema->createTable('earmark_listens');

            $listens->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $listens->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);

            // Raw scrobble text
            $listens->addColumn('artist', Types::STRING, ['notnull' => true, 'length' => 512]);
            $listens->addColumn('track', Types::STRING, ['notnull' => true, 'length' => 512]);
            $listens->addColumn('album', Types::STRING, ['notnull' => false, 'length' => 512]);

            // Derived keys (sha1 hex, 40 chars) — see OCA\Earmark\Normalize
            $listens->addColumn('content_key', Types::STRING, ['notnull' => true, 'length' => 40]);
            $listens->addColumn('dedup_hash', Types::STRING, ['notnull' => true, 'length' => 40]);

            // Resolved MusicBrainz identifiers (UUID, 36 chars)
            $listens->addColumn('artist_mbid', Types::STRING, ['notnull' => false, 'length' => 36]);
            $listens->addColumn('recording_mbid', Types::STRING, ['notnull' => false, 'length' => 36]);
            $listens->addColumn('release_mbid', Types::STRING, ['notnull' => false, 'length' => 36]);

            $listens->addColumn('listened_at', Types::BIGINT, ['notnull' => true]);
            $listens->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 32]);
            $listens->addColumn('resolution_state', Types::STRING, [
                'notnull' => true,
                'length'  => 16,
                'default' => 'pending',
            ]);
            $listens->addColumn('resolved_at', Types::BIGINT, ['notnull' => false]);
            $listens->addColumn('created_at', Types::BIGINT, ['notnull' => true]);

            $listens->setPrimaryKey(['id']);
            // Idempotency: one row per (user, play)
            $listens->addUniqueIndex(['user_id', 'dedup_hash'], 'earmark_listens_dedup');
            // Recent / stats / timestamp-windowed queries
            $listens->addIndex(['user_id', 'listened_at'], 'earmark_listens_time');
            // Resolver work queue: pending listens grouped by track identity
            $listens->addIndex(['resolution_state', 'content_key'], 'earmark_listens_resolve');
        }

        // ── MusicBrainz resolution cache ────────────────────────────────────────
        if (!$schema->hasTable('earmark_mb_cache')) {
            $cache = $schema->createTable('earmark_mb_cache');

            $cache->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $cache->addColumn('cache_key', Types::STRING, ['notnull' => true, 'length' => 40]);
            $cache->addColumn('artist_mbid', Types::STRING, ['notnull' => false, 'length' => 36]);
            $cache->addColumn('recording_mbid', Types::STRING, ['notnull' => false, 'length' => 36]);
            $cache->addColumn('release_mbid', Types::STRING, ['notnull' => false, 'length' => 36]);
            $cache->addColumn('matched', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $cache->addColumn('resolved_at', Types::BIGINT, ['notnull' => true]);

            $cache->setPrimaryKey(['id']);
            $cache->addUniqueIndex(['cache_key'], 'earmark_mbcache_key');
        }

        // ── Inbound scrobble tokens ─────────────────────────────────────────────
        if (!$schema->hasTable('earmark_scrobble_tokens')) {
            $tokens = $schema->createTable('earmark_scrobble_tokens');

            $tokens->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $tokens->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $tokens->addColumn('token_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
            $tokens->addColumn('label', Types::STRING, ['notnull' => false, 'length' => 64]);
            $tokens->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $tokens->addColumn('last_used_at', Types::BIGINT, ['notnull' => false]);

            $tokens->setPrimaryKey(['id']);
            $tokens->addUniqueIndex(['token_hash'], 'earmark_token_hash');
            $tokens->addIndex(['user_id'], 'earmark_token_user');
        }

        return $schema;
    }
}
