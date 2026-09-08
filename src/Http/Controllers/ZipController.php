<?php

namespace Proside\FileManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Proside\FileManager\Support\FileManagerService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ZipController
{
    public function __invoke(Request $request, FileManagerService $service): BinaryFileResponse
    {
        abort_unless(class_exists(ZipArchive::class), 500);

        $paths = array_values(array_filter((array) $request->input('paths', [])));
        abort_if(empty($paths), 404);

        $tmp = tempnam(sys_get_temp_dir(), 'fmzip');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        foreach ($paths as $path) {
            try {
                $path = $service->guard()->normalize($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (! $service->exists($path)) {
                continue;
            }
            $this->add($service, $zip, $path, basename($path));
        }

        $zip->close();

        return response()->download($tmp, 'download.zip')->deleteFileAfterSend(true);
    }

    protected function add(FileManagerService $service, ZipArchive $zip, string $path, string $local): void
    {
        $disk = $service->disk();

        if ($disk->fileExists($path)) {
            try {
                $abs = $disk->path($path);
                if (is_file($abs)) {
                    $zip->addFile($abs, $local);

                    return;
                }
            } catch (\Throwable $e) {
            }
            $zip->addFromString($local, (string) $disk->get($path));

            return;
        }

        $zip->addEmptyDir($local);
        foreach ($disk->files($path) as $file) {
            if (Str::endsWith($file, '.meta.json')) {
                continue;
            }
            $this->add($service, $zip, $file, $local . '/' . basename($file));
        }
        foreach ($disk->directories($path) as $dir) {
            $this->add($service, $zip, $dir, $local . '/' . basename($dir));
        }
    }
}
