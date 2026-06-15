<?php

declare(strict_types=1);

namespace OCA\Earmark\Controller;

use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Exception\LastfmException;
use OCA\Earmark\Service\LastfmImportService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Per-user settings + import control. The Last.fm username and API key are
 * per-user (each user supplies their own key); all endpoints are
 * `#[NoAdminRequired]`. (Spotify's shared OAuth client will live in admin
 * settings instead.)
 */
class SettingsController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly LastfmImportService $importService,
        private readonly ListenMapper $listenMapper,
        private readonly IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function getLastfm(): DataResponse
    {
        $userId = $this->userId();
        return new DataResponse([
            'username'    => $this->importService->getUsername($userId),
            'state'       => $this->importService->getState($userId),
            'hasApiKey'   => $this->importService->hasApiKey($userId),
            'listenCount' => $this->listenMapper->countForUser($userId),
        ]);
    }

    #[NoAdminRequired]
    public function setLastfm(string $lastfmUsername = ''): DataResponse
    {
        // Param is `lastfmUsername`, not `username`: Nextcloud reserves the
        // latter as a request parameter, so it never reaches this method.
        $this->importService->setUsername($this->userId(), $lastfmUsername);
        return $this->getLastfm();
    }

    #[NoAdminRequired]
    public function setApiKey(string $lastfmApiKey = ''): DataResponse
    {
        $this->importService->setApiKey($this->userId(), $lastfmApiKey);
        return $this->getLastfm();
    }

    #[NoAdminRequired]
    public function startImport(): DataResponse
    {
        try {
            $this->importService->startBackfill($this->userId());
        } catch (LastfmException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        return $this->getLastfm();
    }

    private function userId(): string
    {
        $user = $this->userSession->getUser();
        return $user !== null ? $user->getUID() : '';
    }
}
