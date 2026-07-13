{{-- Modal: mover seleção para uma pasta destino --}}
<div x-show="moveModal.open" x-cloak class="fixed inset-0 z-[1000] flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" @click="moveModal.open = false"></div>

    <div class="relative bg-white p-6 rounded-xl shadow-xl w-[28rem] max-h-[80vh] flex flex-col">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-9 flex items-center justify-center text-proximo-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6m0-6l-2 2m2-2l2 2" />
                </svg>
            </div>
            <h2 class="font-bold text-lg"
                x-text="moveModal.mode === 'copy' ? '@lang('file-manager::file-manager.copy_to')' : '@lang('file-manager::file-manager.move_to')'"></h2>
        </div>

        <p class="text-xs text-gray-500 mb-3">
            <span class="font-semibold text-proximo-700" x-text="selected.length"></span>
            @lang('file-manager::file-manager.items_selected') · @lang('file-manager::file-manager.move_pick_dest')
        </p>

        <div class="flex-1 overflow-y-auto border border-gray-200 rounded-lg p-2 fm-scroll min-h-[12rem]">
            {{-- Raízes efetivas (destinos de topo) --}}
            @foreach ($this->roots as $root)
                <div wire:key="move-root-{{ $root['path'] }}">
                    <button type="button" @click="moveModal.target = @js($root['path'])"
                        class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-sm"
                        :class="moveModal.target === @js($root['path']) ? 'bg-proximo-100 text-proximo-800 font-semibold' : 'hover:bg-gray-100 text-gray-700'">
                        <svg class="h-4 w-4 shrink-0 text-proximo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                        </svg>
                        <span class="truncate">{{ $root['label'] }}</span>
                    </button>

                    <ul class="ml-1 mb-1">
                        @foreach ($root['tree'] as $node)
                            @include('file-manager::livewire.partials.move-tree-node', ['node' => $node])
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <button type="button" @click="moveModal.open = false"
                class="px-4 py-1.5 border border-gray-300 rounded-lg">@lang('file-manager::file-manager.cancel')</button>
            <button type="button" @click="confirmMove()" :disabled="moveModal.target === ''"
                class="px-4 py-1.5 text-white rounded-lg bg-proximo-600 disabled:opacity-40 disabled:cursor-not-allowed"
                x-text="moveModal.mode === 'copy' ? '@lang('file-manager::file-manager.copy_here')' : '@lang('file-manager::file-manager.move_here')'">
            </button>
        </div>
    </div>
</div>
