<?php

namespace Proside\FileManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Proside\FileManager\Support\FileManagerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serviço de ficheiros partilhado pelas rotas de media/partilha/download.
 *
 * Todas as respostas levam "nosniff", e tipos que o browser interpretaria
 * como documento na própria origem (SVG, HTML, XML, …) são forçados a
 * descarregar em vez de abrir — é o que impede um SVG com <script> de
 * correr no domínio da aplicação.
 */
trait ServesFiles
{
    protected function serve(
        Request $request,
        FileManagerService $service,
        string $path,
        bool $forceDownload = false,
    ): Response {
        $disposition = ($forceDownload || $this->mustDownload($path))
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $name = basename($path);

        try {
            $abs = $service->disk()->path($path);
            if (is_file($abs)) {
                $response = response()->file($abs, $this->headers());
                $response->setContentDisposition($disposition, $name, Str::ascii($name));
                $response->setAutoLastModified();
                // setAutoEtag() faz hash_file() — lê o ficheiro inteiro a cada
                // pedido. Num vídeo, que faz vários pedidos com Range, isso
                // multiplica-se. size+mtime identifica a versão na mesma.
                $response->setEtag(substr(md5($path.'|'.filesize($abs).'|'.filemtime($abs)), 0, 32));
                $response->isNotModified($request);

                return $response;
            }
        } catch (\Throwable $e) {
            // disco remoto sem caminho local -> stream abaixo
        }

        return response()->stream(function () use ($service, $path) {
            $stream = $service->disk()->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $this->headers() + [
            'Content-Type' => $service->disk()->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . Str::ascii($name) . '"',
        ]);
    }

    /** @return array<string,string> */
    protected function headers(): array
    {
        return [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /** Extensões que nunca podem ser abertas inline na origem da aplicação. */
    protected function mustDownload(string $path): bool
    {
        $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, (array) config('file-manager.attachment_extensions', []), true);
    }

    /** O lixo e os sidecars de metadados nunca são servidos publicamente. */
    protected function abortIfPrivate(FileManagerService $service, string $path): void
    {
        abort_if($service->guard()->isTrash($path), 404);
        abort_if(Str::endsWith($path, '.meta.json'), 404);
    }
}
