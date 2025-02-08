<?php

namespace Agency\Auth\Providers;

use Agency\Auth\ClerkService;
use Agency\Auth\Console\Commands\SetupAgency;  // Add this
use Agency\Auth\Middleware\AuthenticateWithClerk;
use Agency\Auth\Middleware\CheckPermissions;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

class AgencyServiceProvider extends ServiceProvider
{

    protected $commands = [
        SetupAgency::class
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Register config
        $this->mergeConfigFrom(
            dirname(__DIR__, 3) . '/config/auth.php', 
            'agency.auth'
        );

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }

        // Register Clerk service
        $this->app->singleton(ClerkService::class, function ($app) {
            return new ClerkService(
                config('agency.auth.clerk.secret_key'),
                config('agency.auth.clerk.publishable_key')
            );
        });

        // Register facades
        $this->app->alias(ClerkService::class, 'agency.auth');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configs
        $this->publishes([
            dirname(__DIR__, 3) . '/config/auth.php' => config_path('agency/auth.php'),
        ], 'agency-config');

        // Register middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('clerk.auth', AuthenticateWithClerk::class);
        $router->aliasMiddleware('clerk.permissions', CheckPermissions::class);

        // Add global middleware
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', AuthenticateWithClerk::class);
    }
}