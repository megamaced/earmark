<?php

declare(strict_types=1);

namespace OCA\Earmark\BackgroundJob;

use OCA\Earmark\Service\LastfmImportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Periodically advances each user's Last.fm import — a bounded backfill slice
 * while history remains, then a cheap incremental top-up once caught up.
 */
class LastfmImportJob extends TimedJob
{
    public function __construct(
        ITimeFactory $time,
        private readonly LastfmImportService $importService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(5 * 60);
    }

    protected function run($argument): void
    {
        foreach ($this->importService->usersWithActiveImport() as $userId) {
            try {
                $this->importService->runSlice($userId);
            } catch (\Throwable $e) {
                $this->logger->warning('Earmark: Last.fm import slice failed for {user}: {msg}', [
                    'user' => $userId,
                    'msg'  => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->importService->usersWithPendingLovedImport() as $userId) {
            try {
                $this->importService->runLovedSlice($userId);
            } catch (\Throwable $e) {
                $this->logger->warning('Earmark: loved-tracks import slice failed for {user}: {msg}', [
                    'user' => $userId,
                    'msg'  => $e->getMessage(),
                ]);
            }
        }
    }
}
