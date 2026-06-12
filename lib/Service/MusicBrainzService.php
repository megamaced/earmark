<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\Exception\MusicBrainzException;
use OCA\Earmark\MusicBrainz\LookupResponse;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Resolves messy scrobble text to MusicBrainz IDs via ListenBrainz's
 * `metadata/lookup` mapper, which is purpose-built for this. No API key is
 * required; a descriptive User-Agent is sent per MetaBrainz etiquette.
 * Callers are responsible for rate-limiting between requests.
 */
class MusicBrainzService
{
    private const LOOKUP_URL = 'https://api.listenbrainz.org/1/metadata/lookup/';
    private const USER_AGENT = 'Earmark/0.1.0 ( https://github.com/megamaced/earmark )';

    public function __construct(
        private readonly IClientService $clientService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{matched: bool, artistMbid: ?string, recordingMbid: ?string, releaseMbid: ?string}
     * @throws MusicBrainzException
     */
    public function lookup(string $artist, string $track, ?string $album): array
    {
        $query = [
            'artist_name'    => $artist,
            'recording_name' => $track,
        ];
        if ($album !== null && trim($album) !== '') {
            $query['release_name'] = $album;
        }

        try {
            // http_errors=false: handle status codes ourselves so a 429 is a
            // signal to back off, not a thrown 4xx we'd retry into immediately.
            $response = $this->clientService->newClient()->get(self::LOOKUP_URL, [
                'query'       => $query,
                'timeout'     => 30,
                'headers'     => ['User-Agent' => self::USER_AGENT],
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new MusicBrainzException('MusicBrainz request failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status === 429) {
            // Rate limited — the caller should stop for this run.
            throw new MusicBrainzException('MusicBrainz rate limit exceeded', 429);
        }
        if ($status >= 400) {
            throw new MusicBrainzException('MusicBrainz returned HTTP ' . $status, $status);
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new MusicBrainzException('invalid JSON in MusicBrainz response');
        }

        return LookupResponse::parse($decoded);
    }
}
