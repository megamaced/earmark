<?php

declare(strict_types=1);

namespace OCA\Earmark\BackgroundJob;

use OCA\Earmark\Service\MusicBrainzResolveService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Periodically resolves a bounded batch of pending listens to MusicBrainz IDs.
 */
class MusicBrainzResolveJob extends TimedJob
{
    public function __construct(
        ITimeFactory $time,
        private readonly MusicBrainzResolveService $resolveService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(5 * 60);
    }

    protected function run($argument): void
    {
        try {
            $this->resolveService->runSlice();
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: MusicBrainz resolve slice failed: {msg}', [
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
