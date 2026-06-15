<?php

declare(strict_types=1);

namespace OCA\Earmark\AppInfo;

use OCA\Earmark\BackgroundJob\LastfmImportJob;
use OCA\Earmark\BackgroundJob\MusicBrainzResolveJob;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use Throwable;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'earmark';

    private const JOBS = [
        LastfmImportJob::class,
        MusicBrainzResolveJob::class,
    ];

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
    }

    public function boot(IBootContext $context): void
    {
        // Register background jobs here rather than in a migration: Nextcloud
        // removes an app's jobs when it is disabled and does not re-run
        // migrations on re-enable, so migration-registered jobs do not survive
        // a disable/enable (or the upgrade install cycle). boot() runs on every
        // enable, and IJobList::add() is idempotent.
        try {
            $jobList = $context->getServerContainer()->get(IJobList::class);
            foreach (self::JOBS as $job) {
                if (!$jobList->has($job, null)) {
                    $jobList->add($job);
                }
            }
        } catch (Throwable) {
            // Non-fatal: don't break app boot if the job list is unavailable.
        }
    }
}
