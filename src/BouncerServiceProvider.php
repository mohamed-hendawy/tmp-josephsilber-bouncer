<?php

namespace Silber\Bouncer;

use Illuminate\Support\Arr;
use Silber\Bouncer\Database\Models;
use Silber\Bouncer\Console\CleanCommand;

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class BouncerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBouncer();
        $this->commands(CleanCommand::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerAtGate();

        if ($this->app->runningInConsole()) {
            $this->publishMiddleware();
            $this->publishMigrations();
        }
    }

    /**
     * Register Bouncer as a singleton.
     *
     * @return void
     */
    protected function registerBouncer()
    {
        $this->app->singleton(Bouncer::class, function ($app) {
            return Bouncer::make()
                ->withClipboard(new CachedClipboard(new ArrayStore))
                ->withGate($app->make(Gate::class))
                ->create();
        });
    }

    /**
     * Publish the package's middleware.
     *
     * @return void
     */
    protected function publishMiddleware()
    {
        $stub = __DIR__.'/../middleware/ScopeBouncer.php';

        $target = app_path('Http/Middleware/ScopeBouncer.php');

        $this->publishes([$stub => $target], 'bouncer.middleware');
    }

    /**
     * Publish the package's migrations.
     *
     * @return void
     */
    protected function publishMigrations()
    {
        if (class_exists('CreateBouncerTables')) {
            return;
        }

        $timestamp = date('Y_m_d_His', time());

        $stub = __DIR__.'/../migrations/create_bouncer_tables.php';

        $target = $this->app->databasePath().'/migrations/'.$timestamp.'_create_bouncer_tables.php';

        $this->publishes([$stub => $target], 'bouncer.migrations');
    }

    /**
     * Register the bouncer's clipboard at the gate.
     *
     * @return void
     */
    protected function registerAtGate()
    {
        // When creating a Bouncer instance thru the Factory class, it'll
        // auto-register at the gate. We already registered Bouncer in
        // the container using the Factory, so now we'll resolve it.
        $this->app->make(Bouncer::class);
    }
}
