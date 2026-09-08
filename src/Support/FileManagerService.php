<?php

namespace Proside\FileManager\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use League\Flysystem\StorageAttributes;

/**
 * Camada de domínio do File Manager: todas as operações de filesystem
 * (listar, árvore, criar, renomear, mover, lixo, prune) abstraídas sobre
 * um disco do Laravel — funciona com "public", "s3", etc.
 */
class FileManagerService
{
    protected Filesystem $disk;

    protected PathGuard $guard;

    /** Raiz definida na config (ex.: "conteudos"). */
    protected string $configRoot;

    /**
     * Raízes efetivas do utilizador (uma ou várias). Igual a [configRoot]
     * significa acesso total. A primeira é a raiz principal (vista inicial).
     *
     * @var array<int,string>
     */
    protected array $effectiveRoots;

    /** True se o utilizador está confinado a sub-raízes (não acesso total). */
    protected bool $scoped;

    public function __construct(?string $disk = null)
    {
        $config = config('file-manager');
        $this->disk = Storage::disk($disk ?? $config['disk']);
        $this->configRoot = $config['root'];
        $this->effectiveRoots = $this->resolveEffectiveRoots($config);
        $this->scoped = $this->effectiveRoots !== [$this->configRoot];

        foreach ($this->effectiveRoots as $root) {
            $this->ensureExists($root);
        }
        $this->guard = new PathGuard($this->effectiveRoots, $config['trash']);
    }

    public function guard(): PathGuard
    {
        return $this->guard;
    }

    /** Raiz principal do utilizador (a primeira; vista inicial). */
    public function root(): string
    {
        return $this->effectiveRoots[0];
    }

    /**
     * Todas as raízes efetivas do utilizador.
     *
     * @return array<int,string>
     */
    public function roots(): array
    {
        return $this->effectiveRoots;
    }

    /** Ramo do lixo do utilizador (espelha a raiz principal). */
    public function trashRoot(): string
    {
        return $this->guard->trashRoot();
    }

    public function isScoped(): bool
    {
        return $this->scoped;
    }

    /**
     * Resolve as raízes efetivas via o resolver configurado (class-string
     * invocável ou callable). O resolver pode devolver um caminho único
     * (string) ou vários (array). Cada caminho é confinado ao configRoot;
     * caminhos fora dele são ignorados (não escalam privilégios). Se o
     * resolver indicar a própria raiz da config (ou vazio), há acesso total.
     *
     * @return array<int,string>
     */
    protected function resolveEffectiveRoots(array $config): array
    {
        $resolver = $config['root_resolver'] ?? null;
        if (!$resolver) {
            return [$config['root']];
        }

        try {
            $value = is_string($resolver) && class_exists($resolver)
                ? app($resolver)()
                : (is_callable($resolver) ? $resolver() : null);
        } catch (\Throwable $e) {
            report($e);
            if (request()->expectsJson()) {
                abort(401);
            }
            $target = config('file-manager.route.redirect_on_error', 'dashboard');
            throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect(url($target)));
        }

        if ($value === null) {
            return [$config['root']];
        }

        $candidates = is_array($value) ? $value : [$value];
        $roots = [];
        foreach ($candidates as $candidate) {
            $candidate = trim(str_replace('\\', '/', (string) $candidate), '/');

            if ($candidate === '' || $candidate === $config['root']) {
                return [$config['root']];
            }
            if (!str_starts_with($candidate, $config['root'] . '/')) {
                continue;
            }
            $roots[] = $candidate;
        }

        $roots = array_values(array_unique($roots));

