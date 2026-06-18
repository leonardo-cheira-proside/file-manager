{{-- Menu de contexto (clique direito) --}}
<div x-show="menu.open" x-cloak @click.outside="menu.open = false"
    class="fixed z-[999] w-52 bg-white border border-gray-200 shadow-2xl rounded-xl py-1 text-sm text-gray-700"
    :style="`top:${menuY()}px; left:${menuX()}px`">

    <div class="px-4 py-2 border-b mb-1 border-gray-100 font-bold text-proximo-700 truncate text-xs"
        x-text="menu.files.length > 1 ? (menu.files.length + ' @lang('file-manager::file-manager.items_selected')') : (menu.file ? menu.file.name : '')">
    </div>

    @if ($this->inTrash)
        {{-- Ações do lixo: restaurar / eliminar definitivamente --}}
        <button type="button" @click="$wire.restore(targetPaths()); selected=[]; menu.open=false"
            class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
            <svg class="w-4 h-4 text-proximo-600" fill="none" stroke="currentColor" stroke-width="2"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v6h6M3 13a9 9 0 103-7.7L3 8" />
            </svg>
            <span>@lang('file-manager::file-manager.restore')</span>
        </button>
        <div class="border-t border-gray-100 my-1"></div>
        <button type="button" @click="$wire.deleteForever(targetPaths()); selected=[]; menu.open=false"
            class="w-full text-left px-4 py-2.5 text-red-600 hover:bg-proximo-50 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            <span>@lang('file-manager::file-manager.delete_forever')</span>
        </button>
    @else
        {{-- Selecionar todos (no fundo) --}}
        <template x-if="menu.file && menu.file.type === 'background'">
            <button type="button" @click="selectAllVisible()"
                class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                <svg class="w-4 h-4 text-proximo-600" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>@lang('file-manager::file-manager.select_all')</span>
            </button>
        </template>

        {{-- Escolher (picker) --}}
        <template x-if="picker && everySelectedIs(['image','video','other'])">
            <div>
                <button type="button" @click="choose()"
                    class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-proximo-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>@lang('file-manager::file-manager.choose')</span>
                </button>
                <div class="border-t border-gray-100 my-1"></div>
            </div>
        </template>

        {{-- Ações para item único --}}
        <template x-if="menu.files.length <= 1 && menu.file && menu.file.type !== 'background'">
            <div>
                <template x-if="menu.file.type === 'folder'">
                    <button type="button" @click="openModal({ action: 'add', type: 'folder', path: menu.file.path })"
                        class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                        <svg class="w-4 h-4 text-proximo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                        </svg>
                        <span>@lang('file-manager::file-manager.create_subfolder')</span>
                    </button>
                </template>
                <template x-if="menu.file.type === 'image' || menu.file.type === 'video'">
                    <button type="button" @click="preview(menu.file); menu.open=false"
                        class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                        <svg class="w-4 h-4 text-proximo-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span x-text="menu.file.type === 'video' ? '@lang('file-manager::file-manager.view_video')' : '@lang('file-manager::file-manager.view_image')'"></span>
                    </button>
                </template>
                <button type="button"
                    @click="openModal({ action: 'rename', file: menu.file, text: fmStripExt(menu.file) })"
                    class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-proximo-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>@lang('file-manager::file-manager.rename')</span>
                </button>
            </div>
        </template>

        {{-- Descarregar (ficheiros) --}}
        <template x-if="everySelectedIs(['image','video','other'])">
            <button type="button" @click="download()"
                class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                <svg class="w-4 h-4 text-proximo-600" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>@lang('file-manager::file-manager.download')</span>
            </button>
        </template>
        <template x-if="menu.files.length <= 1 && menu.file && menu.file.type !== 'background'">
            <button type="button" @click="openModal({ action: 'info', file: menu.file })"
                class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                <svg class="w-4 h-4 text-proximo-600" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                    viewBox="0 0 24 24">
                    <g data-name="info">
                        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"></path>
                        <circle cx="12" cy="8" r="1"></circle>
                        <path d="M12 10a1 1 0 0 0-1 1v5a1 1 0 0 0 2 0v-5a1 1 0 0 0-1-1z"></path>
                    </g>
                </svg>
                <span>@lang('file-manager::file-manager.info')</span>
            </button>
        </template>

        {{-- Eliminar --}}
        <template x-if="menu.file && menu.file.type !== 'background'">
            <div>
                <div class="border-t border-gray-100 my-1"></div>
                <button type="button" @click="openModal({ action: 'delete', file: menu.file })"
                    class="w-full text-left px-4 py-2.5 text-red-600 hover:bg-proximo-50 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>@lang('file-manager::file-manager.delete')</span>
                </button>
            </div>
        </template>
    @endif
</div>
