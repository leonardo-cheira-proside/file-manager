<div class="fm-root relative flex flex-col h-full w-full bg-gray-50 text-gray-800 select-none" x-data="fileManager({ picker: @js($pickerMode), multiple: @js($multiple), view: @js($viewMode) })"
    @keydown.escape.window="closeAll()">
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

        /* Tooltip ao passar o rato — mostra o aria-label (que os leitores de ecrã
           também anunciam). Aplicado só aos botões com a classe .fm-tip. */
        .fm-tip {
            position: relative;
        }

        .fm-tip::after {
            content: attr(aria-label);
            position: absolute;
            top: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: #fff;
            font-size: 11px;
            line-height: 1.1;
            white-space: nowrap;
            padding: 5px 8px;
            border-radius: 6px;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .12s ease;
            z-index: 50;
        }

        .fm-tip:hover::after,
        .fm-tip:focus-visible::after {
            opacity: 1;
            visibility: visible;
        }

        /* Variante: tooltip alinhado pela direita (termina por baixo do botão). */
        .fm-tip-end::after {
            left: auto;
            right: 0;
            transform: none;
        }

        /* Variante: tooltip à esquerda do botão (ex.: FAB encostado à direita). */
        .fm-tip-left::after {
            top: 50%;
            left: auto;
            right: calc(100% + 8px);
            transform: translateY(-50%);
        }
    </style>
    {{-- ===================== Toolbar ===================== --}}
    <div class="bg-proximo-800 p-2 flex justify-between items-center text-white z-20 shadow-md shrink-0">

        <div class="flex items-center gap-3">
            <button type="button" class="p-1 hover:bg-proximo-700 rounded transition-colors"
                wire:click="$toggle('showTree')" title="@lang('file-manager::file-manager.directories')">
                <x-file-manager::icons.hamburguer-menu />
            </button>
        </div>
        {{-- Pesquisa (filtra a vista atual no cliente) --}}
        <div class="relative max-w-[40vw]">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <x-file-manager::icons.magnifying-glass />
            </span>

            <input type="text" wire:model.live.debounce.300ms="search" placeholder="@lang('file-manager::file-manager.search')"
                class="block w-full pl-10 py-1.5 rounded-md bg-white text-gray-900 text-sm focus:outline-proximo-500 focus:outline-0  focus:ring-proximo-500 focus:border-proximo-500">
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        {{-- ===================== Sidebar (árvorea) ===================== --}}
        <div class="transition-all duration-300 ease-in-out border-r border-proximo-800 flex flex-col bg-white overflow-hidden shrink-0"
            :class="$wire.showTree ? 'w-72' : 'w-0'">
            <div class="bg-proximo-700 font-semibold text-white px-4 h-9 flex items-center shrink-0">
                @lang('file-manager::file-manager.directories')
            </div>
            <div class="p-2 flex-1 overflow-y-auto fm-scroll">
                {{-- Raízes efetivas do utilizador (uma com acesso total, várias se confinado) --}}
                @foreach ($this->roots as $root)
                    <div wire:key="root-{{ $root['path'] }}">
                        <div wire:click="open(@js($root['path']))" @dragover.prevent
                            @drop.prevent="onDropMove($event, @js($root['path']))"
                            class="flex items-center gap-2 px-2 py-1.5 mb-1 cursor-pointer rounded-md hover:bg-gray-100"
                            :class="$wire.path === @js($root['path']) ? 'bg-gray-100 text-proximo-700' :
                                'text-gray-700'">
                            <svg class="h-4 w-4 shrink-0 text-proximo-600" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                            </svg>
                            <span class="text-sm truncate">{{ $root['label'] }}</span>
                        </div>

                        <ul class="ml-2 mb-1">
                            @foreach ($root['tree'] as $node)
                                @include('file-manager::livewire.partials.tree-node', ['node' => $node])
                            @endforeach
                        </ul>
                    </div>
                @endforeach

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
                                <x-file-manager::icons.hopper />
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
                                @foreach (['all' => __('file-manager::file-manager.all'), 'folders' => __('file-manager::file-manager.folders'), 'images' => __('file-manager::file-manager.images'), 'videos' => __('file-manager::file-manager.videos'), 'no-folder' => __('file-manager::file-manager.no-folder')] as $id => $label)
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
                            <button type="button" @click="selected = []" aria-label="@lang('file-manager::file-manager.clear_selection')"
                                class="p-1 rounded-md hover:bg-proximo-100 text-proximo-700">
                                <x-file-manager::icons.cross />

                            </button>
                            <span class="font-semibold whitespace-nowrap"><span x-text="selected.length"></span>
                                @lang('file-manager::file-manager.items_selected')</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if ($this->inTrash)
                                <button type="button" @click="$wire.restore([...selected]); selected = []"
                                    aria-label="@lang('file-manager::file-manager.restore')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <x-file-manager::icons.restore />
                                </button>
                                <button type="button" @click="$wire.deleteForever([...selected]); selected = []"
                                    aria-label="@lang('file-manager::file-manager.delete_forever')"
                                    class="fm-tip p-2 rounded-full bg-red-600 text-white hover:bg-red-700">
                                    <x-file-manager::icons.delete-fill />
                                </button>
                            @else
                                {{-- Escolher (picker) --}}
                                <button type="button" x-show="picker && allSelectedAreFiles()"
                                    @click="chooseSelected()" aria-label="@lang('file-manager::file-manager.choose')"
                                    class="fm-tip p-2 rounded-full bg-proximo-600 text-white hover:bg-proximo-700">
                                    <x-file-manager::icons.tick />
                                </button>
                                {{-- Visualizar (1 imagem/vídeo) --}}
                                <button type="button"
                                    x-show="selectedItem() && ['image','video'].includes(selectedItem().type)"
                                    @click="preview(selectedItem())"
                                    :aria-label="selectedItem() && selectedItem().type === 'video' ? '@lang('file-manager::file-manager.view_video')' :
                                        '@lang('file-manager::file-manager.view_image')'"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <x-file-manager::icons.eye-icon />
                                </button>
                                {{-- Criar subpasta (1 pasta) --}}
                                <button type="button" x-show="selectedItem() && selectedItem().type === 'folder'"
                                    @click="openModal({ action: 'add', type: 'folder', path: selectedItem().path })"
                                    aria-label="@lang('file-manager::file-manager.create_subfolder')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <x-file-manager::icons.add-folder />

                                </button>
                                {{-- Renomear (1 item) --}}
                                <button type="button" x-show="selectedItem()"
                                    @click="openModal({ action: 'rename', file: selectedItem(), text: fmStripExt(selectedItem()) })"
                                    aria-label="@lang('file-manager::file-manager.rename')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <x-file-manager::icons.rename />

                                </button>
                                {{-- Mover (qualquer seleção) --}}
                                <button type="button" @click="openMoveModal()" aria-label="@lang('file-manager::file-manager.move')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <x-file-manager::icons.move-folder />
                                </button>
                                {{-- Descarregar (apenas ficheiros) --}}
                                <button type="button" x-show="allSelectedAreFiles()" @click="downloadSelected()"
                                    aria-label="@lang('file-manager::file-manager.download')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <x-file-manager::icons.download />
                                </button>
                                {{-- Info (1 item) --}}
                                <button type="button" x-show="selectedItem()"
                                    @click="openModal({ action: 'info', file: selectedItem() })"
                                    aria-label="@lang('file-manager::file-manager.info')"
                                    class="fm-tip p-2 rounded-full bg-white border border-proximo-200 text-proximo-700 hover:bg-proximo-100">
                                    <x-file-manager::icons.info />
                                </button>
                                {{-- Eliminar (qualquer seleção) --}}
                                <button type="button" @click="deleteSelected()" aria-label="@lang('file-manager::file-manager.delete')"
                                    class="fm-tip p-2 rounded-full bg-red-600 text-white hover:bg-red-700">
                                    <x-file-manager::icons.delete />
                                </button>
                            @endif

                            {{-- Mais opções: abre o menu de contexto da seleção --}}
                            <button type="button" @click="openSelectionMenu($event)" aria-label="@lang('file-manager::file-manager.more_options')"
                                class="fm-tip fm-tip-end p-2 rounded-full text-proximo-700 hover:bg-proximo-100">
                                <x-file-manager::icons.ellipsis/>
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
                @if (count($items) > 0)
                <div x-show="view === 'grid'" @contextmenu.self.prevent="openBackgroundMenu($event)"
                    class="grid pt-2 grid-cols-[repeat(auto-fill,160px)] gap-2 justify-center content-start min-h-full">
                    @foreach ($items as $file)
                        @include('file-manager::livewire.partials.grid-item', ['file' => $file])
                    @endforeach
                </div>
                @endif

                {{-- LISTA --}}
                @if (count($items) > 0)
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
            </div>
        </div>
    </div>

    {{-- ===================== FAB (upload / nova pasta) ===================== --}}
    @unless ($this->inTrash)
        <div class="absolute bottom-10 right-10 flex flex-col items-center gap-3 z-40" @click.outside="fabOpen = false">
            <div x-show="fabOpen" x-transition class="flex flex-col items-center gap-3">
                <label
                    class="fm-tip fm-tip-left flex items-center justify-center w-12 h-12 bg-white text-proximo-700 rounded-full shadow-xl border border-gray-200 cursor-pointer hover:bg-gray-100"
                    aria-label="@lang('file-manager::file-manager.upload')">
                <x-file-manager::icons.arrow-up/>
                    <input type="file" wire:model="uploads" multiple class="hidden">
                </label>
                <button type="button"
                    @click="$dispatch('fm-modal', { action: 'add', type: 'folder', path: $wire.path }); fabOpen=false"
                    class="fm-tip fm-tip-left flex items-center justify-center w-12 h-12 bg-white text-proximo-700 rounded-full shadow-xl border border-gray-200 hover:bg-gray-100"
                    aria-label="@lang('file-manager::file-manager.new_folder')">
                    <x-file-manager::icons.add-folder class="h-6 w-6" />

                </button>
            </div>
            <button type="button" @click="fabOpen = !fabOpen"
                class="flex items-center justify-center w-14 h-14 text-white rounded-full shadow-2xl transition-all duration-300"
                :class="fabOpen ? ' bg-red-500' : 'rotate-45 bg-proximo-700'">
                <x-file-manager::icons.cross class="h-8 w-8"/>
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