        return empty($roots) ? [$config['root']] : $roots;
    }

    public function disk(): Filesystem
    {
        return $this->disk;
    }

    // ===========================================================
    // Listagem
    // ===========================================================

    /**
     * Lista o conteúdo de uma pasta, aplicando filtro e ordenação.
     *
     * @param  string  $filter  all|folders|images|videos|no-folder|az|za
     * @return array<int,array<string,mixed>>
     */
    public function listing(string $path, string $filter = 'all', string $sort = 'az'): array
    {
        $path = $this->guard->normalize($path);
        $inTrash = $this->guard->isTrash($path);

        [$folders, $files] = $this->scan($path, $inTrash);

        $result = match ($filter) {
            'folders' => $folders,
            'images' => array_values(array_filter($files, fn ($f) => $f['type'] === 'image')),
            'videos' => array_values(array_filter($files, fn ($f) => $f['type'] === 'video')),
            'no-folder' => $files,
            default => [...$folders, ...$files],
        };

        usort($result, fn ($a, $b) => $this->compareEntries($a, $b, $sort));

        return $result;
    }

    /**
     * Lê uma pasta numa única passagem, aproveitando os metadados que o
     * adaptador já devolve (evita size()/lastModified() por entrada — em
     * discos remotos como o S3 isso seriam duas chamadas API por ficheiro).
     *
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
     */
    protected function scan(string $path, bool $inTrash): array
    {
        $folders = [];
        $files = [];

        foreach ($this->attributes($path) as $item) {
            $itemPath = $item->path();

            if ($item->isDir()) {
                $folders[] = $this->folderEntry($itemPath, $item->lastModified(), $inTrash);

                continue;
            }

            if ($inTrash && Str::endsWith($itemPath, '.meta.json')) {
                continue;
            }

            $files[] = $this->fileEntry($itemPath, $item->fileSize(), $item->lastModified(), $inTrash);
        }

        return [$folders, $files];
    }

    /**
     * Metadados das entradas diretas de uma pasta. Usa o Flysystem quando
     * disponível; caso contrário recorre às chamadas individuais do disco.
     *
     * @return iterable<int,StorageAttributes>
     */
    protected function attributes(string $path): iterable
    {
        if (method_exists($this->disk, 'getDriver')) {
            try {
                return iterator_to_array($this->disk->getDriver()->listContents($path, false), false);
            } catch (\Throwable $e) {
                // adaptador sem suporte -> fallback abaixo
            }
        }

        $out = [];
        foreach ($this->disk->directories($path) as $dir) {
            $out[] = new \League\Flysystem\DirectoryAttributes($dir, null, $this->rawModified($dir));
        }
        foreach ($this->disk->files($path) as $file) {
            $out[] = new \League\Flysystem\FileAttributes(
                $file, $this->rawSize($file), null, $this->rawModified($file)
            );
        }

        return $out;
    }

    protected function compareEntries(array $a, array $b, string $sort): int
    {
        return match ($sort) {
            'za' => strcasecmp($b['name'], $a['name']),
            'newest' => strcmp((string) ($b['modified'] ?? ''), (string) ($a['modified'] ?? '')),
            'oldest' => strcmp((string) ($a['modified'] ?? ''), (string) ($b['modified'] ?? '')),
            'largest' => ($b['size'] ?? 0) <=> ($a['size'] ?? 0),
            'smallest' => ($a['size'] ?? 0) <=> ($b['size'] ?? 0),
            default => strcasecmp($a['name'], $b['name']),
        };
    }

    /**
     * Pesquisa por nome em todas as raízes do utilizador. O varrimento
     * recursivo é guardado em cache por raiz durante alguns segundos, para
     * que escrever na caixa de pesquisa não relance a travessia a cada tecla.
     */
    public function search(string $term, string $sort = 'az'): array
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return [];
        }

        $entries = [];
        foreach ($this->index() as $entry) {
            if (str_contains(mb_strtolower($entry['name']), $term)) {
                $entries[] = $entry;
            }
        }

        usort($entries, fn ($a, $b) => $this->compareEntries($a, $b, $sort));

        return array_slice($entries, 0, 500);
    }

    /**
     * Índice plano (caminho + nome + tipo) de todas as raízes do utilizador.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function index(): array
    {
        $ttl = (int) config('file-manager.search.cache_seconds', 10);
        $key = 'file-manager:index:'.md5(implode('|', $this->effectiveRoots));

        $build = function (): array {
            $out = [];
            foreach ($this->effectiveRoots as $root) {
                foreach ($this->disk->allFiles($root) as $file) {
                    $out[] = $this->fileEntry($file, $this->rawSize($file), $this->rawModified($file), false);
                }
                foreach ($this->disk->allDirectories($root) as $dir) {
                    $out[] = $this->folderEntry($dir, $this->rawModified($dir), false);
                }
            }

            return $out;
        };

        return $ttl > 0 ? cache()->remember($key, $ttl, $build) : $build();
    }

    protected function forgetIndex(): void
    {
        cache()->forget('file-manager:index:'.md5(implode('|', $this->effectiveRoots)));
    }

    public function duplicate(array $paths): array
    {
        $out = [];
        foreach ($paths as $path) {
            try {
                $path = $this->guard->normalize($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (! $this->exists($path)) {
                continue;
            }
            $target = $this->uniquePath($path);
            if ($this->isDirectory($path)) {
                $this->copyDirectory($path, $target);
            } else {
                $this->disk->copy($path, $target);
            }
            $out[] = $target;
        }

        $this->audit('duplicate', ['paths' => $paths, 'created' => $out]);
        $this->forgetIndex();

        return $out;
    }

    /**
     * Constrói a árvore de pastas a partir da raiz, expandindo apenas as
     * pastas indicadas em $openFolders (lazy loading recursivo).
     *
     * @param  array<int,string>  $openFolders
     * @return array<int,array<string,mixed>>
     */
    public function tree(?string $base = null, array $openFolders = []): array
    {
        $base = $this->guard->normalize($base ?? $this->guard->root());

        return collect($this->disk->directories($base))
            ->map(function ($dir) use ($openFolders) {
                $hasChildren = count($this->disk->directories($dir)) > 0;

                return [
                    'name' => basename($dir),
                    'path' => $dir,
                    'type' => 'folder',
                    'has_children' => $hasChildren,
                    'children' => in_array($dir, $openFolders, true)
                        ? $this->tree($dir, $openFolders)
                        : [],
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    // ===========================================================
    // Operações
    // ===========================================================

    public function createFolder(string $parent, string $name): string
    {
        $parent = $this->guard->normalize($parent);
        $name = $this->guard->sanitizeName($name);

        $target = $this->uniqueDir($parent . '/' . $name);
        $this->disk->makeDirectory($target);

        $this->audit('create_folder', ['path' => $target]);
        $this->forgetIndex();

        return $target;
    }

    public function rename(string $path, string $newName): string
    {
        $path = $this->guard->normalize($path);
        $newName = $this->guard->sanitizeName($newName);

        $dir = $this->dirname($path);
        $isDir = $this->isDirectory($path);

        if (!$isDir) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if ($ext !== '' && !Str::endsWith(Str::lower($newName), '.' . Str::lower($ext))) {
                $newName .= '.' . $ext;
            }
        }

        $target = $dir === '' ? $newName : $dir . '/' . $newName;

        if ($target === $path) {
            return $path;
        }

        $target = $this->uniquePath($target);
        $this->disk->move($path, $target);

        $this->audit('rename', ['from' => $path, 'to' => $target]);
        $this->forgetIndex();

        return $target;
    }

    /**
     * Move um ou vários itens para uma pasta destino.
     *
     * @param  array<int,string>  $from
     * @return array<int,array<string,mixed>>
     */
    public function move(array $from, string $to): array
    {
        $to = $this->guard->normalize($to);
        $results = [];

        if ($this->disk->fileExists($to)) {
            foreach ($from as $item) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Destino inválido'];
            }

            return $results;
        }

        foreach ($from as $item) {
            try {
                $item = $this->guard->normalize($item);
            } catch (\Throwable $e) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Caminho inválido'];

                continue;
            }

            if ($item === $to || Str::startsWith($to, $item . '/') || $this->dirname($item) === $to) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Destino inválido'];

                continue;
            }

            try {
                $target = $this->uniquePath($to . '/' . basename($item));
                $this->disk->move($item, $target);
                $results[] = ['from' => $item, 'success' => true, 'to' => $target];
                $this->audit('move', ['from' => $item, 'to' => $target]);
            } catch (\Throwable $e) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Não foi possível mover'];
            }
        }

        $this->forgetIndex();

        return $results;
    }

    /**
     * Copia itens (ficheiros e pastas, recursivamente) para uma pasta destino.
     *
     * @param  array<int,string>  $from
     * @return array<int,array<string,mixed>>
     */
    public function copy(array $from, string $to): array
    {
        $to = $this->guard->normalize($to);
        $results = [];

        if ($this->disk->fileExists($to)) {
            foreach ($from as $item) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Destino inválido'];
            }

            return $results;
        }

        foreach ($from as $item) {
            try {
                $item = $this->guard->normalize($item);
            } catch (\Throwable $e) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Caminho inválido'];

                continue;
            }

            if ($item === $to || Str::startsWith($to, $item . '/')) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Destino inválido'];

                continue;
            }

            try {
                $target = $this->uniquePath($to . '/' . basename($item));
                if ($this->isDirectory($item)) {
                    $this->copyDirectory($item, $target);
                } else {
                    $this->disk->copy($item, $target);
                }
                $results[] = ['from' => $item, 'success' => true, 'to' => $target];
                $this->audit('copy', ['from' => $item, 'to' => $target]);
            } catch (\Throwable $e) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Não foi possível copiar'];
            }
        }

        $this->forgetIndex();

        return $results;
    }

    /** Cópia recursiva de uma pasta (ficheiros + subpastas). */
    protected function copyDirectory(string $src, string $dst): void
    {
        $this->disk->makeDirectory($dst);
        foreach ($this->disk->files($src) as $file) {
            $this->disk->copy($file, $dst . '/' . basename($file));
        }
        foreach ($this->disk->directories($src) as $dir) {
            $this->copyDirectory($dir, $dst . '/' . basename($dir));
        }
    }

    /**
     * Move um ficheiro carregado (UploadedFile/TemporaryUploadedFile) para a
     * pasta indicada, mantendo o nome original e evitando colisões.
     */
    public function upload(UploadedFile $file, string $path): string
    {
        $path = $this->guard->normalize($path);
        $name = $this->guard->sanitizeName($file->getClientOriginalName());

        $target = $this->uniquePath($path . '/' . $name);
        for ($i = 0; $i < 5 && $this->disk->fileExists($target); $i++) {
            $target = $this->uniquePath($path . '/' . $name);
        }

        $this->disk->putFileAs($path, $file, basename($target));

        $this->audit('upload', ['path' => $target, 'size' => $file->getSize()]);
        $this->forgetIndex();

        return $target;
    }

    /**
     * Move itens para o lixo. O lixo espelha a árvore de origem
     * ("conteudos/a/x.png" -> "apagados/conteudos/a/x.png"), o que confina
     * cada utilizador ao seu próprio ramo e torna o restauro trivial.
     * Um sidecar ".meta.json" guarda o momento de expiração.
     *
     * @param  array<int,string>  $paths
     * @return array<int,array<string,mixed>>
     */
    public function trash(array $paths): array
    {
        $expireAt = now()->addDays((int) config('file-manager.trash_retention_days'))->timestamp;
        $results = [];

        foreach ($paths as $path) {
            try {
                $path = $this->guard->normalize($path);

                if ($this->guard->isTrash($path) || !$this->exists($path)) {
                    $results[] = ['from' => $path, 'success' => false, 'message' => 'Item indisponível'];

                    continue;
                }

                $target = $this->uniquePath($this->guard->toTrash($path));
                $this->ensureExists($this->dirname($target));
                $this->disk->move($path, $target);

                $this->disk->put($target . '.meta.json', json_encode([
                    'deleteAt' => $expireAt * 1000,
                    'originalPath' => $path,
                ], JSON_PRETTY_PRINT));

                $results[] = ['from' => $path, 'success' => true, 'to' => $target];
                $this->audit('trash', ['from' => $path, 'to' => $target]);
            } catch (\Throwable $e) {
                $results[] = ['from' => $path, 'success' => false, 'message' => 'Não foi possível eliminar'];
            }
        }

        $this->forgetIndex();

        return $results;
    }

    /**
     * Restaura itens do lixo para a sua localização original. O destino vem
     * do próprio caminho no lixo (espelho da árvore), e é revalidado pelo
     * PathGuard — um item não pode ser restaurado para fora das raízes.
     *
     * @param  array<int,string>  $paths
     * @return array<int,array<string,mixed>>
     */
    public function restore(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            try {
                $path = $this->guard->normalize($path);

                if (!$this->guard->isTrash($path) || !$this->exists($path)) {
                    $results[] = ['from' => $path, 'success' => false, 'message' => 'Item indisponível'];

                    continue;
                }

                $original = $this->guard->normalize($this->guard->fromTrash($path));

                $this->ensureExists($this->dirname($original));
                $target = $this->uniquePath($original);
                $this->disk->move($path, $target);
                $this->disk->delete($path . '.meta.json');

                $results[] = ['from' => $path, 'success' => true, 'to' => $target];
                $this->audit('restore', ['from' => $path, 'to' => $target]);
            } catch (\Throwable $e) {
                $results[] = ['from' => $path, 'success' => false, 'message' => 'Não foi possível restaurar'];
            }
        }

        $this->forgetIndex();

        return $results;
    }

    /**
     * Elimina definitivamente itens (apenas dentro do lixo do utilizador).
     *
     * @param  array<int,string>  $paths
     * @return array<int,array<string,mixed>>
     */
    public function deleteForever(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            try {
                $path = $this->guard->normalize($path);

                if (!$this->guard->isTrash($path)) {
                    $results[] = ['from' => $path, 'success' => false, 'message' => 'Item indisponível'];

                    continue;
                }

                $this->destroy($path);
                $this->disk->delete($path . '.meta.json');

                $results[] = ['from' => $path, 'success' => true];
                $this->audit('delete_forever', ['path' => $path]);
            } catch (\Throwable $e) {
                $results[] = ['from' => $path, 'success' => false, 'message' => 'Não foi possível eliminar'];
            }
        }

        return $results;
    }

    /** Remove do lixo todos os itens cujo prazo expirou. Devolve nº removidos. */
    public function pruneTrash(): int
    {
        $removed = 0;
        $trash = $this->guard->trash();
        $now = now()->timestamp * 1000;

        if (! $this->disk->directoryExists($trash)) {
            return 0;
        }

        foreach ($this->disk->allFiles($trash) as $file) {
            if (!Str::endsWith($file, '.meta.json')) {
                continue;
            }

            $meta = json_decode((string) $this->disk->get($file), true) ?: [];
            if (($meta['deleteAt'] ?? PHP_INT_MAX) <= $now) {
                $original = Str::beforeLast($file, '.meta.json');
                $this->destroy($original);
                $this->disk->delete($file);
                $removed++;
                $this->audit('prune', ['path' => $original]);
            }
        }

        return $removed;
    }

    // ===========================================================
    // URL / media / partilha
    // ===========================================================

    public function mediaUrl(string $path): string
    {
        $path = $this->guard->normalize($path);
        $mode = config('file-manager.media_url', 'auto');

        if ($mode === 'route') {
            return $this->routeMediaUrl($path);
        }

        try {
            $url = $this->disk->url($path);
            if ($mode === 'storage' || !empty($url)) {
                return $url;
            }
        } catch (\Throwable $e) {
            // disco sem suporte a url() -> cai na rota
        }

        return $this->routeMediaUrl($path);
    }

    /** URL de media com o caminho direto no URL (barras preservadas). */
    public function routeMediaUrl(string $path): string
    {
        $prefix = trim((string) (config('file-manager.route.prefix') ?? 'file-manager'), '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return url($prefix . '/media/' . $encoded);
    }

    /**
     * Link de partilha assinado e temporário. Ao contrário do URL de media
     * (que é público e permanente), este expira e é inviolável — alterar o
     * caminho invalida a assinatura.
     */
    public function shareUrl(string $path, ?int $minutes = null): string
    {
        $path = $this->guard->normalize($path);

        if (! $this->disk->fileExists($path)) {
            throw new \InvalidArgumentException('Só é possível partilhar ficheiros.');
        }

        $minutes = $minutes ?: (int) config('file-manager.share.expires_minutes', 1440);

        $url = URL::temporarySignedRoute(
            'file-manager.share',
            now()->addMinutes($minutes),
            ['path' => $path],
        );

        $this->audit('share', ['path' => $path, 'minutes' => $minutes]);

        return $url;
    }

    public function readStream(string $path)
    {
        $path = $this->guard->normalize($path);

        return $this->disk->readStream($path);
    }

    public function mimeType(string $path): string
    {
        $path = $this->guard->normalize($path);

        return $this->disk->mimeType($path) ?: 'application/octet-stream';
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    // ===========================================================
    // Auditoria
    // ===========================================================

    /**
     * Regista uma operação no log de auditoria. Usa o logger do Laravel —
     * o canal é configurável, o que permite mandar isto para um ficheiro
     * próprio sem que o package precise de base de dados.
     */
    protected function audit(string $action, array $context = []): void
    {
        if (! config('file-manager.audit.enabled', true)) {
            return;
        }

        try {
            $context['user'] = auth()->id();
            $context['ip'] = request()?->ip();
        } catch (\Throwable $e) {
            // fora de um contexto HTTP/auth (ex.: cron) — regista na mesma
        }

        try {
            Log::channel(config('file-manager.audit.channel'))
                ->info('file-manager.' . $action, $context);
        } catch (\Throwable $e) {
            // auditoria nunca pode fazer falhar a operação
        }
    }

    // ===========================================================
    // Helpers internos
    // ===========================================================

    protected function folderEntry(string $dir, ?int $modified = null, bool $inTrash = false): array
    {
        $entry = [
            'name' => basename($dir),
            'path' => $dir,
            'type' => 'folder',
            'extension' => 'folder',
            'size' => null,
            'sizeFormatted' => null,
            'modified' => $this->formatModified($modified),
        ];

        return $inTrash ? $this->withExpiry($entry, $dir) : $entry;
    }

    protected function fileEntry(string $file, ?int $size = null, ?int $modified = null, bool $inTrash = false): array
    {
        $ext = Str::lower(pathinfo($file, PATHINFO_EXTENSION) ?: 'unknown');

        $entry = [
            'name' => basename($file),
            'path' => $file,
            'type' => $this->classify($ext),
            'extension' => $ext,
            'size' => $size,
            'sizeFormatted' => $size === null ? null : $this->formatBytes($size),
            'modified' => $this->formatModified($modified),
        ];

        return $inTrash ? $this->withExpiry($entry, $file) : $entry;
    }

    /** Acrescenta o prazo de eliminação — vale para ficheiros e pastas. */
    protected function withExpiry(array $entry, string $path): array
    {
        $meta = $this->readMeta($path);
        if (isset($meta['deleteAt'])) {
            $entry['expiresAt'] = (int) $meta['deleteAt'];
        }

        return $entry;
    }

    protected function classify(string $ext): string
    {
        if (in_array($ext, config('file-manager.image_extensions'), true)) {
            return 'image';
        }
        if (in_array($ext, config('file-manager.video_extensions'), true)) {
            return 'video';
        }

        return 'other';
    }

    protected function readMeta(string $path): array
    {
        $metaPath = $path . '.meta.json';
        if (!$this->disk->exists($metaPath)) {
            return [];
        }

        return json_decode((string) $this->disk->get($metaPath), true) ?: [];
    }

    protected function formatModified(?int $timestamp): ?string
    {
        return $timestamp ? date('Y-m-d H:i', $timestamp) : null;
    }

    protected function rawModified(string $path): ?int
    {
        try {
            return $this->disk->lastModified($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function rawSize(string $path): ?int
    {
        try {
            return $this->disk->size($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function isDirectory(string $path): bool
    {
        return !$this->disk->fileExists($path)
            && (count($this->disk->files($path)) > 0
                || count($this->disk->directories($path)) > 0
                || $this->disk->directoryExists($path));
    }

    protected function destroy(string $path): void
    {
        if ($this->disk->fileExists($path)) {
            $this->disk->delete($path);
        } else {
            $this->disk->deleteDirectory($path);
        }
    }

    protected function uniquePath(string $path): string
    {
        if (!$this->exists($path)) {
            return $path;
        }

        $dir = $this->dirname($path);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $suffix = $ext !== '' ? '.' . $ext : '';

        $counter = 1;
        do {
            $candidate = ($dir === '' ? '' : $dir . '/') . "{$base} ({$counter}){$suffix}";
            $counter++;
        } while ($this->exists($candidate));

        return $candidate;
    }

    protected function uniqueDir(string $path): string
    {
        if (!$this->exists($path)) {
            return $path;
        }

        $counter = 1;
        do {
            $candidate = "{$path} ({$counter})";
            $counter++;
        } while ($this->exists($candidate));

        return $candidate;
    }

    protected function dirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));

        return $dir === '.' ? '' : $dir;
    }

    protected function ensureExists(string $path): void
    {
        if ($path !== '' && !$this->disk->directoryExists($path)) {
            $this->disk->makeDirectory($path);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
