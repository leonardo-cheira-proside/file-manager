<?php

namespace Proside\FileManager\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Proside\FileManager\Support\FileManagerService;

class FileManager extends Component
{
    use WithFileUploads;

    /** Pasta atualmente aberta (relativa ao disco). */
    public string $path = '';

    /** Filtro de conteúdo: all|folders|images|videos|no-folder */
    public string $filter = 'all';

    public string $sort = 'az';

    public int $limit = 60;

    /** Mensagem de erro da última operação (vazia = sem erro). */
    public string $error = '';

    /** Modo de visualização: grid|list */
    public string $viewMode = 'grid';

    /** Termo de pesquisa (filtra a pasta atual pelo nome). */
    public string $search = '';

    /** Pastas expandidas na árvore lateral. @var array<int,string> */
    public array $openFolders = [];

    /** Caminhos selecionados. @var array<int,string> */
    public array $selected = [];

    /** Sidebar de diretórios visível. */
    public bool $showTree = true;

    /** Ficheiros em upload (wire:model). */
    public array $uploads = [];

    /** Modo "picker": ativa a ação "Escolher" e emite a seleção. */
    #[Locked]
    public bool $pickerMode = false;

    /** Em modo picker, permite escolher vários ficheiros. */
    #[Locked]
    public bool $multiple = false;

    /** Esconde a UI de filtros (ex.: picker forçado a imagens/vídeos). */
    #[Locked]
    public bool $lockFilter = false;

    public function mount(
        ?string $path = null,
        ?string $filter = null,
        bool $pickerMode = false,
        bool $multiple = false,
        bool $lockFilter = false,
    ): void {
        $this->path = $path ?: $this->service()->root();
        $this->filter = $filter ?: 'all';
        $this->pickerMode = $pickerMode;
        $this->multiple = $multiple;
        $this->lockFilter = $lockFilter;
    }

    protected function service(): FileManagerService
    {
        return app(FileManagerService::class);
    }

    // ===========================================================
    // Dados (computed)
    // ===========================================================

    #[Computed]
    public function allFiles(): array
    {
        $service = $this->service();

        try {
            $entries = $this->search !== ''
                ? $service->search($this->search, $this->sort)
                : $service->listing($this->path, $this->filter, $this->sort);
        } catch (\Throwable $e) {
            $this->path = $service->root();
            $entries = $service->listing($this->path, $this->filter, $this->sort);
        }

        return array_map(function (array $entry) use ($service) {
            $entry['url'] = in_array($entry['type'], ['image', 'video', 'other'], true)
                ? $service->mediaUrl($entry['path'])
                : null;

            return $entry;
        }, $entries);
    }

    #[Computed]
    public function files(): array
    {
        return array_slice($this->allFiles, 0, $this->limit);
    }

    #[Computed]
    public function hasMore(): bool
    {
        return count($this->allFiles) > $this->limit;
    }

    #[Computed]
    public function tree(): array
    {
        return $this->service()->tree(null, $this->openFolders);
    }

    /**
     * Raízes efetivas com a respetiva árvore (lazy via openFolders). Com acesso
     * total é só uma (a raiz da config); confinado pode haver várias.
     *
     * @return array<int,array<string,mixed>>
     */
    #[Computed]
    public function roots(): array
    {
        $service = $this->service();

        return array_map(fn ($root) => [
            'path' => $root,
            'label' => basename($root),
            'tree' => $service->tree($root, $this->openFolders),
        ], $service->roots());
    }

    #[Computed]
    public function breadcrumbs(): array
    {
        // Base = a raiz (ou lixo) mais específica que contém o caminho atual,
        // para não expor segmentos acima dela (válido com várias raízes).
        $guard = $this->service()->guard();
        $roots = $guard->roots();

        $base = $roots[0];
        $bestLen = -1;
        foreach ([...$roots, ...$guard->trashRoots()] as $candidate) {
            if (($this->path === $candidate || str_starts_with($this->path, $candidate.'/'))
                && strlen($candidate) > $bestLen) {
                $base = $candidate;
                $bestLen = strlen($candidate);
            }
        }

        $baseDepth = count(explode('/', $base));
        $segments = explode('/', $this->path);
        $crumbs = [];
        $acc = [];
        foreach ($segments as $i => $segment) {
            $acc[] = $segment;
            if ($i < $baseDepth - 1) {
                continue;
            }
            $crumbs[] = ['label' => $segment, 'path' => implode('/', $acc)];
        }

        return $crumbs;
    }

    #[Computed]
    public function inTrash(): bool
    {
        return $this->service()->guard()->isTrash($this->path);
    }

    /** Caminho da raiz efetiva (para o link da sidebar). */
    #[Computed]
    public function rootPath(): string
    {
        return $this->service()->root();
    }

    /** Ramo do lixo do utilizador (o lixo espelha a árvore de origem). */
    #[Computed]
    public function trashPath(): string
    {
        return $this->service()->trashRoot();
    }

    /** Etiqueta da raiz (último segmento; "conteudos" em acesso total). */
    #[Computed]
    public function rootLabel(): string
    {
        return basename($this->service()->root());
    }

    // ===========================================================
    // Navegação / seleção
    // ===========================================================

    public function open(string $path): void
    {
        $service = $this->service();

        try {
            $this->path = $service->guard()->normalize($path);
        } catch (\Throwable $e) {
            $this->path = $service->root();
        }

        $this->selected = [];
        $this->limit = 60;
        $this->error = '';

        $this->dispatch('fm-navigated', path: $this->path);
    }

    public function toggleFolder(string $path): void
    {
        if (($key = array_search($path, $this->openFolders, true)) !== false) {
            unset($this->openFolders[$key]);
            $this->openFolders = array_values($this->openFolders);
        } else {
            $this->openFolders[] = $path;
        }
    }

