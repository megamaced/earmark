<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // ListenBrainz-compatible inbound scrobble submission. Public + CSRF-exempt;
        // authenticated by per-user scrobble token in the Authorization header.
        ['name' => 'listenBrainz#submitListens', 'url' => '/1/submit-listens', 'verb' => 'POST'],

        // AudioScrobbler 1.2 ("Last.fm-style") protocol. Public + CSRF-exempt;
        // handshake authenticates and issues a session used by np/submit.
        ['name' => 'audioScrobbler#handshake',  'url' => '/scrobble',        'verb' => 'GET'],
        ['name' => 'audioScrobbler#nowPlaying', 'url' => '/scrobble/np',     'verb' => 'POST'],
        ['name' => 'audioScrobbler#submit',     'url' => '/scrobble/submit', 'verb' => 'POST'],
    ],
    'ocs' => [
        // Per-user scrobble tokens — Nextcloud-authenticated, used by the settings UI.
        ['name' => 'token#index',   'url' => '/api/v1/tokens',      'verb' => 'GET'],
        ['name' => 'token#create',  'url' => '/api/v1/tokens',      'verb' => 'POST'],
        ['name' => 'token#destroy', 'url' => '/api/v1/tokens/{id}', 'verb' => 'DELETE'],

        // Settings + Last.fm import control.
        ['name' => 'settings#getLastfm',   'url' => '/api/v1/settings/lastfm',        'verb' => 'GET'],
        ['name' => 'settings#setLastfm',   'url' => '/api/v1/settings/lastfm',        'verb' => 'POST'],
        ['name' => 'settings#startImport', 'url' => '/api/v1/settings/lastfm/import', 'verb' => 'POST'],
        ['name' => 'settings#setApiKey',   'url' => '/api/v1/settings/lastfm/api-key', 'verb' => 'POST'],
    ],
];
