@php $fm = \Illuminate\Support\Js::from($file); @endphp
<tr wire:key="list-{{ $file['path'] }}" data-fm-path="{{ $file['path'] }}" data-fm-type="{{ $file['type'] }}"
    data-fm-name="{{ $file['name'] }}" data-fm-url="{{ $file['url'] }}" class="group cursor-pointer"
    :class="isSelected(@js($file['path'])) ? 'bg-proximo-100' : 'hover:bg-proximo-50'"
    @click="toggleSelect(@js($file['path']), $event.shiftKey)" @dblclick="openItem({{ $fm }})"
    @contextmenu.prevent="openMenu($event, {{ $fm }})" draggable="true"
    @dragstart="onDragStart($event, {{ $fm }})"
    @if ($file['type'] === 'folder') @dragover.prevent @drop.prevent="onDropMove($event, @js($file['path']))" @endif>
    <td class="px-4 py-2 whitespace-nowrap overflow-hidden">
        <div class="flex items-center gap-3">
            @if ($file['type'] === 'folder')
                <svg class="w-4 h-4 text-proximo-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                </svg>
            @elseif ($file['type'] === 'image')
                <svg class="w-4 h-4 text-gray-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                        clip-rule="evenodd" />
                </svg>
            @elseif ($file['type'] === 'video')
                <svg class="w-4 h-4 text-red-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
                </svg>
            @else
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 12.75H12" />
                </svg>
            @endif
            <span
                class="text-[13px] font-medium text-gray-700 group-hover:text-proximo-900 truncate">{{ $file['name'] }}</span>
        </div>
    </td>
    <td class="px-3 py-2 text-center text-[12px] text-gray-500 tabular-nums">{{ $file['sizeFormatted'] ?? '—' }}</td>
    <td class="px-3 py-2 text-center">
        <span
            class="text-[10px] font-bold uppercase text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ $file['extension'] ?: ($file['type'] === 'folder' ? __('file-manager::file-manager.folder') : 'file') }}</span>
    </td>
    <td class="px-4 py-2 text-right text-[12px] text-gray-400 tabular-nums">{{ $file['modified'] ?? '—' }}</td>
    @if ($this->inTrash)
        <td class="px-4 py-2 text-right text-[12px] text-red-500 tabular-nums">
            @if (isset($file['expiresAt']))
                <span x-data x-text="fmTimeLeft({{ $file['expiresAt'] }})"></span>
            @endif
        </td>
    @endif
</tr>
