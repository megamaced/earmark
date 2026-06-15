<?php

declare(strict_types=1);

namespace OCA\Earmark\Controller;

use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Db\LovedMapper;
use OCA\Earmark\Service\StatsService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Read API for the web UI (and, later, the Android companion): recent listens
 * and listening statistics. All endpoints are user-scoped.
 */
class ApiController extends OCSController
{
    private const MAX_LIMIT = 200;

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ListenMapper $listenMapper,
        private readonly LovedMapper $lovedMapper,
        private readonly StatsService $statsService,
        private readonly IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function listens(
        int $limit = 50,
        int $offset = 0,
        string $range = 'all',
        int $from = 0,
        int $to = 0,
    ): DataResponse {
        $limit  = max(1, min($limit, self::MAX_LIMIT));
        $offset = max(0, $offset);
        [$lo, $hi] = $this->statsService->window($range, self::nz($from), self::nz($to));

        $listens = $this->listenMapper->findRecent($this->userId(), $limit, $offset, $lo, $hi);
        return new DataResponse(array_map(static fn ($l) => $l->jsonSerialize(), $listens));
    }

    #[NoAdminRequired]
    public function top(
        string $type = 'artist',
        string $range = 'all',
        int $limit = 20,
        int $offset = 0,
        int $from = 0,
        int $to = 0,
    ): DataResponse {
        $limit  = max(1, min($limit, 100));
        $offset = max(0, $offset);
        return new DataResponse(
            $this->statsService->top($this->userId(), $type, $range, $limit, $offset, self::nz($from), self::nz($to)),
        );
    }

    #[NoAdminRequired]
    public function clock(string $range = 'all', int $from = 0, int $to = 0): DataResponse
    {
        return new DataResponse($this->statsService->clock($this->userId(), $range, self::nz($from), self::nz($to)));
    }

    #[NoAdminRequired]
    public function totals(): DataResponse
    {
        return new DataResponse($this->statsService->totals($this->userId()));
    }

    #[NoAdminRequired]
    public function years(): DataResponse
    {
        return new DataResponse($this->statsService->perYear($this->userId()));
    }

    #[NoAdminRequired]
    public function loved(int $limit = 50, int $offset = 0): DataResponse
    {
        $limit  = max(1, min($limit, self::MAX_LIMIT));
        $offset = max(0, $offset);

        $loved = $this->lovedMapper->findForUser($this->userId(), $limit, $offset);
        return new DataResponse(array_map(static fn ($l) => $l->jsonSerialize(), $loved));
    }

    /** Treat 0 (an absent query param) as "no bound". */
    private static function nz(int $value): ?int
    {
        return $value > 0 ? $value : null;
    }

    private function userId(): string
    {
        $user = $this->userSession->getUser();
        return $user !== null ? $user->getUID() : '';
    }
}
