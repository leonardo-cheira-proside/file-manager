@php $fm = \Illuminate\Support\Js::from($file); @endphp
<div wire:key="grid-{{ $file['path'] }}"
     data-fm-path="{{ $file['path'] }}" data-fm-type="{{ $file['type'] }}"
     data-fm-name="{{ $file['name'] }}" data-fm-url="{{ $file['url'] }}"
     class="cursor-pointer w-40 p-4 border rounded-xl transition-all flex flex-col items-center justify-center text-center group"
     :class="isSelected(@js($file['path'])) ? 'border-teal-600 bg-teal-50 ring-2 ring-teal-200' : 'border-gray-200 bg-white hover:border-teal-300 hover:shadow-md'"
     title="{{ $file['name'] }}"
     @click="toggleSelect(@js($file['path']), $event.shiftKey)"
     @dblclick="openItem({{ $fm }})"
     @contextmenu.prevent="openMenu($event, {{ $fm }})"
     draggable="true"
     @dragstart="onDragStart($event, {{ $fm }})"
     @if ($file['type'] === 'folder')
         @dragover.prevent @drop.prevent="onDropMove($event, @js($file['path']))"
     @endif>

    @if ($file['type'] === 'folder')
        <svg class="h-14 w-14 text-teal-600 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
        <p class="w-full text-[11px] mt-2 font-medium text-gray-600 group-hover:text-teal-700 truncate px-2">{{ $file['name'] }}</p>
        <span class="text-[10px] text-gray-400 mt-1">@lang('file-manager::file-manager.folder')</span>
    @else
        <div class="w-full h-24 flex items-center justify-center bg-gray-50 rounded-lg p-1 overflow-hidden">
            @if ($file['type'] === 'image')
                <img src="{{ $file['url'] }}" loading="lazy" class="max-h-full max-w-full object-contain rounded" draggable="false" alt="{{ $file['name'] }}">
            @elseif ($file['type'] === 'video')
                <video src="{{ $file['url'] }}#t=0.5" preload="metadata" muted class="max-h-full max-w-full object-cover rounded"></video>
            @else
                <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            @endif
        </div>
        <p class="text-[11px] mt-2 truncate font-medium text-gray-600 group-hover:text-teal-700 w-full px-1">{{ $file['name'] }}</p>
        <p class="text-[10px] text-gray-400 mt-0.5 uppercase">{{ $file['extension'] ?: 'file' }}</p>
    @endif

    @if ($this->inTrash && isset($file['expiresAt']))
        <p class="text-[10px] text-red-500 mt-1" x-data x-text="fmTimeLeft({{ $file['expiresAt'] }})"></p>
    @endif
</div>
