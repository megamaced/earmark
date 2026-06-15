<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\AppInfo\Application;
use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Db\Loved;
use OCA\Earmark\Db\LovedMapper;
use OCA\Earmark\Exception\LastfmException;
use OCA\Earmark\Normalize;
use OCA\Earmark\Source;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Drives the per-user Last.fm history import. State lives in user config:
 *
 *   lastfm_username      — the account to import from
 *   lastfm_api_key       — the user's own Last.fm API key (per-user, not shared)
 *   lastfm_import_state  — '' (idle) | 'backfill' | 'done'
 *   lastfm_import_to     — backfill cursor: the upper `to` bound (Unix ts) for
 *                          the next fetch; walks newest→oldest, decreasing
 *   lastfm_loved_state   — '' (idle) | 'pending' | 'done' (loved-tracks import)
 *   lastfm_loved_page    — loved-import cursor: the next page to fetch
 *
 * Backfill windows by timestamp (robust against deep-pagination drift); once
 * the oldest page is reached the state flips to 'done' and subsequent runs do
 * a cheap incremental top-up. All inserts are idempotent, so overlapping
 * windows never duplicate. Work is bounded per run so a cron tick stays short.
 * The loved-tracks import is a separate, simpler one-shot paginated pass.
 */
class LastfmImportService
{
    public const STATE_IDLE = '';
    public const STATE_BACKFILL = 'backfill';
    public const STATE_DONE = 'done';
    public const LOVED_PENDING = 'pending';
    public const LOVED_DONE = 'done';

    private const KEY_USERNAME = 'lastfm_username';
    private const KEY_API = 'lastfm_api_key';
    private const KEY_STATE = 'lastfm_import_state';
    private const KEY_CURSOR = 'lastfm_import_to';
    private const KEY_LOVED_STATE = 'lastfm_loved_state';
    private const KEY_LOVED_PAGE = 'lastfm_loved_page';

    private const PAGES_PER_RUN = 25;
    private const THROTTLE_MICROSECONDS = 250_000; // ~4 req/s, within Last.fm's limit

