<?php

declare(strict_types=1);

namespace OCA\Earmark\Controller;

use OCA\Earmark\Exception\InvalidPayloadException;
use OCA\Earmark\Scrobble\ListenBrainzPayload;
use OCA\Earmark\Service\ListenIngestService;
use OCA\Earmark\Service\ScrobbleTokenService;
use OCA\Earmark\Source;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * ListenBrainz-compatible inbound scrobble endpoint. Reached by third-party
 * clients with no Nextcloud session, so the route is public and CSRF-exempt;
 * authentication is by per-user scrobble token in the `Authorization: Token`
 * header. Mirrors the public ListenBrainz `submit-listens` contract.
 */
class ListenBrainzController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ScrobbleTokenService $tokenService,
        private readonly ListenIngestService $ingestService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function submitListens(): JSONResponse
    {
        $userId = $this->authenticate();
        if ($userId === null) {
            return new JSONResponse(
                ['code' => Http::STATUS_UNAUTHORIZED, 'error' => 'Invalid authorization token'],
                Http::STATUS_UNAUTHORIZED,
            );
        }

        try {
            $parsed = ListenBrainzPayload::parse(
                $this->request->getParam('listen_type'),
                $this->request->getParam('payload'),
            );
        } catch (InvalidPayloadException $e) {
            return new JSONResponse(
                ['code' => Http::STATUS_BAD_REQUEST, 'error' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST,
            );
        }

        $accepted = 0;
        foreach ($parsed['listens'] as $listen) {
            // "now playing" carries no timestamp — accepted, but not persisted.
            if ($listen->listenedAt === null) {
                continue;
            }
            try {
                $stored = $this->ingestService->ingest(
                    $userId,
                    $listen->artist,
                    $listen->track,
                    $listen->album,
                    $listen->listenedAt,
                    Source::SCROBBLE,
                );
                if ($stored) {
                    $accepted++;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Earmark: dropped a listen during ingest: {msg}', [
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return new JSONResponse(['status' => 'ok', 'accepted' => $accepted]);
    }

    private function authenticate(): ?string
    {
        $header = $this->request->getHeader('Authorization');
        if (!str_starts_with($header, 'Token ')) {
            return null;
        }
        $token = trim(substr($header, 6));
        return $this->tokenService->authenticate($token);
    }
}
