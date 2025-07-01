<?php

declare(strict_types=1);

namespace App\Base;

use Illuminate\Foundation\Application as BaseApplication;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Override;

class Application extends BaseApplication
{
    /**
     * Create a new Illuminate application instance.
     */
    public function __construct(?string $basePath = null)
    {
        parent::__construct($basePath);

        $this->registerBootstrapProviders();
    }

    /**
     * Register provider added in bootstrap/providers.php.
     */
    public function registerBootstrapProviders(): void
    {
        RegisterProviders::merge([], $this->getBootstrapProvidersPath());
    }

    /**
     * Register any services needed for Laravel Cloud.
     */
    #[Override]
    protected function registerLaravelCloudServices(): void
    {
        // Do nothing as the application is not running in Laravel Cloud
    }
}
