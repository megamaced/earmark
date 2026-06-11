<?php

declare(strict_types=1);

namespace OCA\Earmark\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'earmark';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerDeclarativeSettings(\OCA\Earmark\Settings\AdminSettings::class);
    }

    public function boot(IBootContext $context): void
    {
    }
}
