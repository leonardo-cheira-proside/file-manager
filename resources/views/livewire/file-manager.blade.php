<div class="fm-root relative flex flex-col h-full w-full bg-gray-50 text-gray-800 select-none" x-data="fileManager({ picker: @js($pickerMode), multiple: @js($multiple), view: @js($viewMode) })"
    @keydown.escape.window="closeAll()" @keydown.window="onShortcut($event)">
    {{-- Estilos do componente inline: garantem que carregam em QUALQUER contexto
         (página inteira, embebido ou dentro do modal do picker), sem depender de
         `@assets` nem de `vendor:publish`. Sobretudo o [x-cloak], que esconde os
         overlays (lightbox/move-modal) até o Alpine ligar. --}}
    <style>
        [x-cloak] {
            display: none !important;
        }

        .fm-root {
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        /* Scrollbar discreta nas áreas roláveis */
        .fm-scroll {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        .fm-scroll::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .fm-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }

        .fm-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        /* Evita seleção de texto durante drag */
        .fm-root [draggable="true"] {
            -webkit-user-drag: element;
        }

        /* Checkbox de seleção usa a cor da marca (independente do plugin forms) */
        .fm-check {
            accent-color: currentColor;
        }
    </style>

    @php
        $crumbs = $this->breadcrumbs;
        $parentPath = count($crumbs) > 1 ? $crumbs[count($crumbs) - 2]['path'] : ($crumbs[0]['path'] ?? $this->rootPath);
    @endphp

    <div class="flex flex-1 overflow-hidden h-full"
        @fm-navigated.window="onNav($event.detail.path)"
        x-data="{
            sbW: 320,
            lastW: 320,
            dragging: false,
            hist: [],
            hi: -1,
            viaHistory: false,
            init() {
                const saved = parseInt(localStorage.getItem('fmSidebarW'));
                if (!isNaN(saved)) this.sbW = saved;
                if (this.sbW > 0) this.lastW = this.sbW;
                this.$watch('sbW', v => localStorage.setItem('fmSidebarW', v));
                // Semente do histórico com a pasta atual.
                this.hist = [this.$wire.path];
                this.hi = 0;
            },
            onNav(p) {
                if (p === undefined || p === null) return;
                if (this.viaHistory) { this.viaHistory = false; return; }
                if (this.hist[this.hi] === p) return;
                this.hist = this.hist.slice(0, this.hi + 1);
                this.hist.push(p);
                this.hi = this.hist.length - 1;
            },
            canBack() { return this.hi > 0; },
            canFwd() { return this.hi < this.hist.length - 1; },
            goBack() { if (this.canBack()) { this.viaHistory = true; this.hi--; this.$wire.open(this.hist[this.hi]); } },
            goFwd() { if (this.canFwd()) { this.viaHistory = true; this.hi++; this.$wire.open(this.hist[this.hi]); } },
            copyPath() {
                const p = this.$wire.path || '';
                (navigator.clipboard ? navigator.clipboard.writeText(p) : Promise.reject())
                    .then(() => { this.pathCopied = true; setTimeout(() => this.pathCopied = false, 1200); })
                    .catch(() => {});
            },
            pathCopied: false,
            toggle() {
                if (this.sbW > 0) { this.lastW = this.sbW; this.sbW = 0; }
                else { this.sbW = this.lastW || 320; }
            },
            startDrag(e) {
                this.dragging = true;
                const startX = e.clientX;
                const startW = this.sbW;
                const move = (ev) => {
                    let w = Math.min(560, Math.max(0, startW + (ev.clientX - startX)));
                    if (Math.abs(w - 320) <= 18) w = 320; // snap aos 320
                    this.sbW = w;
                    if (w > 0) this.lastW = w;
                };
                const up = () => {
                    this.dragging = false;
                    window.removeEventListener('mousemove', move);
                    window.removeEventListener('mouseup', up);
                    document.body.style.userSelect = '';
                };
                window.addEventListener('mousemove', move);
                window.addEventListener('mouseup', up);
                document.body.style.userSelect = 'none';
            }
        }">
        {{-- ===================== Sidebar (árvore) ===================== --}}
        <nav :style="`width:${sbW}px`"
            class="border-gray-200/50 bg-white shrink-0 h-full overflow-y-auto overflow-x-hidden fm-scroll"
            :class="sbW > 0 ? 'p-3' : 'p-0'">
            @foreach ($this->roots as $root)
                <div wire:key="root-{{ $root['path'] }}" class="mb-1">
                    <div wire:click="open(@js($root['path']))" @dragover.prevent
                        @drop.prevent="onDropMove($event, @js($root['path']))"
                        class="flex items-center gap-2 px-2 py-1.5 cursor-pointer rounded-md hover:bg-gray-100 text-sm"
                        :class="$wire.path === @js($root['path']) ? 'bg-gray-100 text-proximo-700 font-medium' : 'text-gray-700'">
                        <x-heroicon-s-home-modern class="h-4 w-4 text-proximo-800 shrink-0" />
                        <span class="truncate">{{ $root['label'] }}</span>
                    </div>
                    <ul class="ml-2">
                        @foreach ($root['tree'] as $node)
                            @include('file-manager::livewire.partials.tree-node', ['node' => $node])
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <hr class="border-gray-200 my-2">

            {{-- Lixo --}}
            <div wire:click="open('{{ config('file-manager.trash') }}')"
                class="flex items-center gap-2 px-2 py-1.5 cursor-pointer rounded-md hover:bg-gray-100 text-sm"
                :class="$wire.path === '{{ config('file-manager.trash') }}' ? 'bg-gray-100 text-red-700' : 'text-gray-700'">
                <x-file-manager::icons.delete class="h-4 w-4 text-red-600 shrink-0" />
                <span class="truncate">{{ config('file-manager.trash') }}</span>
            </div>
        </nav>

        {{-- Handle de redimensionar a sidebar (fica sempre, mesmo com a sidebar a 0) --}}
        <div @mousedown.prevent="startDrag($event)" @dblclick="toggle()"
            class="group relative w-1 shrink-0 cursor-col-resize flex items-stretch justify-center">
            {{-- linha fina --}}
            <div class="w-px bg-gray-200 group-hover:bg-proximo-400 transition-colors"
                :class="dragging ? 'bg-proximo-500' : ''"></div>
            {{-- pega: pontos ao centro (indica que é arrastável) --}}
            <div class="absolute top-1/2 -translate-y-1/2 flex flex-col items-center gap-[3px] rounded-full border border-gray-200 bg-white px-[3px] py-1.5 text-gray-400 shadow-sm group-hover:border-proximo-300 group-hover:text-proximo-500"
                :class="dragging ? 'border-proximo-400 text-proximo-600' : ''">
                <span class="h-[3px] w-[3px] rounded-full bg-current"></span>
                <span class="h-[3px] w-[3px] rounded-full bg-current"></span>
                <span class="h-[3px] w-[3px] rounded-full bg-current"></span>
            </div>
        </div>

        {{-- ===================== Conteúdo ===================== --}}
        <div class="flex-1 flex flex-col h-full min-w-0 overflow-hidden">
            {{-- Header: navegação + breadcrumb + pesquisa --}}
            <header class="shadow-[inset_1px_-39px_70px_-49px_rgba(0,0,0,0.15)] py-3 w-full px-3 border-b border-gray-200 gap-4 flex shrink-0">
                <div class="flex">
                    <button type="button" @click="goBack()" :disabled="!canBack()"
                        class="h-8 w-8 bg-gray-50 shadow-[inset_1px_-39px_70px_-49px_rgba(0,0,0,0.11)] items-center justify-center flex border border-gray-200 hover:bg-gray-100 disabled:opacity-40 disabled:pointer-events-none">
                        <x-heroicon-s-chevron-left class="text-gray-500 h-4 w-4" />
                    </button>
                    <button type="button" @click="goFwd()" :disabled="!canFwd()"
                        class="h-8 w-8 bg-gray-50 shadow-[inset_1px_-39px_70px_-49px_rgba(0,0,0,0.11)] items-center justify-center flex border border-gray-200 hover:bg-gray-100 disabled:opacity-40 disabled:pointer-events-none">
                        <x-heroicon-s-chevron-right class="text-gray-500 h-4 w-4" />
                    </button>
                </div>

                <div class="flex h-8 text-gray-600 items-center w-full min-w-0 ">
                    <button type="button" wire:click="open(@js($this->rootPath))"
                        class="bg-gray-50 shadow-[inset_1px_-39px_70px_-49px_rgba(0,0,0,0.11)] flex items-center justify-center w-8 h-8 shrink-0 border hover:bg-gray-100">
                        <x-heroicon-s-home-modern class="h-4 w-4 text-proximo-800" />
                    </button>
                    <div class="w-full h-8 flex bg-white border-y items-center overflow-x-auto fm-scroll">
                        @foreach ($crumbs as $i => $crumb)
                            @if ($i > 0)
                                <div class="h-full text-gray-200 shrink-0">
                                    <svg class="h-full" width="8" viewBox="0 0 12 40" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                                        <path d="M0 0L12 20L0 40" stroke="currentColor" stroke-width="1"
                                            vector-effect="non-scaling-stroke" />
                                    </svg>
                                </div>
                            @endif
                            <button type="button" wire:click="open('{{ $crumb['path'] }}')"
                                class="px-2 shrink-0 whitespace-nowrap hover:text-proximo-700 {{ $i === count($crumbs) - 1 ? 'font-semibold text-gray-800' : '' }}">
                                {{ $crumb['label'] }}
                            </button>
                        @endforeach
                    </div>
                    <button type="button" @click="copyPath()"
                        class="bg-gray-100 flex items-center justify-center w-8 h-8 shrink-0 border hover:bg-gray-200"
                        title="@lang('file-manager::file-manager.copy_path')">
                        <template x-if="!pathCopied">
                            <x-heroicon-s-clipboard-document class="h-4 w-4 text-gray-500" />
                        </template>
                        <template x-if="pathCopied">
                            <x-heroicon-s-check class="h-4 w-4 text-green-600" />
                        </template>
                    </button>
                </div>

                <div class="flex">
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="@lang('file-manager::file-manager.search')"
                        class="bg-white border border-gray-200 min-w-[140px] h-8 px-2 text-sm text-gray-700 outline-none focus:outline-none focus:ring-0 focus:border-gray-200">
                    <div class="bg-gray-100 flex items-center justify-center w-8 h-8 shrink-0 border">
                        <x-heroicon-s-magnifying-glass class="h-4 w-4 text-gray-500" />
                    </div>
                </div>
            </header>

            {{-- Toolbar --}}
            @php
                $tbBtn = 'flex border w-fit h-8 items-center justify-center px-2 gap-1 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:pointer-events-none whitespace-nowrap';
                $menuItem = 'w-full text-left px-4 py-2 hover:bg-proximo-50 flex items-center gap-2 text-sm';
            @endphp
            <div class="bg-white p-2 shadow-md flex justify-between shrink-0 border-y border-gray-200">
                <div class="flex items-center">
                    @unless ($this->inTrash)
                        <button type="button"
                            @click="$dispatch('fm-modal', { action: 'add', type: 'folder', path: $wire.path })"
                            class="{{ $tbBtn }}">
                            <x-heroicon-o-folder class="text-gray-400 h-4 w-4" /> @lang('file-manager::file-manager.new_folder')
                        </button>
                        <label class="{{ $tbBtn }} cursor-pointer">
                            <x-heroicon-s-cloud-arrow-up class="text-gray-400 h-4 w-4" /> @lang('file-manager::file-manager.upload')
                            <input type="file" wire:model="uploads" multiple class="hidden">
                        </label>
                    @endunless

                    {{-- Ações sobre a seleção (as mesmas do menu de contexto). --}}
                    <div class="w-px h-6 bg-gray-200 mx-1" x-show="selected.length > 0" x-cloak></div>

                    @if ($this->inTrash)
                        <button type="button" x-show="selected.length > 0" x-cloak
                            @click="$wire.restore([...selected]); selected = []" class="{{ $tbBtn }}">
                            <x-file-manager::icons.restore class="h-4 w-4 text-proximo-600" /> @lang('file-manager::file-manager.restore')
                        </button>
                        <button type="button" x-show="selected.length > 0" x-cloak
                            @click="$wire.deleteForever([...selected]); selected = []"
                            class="{{ $tbBtn }} text-red-600">
                            <x-file-manager::icons.delete-fill /> @lang('file-manager::file-manager.delete_forever')
                        </button>
                    @else
                        {{-- No máximo 2 ações inline (aplicam-se sempre a qualquer seleção); o resto no "Ver mais". --}}
                        <button type="button" x-show="selected.length > 0" x-cloak @click="openMoveModal()"
                            class="{{ $tbBtn }}">
                            <x-file-manager::icons.move-folder class="h-4 w-4 text-proximo-600" /> @lang('file-manager::file-manager.move')
                        </button>
                        <button type="button" x-show="selected.length > 0" x-cloak @click="deleteSelected()"
                            class="{{ $tbBtn }} text-red-600">
                            <x-file-manager::icons.delete /> @lang('file-manager::file-manager.delete')
                        </button>

                        {{-- Ver mais : restantes features do menu de contexto --}}
                        <div class="relative" x-data="{ moreOpen: false }" x-show="selected.length > 0" x-cloak
                            @click.outside="moreOpen = false">
                            <button type="button" @click="moreOpen = !moreOpen" class="{{ $tbBtn }}">
                                <x-heroicon-o-ellipsis-horizontal class="text-gray-400 h-4 w-4" />
                                @lang('file-manager::file-manager.more_options') ▾
                            </button>
                            <div x-show="moreOpen" x-cloak x-transition
                                class="absolute left-0 mt-1 w-56 bg-white border border-gray-200 shadow-xl rounded-xl py-1 z-50 text-gray-700">
                                <button type="button" x-show="picker && allSelectedAreFiles()"
                                    @click="chooseSelected(); moreOpen=false" class="{{ $menuItem }}">
                                    <x-file-manager::icons.tick class="h-4 w-4 text-proximo-600" />
                                    @lang('file-manager::file-manager.choose')
                                </button>
                                <button type="button" x-show="selectedItem() && ['image','video'].includes(selectedItem().type)"
                                    @click="preview(selectedItem()); moreOpen=false" class="{{ $menuItem }}">
                                    <x-file-manager::icons.eye-icon class="h-4 w-4 text-proximo-600" />
                                    <span x-text="selectedItem() && selectedItem().type === 'video' ? '@lang('file-manager::file-manager.view_video')' : '@lang('file-manager::file-manager.view_image')'"></span>
                                </button>
                                <button type="button" x-show="selectedItem()"
                                    @click="openModal({ action: 'rename', file: selectedItem(), text: fmStripExt(selectedItem()) }); moreOpen=false"
                                    class="{{ $menuItem }}">
                                    <x-file-manager::icons.rename class="h-4 w-4 text-proximo-600" />
                                    @lang('file-manager::file-manager.rename')
                                </button>
                                <button type="button" @click="openCopyModal(); moreOpen=false" class="{{ $menuItem }}">
                                    <x-heroicon-o-document-duplicate class="h-4 w-4 text-proximo-600" />
                                    @lang('file-manager::file-manager.copy_to')
                                </button>
                                <button type="button" x-show="allSelectedAreFiles()"
                                    @click="downloadSelected(); moreOpen=false" class="{{ $menuItem }}">
                                    <x-file-manager::icons.download class="h-4 w-4 text-proximo-600" />
                                    @lang('file-manager::file-manager.download')
                                </button>
                                <button type="button" x-show="selectedItem() && selectedItem().type === 'folder'"
                                    @click="openModal({ action: 'add', type: 'folder', path: selectedItem().path }); moreOpen=false"
                                    class="{{ $menuItem }}">
                                    <x-file-manager::icons.add-folder class="h-4 w-4 text-proximo-600" />
                                    @lang('file-manager::file-manager.create_subfolder')
                                </button>
                                <button type="button" x-show="selectedItem()"
                                    @click="openModal({ action: 'info', file: selectedItem() }); moreOpen=false"
                                    class="{{ $menuItem }}">
                                    <x-file-manager::icons.info class="h-4 w-4 text-proximo-600" />
                                    @lang('file-manager::file-manager.info')
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex">
                    <button type="button" @click="view = 'grid'"
                        class="flex border w-fit h-8 items-center justify-center px-2 gap-1 hover:bg-gray-50"
                        :class="view === 'grid' ? 'bg-proximo-50 text-proximo-700 border-proximo-300' : 'text-gray-400'">
                        <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                    </button>
                    <button type="button" @click="view = 'list'"
                        class="flex border w-fit h-8 items-center justify-center px-2 gap-1 hover:bg-gray-50"
                        :class="view === 'list' ? 'bg-proximo-50 text-proximo-700 border-proximo-300' : 'text-gray-400'">
                        <x-heroicon-o-list-bullet class="h-4 w-4" />
                    </button>
                </div>
            </div>

            {{-- ===================== Itens (única zona rolável) ===================== --}}
            <main class="relative flex-1 overflow-y-auto fm-scroll px-2 bg-white" wire:loading.class="opacity-60"
                @click.self="selected = []" @contextmenu.self.prevent="openBackgroundMenu($event)"
                @dragover.prevent="onDragOverUpload($event)" @dragleave="uploadHover = false"
                @drop.prevent="onDropUpload($event)" :class="uploadHover ? 'bg-proximo-100/50' : ''">

                @php $items = $this->files; @endphp

                @if (count($items) === 0)
                    <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                        <svg class="h-16 w-16 opacity-20 mb-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                        </svg>
                        <p class="text-sm">
                            {{ $search !== '' ? __('file-manager::file-manager.no_results') : __('file-manager::file-manager.empty') }}
                        </p>
                    </div>
                @endif

                @if (count($items) > 0)
                    <div x-show="view === 'grid'" x-cloak @contextmenu.self.prevent="openBackgroundMenu($event)"
                        class="grid pt-2 grid-cols-[repeat(auto-fill,160px)] gap-2 justify-center content-start">
                        @foreach ($items as $file)
                            @include('file-manager::livewire.partials.grid-item', ['file' => $file])
                        @endforeach
                    </div>

                    <table x-show="view === 'list'" x-cloak class="w-full text-left text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10 text-[11px] uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-3 py-2.5 w-10 text-center" @click.stop>
                                    <input type="checkbox" :checked="allChecked()" @change="toggleCheckAll()"
                                        class="fm-check h-4 w-4 rounded border-gray-300 text-proximo-600 cursor-pointer align-middle">
                                </th>
                                <th class="px-4 py-2.5">@lang('file-manager::file-manager.name')</th>
                                <th class="px-3 py-2.5 text-center w-24">@lang('file-manager::file-manager.size')</th>
                                <th class="px-3 py-2.5 text-center w-28">@lang('file-manager::file-manager.type')</th>
                                <th class="px-4 py-2.5 text-right w-40">@lang('file-manager::file-manager.modified')</th>
                                @if ($this->inTrash)
                                    <th class="px-4 py-2.5 text-right w-32">@lang('file-manager::file-manager.time_left')</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($items as $file)
                                @include('file-manager::livewire.partials.list-item', ['file' => $file])
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </main>
        </div>
    </div>

    {{-- Barra de progresso de upload --}}
    <div wire:loading wire:target="uploads"
        class="fixed bottom-6 right-6 z-40 w-40 h-2 bg-gray-200 rounded-full overflow-hidden">
        <div class="h-full bg-proximo-500 animate-pulse w-full"></div>
    </div>

    {{-- ===================== Menu de contexto ===================== --}}
    @include('file-manager::livewire.partials.context-menu')

    {{-- ===================== Modal (criar/renomear/eliminar) ===================== --}}
    @include('file-manager::livewire.partials.modal')

    {{-- ===================== Modal (mover para…) ===================== --}}
    @include('file-manager::livewire.partials.move-modal')

    {{-- ===================== Lightbox ===================== --}}
    @include('file-manager::livewire.partials.lightbox')

    {{-- Regista o componente Alpine uma única vez (Livewire @assets). --}}
    @include('file-manager::livewire.partials.script')
</div>
