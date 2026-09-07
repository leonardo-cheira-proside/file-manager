<?php

namespace Proside\FileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Proside\FileManager\Support\FileManagerService;

/**
 * Upload direto usado pelo picker (drag & drop na dropzone): guarda o ficheiro
 * na raiz do utilizador e devolve o caminho para pré-selecionar no formulário.
 */
class UploadController
{
    public function __invoke(Request $request, FileManagerService $service): JsonResponse
    {
        $rules = ['file' => 'required|file|max:' . (int) config('file-manager.uploads.max_size')];
        if ($mimes = config('file-manager.uploads.mimes')) {
            $rules['file'] .= '|mimes:' . implode(',', (array) $mimes);
        }
        $request->validate($rules);

        $path = $service->upload($request->file('file'), $service->root());

        return response()->json(['path' => $path]);
    }
}
