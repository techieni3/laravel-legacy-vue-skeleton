<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel()
    ->ignoring(AppServiceProvider::class); // Avoid manual registration of TelescopeServiceProvider
