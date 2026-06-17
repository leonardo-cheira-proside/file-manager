<?php

namespace Proside\FileManager\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Proside\FileManager\FileManagerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            FileManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('file-manager.disk', 'fm-test');
        $app['config']->set('filesystems.disks.fm-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/fm'),
            'url' => '/storage/fm',
            'visibility' => 'public',
        ]);
    }
}
