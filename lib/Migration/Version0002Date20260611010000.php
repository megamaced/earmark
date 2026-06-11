<?php

declare(strict_types=1);

namespace OCA\Earmark\Migration;

use Closure;
use OCA\Earmark\BackgroundJob\LastfmImportJob;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Registers the recurring Last.fm import job. Idempotent — safe to run again.
 */
class Version0002Date20260611010000 extends SimpleMigrationStep
{
    public function __construct(private readonly IJobList $jobList)
    {
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        if (!$this->jobList->has(LastfmImportJob::class, null)) {
            $this->jobList->add(LastfmImportJob::class);
        }
    }
}
