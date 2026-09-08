<?php

use Illuminate\Support\Facades\Route;
use Proside\FileManager\Http\Controllers\MediaController;
use Proside\FileManager\Http\Controllers\UploadController;

$config = config('file-manager.route');
$prefix = $config['prefix'] ?? 'file-manager';
$middleware = $config['middleware'] ?? ['web'];

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('file-manager.')
    ->group(function () use ($config) {
        // Serve media (disco-agnóstico, respeita auth). O caminho vai direto no
        // URL (ex.: /file-manager/media/pasta/ficheiro.png) em vez de ?path=.
        Route::get('media/{path}', MediaController::class)->where('path', '.*')->name('media');

        // Upload direto (drag & drop do picker).
        Route::post('upload', UploadController::class)->name('upload');

        // Página full-page opcional com o gestor completo.
        if ($config['enabled'] ?? true) {
            Route::view('/', 'file-manager::page')->name('index');
        }
    });
