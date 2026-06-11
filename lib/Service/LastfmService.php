<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\AppInfo\Application;
use OCA\Earmark\Exception\LastfmException;
use OCA\Earmark\Scrobble\LastfmRecentTracks;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Thin client over the Last.fm `user.getRecentTracks` endpoint. The API key
 * is an instance-wide (admin) setting; the username is per-user and supplied
 * by {@see LastfmImportService}.
 */
class LastfmService
{
    private const API_ROOT = 'https://ws.audioscrobbler.com/2.0/';
    public const MAX_PAGE_SIZE = 200;
    private const API_KEY_CONFIG = 'lastfm_api_key';

    public function __construct(
        private readonly IClientService $clientService,
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getApiKey(): string
    {
        return $this->config->getAppValue(Application::APP_ID, self::API_KEY_CONFIG, '');
    }

    public function hasApiKey(): bool
    {
        return $this->getApiKey() !== '';
    }

    public function setApiKey(string $apiKey): void
    {
        $this->config->setAppValue(Application::APP_ID, self::API_KEY_CONFIG, trim($apiKey));
    }

    /**
     * Fetch one page of a user's recent tracks. `from` / `to` bound the window
     * by Unix timestamp; the API returns newest-first.
     *
     * @return array{page: int, totalPages: int, total: int, listens: list<\OCA\Earmark\Dto\IncomingListen>}
     * @throws LastfmException
     */
    public function fetchRecentTracks(
        string $username,
        int $page = 1,
        int $limit = self::MAX_PAGE_SIZE,
        ?int $from = null,
        ?int $to = null,
    ): array {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            throw new LastfmException('Last.fm API key is not configured');
        }
        if (trim($username) === '') {
            throw new LastfmException('Last.fm username is not set');
        }

        $query = [
            'method'  => 'user.getrecenttracks',
            'user'    => $username,
            'api_key' => $apiKey,
            'format'  => 'json',
            'limit'   => (string) max(1, min($limit, self::MAX_PAGE_SIZE)),
            'page'    => (string) max(1, $page),
        ];
        if ($from !== null) {
            $query['from'] = (string) $from;
        }
        if ($to !== null) {
            $query['to'] = (string) $to;
        }

        try {
            $response = $this->clientService->newClient()->get(self::API_ROOT, [
                'query'   => $query,
                'timeout' => 30,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: Last.fm request failed: {msg}', ['msg' => $e->getMessage()]);
            throw new LastfmException('Last.fm request failed: ' . $e->getMessage(), 0, $e);
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new LastfmException('invalid JSON in Last.fm response');
        }

        return LastfmRecentTracks::parse($decoded);
    }
}
