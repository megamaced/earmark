<?php

declare(strict_types=1);

namespace OCA\Earmark\Settings;

use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsForm;

/**
 * Instance-wide admin settings, shown under Settings → Administration →
 * Additional settings. Holds the Last.fm API key (one application credential
 * shared by all users).
 *
 * Uses `internal` storage, so Nextcloud persists and repopulates the value
 * itself in app config under (app: earmark, key: lastfm_api_key) — the same
 * key {@see \OCA\Earmark\Service\LastfmService} reads.
 */
class AdminSettings implements IDeclarativeSettingsForm
{
    public function getSchema(): array
    {
        return [
            'id'           => 'earmark_admin',
            'priority'     => 50,
            'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
            'section_id'   => 'additional',
            'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_INTERNAL,
            'title'        => 'Earmark',
            'description'  => 'Last.fm API key used for history imports. Create one at '
                . 'https://www.last.fm/api — it is shared by all users on this instance.',
            'fields'       => [
                [
                    'id'          => 'lastfm_api_key',
                    'title'       => 'Last.fm API key',
                    'type'        => DeclarativeSettingsTypes::PASSWORD,
                    'placeholder' => 'e.g. 0123456789abcdef0123456789abcdef',
                    'default'     => '',
                ],
            ],
        ];
    }
}
