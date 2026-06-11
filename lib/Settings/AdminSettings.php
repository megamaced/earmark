<?php

declare(strict_types=1);

namespace OCA\Earmark\Settings;

use OCA\Earmark\Service\LastfmService;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsFormWithHandlers;
use OCP\IUser;

/**
 * Instance-wide admin settings, shown under Settings → Administration. Holds
 * the Last.fm API key (one application credential shared by all users), with
 * `external` storage so the value is read/written through {@see LastfmService}
 * — i.e. the same app-config key the importer reads.
 */
class AdminSettings implements IDeclarativeSettingsFormWithHandlers
{
    public function __construct(
        private readonly LastfmService $lastfmService,
    ) {
    }

    public function getSchema(): array
    {
        return [
            'id'           => 'earmark_admin',
            'priority'     => 50,
            'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
            'section_id'   => 'additional',
            'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_EXTERNAL,
            'title'        => 'Earmark',
            'description'  => 'Last.fm API key used for history imports. Create one at '
                . 'https://www.last.fm/api — it is shared by all users on this instance.',
            'fields'       => [
                [
                    'id'          => 'lastfm_api_key',
                    'title'       => 'Last.fm API key',
                    'type'        => DeclarativeSettingsTypes::TEXT,
                    'placeholder' => 'e.g. 0123456789abcdef0123456789abcdef',
                    'default'     => '',
                ],
            ],
        ];
    }

    public function getValue(string $fieldId, IUser $user): mixed
    {
        return $fieldId === 'lastfm_api_key' ? $this->lastfmService->getApiKey() : '';
    }

    public function setValue(string $fieldId, mixed $value, IUser $user): void
    {
        if ($fieldId === 'lastfm_api_key') {
            $this->lastfmService->setApiKey((string) $value);
        }
    }
}
