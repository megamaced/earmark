<?php

declare(strict_types=1);

namespace OCA\Earmark\Controller;

use OCA\Earmark\Scrobble\AudioScrobblerSubmission;
use OCA\Earmark\Service\ListenIngestService;
use OCA\Earmark\Service\ScrobbleSessionService;
use OCA\Earmark\Service\ScrobbleTokenService;
use OCA\Earmark\Source;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * AudioScrobbler 1.2 ("Last.fm-style") inbound protocol, for legacy scrobble
 * clients. Three plain-text endpoints, all public + CSRF-exempt:
 *
 *   GET  /scrobble         — handshake (hs=true): auth, then issue a session
 *   POST /scrobble/np      — now-playing notification (validated, not stored)
 *   POST /scrobble/submit  — batched submission of played tracks
 *
 * Per the protocol, every response is HTTP 200 with a status word on the
 * first line (OK / BADAUTH / BADSESSION / FAILED ...).
 */
class AudioScrobblerController extends Controller
{
    private const SUPPORTED_PROTOCOLS = ['1.2', '1.2.1'];

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ScrobbleTokenService $tokenService,
        private readonly ScrobbleSessionService $sessionService,
        private readonly ListenIngestService $ingestService,
        private readonly IURLGenerator $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function handshake(): DataDisplayResponse
    {
        if ($this->request->getParam('hs') !== 'true') {
            return $this->text('FAILED Not a handshake request');
        }

        $protocol = (string) $this->request->getParam('p');
        if (!in_array($protocol, self::SUPPORTED_PROTOCOLS, true)) {
            return $this->text('FAILED Unsupported protocol version');
        }

        $user      = (string) $this->request->getParam('u');
        $timestamp = (string) $this->request->getParam('t');
        $auth      = (string) $this->request->getParam('a');
        if ($user === '' || !$this->tokenService->authenticateLegacy($user, $timestamp, $auth)) {
            return $this->text('BADAUTH');
        }

        $sessionId = $this->sessionService->create($user);
        $npUrl     = $this->urlGenerator->linkToRouteAbsolute('earmark.audioScrobbler.nowPlaying');
        $submitUrl = $this->urlGenerator->linkToRouteAbsolute('earmark.audioScrobbler.submit');

        return $this->text("OK\n{$sessionId}\n{$npUrl}\n{$submitUrl}");
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function nowPlaying(): DataDisplayResponse
    {
        if ($this->sessionService->resolve((string) $this->request->getParam('s')) === null) {
            return $this->text('BADSESSION');
        }
        // Now-playing carries no timestamp — acknowledge without storing.
        return $this->text('OK');
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function submit(): DataDisplayResponse
    {
        $userId = $this->sessionService->resolve((string) $this->request->getParam('s'));
        if ($userId === null) {
            return $this->text('BADSESSION');
        }

        foreach (AudioScrobblerSubmission::parse($this->request->getParams()) as $listen) {
            try {
                $this->ingestService->ingest(
                    $userId,
                    $listen->artist,
                    $listen->track,
                    $listen->album,
                    (int) $listen->listenedAt,
                    Source::SCROBBLE,
                );
            } catch (\Throwable $e) {
                $this->logger->warning('Earmark: dropped a listen during AS submission: {msg}', [
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return $this->text('OK');
    }

    private function text(string $body): DataDisplayResponse
    {
        return new DataDisplayResponse($body . "\n", Http::STATUS_OK, ['Content-Type' => 'text/plain']);
    }
}
