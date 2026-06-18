{{-- Nó recursivo do seletor de pasta destino (modal "mover para…") --}}
<li wire:key="move-{{ $node['path'] }}" class="flex flex-col">
    <div class="flex items-center gap-1">
        @if ($node['has_children'])
            <button type="button" class="p-1 shrink-0" wire:click.stop="toggleFolder('{{ $node['path'] }}')">
                <svg class="h-3 w-3 opacity-40 transition-transform {{ in_array($node['path'], $openFolders, true) ? 'rotate-90' : '' }}"
                    fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        @else
            <span class="w-5 shrink-0"></span>
        @endif
        <button type="button" @click="moveModal.target = @js($node['path'])"
            class="flex-1 flex items-center gap-2 px-2 py-1 rounded-md text-sm text-left min-w-0"
            :class="moveModal.target === @js($node['path']) ? 'bg-proximo-100 text-proximo-800 font-semibold' : 'hover:bg-gray-100 text-gray-700'">
            <svg class="h-4 w-4 shrink-0 text-proximo-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
            </svg>
            <span class="truncate">{{ $node['name'] }}</span>
        </button>
    </div>

    @if (!empty($node['children']))
        <ul class="ml-4 border-l border-gray-200">
            @foreach ($node['children'] as $child)
                @include('file-manager::livewire.partials.move-tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
