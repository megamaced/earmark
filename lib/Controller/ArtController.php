<?php

declare(strict_types=1);

namespace OCA\Earmark\Controller;

use OCA\Earmark\Service\ArtworkService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;

/**
 * Serves release cover art by MBID, proxied + cached from the Cover Art
 * Archive. `#[NoCSRFRequired]` because it's loaded via `<img>` (no CSRF
 * token); a 404 lets the UI fall back to a placeholder.
 */
class ArtController extends Controller
{
    private const CACHE_SECONDS = 60 * 60 * 24 * 30;

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ArtworkService $artworkService,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function release(string $mbid): DataDisplayResponse
    {
        $art = $this->artworkService->getReleaseCover($mbid);
        if ($art === null) {
            return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
        }

        $response = new DataDisplayResponse($art['content'], Http::STATUS_OK, ['Content-Type' => $art['mime']]);
        $response->cacheFor(self::CACHE_SECONDS);
        return $response;
    }
}
