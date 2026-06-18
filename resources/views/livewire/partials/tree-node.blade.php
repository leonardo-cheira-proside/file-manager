{{-- Nó recursivo da árvore de diretórios --}}
<li wire:key="tree-{{ $node['path'] }}" class="flex flex-col">
    <div class="flex items-center gap-2 px-2 py-1.5 cursor-pointer rounded-md hover:bg-gray-100 group"
        :class="$wire.path === @js($node['path']) ? 'bg-gray-100 text-proximo-700' : 'text-gray-700'"
        wire:click="open('{{ $node['path'] }}')"
        @contextmenu.prevent="openMenu($event, { path: @js($node['path']), name: @js($node['name']), type: 'folder' })"
        draggable="true" @dragstart="onDragStart($event, { path: @js($node['path']), type: 'folder' })"
        @dragover.prevent @drop.prevent="onDropMove($event, @js($node['path']))">
        <svg class="h-4 w-4 shrink-0 text-proximo-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
        </svg>
        <span class="text-sm truncate flex-1">{{ $node['name'] }}</span>
        @if ($node['has_children'])
            <button type="button" class="p-1" wire:click.stop="toggleFolder('{{ $node['path'] }}')">
                <svg class="h-3 w-3 shrink-0 opacity-40 transition-transform {{ in_array($node['path'], $openFolders, true) ? 'rotate-90' : '' }}"
                    fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        @endif
    </div>

    @if (!empty($node['children']))
        <ul class="ml-4 border-l border-gray-200 mt-0.5">
            @foreach ($node['children'] as $child)
                @include('file-manager::livewire.partials.tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
