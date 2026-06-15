<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Fetches release cover art from the Cover Art Archive by release MBID and
 * caches it in the app's data folder. Serving through our own endpoint (rather
 * than letting the browser hit coverartarchive.org) avoids CSP issues and the
 * archive's redirects, and lets us cache. Releases with no art are
 * negatively cached (empty file) so we don't refetch them every time.
 */
class ArtworkService
{
    private const CAA_URL = 'https://coverartarchive.org/release/%s/front-250';
    private const CAA_RG_URL = 'https://coverartarchive.org/release-group/%s/front-250';
    private const MB_RG_URL = 'https://musicbrainz.org/ws/2/release/%s?inc=release-groups&fmt=json';
    private const USER_AGENT = 'Earmark/0.2.1 ( https://github.com/megamaced/earmark )';
    // Bumped to -v2 when release-group fallback was added, so releases that
    // were negatively cached under the release-only logic get re-attempted.
    private const CACHE_FOLDER = 'release-art-v2';

    public function __construct(
        private readonly IClientService $clientService,
        private readonly IAppDataFactory $appDataFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{content: string, mime: string}|null null if the MBID is
     *         invalid or the release has no front cover
     */
    public function getReleaseCover(string $mbid): ?array
    {
        if (!self::isUuid($mbid)) {
            return null;
        }

        $folder = $this->cacheFolder();

        if ($folder->fileExists($mbid)) {
            try {
                $cached = $folder->getFile($mbid)->getContent();
                // Empty file = negatively cached "no art".
                return $cached !== '' ? ['content' => $cached, 'mime' => self::mime($cached)] : null;
            } catch (\Throwable $e) {
                // fall through and refetch
            }
        }

        $content = $this->fetch($mbid);
        $this->cache($folder, $mbid, $content ?? '');

        return $content !== null ? ['content' => $content, 'mime' => self::mime($content)] : null;
    }

    private function fetch(string $mbid): ?string
    {
        // First try the exact release. If that pressing has no front cover,
        // fall back to its release-group, which aggregates art across every
        // edition of the album and is usually populated even when a specific
        // release is not.
        $cover = $this->getImage(sprintf(self::CAA_URL, $mbid));
        if ($cover !== null) {
            return $cover;
        }

        $rgid = $this->releaseGroupId($mbid);
        if ($rgid !== null) {
            return $this->getImage(sprintf(self::CAA_RG_URL, $rgid));
        }

        return null;
    }

    /** GET an image URL from the Cover Art Archive, following its redirect. */
    private function getImage(string $url): ?string
    {
        try {
            $response = $this->clientService->newClient()->get($url, [
                'timeout'     => 20,
                'http_errors' => false,
                'headers'     => ['User-Agent' => self::USER_AGENT],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: cover art fetch failed: {msg}', ['msg' => $e->getMessage()]);
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null; // 404 = no front cover
        }

        $body = (string) $response->getBody();
        return $body !== '' ? $body : null;
    }

    /**
     * Resolve a release MBID to its release-group MBID via MusicBrainz, so we
     * can fall back to group-level cover art. Returns null on any failure.
     */
    private function releaseGroupId(string $mbid): ?string
    {
        try {
            $response = $this->clientService->newClient()->get(sprintf(self::MB_RG_URL, $mbid), [
                'timeout'     => 20,
                'http_errors' => false,
                'headers'     => ['User-Agent' => self::USER_AGENT],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: release-group lookup failed: {msg}', ['msg' => $e->getMessage()]);
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $decoded = json_decode((string) $response->getBody(), true);
        $rgid    = $decoded['release-group']['id'] ?? null;

        return is_string($rgid) && self::isUuid($rgid) ? $rgid : null;
    }

    private function cache(ISimpleFolder $folder, string $mbid, string $content): void
    {
        try {
            if ($folder->fileExists($mbid)) {
                $folder->getFile($mbid)->putContent($content);
            } else {
                $folder->newFile($mbid, $content);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Earmark: cover art cache write failed: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    private function cacheFolder(): ISimpleFolder
    {
        $appData = $this->appDataFactory->get(Application::APP_ID);
        try {
            return $appData->getFolder(self::CACHE_FOLDER);
        } catch (NotFoundException $e) {
            return $appData->newFolder(self::CACHE_FOLDER);
        }
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    private static function mime(string $content): string
    {
        $info = @getimagesizefromstring($content);
        return is_array($info) && !empty($info['mime']) ? (string) $info['mime'] : 'image/jpeg';
    }
}
