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

    /** Filtro/ordenação: all|folders|images|videos|az|za */
    public string $filter = 'all';

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
    public function files(): array
    {
        $service = $this->service();
        $entries = $service->listing($this->path, $this->filter);

        if ($this->search !== '') {
            $needle = mb_strtolower(trim($this->search));
            $entries = array_values(array_filter(
                $entries,
                fn ($e) => str_contains(mb_strtolower($e['name']), $needle)
            ));
        }

        return array_map(function (array $entry) use ($service) {
            $entry['url'] = in_array($entry['type'], ['image', 'video', 'other'], true)
                ? $service->mediaUrl($entry['path'])
                : null;

            return $entry;
        }, $entries);
    }

    #[Computed]
    public function tree(): array
    {
        return $this->service()->tree(null, $this->openFolders);
    }

    #[Computed]
    public function breadcrumbs(): array
    {
        // Breadcrumbs a partir da raiz efetiva (não expõe segmentos acima dela).
        $rootDepth = count(explode('/', $this->service()->root()));
        $segments = explode('/', $this->path);
        $crumbs = [];
        $acc = [];
        foreach ($segments as $i => $segment) {
            $acc[] = $segment;
            if ($i < $rootDepth - 1) {
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
        $this->path = $this->service()->guard()->normalize($path);
        $this->selected = [];
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
        $newPath = $this->service()->createFolder($parent, $name);

        // Expande o pai e a nova pasta na árvore.
        $this->openFolders = array_values(array_unique([...$this->openFolders, $parent, $newPath]));
        $this->refreshData();
    }

    public function rename(string $path, string $newName): void
    {
        if ($this->inTrash) {
            return;
        }
        $this->service()->rename($path, $newName);
        $this->refreshData();
    }

    public function delete(array|string $paths): void
    {
        $paths = (array) $paths;
        $this->service()->trash($paths);

        // Se a pasta atual foi para o lixo, sobe um nível.
        if (in_array($this->path, $paths, true)) {
            $this->path = $this->parentPath($this->path);
        }
        $this->selected = [];
        $this->refreshData();
    }

    public function restore(array|string $paths): void
    {
        $this->service()->restore((array) $paths);
        $this->selected = [];
        $this->refreshData();
    }

    public function deleteForever(array|string $paths): void
    {
        $this->service()->deleteForever((array) $paths);
        $this->selected = [];
        $this->refreshData();
    }

    public function moveItems(array $from, string $to): void
    {
        if ($this->inTrash) {
            return;
        }
        $this->service()->move($from, $to);
        $this->selected = [];
        $this->refreshData();
    }

    /** Lifecycle hook: dispara quando o upload (wire:model) termina. */
    public function updatedUploads(): void
    {
        if ($this->inTrash) {
            $this->uploads = [];

            return;
        }

        $rules = ['uploads.*' => 'file|max:'.(int) config('file-manager.uploads.max_size')];
        if ($mimes = config('file-manager.uploads.mimes')) {
            $rules['uploads.*'] .= '|mimes:'.implode(',', (array) $mimes);
        }
        $this->validate($rules);

        foreach ($this->uploads as $file) {
            $this->service()->upload($file, $this->path);
        }
        $this->uploads = [];
        $this->refreshData();
        $this->dispatch('file-manager-uploaded');
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

    protected function refreshData(): void
    {
        unset($this->files, $this->tree, $this->breadcrumbs, $this->inTrash);
    }

    protected function parentPath(string $path): string
    {
        $root = $this->service()->root();
        $parent = str_replace('\\', '/', dirname($path));
        $parent = $parent === '.' ? '' : $parent;

        // Nunca subir acima da raiz efetiva.
        if ($parent === '' || ! ($parent === $root || str_starts_with($parent, $root.'/'))) {
            return $root;
        }

        return $parent;
    }

    public function render()
    {
        return view('file-manager::livewire.file-manager');
    }
}
