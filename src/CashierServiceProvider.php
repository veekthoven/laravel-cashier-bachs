<?php

namespace Veekthoven\CashierBachs;

use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Veekthoven\CashierBachs\Commands\WebhookCommand;

class CashierServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cashier')
            ->hasConfigFile()
            ->hasCommand(WebhookCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BachsApi::class);
    }

    public function packageBooted(): void
    {
        $this->bootRoutes();
        $this->bootMigrations();
    }

    /**
     * Register the package's webhook route.
     */
    protected function bootRoutes(): void
    {
        if (! Cashier::$registersRoutes) {
            return;
        }

        Route::group([
            'prefix' => config('cashier.path'),
            'as' => 'cashier.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register and publish the package's migrations.
     */
    protected function bootMigrations(): void
    {
        if (Cashier::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'cashier-migrations');
        }
    }
}
