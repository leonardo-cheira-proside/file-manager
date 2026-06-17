<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de armazenamento
    |--------------------------------------------------------------------------
    |
    | Disco do filesystem (config/filesystems.php) onde os ficheiros vivem.
    | Por omissão usa o disco "public" (acessível via /storage após
    | `php artisan storage:link`). Pode ser trocado por "s3" ou outro.
    |
    */
    'disk' => env('FILE_MANAGER_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Pastas raiz
    |--------------------------------------------------------------------------
    |
    | "root"  -> pasta base navegável (equivalente a "conteudos" no FM antigo)
    | "trash" -> pasta de itens eliminados (equivalente a "apagados")
    |
    */
    'root' => env('FILE_MANAGER_ROOT', 'conteudos'),
    'trash' => env('FILE_MANAGER_TRASH', 'apagados'),

    /*
    |--------------------------------------------------------------------------
    | Retenção do lixo (dias)
    |--------------------------------------------------------------------------
    |
    | Dias que um item permanece no lixo antes de ser eliminado
    | definitivamente pelo comando `file-manager:prune-trash`.
    | (No FM antigo o código usava 60s; o texto do modal dizia 30 dias —
    | aqui fica configurável e correto.)
    |
    */
    'trash_retention_days' => (int) env('FILE_MANAGER_TRASH_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        // Tamanho máximo por ficheiro, em KB. (51200 = 50 MB)
        'max_size' => (int) env('FILE_MANAGER_MAX_UPLOAD', 51200),
        // Lista de mime types aceites (null = todos).
        'mimes' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Classificação de tipos por extensão
    |--------------------------------------------------------------------------
    */
    'image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'],
    'video_extensions' => ['mp4', 'mov', 'webm', 'avi', 'ogg', 'mkv', 'm4v'],

    /*
    |--------------------------------------------------------------------------
    | Rota full-page (opcional)
    |--------------------------------------------------------------------------
    |
    | Regista uma rota que mostra o File Manager em página inteira. Útil para
    | abrir o gestor diretamente (ex.: /file-manager). Não é necessário para o
    | uso embebido via <livewire:file-manager /> ou <x-file-manager::picker />.
    |
    */
    'route' => [
        'enabled' => env('FILE_MANAGER_ROUTE', true),
        'prefix' => env('FILE_MANAGER_ROUTE_PREFIX', 'file-manager'),
        'middleware' => ['web', 'auth'],
        // Layout Blade que envolve a página full-page (deve ter @yield('content')
        // ou um slot $slot). Por omissão usa o layout próprio do package.
        'layout' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Servir media
    |--------------------------------------------------------------------------
    |
    | "auto"   -> usa Storage::url() em discos públicos, senão a rota interna
    | "route"  -> serve sempre via rota interna (respeita auth, qualquer disco)
    | "storage"-> usa sempre Storage::url() (apenas discos públicos)
    |
    */
    'media_url' => env('FILE_MANAGER_MEDIA_URL', 'auto'),
];
