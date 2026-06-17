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
    | Root por utilizador (scoping de permissões)
    |--------------------------------------------------------------------------
    |
    | Resolver opcional que devolve a pasta-raiz efetiva do utilizador atual
    | (relativa ao disco, ex.: "conteudos/optivisao"). O utilizador só vê essa
    | pasta para baixo. Devolver null/"" => acesso total (usa a raiz "root").
    |
    | Pode ser:
    |  - class-string de uma classe invocável (__invoke(): ?string) [compatível
    |    com config:cache] — RECOMENDADO;
    |  - um callable (closure) — simples, mas impede config:cache.
    |
    | Exemplo (Backoffice): \App\FileManager\LevelRootResolver::class
    |
    */
    'root_resolver' => null,

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
    | "route"  -> serve via rota interna (respeita auth, qualquer disco, e
    |             codifica o path — seguro com nomes que tenham "+", espaços,
    |             etc.). É o default por ser à prova de bala. Nota: o
    |             `php artisan serve` faz urldecode aos paths estáticos, pelo
    |             que Storage::url() falha com ficheiros que tenham "+" no nome.
    | "storage"-> usa sempre Storage::url() (mais rápido; requer web server a
    |             servir o disco público e nomes de ficheiro URL-safe).
    | "auto"   -> Storage::url() em discos públicos, senão a rota interna.
    |
    */
    'media_url' => env('FILE_MANAGER_MEDIA_URL', 'route'),
];
