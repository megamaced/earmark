<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\Exception\LastfmException;
use OCA\Earmark\Scrobble\LastfmLovedTracks;
use OCA\Earmark\Scrobble\LastfmRecentTracks;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Thin, stateless client over the Last.fm `user.getRecentTracks` endpoint.
 * The API key and username are per-user and supplied by the caller
 * ({@see LastfmImportService}).
 */
class LastfmService
{
    private const API_ROOT = 'https://ws.audioscrobbler.com/2.0/';
    public const MAX_PAGE_SIZE = 200;

    public function __construct(
        private readonly IClientService $clientService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Fetch one page of a user's recent tracks. `from` / `to` bound the window
     * by Unix timestamp; the API returns newest-first.
     *
     * @return array{page: int, totalPages: int, total: int, listens: list<\OCA\Earmark\Dto\IncomingListen>}
     * @throws LastfmException
     */
    public function fetchRecentTracks(
        string $apiKey,
        string $username,
        int $page = 1,
        int $limit = self::MAX_PAGE_SIZE,
        ?int $from = null,
        ?int $to = null,
    ): array {
        if (trim($apiKey) === '') {
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

    /**
     * Fetch one page of a user's loved tracks (newest-loved first).
     *
     * @return array{
     *     page: int,
     *     totalPages: int,
     *     total: int,
     *     loved: list<array{artist: string, track: string, recordingMbid: ?string, lovedAt: int}>
     * }
     * @throws LastfmException
     */
    public function fetchLovedTracks(
        string $apiKey,
        string $username,
        int $page = 1,
        int $limit = self::MAX_PAGE_SIZE,
    ): array {
        if (trim($apiKey) === '') {
            throw new LastfmException('Last.fm API key is not configured');
        }
        if (trim($username) === '') {
            throw new LastfmException('Last.fm username is not set');
        }

        $query = [
            'method'  => 'user.getlovedtracks',
            'user'    => $username,
            'api_key' => $apiKey,
            'format'  => 'json',
            'limit'   => (string) max(1, min($limit, self::MAX_PAGE_SIZE)),
            'page'    => (string) max(1, $page),
        ];

        try {
            $response = $this->clientService->newClient()->get(self::API_ROOT, [
                'query'   => $query,
                'timeout' => 30,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: Last.fm loved request failed: {msg}', ['msg' => $e->getMessage()]);
            throw new LastfmException('Last.fm request failed: ' . $e->getMessage(), 0, $e);
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new LastfmException('invalid JSON in Last.fm response');
        }

        return LastfmLovedTracks::parse($decoded);
    }
}
