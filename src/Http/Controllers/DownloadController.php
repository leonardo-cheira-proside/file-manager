<?php

namespace Proside\FileManager\Http\Controllers;

use Illuminate\Http\Request;
use Proside\FileManager\Support\FileManagerService;
use Symfony\Component\HttpFoundation\Response;

class DownloadController
{
    use ServesFiles;

    public function __invoke(Request $request, FileManagerService $service, string $path): Response
    {
        abort_if($path === '', 404);

        try {
            $path = $service->guard()->normalize($path);
        } catch (\Throwable $e) {
            abort(404);
        }

        abort_if(str_ends_with($path, '.meta.json'), 404);
        abort_unless($service->disk()->fileExists($path), 404);

        return $this->serve($request, $service, $path, forceDownload: true);
    }
}
