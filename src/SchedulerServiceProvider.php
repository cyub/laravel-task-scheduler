<?php

namespace Tink\Scheduler;

use Illuminate\Support\ServiceProvider;

class SchedulerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfigs();
        $this->registerCommands();
        $this->registerPublishableResources();
    }

    protected function registerConfigs()
    {
        $this->mergeConfigFrom(__DIR__ . '/../publishable/config/scheduler.php', 'scheduler');
    }

    protected function registerCommands()
    {
        $this->app->bindIf('command.scheduler', function () {
            return new Commands\Scheduler;
        });

        $this->app->bindIf('command.scheduler.install', function () {
            return  new Commands\Install;
        });

        $this->app->bindIf('command.scheduler.dispatch', function () {
            return new Commands\Dispatch;
        });

        $this->app->bindIf('command.scheduler.info', function() {
            return new Commands\Info;
        });

        $this->app->bindIf('command.scheduler.clean', function() {
            return new Commands\Clean;
        });

        $this->commands(
            'command.scheduler',
            'command.scheduler.install',
            'command.scheduler.dispatch',
            'command.scheduler.info',
            'command.scheduler.clean'
        );
    }

    protected function registerPublishableResources() 
    {
        $publishablePath = dirname(__DIR__) . '/publishable';

        $publishable = [
            'config' => [
                "$publishablePath/config/scheduler.php" => config_path("scheduler.php")
            ],
            'migrations' => [
                "$publishablePath/database/migrations/" => database_path('migrations/scheduler')
            ]
        ];

        foreach ($publishable as $group => $paths) {
            $this->publishes($paths, $group);
        }
    }
}
