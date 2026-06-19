{{-- Modal: criar pasta / renomear / eliminar --}}
<div x-show="modal.open" x-cloak class="fixed inset-0 z-[1000] flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" @click="modal.open = false"></div>

    <div class="relative bg-white p-6 rounded-xl shadow-xl w-96">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-9 flex items-center justify-center text-proximo-600">
                <template x-if="modal.action === 'add'">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                    </svg>
                </template>
                <template x-if="modal.action === 'rename'">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </template>
                <template x-if="modal.action === 'delete'">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </template>
                <template x-if="modal.action === 'info'">
                    <svg class="w-7 h-7 text-proximo-600" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                        viewBox="0 0 24 24">
                        <g data-name="info">
                            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z">
                            </path>
                            <circle cx="12" cy="8" r="1"></circle>
                            <path d="M12 10a1 1 0 0 0-1 1v5a1 1 0 0 0 2 0v-5a1 1 0 0 0-1-1z"></path>
                        </g>
                    </svg>
                </template>
            </div>
            <h2 class="font-bold text-lg"
                x-text="{
                add: '{{ __('file-manager::file-manager.new_folder') }}',
                rename: '{{ __('file-manager::file-manager.rename') }}',
                info: '{{ __('file-manager::file-manager.info') }}',
                delete: '{{ __('file-manager::file-manager.delete') }}'
            }[modal.action]">
            </h2>
        </div>

        <template x-if="modal.action === 'add' || modal.action === 'rename'">
            <input type="text" data-fm-modal-input x-model="modal.text" placeholder="@lang('file-manager::file-manager.name_placeholder')"
                @keydown.enter.prevent="confirmModal()"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 mb-4 focus:outline-proximo-500 focus:outline-0  focus:ring-2 focus:ring-proximo-500 focus:border-proximo-500">
        </template>

        <template x-if="modal.action === 'delete'">
            <p class="py-2 px-1 mb-4 text-gray-600 text-sm">
                @lang('file-manager::file-manager.delete_confirm', ['days' => config('file-manager.trash_retention_days')])
            </p>
        </template>

        <template x-if="modal.action === 'info'">
            <div class="mb-4">
                {{-- Pré-visualização para imagens --}}
                <template x-if="modal.file && modal.file.type === 'image' && modal.file.url">
                    <div
                        class="w-full flex items-center justify-center bg-gray-50 rounded-lg p-2 mb-3 max-h-40 overflow-hidden">
                        <img :src="modal.file.url" class="max-h-36 max-w-full object-contain rounded" alt="">
                    </div>
                </template>

                <dl class="text-sm divide-y divide-gray-100">
                    <div class="flex justify-between gap-4 py-1.5">
                        <dt class="text-gray-400 shrink-0">@lang('file-manager::file-manager.name')</dt>
                        <dd class="text-gray-800 font-medium truncate text-right" x-text="modal.file?.name"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-1.5">
                        <dt class="text-gray-400 shrink-0">@lang('file-manager::file-manager.type')</dt>
                        <dd class="text-gray-700 text-right"
                            x-text="({
                            folder: '@lang('file-manager::file-manager.type_folder')',
                            image: '@lang('file-manager::file-manager.type_image')',
                            video: '@lang('file-manager::file-manager.type_video')',
                            other: '@lang('file-manager::file-manager.type_file')'
                        })[modal.file?.type] ?? modal.file?.type">
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 py-1.5">
                        <dt class="text-gray-400 shrink-0">@lang('file-manager::file-manager.size')</dt>
                        <dd class="text-gray-700 text-right tabular-nums" x-text="modal.file?.sizeFormatted || '—'">
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 py-1.5" x-show="modal.file && modal.file.type !== 'folder'">
                        <dt class="text-gray-400 shrink-0">@lang('file-manager::file-manager.extension')</dt>
                        <dd class="text-gray-700 text-right uppercase" x-text="modal.file?.extension || '—'"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-1.5">
                        <dt class="text-gray-400 shrink-0">@lang('file-manager::file-manager.modified')</dt>
                        <dd class="text-gray-700 text-right tabular-nums" x-text="modal.file?.modified || '—'"></dd>
                    </div>
                    <div x-data="{ copied: false }" class=" py-1.5 flex justify-between gap-4">
                        <dt class="text-gray-400 flex items-center">@lang('file-manager::file-manager.path')</dt>
                    
                        <div class="flex items-center">
                            <dd class="text-gray-600 break-all text-xs" x-text="modal.file?.path"></dd>
                    
                            <button
                                type="button"
                                class="p-1 text-gray-500 hover:text-gray-700"
                                @click="
                                    navigator.clipboard.writeText(modal.file?.path || '');
                                    copied = true;
                                    setTimeout(() => copied = false, 1500);
                                "
                            >
                                <!-- Not copied -->
                                <svg
                                    x-show="!copied"
                                    class="w-4 h-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12.9v4.2c0 3.5-1.4 4.9-4.9 4.9H6.9C3.4 22 2 20.6 2 17.1v-4.2C2 9.4 3.4 8 6.9 8h4.2c3.5 0 4.9 1.4 4.9 4.9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M22 6.9v4.2c0 3.5-1.4 4.9-4.9 4.9H16v-3.1C16 9.4 14.6 8 11.1 8H8V6.9C8 3.4 9.4 2 12.9 2h4.2C20.6 2 22 3.4 22 6.9z"></path>
                                </svg>
                    
                                <!-- Copied -->
                                
                                <svg x-show="copied" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15 15" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M14.707 3L5.5 12.207.293 7 1 6.293l4.5 4.5 8.5-8.5.707.707z" fill="currentColor"></path></svg>

                            </button>
                        </div>

                    </div>
                </dl>
            </div>
        </template>

        <div class="flex justify-end gap-2">
            <button type="button" @click="modal.open = false" class="px-4 py-1.5 border border-gray-300 rounded-lg"
                x-text="modal.action === 'info' ? '@lang('file-manager::file-manager.close')' : '@lang('file-manager::file-manager.cancel')'"></button>
            <button x-show="modal.action !== 'info'" type="button" @click="confirmModal()"
                class="px-4 py-1.5 text-white rounded-lg"
                :class="modal.action === 'delete' ? 'bg-red-600' : 'bg-proximo-600'"
                x-text="modal.action === 'delete' ? '@lang('file-manager::file-manager.continue')' : '@lang('file-manager::file-manager.save')'">
            </button>
        </div>
    </div>
</div>