    public function __construct(
        private readonly LastfmService $lastfm,
        private readonly ListenIngestService $ingest,
        private readonly ListenMapper $listenMapper,
        private readonly LovedMapper $lovedMapper,
        private readonly ITimeFactory $timeFactory,
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getUsername(string $userId): string
    {
        return $this->config->getUserValue($userId, Application::APP_ID, self::KEY_USERNAME, '');
    }

    public function setUsername(string $userId, string $username): void
    {
        $this->config->setUserValue($userId, Application::APP_ID, self::KEY_USERNAME, trim($username));
    }

    public function getApiKey(string $userId): string
    {
        return $this->config->getUserValue($userId, Application::APP_ID, self::KEY_API, '');
    }

    public function setApiKey(string $userId, string $apiKey): void
    {
        $this->config->setUserValue($userId, Application::APP_ID, self::KEY_API, trim($apiKey));
    }

    public function hasApiKey(string $userId): bool
    {
        return $this->getApiKey($userId) !== '';
    }

    public function getState(string $userId): string
    {
        return $this->config->getUserValue($userId, Application::APP_ID, self::KEY_STATE, self::STATE_IDLE);
    }

    public function isConfigured(string $userId): bool
    {
        return $this->getUsername($userId) !== '' && $this->hasApiKey($userId);
    }

    /** Begin (or restart) a full backfill for the user. */
    public function startBackfill(string $userId): void
    {
        if (!$this->isConfigured($userId)) {
            throw new LastfmException('Last.fm username and API key must be set before importing');
        }
        $this->config->setUserValue($userId, Application::APP_ID, self::KEY_STATE, self::STATE_BACKFILL);
        $this->config->setUserValue($userId, Application::APP_ID, self::KEY_CURSOR, '0');
    }

    /** Users the background job should service this tick. */
    public function usersWithActiveImport(): array
    {
        return array_values(array_unique(array_merge(
            $this->config->getUsersForUserValue(Application::APP_ID, self::KEY_STATE, self::STATE_BACKFILL),
            $this->config->getUsersForUserValue(Application::APP_ID, self::KEY_STATE, self::STATE_DONE),
        )));
    }

    public function getLovedState(string $userId): string
    {
        return $this->config->getUserValue($userId, Application::APP_ID, self::KEY_LOVED_STATE, self::STATE_IDLE);
    }

    /** Begin (or restart) a loved-tracks import for the user. */
    public function startLovedImport(string $userId): void
    {
        if (!$this->isConfigured($userId)) {
            throw new LastfmException('Last.fm username and API key must be set before importing');
        }
        $this->config->setUserValue($userId, Application::APP_ID, self::KEY_LOVED_STATE, self::LOVED_PENDING);
        $this->config->setUserValue($userId, Application::APP_ID, self::KEY_LOVED_PAGE, '1');
    }

    /** Users with a loved-tracks import still in progress. */
    public function usersWithPendingLovedImport(): array
    {
        return $this->config->getUsersForUserValue(Application::APP_ID, self::KEY_LOVED_STATE, self::LOVED_PENDING);
    }

    /**
     * Run one bounded slice of import work for a user.
     *
     * @return int number of newly-stored listens
     */
    public function runSlice(string $userId): int
    {
        if (!$this->isConfigured($userId)) {
            return 0;
        }

        return match ($this->getState($userId)) {
            self::STATE_BACKFILL => $this->runBackfillSlice($userId),
            self::STATE_DONE     => $this->runIncremental($userId),
            default              => 0,
        };
    }

    private function runBackfillSlice(string $userId): int
    {
        $apiKey   = $this->getApiKey($userId);
        $username = $this->getUsername($userId);
        $cursor   = (int) $this->config->getUserValue($userId, Application::APP_ID, self::KEY_CURSOR, '0');
        $stored   = 0;

        for ($i = 0; $i < self::PAGES_PER_RUN; $i++) {
            $result = $this->lastfm->fetchRecentTracks(
                $apiKey,
                $username,
                1,
                LastfmService::MAX_PAGE_SIZE,
                null,
                $cursor > 0 ? $cursor : null,
            );

            if ($result['listens'] === []) {
                $this->markDone($userId);
                break;
            }

            $oldest = PHP_INT_MAX;
            foreach ($result['listens'] as $listen) {
                $stored += $this->store($userId, $listen) ? 1 : 0;
                $oldest = min($oldest, (int) $listen->listenedAt);
            }

            // Next window: strictly older than the oldest play we just saw.
            $cursor = $oldest - 1;
            $this->config->setUserValue($userId, Application::APP_ID, self::KEY_CURSOR, (string) $cursor);

            if ($cursor <= 0) {
                $this->markDone($userId);
                break;
            }

            usleep(self::THROTTLE_MICROSECONDS);
        }

        return $stored;
    }

    private function runIncremental(string $userId): int
    {
        $result = $this->lastfm->fetchRecentTracks(
            $this->getApiKey($userId),
            $this->getUsername($userId),
            1,
            LastfmService::MAX_PAGE_SIZE,
        );
        $stored = 0;
        foreach ($result['listens'] as $listen) {
            $stored += $this->store($userId, $listen) ? 1 : 0;
        }
        return $stored;
    }

    /**
     * Run one bounded slice of the loved-tracks import: fetch up to
     * PAGES_PER_RUN pages from where we left off, storing each, until the
     * last page is reached (then mark done).
     *
     * @return int number of newly-stored loved tracks
     */
    public function runLovedSlice(string $userId): int
    {
        if (!$this->isConfigured($userId) || $this->getLovedState($userId) !== self::LOVED_PENDING) {
            return 0;
        }

        $apiKey   = $this->getApiKey($userId);
        $username = $this->getUsername($userId);
        $page     = max(1, (int) $this->config->getUserValue($userId, Application::APP_ID, self::KEY_LOVED_PAGE, '1'));
        $stored   = 0;

        for ($i = 0; $i < self::PAGES_PER_RUN; $i++) {
            $result = $this->lastfm->fetchLovedTracks($apiKey, $username, $page, LastfmService::MAX_PAGE_SIZE);

            foreach ($result['loved'] as $row) {
                $stored += $this->storeLoved($userId, $row) ? 1 : 0;
            }

            if ($result['loved'] === [] || $page >= max(1, $result['totalPages'])) {
                $this->config->setUserValue($userId, Application::APP_ID, self::KEY_LOVED_STATE, self::LOVED_DONE);
                break;
            }

            $page++;
            $this->config->setUserValue($userId, Application::APP_ID, self::KEY_LOVED_PAGE, (string) $page);
            usleep(self::THROTTLE_MICROSECONDS);
        }

        return $stored;
    }

    /**
     * @param array{artist: string, track: string, recordingMbid: ?string, lovedAt: int} $row
     */
    private function storeLoved(string $userId, array $row): bool
    {
        try {
            $loved = new Loved();
            $loved->setUserId($userId);
            $loved->setArtist(mb_substr($row['artist'], 0, 512));
            $loved->setTrack(mb_substr($row['track'], 0, 512));
            $loved->setContentKey(Normalize::contentKey($row['artist'], $row['track'], null));
            $loved->setRecordingMbid($row['recordingMbid']);
            $loved->setLovedAt($row['lovedAt']);
            $loved->setCreatedAt($this->timeFactory->getTime());

            return $this->lovedMapper->createIfNew($loved);
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: dropped a loved track during import: {msg}', [
                'msg' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function store(string $userId, \OCA\Earmark\Dto\IncomingListen $listen): bool
    {
        try {
            return $this->ingest->ingest(
                $userId,
                $listen->artist,
                $listen->track,
                $listen->album,
                (int) $listen->listenedAt,
                Source::LASTFM,
                $listen->artistMbid,
                $listen->recordingMbid,
                $listen->releaseMbid,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: dropped a Last.fm listen during import: {msg}', [
                'msg' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function markDone(string $userId): void
    {
        $this->config->setUserValue($userId, Application::APP_ID, self::KEY_STATE, self::STATE_DONE);
    }
}
