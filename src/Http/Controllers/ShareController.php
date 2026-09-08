<?php

namespace Proside\FileManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Proside\FileManager\Support\FileManagerService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve um ficheiro através de um link assinado e temporário.
 *
 * A autorização aqui é a assinatura do URL, não a sessão: o link funciona
 * para quem o receber, expira na data embutida, e qualquer alteração ao
 * caminho invalida-o. Por isso não passa pelo PathGuard do utilizador atual
 * (que pode nem existir) — mas continua a recusar traversal, lixo e sidecars.
 */
class ShareController
{
    use ServesFiles;

    public function __invoke(Request $request, FileManagerService $service, string $path): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $path = trim(str_replace('\\', '/', $path), '/');
        abort_if($path === '', 404);
        abort_if(in_array('..', explode('/', $path), true), 404);

        abort_if($service->guard()->isTrash($path), 404);
        abort_if(Str::endsWith($path, '.meta.json'), 404);

        abort_unless($service->disk()->fileExists($path), 404);

        return $this->serve($request, $service, $path);
    }
}
