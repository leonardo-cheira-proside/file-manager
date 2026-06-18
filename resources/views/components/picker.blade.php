@props([
    'inputName' => 'selected_file_path',
    'value' => '',
    'multiple' => false,
    'filter' => null,        // null | 'images' | 'videos'
    'label' => null,
])

@php
    $isArrayInput = str_ends_with($inputName, '[]');
    $allowMultiple = $multiple || $isArrayInput;
    $initial = is_array($value) ? array_values(array_filter($value)) : ($value !== '' && $value !== null ? [$value] : []);
    $pickerId = 'fmp_'.md5($inputName.uniqid('', true));
    $mediaBase = route('file-manager.media');
@endphp

<div x-data="{
        open: false,
        selected: @js($initial),
        mediaBase: @js($mediaBase),
        broken: {},
        init() {
            // Forma canónica de ouvir um evento despachado por um componente Livewire.
            if (window.Livewire) {
                window.Livewire.on('file-manager-selected', (e) => this.applySelection(e));
            }
        },
        applySelection(e) {
            if (!this.open) return;
            // Aceita várias formas de payload: {paths:[...]}, [{paths:[...]}], [...] ou string.
            let p = e;
            if (Array.isArray(e)) p = (e[0] && e[0].paths) ? e[0].paths : e;
            else if (e && e.paths !== undefined) p = e.paths;
            else if (e && e.detail !== undefined) p = e.detail.paths ?? e.detail;
            this.broken = {};
            this.selected = Array.isArray(p) ? p : (p ? [p] : []);
            this.open = false;
        },
        markBroken(path) { this.broken = { ...this.broken, [path]: true }; },
        isBroken(path) { return !!this.broken[path]; },
        preview(path) {
            if (!path) return '';
            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage')) return path;
            return this.mediaBase + '?path=' + encodeURIComponent(path);
        },
        isImage(p) { return /\.(jpe?g|png|gif|webp|svg|bmp|avif)$/i.test(p || ''); },
        isVideo(p) { return /\.(mp4|webm|ogg|mov|m4v|avi)$/i.test(p || ''); },
     }"
     @file-manager-selected.window="applySelection($event.detail)"
     @keydown.escape.window="open = false"
     {{ $attributes->merge(['class' => 'w-full']) }}>

    {{-- Botão + pré-visualização --}}
    <div class="flex items-center gap-3 p-3 border border-dashed border-gray-300 rounded-xl bg-gray-50/50 flex-wrap">
        <button type="button" @click="open = true"
                class="px-4 py-2 bg-proximo-600 text-white text-sm font-semibold rounded-lg hover:bg-proximo-700 transition flex items-center gap-2 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"/>
            </svg>
            {{ $label ?? __('file-manager::file-manager.select_file') }}
        </button>

        <template x-for="(path, i) in selected" :key="path">
            {{-- Só mostra o tile quando a media existe (imagem/vídeo carrega). Se falhar, esconde tudo. --}}
            <div class="relative group flex items-center" x-show="!isBroken(path)">
                <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center shrink-0" :title="path">
                    <template x-if="isImage(path)"><img :src="preview(path)" class="w-full h-full object-cover" loading="lazy" @@error="markBroken(path)" alt=""></template>
                    <template x-if="isVideo(path)"><video :src="preview(path)" class="w-full h-full object-cover" muted @@error="markBroken(path)"></video></template>
                    <template x-if="!isImage(path) && !isVideo(path)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </template>
                </div>
                <button type="button" @click.stop="selected = selected.filter((_, idx) => idx !== i)" title="@lang('file-manager::file-manager.remove')"
                        class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center hover:bg-red-600 shadow-sm">&times;</button>
            </div>
        </template>
    </div>

    {{-- Inputs ocultos para o formulário --}}
    @if ($isArrayInput || $allowMultiple)
        <template x-for="(path, i) in selected" :key="'inp'+i">
            <input type="hidden" name="{{ $isArrayInput ? $inputName : $inputName.'[]' }}" :value="path">
        </template>
    @else
        <input type="hidden" name="{{ $inputName }}" :value="selected[0] ?? ''">
    @endif

    {{-- Modal com o File Manager nativo (sem iframe / sem JWT) --}}
    <div x-show="open" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[9999] p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[80vh] flex flex-col overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-lg font-bold text-gray-800">@lang('file-manager::file-manager.library')</h2>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-3xl leading-none">&times;</button>
            </div>
            <div class="flex-1 overflow-hidden">
                {{-- lazy: só carrega quando o modal abre (fica visível), evitando
                     renderizar vários File Managers no carregamento do formulário. --}}
                <livewire:file-manager
                    :picker-mode="true"
                    :multiple="$allowMultiple"
                    :filter="$filter ?? 'all'"
                    :lock-filter="(bool) $filter"
                    lazy
                    wire:key="{{ $pickerId }}" />
            </div>
        </div>
    </div>
</div>
