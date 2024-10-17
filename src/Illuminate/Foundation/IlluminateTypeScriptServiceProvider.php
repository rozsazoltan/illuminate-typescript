<?php

namespace Rose\IlluminateTypeScript\Foundation;

use Rose\IlluminateTypeScript\Commands\TypeScriptGenerateCommand;
use Illuminate\Support\ServiceProvider;

class IlluminateTypeScriptServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();
    }

    /**
     * Register the package's publishable resources.
     */
    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->mergeConfigFrom(
                __DIR__ . '/../../../config/typescript.php', 'typescript'
            );
        }
    }

    /**
     * Register the package's commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TypeScriptGenerateCommand::class,
            ]);
        }
    }
}
