<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Sleep;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Password;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {

        /**
         * Register Telescope in local environment.
         *
         * @see https://laravel.com/docs/telescope
         * @see migrations/0001_01_01_000004_create_telescope_entries_table.php
         */
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureCommands();
        $this->configureDates();
        $this->configureModels();
        $this->configureUrl();
        $this->configureVite();
        $this->configureValidations();
        $this->optimizeTests();
    }

    /**
     * Configure the application's commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * It's recommended to use CarbonImmutable as it's immutable and thread-safe to avoid issues with mutability.
     *
     * @see https://dyrynda.com.au/blog/laravel-immutable-dates
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configure the application's models.
     *
     * @see https://laravel.com/docs/eloquent#configuring-eloquent-strictness
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict($this->app->isLocal());

        Model::unguard();

        Model::automaticallyEagerLoadRelationships($this->app->isProduction());
    }

    /**
     * Configure the application's URL.
     *
     * @see https://laravel.com/docs/octane#serving-your-application-via-https
     */
    private function configureUrl(): void
    {
        URL::forceHttps($this->app->isProduction());
    }

    /**
     * Configure the application's Vite loading strategy.
     */
    private function configureVite(): void
    {
        Vite::useAggressivePrefetching();
    }

    /**
     * Configure validation rules.
     */
    private function configureValidations(): void
    {
        Password::defaults(fn () => $this->app->isProduction() ? Password::min(12)->max(255)->uncompromised() : null);
        Email::defaults(fn () => $this->app->isProduction() ? Email::default()->validateMxRecord()->preventSpoofing() : null);
    }

    /**
     * Configure Stray Requests & sleep when running tests.
     */
    private function optimizeTests(): void
    {
        Http::preventStrayRequests($this->app->runningUnitTests());

        Sleep::fake($this->app->runningUnitTests());
    }
}
