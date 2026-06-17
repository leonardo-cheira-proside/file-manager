<?php

namespace Proside\FileManager;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Proside\FileManager\Console\PruneTrashCommand;
use Proside\FileManager\Livewire\FileManager;
use Proside\FileManager\Support\FileManagerService;

class FileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/file-manager.php', 'file-manager');

        $this->app->bind(FileManagerService::class, fn () => new FileManagerService());
    }

    public function boot(): void
    {
        // Vistas, traduções e componentes Blade do package.
        // loadViewsFrom regista também o namespace de componentes anónimos,
        // disponibilizando <x-file-manager::picker /> a partir de
        // resources/views/components/picker.blade.php.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'file-manager');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'file-manager');

        // Rotas (full-page opcional + media).
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Componente Livewire: <livewire:file-manager />
        Livewire::component('file-manager', FileManager::class);

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->commands([PruneTrashCommand::class]);
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/file-manager.php' => config_path('file-manager.php'),
        ], 'file-manager-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/file-manager'),
        ], 'file-manager-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/file-manager'),
        ], 'file-manager-lang');

        $this->publishes([
            __DIR__.'/../resources/css/file-manager.css' => public_path('vendor/file-manager/file-manager.css'),
        ], 'file-manager-assets');
    }
}
