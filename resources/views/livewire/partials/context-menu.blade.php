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
            class="w-full text-left px-4 py-2.5 hover:bg-proximo-50  flex items-center gap-2">
            <x-file-manager::icons.restore class="h-4 w-4 text-proximo-600" />
            <span>@lang('file-manager::file-manager.restore')</span>
        </button>
        <div class="border-t border-gray-100 my-1"></div>
        <button type="button" @click="$wire.deleteForever(targetPaths()); selected=[]; menu.open=false"
            class="w-full text-left px-4 py-2.5 text-red-600 hover:bg-proximo-50 flex items-center gap-2">
            <x-file-manager::icons.delete-fill />
            <span>@lang('file-manager::file-manager.delete_forever')</span>
        </button>
    @else
        {{-- Selecionar todos (no fundo) --}}
        <template x-if="menu.file && menu.file.type === 'background'">
            <button type="button" @click="selectAllVisible()"
                class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2 ">
                <x-file-manager::icons.tick-circle class="h-4 w-4 text-proximo-600" />
                <span>@lang('file-manager::file-manager.select_all')</span>
            </button>
        </template>

        {{-- Escolher (picker) --}}
        <template x-if="picker && everySelectedIs(['image','video','other'])">
            <div>
                <button type="button" @click="choose()"
                    class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                    <x-file-manager::icons.tick class="h-4 w-4 text-proximo-600" />

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
                        <x-file-manager::icons.add-folder class="h-4 w-4 text-proximo-600" />
                        <span>@lang('file-manager::file-manager.create_subfolder')</span>
                    </button>
                </template>
                <template x-if="menu.file.type === 'image' || menu.file.type === 'video'">
                    <button type="button" @click="preview(menu.file); menu.open=false"
                        class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                        <x-file-manager::icons.eye-icon class="h-4 w-4 text-proximo-600" />
                        <span x-text="menu.file.type === 'video' ? '@lang('file-manager::file-manager.view_video')' : '@lang('file-manager::file-manager.view_image')'"></span>
                    </button>
                </template>
                <button type="button"
                    @click="openModal({ action: 'rename', file: menu.file, text: fmStripExt(menu.file) })"
                    class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                    <x-file-manager::icons.rename class="h-4 w-4 text-proximo-600" />

                    <span>@lang('file-manager::file-manager.rename')</span>
                </button>
            </div>
        </template>

        {{-- Descarregar (ficheiros) --}}
        <template x-if="everySelectedIs(['image','video','other'])">
            <button type="button" @click="download()"
                class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                <x-file-manager::icons.download class="h-4 w-4 text-proximo-600"/>
                <span>@lang('file-manager::file-manager.download')</span>
            </button>
        </template>
        <template x-if="menu.files.length <= 1 && menu.file && menu.file.type !== 'background'">
            <button type="button" @click="openModal({ action: 'info', file: menu.file })"
                class="w-full text-left px-4 py-2.5 hover:bg-proximo-50 flex items-center gap-2">
                <x-file-manager::icons.info class="h-4 w-4 text-proximo-600"/>

                <span>@lang('file-manager::file-manager.info')</span>
            </button>
        </template>

        {{-- Eliminar --}}
        <template x-if="menu.file && menu.file.type !== 'background'">
            <div>
                <div class="border-t border-gray-100 my-1"></div>
                <button type="button" @click="openModal({ action: 'delete', file: menu.file })"
                    class="w-full text-left px-4 py-2.5 text-red-600 hover:bg-proximo-50 flex items-center gap-2">
                    <x-file-manager::icons.delete />

                    <span>@lang('file-manager::file-manager.delete')</span>
                </button>
            </div>
        </template>
    @endif
</div>
