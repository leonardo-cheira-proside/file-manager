<div class="fm-root flex flex-col h-full w-full bg-gray-50 text-gray-800 select-none" x-data="fileManager({ picker: @js($pickerMode), multiple: @js($multiple), view: @js($viewMode) })"
    @keydown.escape.window="closeAll()">
    {{-- ===================== Toolbar ===================== --}}
    <div class="bg-proximo-800 p-2 flex justify-end items-center text-white z-20 shadow-md shrink-0">


        {{-- Pesquisa (filtra a vista atual no cliente) --}}
        <div class="relative max-w-[40vw]">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                </svg>
            </span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="@lang('file-manager::file-manager.search')"
                class="block w-full pl-10 py-1.5 rounded-md bg-white text-gray-900 text-sm focus:outline-none">
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        {{-- ===================== Sidebar (árvorea) ===================== --}}
        <div class="transition-all duration-300 ease-in-out border-r border-proximo-800 flex flex-col bg-white overflow-hidden shrink-0"
            :class="$wire.showTree ? 'w-72' : 'w-0'">
            <div class="bg-proximo-700 font-semibold text-white px-4 h-9 flex items-center shrink-0">
                <div class="flex items-center gap-3">
                    <button type="button" class="p-1 hover:bg-proximo-700 rounded transition-colors"
                        wire:click="$toggle('showTree')" title="@lang('file-manager::file-manager.directories')">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div> @lang('file-manager::file-manager.directories')
            </div>
            <div class="p-2 flex-1 overflow-y-auto fm-scroll">
                {{-- Raiz efetiva do utilizador --}}
                <div wire:click="open(@js($this->rootPath))" @dragover.prevent
                    @drop.prevent="onDropMove($event, @js($this->rootPath))"
                    class="flex items-center gap-2 px-2 py-1.5 mb-1 cursor-pointer rounded-md hover:bg-gray-100"
                    :class="$wire.path === @js($this->rootPath) ? 'bg-gray-100 text-proximo-700' : 'text-gray-700'">
                    <svg class="h-4 w-4 shrink-0 text-proximo-600" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    <span class="text-sm truncate">{{ $this->rootLabel }}</span>
                </div>

                <ul class="ml-2">
                    @foreach ($this->tree as $node)
                        @include('file-manager::livewire.partials.tree-node', ['node' => $node])
                    @endforeach
                </ul>

                <hr class="border-gray-200 my-2">

                {{-- Lixo "apagados" --}}
                <div wire:click="open('{{ config('file-manager.trash') }}')"
                    class="flex items-center gap-2 px-2 py-1.5 cursor-pointer rounded-md hover:bg-gray-100"
                    :class="$wire.path === '{{ config('file-manager.trash') }}' ? 'bg-gray-100 text-red-700' : 'text-gray-700'">
                    <svg class="h-4 w-4 shrink-0 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm truncate">{{ config('file-manager.trash') }}</span>
                </div>
            </div>
        </div>

        {{-- ===================== Conteúdo ===================== --}}
        <div class="flex-1 flex flex-col overflow-hidden">
            {{-- Barra: breadcrumbs + filtros --}}
            <div class="flex justify-between items-center bg-proximo-700 text-white h-9 px-4 shrink-0">
                <nav class="flex items-center gap-1 text-sm overflow-x-auto">
                    @foreach ($this->breadcrumbs as $i => $crumb)
                        @if ($i > 0)
                            <span class="opacity-40">/</span>
                        @endif
                        <button type="button" wire:click="open('{{ $crumb['path'] }}')"
                            class="hover:underline whitespace-nowrap {{ $i === count($this->breadcrumbs) - 1 ? 'font-semibold' : '' }}">
                            {{ $crumb['label'] }}
                        </button>
                    @endforeach
                </nav>
                <div class="flex gap-3 items-center ">
                    @unless ($lockFilter)
                        <div class="relative" @click.outside="filterOpen = false">
                            <button type="button" @click="filterOpen = !filterOpen"
                                class="flex items-center gap-2 bg-white/10 hover:bg-white/20 px-3 py-1 rounded-full text-xs border border-white/20">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 10.414V15a1 1 0 01-.553.894l-2 1A1 1 0 018 16v-5.586L3.293 6.707A1 1 0 013 6V3z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>@lang('file-manager::file-manager.filters')</span>
                            </button>
                            <div x-show="filterOpen" x-cloak x-transition
                                class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50 text-gray-700 text-sm">
                                <p class="px-4 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                    @lang('file-manager::file-manager.sort_by')</p>
                                @foreach (['az' => 'A–Z', 'za' => 'Z–A'] as $id => $label)
                                    <button type="button" wire:click="setFilter('{{ $id }}')"
                                        @click="filterOpen=false"
                                        class="w-full text-left px-4 py-2 hover:bg-proximo-50 {{ $filter === $id ? 'bg-gray-100 font-semibold text-proximo-900' : '' }}">{{ $label }}</button>
                                @endforeach
                                <div class="h-px bg-gray-100 my-1.5 mx-2"></div>
                                <p class="px-4 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                    @lang('file-manager::file-manager.filter_content')</p>
                                @foreach (['all' => __('file-manager::file-manager.all'), 'folders' => __('file-manager::file-manager.folders'), 'images' => __('file-manager::file-manager.images'), 'videos' => __('file-manager::file-manager.videos')] as $id => $label)
                                    <button type="button" wire:click="setFilter('{{ $id }}')"
                                        @click="filterOpen=false"
                                        class="w-full text-left px-4 py-2 hover:bg-proximo-50 {{ $filter === $id ? 'bg-gray-100 font-semibold text-proximo-900' : '' }}">{{ $label }}</button>
                                @endforeach
                                <div class="h-px bg-gray-100 my-1.5 mx-2"></div>
                                <p class="px-4 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                    @lang('file-manager::file-manager.view_mode')</p>
                                @foreach (['grid' => __('file-manager::file-manager.grid'), 'list' => __('file-manager::file-manager.list')] as $id => $label)
                                    <button type="button" @click="view='{{ $id }}'; filterOpen=false"
                                        class="w-full text-left px-4 py-2 hover:bg-proximo-50"
                                        :class="view === '{{ $id }}' ? 'bg-gray-100 font-semibold text-proximo-900' :
                                            ''">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endunless
                </div>

            </div>

            {{-- ===================== Barra de ações de seleção ===================== --}}
            {{-- Altura fixa reservada: a barra ocupa sempre o mesmo espaço, mesmo
                 sem seleção, para não empurrar os itens para baixo ao ativar. --}}
            <div class="flex items-center h-12 px-4 border-b shrink-0 transition-colors"
                :class="selected.length > 0 ? 'bg-proximo-50 border-proximo-200' : 'bg-gray-50 border-gray-100'">

                {{-- Sem seleção: dica discreta (mantém a altura) --}}
                <template x-if="selected.length === 0">
                   
                </template>

                {{-- Com seleção: contador + ações --}}
                <template x-if="selected.length > 0">
                    <div class="flex items-center justify-between gap-3 w-full">
                        <div class="flex items-center gap-2 text-sm text-proximo-900 shrink-0">
                            <button type="button" @click="selected = []"
                                aria-label="@lang('file-manager::file-manager.clear_selection')"
                                class="p-1 rounded-md hover:bg-proximo-100 text-proximo-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <span class="font-semibold whitespace-nowrap"><span x-text="selected.length"></span>
                                @lang('file-manager::file-manager.items_selected')</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if ($this->inTrash)
                                <button type="button" @click="$wire.restore([...selected]); selected = []"
                                    aria-label="@lang('file-manager::file-manager.restore')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v6h6M3 13a9 9 0 103-7.7L3 8" />
                                    </svg>
                                </button>
                                <button type="button" @click="$wire.deleteForever([...selected]); selected = []"
                                    aria-label="@lang('file-manager::file-manager.delete_forever')"
                                    class="fm-tip p-2 rounded-full bg-red-600 text-white hover:bg-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @else
                                {{-- Escolher (picker) --}}
                                <button type="button" x-show="picker && allSelectedAreFiles()" @click="chooseSelected()"
                                    aria-label="@lang('file-manager::file-manager.choose')"
                                    class="fm-tip p-2 rounded-full bg-proximo-600 text-white hover:bg-proximo-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                {{-- Visualizar (1 imagem/vídeo) --}}
                                <button type="button"
                                    x-show="selectedItem() && ['image','video'].includes(selectedItem().type)"
                                    @click="preview(selectedItem())"
                                    :aria-label="selectedItem() && selectedItem().type === 'video' ? '@lang('file-manager::file-manager.view_video')' : '@lang('file-manager::file-manager.view_image')'"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                                {{-- Criar subpasta (1 pasta) --}}
                                <button type="button" x-show="selectedItem() && selectedItem().type === 'folder'"
                                    @click="openModal({ action: 'add', type: 'folder', path: selectedItem().path })"
                                    aria-label="@lang('file-manager::file-manager.create_subfolder')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                    </svg>
                                </button>
                                {{-- Renomear (1 item) --}}
                                <button type="button" x-show="selectedItem()"
                                    @click="openModal({ action: 'rename', file: selectedItem(), text: fmStripExt(selectedItem()) })"
                                    aria-label="@lang('file-manager::file-manager.rename')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                {{-- Mover (qualquer seleção) --}}
                                <button type="button" @click="openMoveModal()"
                                    aria-label="@lang('file-manager::file-manager.move')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6m0-6l-2 2m2-2l2 2" />
                                    </svg>
                                </button>
                                {{-- Descarregar (apenas ficheiros) --}}
                                <button type="button" x-show="allSelectedAreFiles()" @click="downloadSelected()"
                                    aria-label="@lang('file-manager::file-manager.download')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </button>
                                {{-- Info (1 item) --}}
                                <button type="button" x-show="selectedItem()"
                                    @click="openModal({ action: 'info', file: selectedItem() })"
                                    aria-label="@lang('file-manager::file-manager.info')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z" />
                                        <circle cx="12" cy="8" r="1" />
                                        <path d="M12 10a1 1 0 0 0-1 1v5a1 1 0 0 0 2 0v-5a1 1 0 0 0-1-1z" />
                                    </svg>
                                </button>
                                {{-- Eliminar (qualquer seleção) --}}
                                <button type="button" @click="deleteSelected()"
                                    aria-label="@lang('file-manager::file-manager.delete')"
                                    class="fm-tip p-2 rounded-full bg-red-600 text-white hover:bg-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @endif

                            {{-- Mais opções: abre o menu de contexto da seleção --}}
                            <button type="button" @click="openSelectionMenu($event)"
                                aria-label="@lang('file-manager::file-manager.more_options')"
                                class="fm-tip fm-tip-end p-2 rounded-full text-proximo-700 hover:bg-proximo-100">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M6 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM11.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM17 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Área de ficheiros (drop de upload do SO) --}}
            <div class="relative flex-1 overflow-y-auto fm-scroll" wire:loading.class="opacity-60"
                @click.self="selected = []" @contextmenu.self.prevent="openBackgroundMenu($event)"
                @dragover.prevent="onDragOverUpload($event)" @dragleave="uploadHover = false"
                @drop.prevent="onDropUpload($event)" :class="uploadHover ? 'bg-proximo-100/50' : ''">

                @php $items = $this->files; @endphp

                {{-- Vazio --}}
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

                {{-- GRID --}}
                <div x-show="view === 'grid'" @contextmenu.self.prevent="openBackgroundMenu($event)"
                    class="p-6 grid grid-cols-[repeat(auto-fill,160px)] gap-5 justify-center content-start min-h-full">
                    @foreach ($items as $file)
                        @include('file-manager::livewire.partials.grid-item', ['file' => $file])
                    @endforeach
                </div>

                {{-- LISTA --}}
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
            </div>
        </div>
    </div>

    {{-- ===================== FAB (upload / nova pasta) ===================== --}}
    @unless ($this->inTrash)
        <div class="fixed bottom-10 right-10 flex flex-col items-center gap-3 z-40" @click.outside="fabOpen = false">
            <div x-show="fabOpen" x-transition class="flex flex-col items-center gap-3">
                <label
                    class="fm-tip fm-tip-left flex items-center justify-center w-12 h-12 bg-white text-proximo-700 rounded-full shadow-xl border border-gray-200 cursor-pointer hover:bg-gray-100"
                    aria-label="@lang('file-manager::file-manager.upload')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                    <input type="file" wire:model="uploads" multiple class="hidden">
                </label>
                <button type="button"
                    @click="$dispatch('fm-modal', { action: 'add', type: 'folder', path: $wire.path }); fabOpen=false"
                    class="fm-tip fm-tip-left flex items-center justify-center w-12 h-12 bg-white text-proximo-700 rounded-full shadow-xl border border-gray-200 hover:bg-gray-100"
                    aria-label="@lang('file-manager::file-manager.new_folder')">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>

                </button>
            </div>
            <button type="button" @click="fabOpen = !fabOpen"
                class="flex items-center justify-center w-14 h-14 text-white rounded-full shadow-2xl transition-all duration-300"
                :class="fabOpen ? 'rotate-45 bg-red-500' : 'bg-proximo-700'">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>

        {{-- Barra de progresso de upload --}}
        <div wire:loading wire:target="uploads"
            class="fixed bottom-28 right-10 z-40 w-40 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div class="h-full bg-proximo-500 animate-pulse w-full"></div>
        </div>
    @endunless

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