    public function select(string $path, bool $append = false): void
    {
        if (! $append) {
            $this->selected = [$path];

            return;
        }

        if (($key = array_search($path, $this->selected, true)) !== false) {
            unset($this->selected[$key]);
            $this->selected = array_values($this->selected);
        } else {
            $this->selected[] = $path;
        }
    }

    public function selectAll(): void
    {
        $this->selected = array_map(fn ($f) => $f['path'], $this->files);
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->limit = 60;
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
        $this->limit = 60;
    }

    public function loadMore(): void
    {
        $this->limit = min($this->limit + 60, 600);
    }

    public function updatedSearch(): void
    {
        $this->limit = 60;
    }

    public function setView(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    // ===========================================================
    // Operações
    // ===========================================================

    public function createFolder(string $name, ?string $parent = null): void
    {
        if ($this->inTrash) {
            return;
        }

        $parent = $parent ?: $this->path;

        $this->guarded(function () use ($name, $parent) {
            $newPath = $this->service()->createFolder($parent, $name);
            $this->openFolders = array_values(array_unique([...$this->openFolders, $parent, $newPath]));
        });
    }

    public function rename(string $path, string $newName): void
    {
        if ($this->inTrash) {
            return;
        }

        $this->guarded(fn () => $this->service()->rename($path, $newName));
    }

    public function delete(array|string $paths): void
    {
        $paths = (array) $paths;

        $this->guarded(function () use ($paths) {
            $this->service()->trash($paths);

            if (in_array($this->path, $paths, true)) {
                $this->path = $this->parentPath($this->path);
            }
        });
    }

    public function restore(array|string $paths): void
    {
        $this->guarded(fn () => $this->service()->restore((array) $paths));
    }

    public function deleteForever(array|string $paths): void
    {
        $this->guarded(fn () => $this->service()->deleteForever((array) $paths));
    }

    public function moveItems(array $from, string $to): void
    {
        if ($this->inTrash) {
            return;
        }

        $this->guarded(fn () => $this->service()->move($from, $to));
    }

    public function copyItems(array $from, string $to): void
    {
        if ($this->inTrash) {
            return;
        }

        $this->guarded(fn () => $this->service()->copy($from, $to));
    }

    public function duplicate(array|string $paths): void
    {
        if ($this->inTrash) {
            return;
        }

        $this->guarded(fn () => $this->service()->duplicate((array) $paths));
    }

    public function dismissError(): void
    {
        $this->error = '';
    }

    /**
     * Lifecycle hook do wire:model. Não guarda nada: quem decide o destino é
     * o cliente, via storeUploads(), com a pasta que estava aberta quando o
     * upload arrancou. Decidir aqui usaria $this->path no momento em que o
     * upload TERMINA — trocar de pasta a meio mandava o ficheiro para o
     * sítio errado e deixava o placeholder da pasta de origem encravado.
     */
    public function updatedUploads(): void
    {
        if ($this->inTrash) {
            $this->uploads = [];
        }
    }

    /** Guarda os ficheiros já carregados na pasta onde o upload começou. */
    public function storeUploads(?string $folder = null): void
    {
        if (empty($this->uploads)) {
            return;
        }

        $service = $this->service();

        try {
            $target = $service->guard()->normalize($folder ?: $this->path);
        } catch (\Throwable $e) {
            $target = $service->root();
        }

        if ($service->guard()->isTrash($target)) {
            $this->uploads = [];

            return;
        }

        $rules = ['uploads.*' => 'file|max:'.(int) config('file-manager.uploads.max_size')];
        if ($mimes = config('file-manager.uploads.mimes')) {
            $rules['uploads.*'] .= '|mimes:'.implode(',', (array) $mimes);
        }
        $this->validate($rules);

        $uploads = $this->uploads;
        $this->uploads = [];

        $this->guarded(function () use ($uploads, $target, $service) {
            foreach ($uploads as $file) {
                $service->upload($file, $target);
            }
        });

        $this->dispatch('file-manager-uploaded', path: $target);
    }

    // ===========================================================
    // Picker
    // ===========================================================

    public function choose(array|string $paths): void
    {
        if (! $this->pickerMode) {
            return;
        }

        $paths = array_values(array_filter((array) $paths));
        if (empty($paths)) {
            return;
        }

        if (! $this->multiple) {
            $paths = [$paths[0]];
        }

        $this->dispatch('file-manager-selected', paths: $paths);
    }

    // ===========================================================
    // Helpers
    // ===========================================================

    /**
     * Corre uma operação sem deixar que uma exceção rebente a página.
     * Um caminho inválido ou uma escrita falhada viram uma mensagem, não um 500.
     */
    protected function guarded(\Closure $op): void
    {
        $this->error = '';

        try {
            $op();
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('file-manager::file-manager.operation_failed');
        }

        $this->selected = [];
        $this->refreshData();
    }

    protected function refreshData(): void
    {
        unset($this->allFiles, $this->files, $this->hasMore, $this->tree, $this->roots, $this->breadcrumbs, $this->inTrash, $this->trashPath);
    }

    protected function parentPath(string $path): string
    {
        $guard = $this->service()->guard();
        $bases = [...$guard->roots(), ...$guard->trashRoots()];
        $parent = str_replace('\\', '/', dirname($path));
        $parent = $parent === '.' ? '' : $parent;

        // Nunca subir acima de uma raiz efetiva (nem do respetivo ramo do lixo).
        foreach ($bases as $base) {
            if ($parent === $base || str_starts_with($parent, $base.'/')) {
                return $parent;
            }
        }

        return $guard->root();
    }

    public function render()
    {
        return view('file-manager::livewire.file-manager');
    }
}
