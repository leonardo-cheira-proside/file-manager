@php $fm = \Illuminate\Support\Js::from($file); @endphp
<div wire:key="grid-{{ $file['path'] }}" data-fm-path="{{ $file['path'] }}" data-fm-type="{{ $file['type'] }}"
    data-fm-name="{{ $file['name'] }}" data-fm-url="{{ $file['url'] }}" data-fm-ext="{{ $file['extension'] ?? '' }}"
    data-fm-size="{{ $file['sizeFormatted'] ?? '' }}" data-fm-modified="{{ $file['modified'] ?? '' }}"
    class="relative cursor-pointer w-40 p-4 border rounded-xl transition-all flex flex-col items-center justify-center text-center group"
    :class="isSelected(@js($file['path'])) ? 'border-blue-400 bg-blue-100 ring-1 ring-blue-300' :
        'border-transparent hover:bg-gray-100'"
    title="{{ $file['name'] }}" @click="toggleSelect(@js($file['path']), $event.shiftKey)"
    @dblclick="openItem({{ $fm }})" @contextmenu.prevent="openMenu($event, {{ $fm }})"
    draggable="true" @dragstart="onDragStart($event, {{ $fm }})"
    @if ($file['type'] === 'folder') @dragover.prevent @drop.prevent="onDropMove($event, @js($file['path']))" @endif>

    {{-- Checkbox de seleção (visível no hover ou quando selecionado) --}}
    <div class="absolute top-2 left-2 z-10 transition-opacity"
        :class="isSelected(@js($file['path'])) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
        @click.stop>
        <input type="checkbox" :checked="isSelected(@js($file['path']))"
            @change="toggleCheck(@js($file['path']))"
            class="fm-check h-4 w-4 rounded border-gray-300 text-proximo-600 cursor-pointer">
    </div>

    @if ($file['type'] === 'folder')
        <svg class="h-14 w-14 text-proximo-600 group-hover:scale-110 transition-transform" fill="currentColor"
            viewBox="0 0 20 20">
            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
        </svg>
        <p class="w-full text-[11px] mt-2 font-medium text-gray-600 group-hover:text-proximo-700 truncate px-2">
            {{ $file['name'] }}</p>
        <span class="text-[10px] text-gray-400 mt-1">@lang('file-manager::file-manager.folder')</span>
    @else
        <div class="w-full h-24 flex items-center justify-center bg-gray-50 rounded-lg p-1 overflow-hidden">
            @if ($file['type'] === 'image')
                <img src="{{ $file['url'] }}" loading="lazy" class="max-h-full max-w-full object-contain rounded"
                    draggable="false" alt="{{ $file['name'] }}">
            @elseif ($file['type'] === 'video')
                <video src="{{ $file['url'] }}#t=0.5" preload="metadata" muted
                    class="max-h-full max-w-full object-cover rounded"></video>
            @else
                @include('file-manager::livewire.partials.file-icon', ['file' => $file, 'class' => 'h-14 w-14'])
            @endif
        </div>
        <p class="text-[11px] mt-2 truncate font-medium text-gray-600 group-hover:text-proximo-700 w-full px-1">
            {{ $file['name'] }}</p>
        <p class="text-[10px] text-gray-400 mt-0.5 uppercase">{{ $file['extension'] ?: 'file' }}</p>
    @endif

    @if ($this->inTrash && isset($file['expiresAt']))
        <p class="text-[10px] text-red-500 mt-1" x-data x-text="fmTimeLeft({{ $file['expiresAt'] }})"></p>
    @endif
</div>
