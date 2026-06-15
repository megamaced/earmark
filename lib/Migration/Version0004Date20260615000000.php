<?php

declare(strict_types=1);

namespace OCA\Earmark\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the `earmark_loved` table — a user's Last.fm "loved" tracks. Unlike
 * listens this is keyed per track identity (not per play), so it carries a
 * `content_key` and a unique (user_id, content_key) index for idempotent
 * re-imports. `loved_at` is the Unix timestamp Last.fm recorded the love.
 */
class Version0004Date20260615000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('earmark_loved')) {
            $loved = $schema->createTable('earmark_loved');

            $loved->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $loved->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $loved->addColumn('artist', Types::STRING, ['notnull' => true, 'length' => 512]);
            $loved->addColumn('track', Types::STRING, ['notnull' => true, 'length' => 512]);
            // sha1 of normalised artist|track (no album) — see OCA\Earmark\Normalize
            $loved->addColumn('content_key', Types::STRING, ['notnull' => true, 'length' => 40]);
            $loved->addColumn('recording_mbid', Types::STRING, ['notnull' => false, 'length' => 36]);
            $loved->addColumn('loved_at', Types::BIGINT, ['notnull' => true]);
            $loved->addColumn('created_at', Types::BIGINT, ['notnull' => true]);

            $loved->setPrimaryKey(['id']);
            $loved->addUniqueIndex(['user_id', 'content_key'], 'earmark_loved_uniq');
            $loved->addIndex(['user_id', 'loved_at'], 'earmark_loved_time');
        }

        return $schema;
    }
}
