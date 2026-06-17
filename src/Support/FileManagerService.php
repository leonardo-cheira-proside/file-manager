<?php

namespace Proside\FileManager\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Camada de domínio do File Manager: todas as operações de filesystem
 * (listar, árvore, criar, renomear, mover, lixo, prune) abstraídas sobre
 * um disco do Laravel — funciona com "public", "s3", etc.
 */
class FileManagerService
{
    protected Filesystem $disk;

    protected PathGuard $guard;

    public function __construct(?string $disk = null)
    {
        $config = config('file-manager');
        $this->disk = Storage::disk($disk ?? $config['disk']);
        $this->guard = new PathGuard($config['root'], $config['trash']);

        $this->ensureExists($config['root']);
        $this->ensureExists($config['trash']);
    }

    public function guard(): PathGuard
    {
        return $this->guard;
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
     * @param  string  $filter  all|folders|images|videos|az|za
     * @return array<int,array<string,mixed>>
     */
    public function listing(string $path, string $filter = 'all'): array
    {
        $path = $this->guard->normalize($path);

        $folders = collect($this->disk->directories($path))
            ->map(fn ($dir) => $this->folderEntry($dir))
            ->values();

        $files = collect($this->disk->files($path))
            ->reject(fn ($file) => Str::endsWith($file, '.meta.json'))
            ->map(fn ($file) => $this->fileEntry($file))
            ->values();

        // Melhoria face ao FM antigo: ao filtrar por imagens/vídeos mantém-se
        // as pastas visíveis para que a navegação continue possível.
        $result = match ($filter) {
            'folders' => $folders,
            'images' => $folders->concat($files->where('type', 'image')->values()),
            'videos' => $folders->concat($files->where('type', 'video')->values()),
            default => $folders->concat($files),
        };

        $result = $result->sort(function ($a, $b) use ($filter) {
            $cmp = strcasecmp($a['name'], $b['name']);

            return $filter === 'za' ? -$cmp : $cmp;
        })->values();

        return $result->all();
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

        $target = $this->uniqueDir($parent.'/'.$name);
        $this->disk->makeDirectory($target);

        return $target;
    }

    public function rename(string $path, string $newName): string
    {
        $path = $this->guard->normalize($path);
        $newName = $this->guard->sanitizeName($newName);

        $dir = $this->dirname($path);
        $isDir = $this->isDirectory($path);

        // Preserva a extensão original em ficheiros, tal como o FM antigo.
        if (! $isDir) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if ($ext !== '' && ! Str::endsWith(Str::lower($newName), '.'.Str::lower($ext))) {
                $newName .= '.'.$ext;
            }
        }

        $target = $dir === '' ? $newName : $dir.'/'.$newName;

        if ($target === $path) {
            return $path;
        }

        $this->disk->move($path, $this->uniquePath($target));

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

        foreach ($from as $item) {
            try {
                $item = $this->guard->normalize($item);
            } catch (\Throwable $e) {
                $results[] = ['from' => $item, 'success' => false, 'message' => $e->getMessage()];

                continue;
            }

            // Não mover para dentro de si próprio nem para a pasta atual.
            if ($item === $to || Str::startsWith($to, $item.'/') || $this->dirname($item) === $to) {
                $results[] = ['from' => $item, 'success' => false, 'message' => 'Destino inválido'];

                continue;
            }

            $target = $this->uniquePath($to.'/'.basename($item));
            $this->disk->move($item, $target);
            $results[] = ['from' => $item, 'success' => true, 'to' => $target];
        }

        return $results;
    }

    /**
     * Move um ficheiro carregado (UploadedFile/TemporaryUploadedFile) para a
     * pasta indicada, mantendo o nome original e evitando colisões.
     */
    public function upload(UploadedFile $file, string $path): string
    {
        $path = $this->guard->normalize($path);
        $name = $this->guard->sanitizeName($file->getClientOriginalName());

        $target = $this->uniquePath($path.'/'.$name);
        $this->disk->putFileAs($path, $file, basename($target));

        return $target;
    }

