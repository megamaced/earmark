<?php

declare(strict_types=1);

namespace OCA\Earmark\Migration;

use Closure;
use OCA\Earmark\BackgroundJob\MusicBrainzResolveJob;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Registers the recurring MusicBrainz resolver job. Idempotent.
 */
class Version0003Date20260611020000 extends SimpleMigrationStep
{
    public function __construct(private readonly IJobList $jobList)
    {
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        if (!$this->jobList->has(MusicBrainzResolveJob::class, null)) {
            $this->jobList->add(MusicBrainzResolveJob::class);
        }
    }
}
