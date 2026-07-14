<?php

namespace Veekthoven\CashierBachs\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Veekthoven\CashierBachs\Cashier;
use Veekthoven\CashierBachs\CashierServiceProvider;
use Veekthoven\CashierBachs\Tests\Fixtures\User;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Cashier::useCustomerModel(User::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('bachs_id')->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        $migration = include __DIR__.'/../database/migrations/2026_01_01_000002_create_subscriptions_table.php';
        $migration->up();
    }

    protected function getPackageProviders($app)
    {
        return [
            CashierServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('cashier.api_key', 'sk_sandbox_testing_key');
        config()->set('cashier.webhook.secret', 'whsec_testing_secret');
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ], $attributes));
    }

    protected function createCustomer(array $attributes = []): User
    {
        return $this->createUser(array_merge(['bachs_id' => 'cust_123'], $attributes));
    }
}