    /**
     * Move itens para o lixo, registando o momento de expiração num
     * sidecar ".meta.json" (compatível com qualquer disco).
     *
     * @param  array<int,string>  $paths
     */
    public function trash(array $paths): void
    {
        $expireAt = now()->addDays((int) config('file-manager.trash_retention_days'))->timestamp;

        foreach ($paths as $path) {
            $path = $this->guard->normalize($path);
            if ($this->guard->isTrash($path) || ! $this->exists($path)) {
                continue;
            }

            $target = $this->uniquePath($this->guard->trash().'/'.basename($path));
            $this->disk->move($path, $target);

            $this->disk->put($target.'.meta.json', json_encode([
                'deleteAt' => $expireAt * 1000, // ms (compat com o FM antigo)
                'originalPath' => $path,
            ], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Restaura itens do lixo para a sua localização original (melhoria
     * face ao FM antigo, que não tinha restauro).
     *
     * @param  array<int,string>  $paths
     */
    public function restore(array $paths): void
    {
        foreach ($paths as $path) {
            $path = $this->guard->normalize($path);
            if (! $this->guard->isTrash($path) || ! $this->exists($path)) {
                continue;
            }

            $meta = $this->readMeta($path);
            $original = $meta['originalPath'] ?? ($this->guard->root().'/'.basename($path));
            $original = $this->guard->normalize($this->dirname($original)).'/'.basename($original);

            $this->ensureExists($this->dirname($original));
            $this->disk->move($path, $this->uniquePath($original));
            $this->disk->delete($path.'.meta.json');
        }
    }

    /**
     * Elimina definitivamente itens (apenas dentro do lixo).
     *
     * @param  array<int,string>  $paths
     */
    public function deleteForever(array $paths): void
    {
        foreach ($paths as $path) {
            $path = $this->guard->normalize($path);
            if (! $this->guard->isTrash($path)) {
                continue;
            }
            $this->destroy($path);
            $this->disk->delete($path.'.meta.json');
        }
    }

    /** Remove do lixo todos os itens cujo prazo expirou. Devolve nº removidos. */
    public function pruneTrash(): int
    {
        $removed = 0;
        $trash = $this->guard->trash();
        $now = now()->timestamp * 1000;

        foreach ($this->disk->files($trash) as $file) {
            if (! Str::endsWith($file, '.meta.json')) {
                continue;
            }

            $meta = json_decode((string) $this->disk->get($file), true) ?: [];
            if (($meta['deleteAt'] ?? PHP_INT_MAX) <= $now) {
                $original = Str::beforeLast($file, '.meta.json');
                $this->destroy($original);
                $this->disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }

    // ===========================================================
    // URL / media
    // ===========================================================

    public function mediaUrl(string $path): string
    {
        $path = $this->guard->normalize($path);
        $mode = config('file-manager.media_url', 'auto');

        if ($mode === 'route') {
            return route('file-manager.media', ['path' => $path]);
        }

        try {
            $url = $this->disk->url($path);
            if ($mode === 'storage' || ! empty($url)) {
                return $url;
            }
        } catch (\Throwable $e) {
            // disco sem suporte a url() -> cai na rota
        }

        return route('file-manager.media', ['path' => $path]);
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
    // Helpers internos
    // ===========================================================

    protected function folderEntry(string $dir): array
    {
        $size = $this->directorySize($dir);

        return [
            'name' => basename($dir),
            'path' => $dir,
            'type' => 'folder',
            'extension' => 'folder',
            'size' => $size,
            'sizeFormatted' => $this->formatBytes($size),
            'modified' => $this->modified($dir),
        ];
    }

    protected function fileEntry(string $file): array
    {
        $ext = Str::lower(pathinfo($file, PATHINFO_EXTENSION) ?: 'unknown');
        $size = $this->disk->size($file);

        $entry = [
            'name' => basename($file),
            'path' => $file,
            'type' => $this->classify($ext),
            'extension' => $ext,
            'size' => $size,
            'sizeFormatted' => $this->formatBytes($size),
            'modified' => $this->modified($file),
        ];

        // No lixo, expõe quanto tempo resta antes da eliminação definitiva.
        if ($this->guard->isTrash($file)) {
            $meta = $this->readMeta($file);
            if (isset($meta['deleteAt'])) {
                $entry['expiresAt'] = (int) $meta['deleteAt'];
            }
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
        $metaPath = $path.'.meta.json';
        if (! $this->disk->exists($metaPath)) {
            return [];
        }

        return json_decode((string) $this->disk->get($metaPath), true) ?: [];
    }

    protected function directorySize(string $dir): int
    {
        $total = 0;
        foreach ($this->disk->allFiles($dir) as $file) {
            if (Str::endsWith($file, '.meta.json')) {
                continue;
            }
            $total += $this->disk->size($file);
        }

        return $total;
    }

    protected function modified(string $path): ?string
    {
        try {
            return date('Y-m-d H:i', $this->disk->lastModified($path));
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function isDirectory(string $path): bool
    {
        // Num disco abstrato, "é pasta" = não é ficheiro mas existe como prefixo.
        return ! $this->disk->fileExists($path)
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
        if (! $this->exists($path)) {
            return $path;
        }

        $dir = $this->dirname($path);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $suffix = $ext !== '' ? '.'.$ext : '';

        $counter = 1;
        do {
            $candidate = ($dir === '' ? '' : $dir.'/')."{$base} ({$counter}){$suffix}";
            $counter++;
        } while ($this->exists($candidate));

        return $candidate;
    }

    protected function uniqueDir(string $path): string
    {
        if (! $this->exists($path)) {
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
        if (! $this->disk->directoryExists($path)) {
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

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }
}
