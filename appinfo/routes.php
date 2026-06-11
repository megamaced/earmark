<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // ListenBrainz-compatible inbound scrobble submission. Public + CSRF-exempt;
        // authenticated by per-user scrobble token in the Authorization header.
        ['name' => 'listenBrainz#submitListens', 'url' => '/1/submit-listens', 'verb' => 'POST'],
    ],
    'ocs' => [
        // Per-user scrobble tokens — Nextcloud-authenticated, used by the settings UI.
        ['name' => 'token#index',   'url' => '/api/v1/tokens',      'verb' => 'GET'],
        ['name' => 'token#create',  'url' => '/api/v1/tokens',      'verb' => 'POST'],
        ['name' => 'token#destroy', 'url' => '/api/v1/tokens/{id}', 'verb' => 'DELETE'],
    ],
];
