<?php

use Illuminate\Support\Facades\Route;
use Proside\FileManager\Http\Controllers\DownloadController;
use Proside\FileManager\Http\Controllers\MediaController;
use Proside\FileManager\Http\Controllers\ShareController;
use Proside\FileManager\Http\Controllers\UploadController;
use Proside\FileManager\Http\Controllers\ZipController;

$config = config('file-manager.route');
$prefix = $config['prefix'] ?? 'file-manager';
$middleware = $config['middleware'] ?? ['web'];
$mediaMiddleware = $config['media_middleware'] ?? ['web'];

Route::prefix($prefix)
    ->name('file-manager.')
    ->group(function () use ($config, $middleware, $mediaMiddleware) {
        // Serve media (disco-agnóstico). Pública por omissão: ver conteúdo não
        // exige auth. O lixo e os sidecars de metadados nunca são servidos aqui.
        Route::get('media/{path}', MediaController::class)
            ->where('path', '.*')
            ->middleware($mediaMiddleware)
            ->name('media');

        // Link de partilha assinado e temporário: a autorização é a assinatura.
        Route::get('share/{path}', ShareController::class)
            ->where('path', '.*')
            ->middleware($mediaMiddleware)
            ->name('share');

        // Alterar (upload) e entrar no gestor: middleware protegido (auth).
        Route::middleware($middleware)->group(function () use ($config) {
            Route::post('upload', UploadController::class)->name('upload');
            Route::get('download/{path}', DownloadController::class)->where('path', '.*')->name('download');
            Route::post('download-zip', ZipController::class)->name('download-zip');

            if ($config['enabled'] ?? true) {
                Route::view('/', 'file-manager::page')->name('index');
            }
        });
    });
