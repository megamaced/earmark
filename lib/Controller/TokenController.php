<?php

declare(strict_types=1);

namespace OCA\Earmark\Controller;

use OCA\Earmark\Service\ScrobbleTokenService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Manage the current user's scrobble tokens (used by the personal-settings
 * UI). Authenticated as the Nextcloud user; the inbound scrobble API itself
 * lives in {@see ListenBrainzController} and authenticates by token instead.
 */
class TokenController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ScrobbleTokenService $tokenService,
        private readonly IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): DataResponse
    {
        $tokens = $this->tokenService->listForUser($this->userId());
        return new DataResponse(array_map(static fn ($t) => $t->jsonSerialize(), $tokens));
    }

    #[NoAdminRequired]
    public function create(?string $label = null): DataResponse
    {
        $result = $this->tokenService->issue($this->userId(), $label);
        $data = $result['entity']->jsonSerialize();
        // The plaintext token is returned exactly once, here.
        $data['token'] = $result['token'];
        return new DataResponse($data, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse
    {
        $deleted = $this->tokenService->revoke($this->userId(), $id);
        if (!$deleted) {
            return new DataResponse(['deleted' => false], Http::STATUS_NOT_FOUND);
        }
        return new DataResponse(['deleted' => true]);
    }

    private function userId(): string
    {
        $user = $this->userSession->getUser();
        return $user !== null ? $user->getUID() : '';
    }
}
