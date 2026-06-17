<?php

use Illuminate\Support\Facades\Route;
use Proside\FileManager\Http\Controllers\MediaController;

$config = config('file-manager.route');
$prefix = $config['prefix'] ?? 'file-manager';
$middleware = $config['middleware'] ?? ['web'];

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('file-manager.')
    ->group(function () use ($config) {
        // Serve media (disco-agnóstico, respeita auth).
        Route::get('media', MediaController::class)->name('media');

        // Página full-page opcional com o gestor completo.
        if ($config['enabled'] ?? true) {
            Route::view('/', 'file-manager::page')->name('index');
        }
    });
