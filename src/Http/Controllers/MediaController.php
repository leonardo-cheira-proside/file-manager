<?php

namespace Proside\FileManager\Http\Controllers;

use Illuminate\Http\Request;
use Proside\FileManager\Support\FileManagerService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve ficheiros do disco do File Manager de forma agnóstica ao disco
 * (funciona com discos privados) e respeitando a auth da aplicação.
 * Usado quando media_url = "route" ou quando o disco não suporta url().
 */
class MediaController
{
    public function __invoke(Request $request, FileManagerService $service, string $path): StreamedResponse
    {
        abort_if($path === '', 404);

        try {
            $path = $service->guard()->normalize($path);
        } catch (\Throwable $e) {
            abort(404);
        }

        abort_unless($service->exists($path), 404);

        $mime = $service->mimeType($path);

        return response()->stream(function () use ($service, $path) {
            $stream = $service->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
