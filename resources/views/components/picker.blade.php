@props([
    'inputName' => 'selected_file_path',
    'value' => '',
    'multiple' => false,
    'filter' => null, // null | 'images' | 'videos'
    'label' => null,
    'size' => 'small', // small (compacto) | large (dropzone)
])

@php
    $isArrayInput = str_ends_with($inputName, '[]');
    $allowMultiple = $multiple || $isArrayInput;
    $rawInitial = is_array($value)
        ? array_values(array_filter($value))
        : ($value !== '' && $value !== null
            ? [$value]
            : []);

    // Só pré-seleciona valores que realmente existem no disco. Assim, abrir o
    // picker com um valor inexistente não seleciona nada (nem mostra tile
    // partido, nem o submete no formulário). URLs/caminhos externos passam.
    $fmDisk = \Illuminate\Support\Facades\Storage::disk(config('file-manager.disk'));
    $initial = array_values(
        array_filter($rawInitial, function ($p) use ($fmDisk) {
            $p = (string) $p;
            if ($p === '') {
                return false;
            }
            if (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '//'])) {
                return true; // URL externa: não dá para verificar aqui.
            }
            try {
                return $fmDisk->exists($p);
            } catch (\Throwable $e) {
                return false;
            }
        }),
    );

    $pickerId = 'fmp_' . md5($inputName . uniqid('', true));
    $mediaBase = route('file-manager.media');

    // Subtítulo da dropzone (variante large): formatos + tamanho máximo.
    $maxMb = max(1, (int) round(((int) config('file-manager.uploads.max_size', 51200)) / 1024));
    $mimes = array_filter((array) config('file-manager.uploads.mimes'));
    $dropHint = __('file-manager::file-manager.drop_hint', [
        'formats' => $mimes ? strtoupper(implode(', ', $mimes)) : __('file-manager::file-manager.all_formats'),
        'max' => $maxMb,
    ]);
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
    markBroken(path) {
        if (this.broken[path]) return; // já marcado: evita re-disparar reatividade (loop)
        this.broken = { ...this.broken, [path]: true };
    },
    isBroken(path) { return !!this.broken[path]; },
    preview(path) {
        if (!path) return '';
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage')) return path;
        return this.mediaBase + '?path=' + encodeURIComponent(path);
    },
    isImage(p) { return /\.(jpe?g|png|gif|webp|svg|bmp|avif)$/i.test(p || ''); },
    isVideo(p) { return /\.(mp4|webm|ogg|mov|m4v|avi)$/i.test(p || ''); },
    isMedia(p) { return this.isImage(p) || this.isVideo(p); },

    // Ver imagem/vídeo em grande (lightbox local do picker).
    light: { open: false, url: '', video: false },
    openLight(path) {
        if (!this.isMedia(path)) return;
        this.light = { open: true, url: this.preview(path), video: this.isVideo(path) };
    },

    // Drag & drop na dropzone: faz upload e seleciona automaticamente.
    multiple: @js($allowMultiple),
    uploadUrl: @js(route('file-manager.upload')),
    csrf: @js(csrf_token()),
    dragOver: false,
    uploading: 0,
    onDrop(e) {
        this.dragOver = false;
        const files = [...(e.dataTransfer?.files || [])];
        if (files.length) this.uploadFiles(files);
    },
    uploadFiles(files) {
        (this.multiple ? files : files.slice(0, 1)).forEach((file) => {
            const fd = new FormData();
            fd.append('file', file);
            this.uploading++;
            fetch(this.uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }, body: fd })
                .then((r) => r.ok ? r.json() : Promise.reject(r))
                .then((d) => {
                    if (!d.path) return;
                    this.broken = {};
                    this.selected = this.multiple ? [...this.selected, d.path] : [d.path];
                })
                .catch(() => {})
                .finally(() => this.uploading--);
        });
    },
}" @file-manager-selected.window="applySelection($event.detail)"
    @reset-file-picker.window="if (!$event.detail?.inputName || $event.detail.inputName === @js($inputName)) { selected = []; open = false; broken = {}; }"
    @keydown.escape.window="open = false" {{ $attributes->merge(['class' => 'w-full']) }}>

    @if ($size === 'large')
        {{-- Dropzone: clicável (abre modal) e o drop faz upload + seleciona automaticamente. --}}
        <button type="button" @click="open = true"
            @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop($event)"
            class="w-full flex flex-col items-center justify-center gap-2 px-6 py-10 border-2 border-dashed rounded-2xl transition text-center"
            :class="dragOver ? 'border-proximo-500 bg-proximo-50/60' : 'border-gray-300 bg-white hover:border-proximo-400 hover:bg-gray-50/60'">
            <svg class="h-9 w-9 text-gray-700" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M7 18a4 4 0 01-.9-7.9A5 5 0 0116.9 8.6 3.5 3.5 0 0117 18h-1" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 14.5l2 2 3.5-4" />
            </svg>
            <span class="text-lg font-semibold text-gray-800">@lang('file-manager::file-manager.drop_title')</span>
            <span class="text-sm text-gray-400">{{ $dropHint }}</span>
            <span
                class="mt-3 px-5 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium">@lang('file-manager::file-manager.browse_file')</span>
            <span x-show="uploading > 0" x-cloak class="text-xs text-proximo-600 mt-1 animate-pulse">
                @lang('file-manager::file-manager.uploading')</span>
        </button>

        {{-- Selecionados: cards grandes, clicáveis para ver em grande. --}}
        <div class="mt-3 flex flex-wrap gap-3" x-show="selected.length">
            <template x-for="(path, i) in selected" :key="path">
                <div class="relative group" x-show="!isBroken(path)">
                    <div class="w-28 h-28 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center"
                        :class="isMedia(path) ? 'cursor-zoom-in' : ''" :title="path" @click="openLight(path)">
                        <template x-if="isImage(path)"><img :src="preview(path)" class="w-full h-full object-cover"
                                loading="lazy" @@error="markBroken(path)" alt=""></template>
                        <template x-if="isVideo(path)"><video :src="preview(path)" class="w-full h-full object-cover"
                                muted @@error="markBroken(path)"></video></template>
                        <template x-if="!isMedia(path)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </template>
                    </div>
                    <button type="button" @click.stop="selected = selected.filter((_, idx) => idx !== i)"
                        aria-label="@lang('file-manager::file-manager.remove')"
                        class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-white border border-gray-200 shadow flex items-center justify-center text-gray-500 hover:text-red-600">
                        <x-file-manager::icons.cross class="w-4 h-4" />
                    </button>
                </div>
            </template>
        </div>
    @else
    {{-- Botão + pré-visualização --}}
    <div class="flex items-center gap-3 p-3 border border-dashed border-gray-300 rounded-xl bg-gray-50/50 flex-wrap">
        <button type="button" @click="open = true"
            class="px-4 py-2 bg-proximo-600 text-white text-sm font-semibold rounded-lg hover:bg-proximo-700 transition flex items-center gap-2 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path
                    d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z" />
            </svg>
            {{ $label ?? __('file-manager::file-manager.select_file') }}
        </button>

        <template x-for="(path, i) in selected" :key="path">
            {{-- Só mostra o tile quando a media existe (imagem/vídeo carrega). Se falhar, esconde tudo. --}}
            <div class="relative group flex items-center" x-show="!isBroken(path)">
                <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center shrink-0"
                    :class="isMedia(path) ? 'cursor-zoom-in' : ''" :title="path" @click="openLight(path)">
                    <template x-if="isImage(path)"><img :src="preview(path)" class="w-full h-full object-cover"
                            loading="lazy" @@error="markBroken(path)" alt=""></template>
                    <template x-if="isVideo(path)"><video :src="preview(path)" class="w-full h-full object-cover"
                            muted @@error="markBroken(path)"></video></template>
                    <template x-if="!isImage(path) && !isVideo(path)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </template>
                </div>
                <button type="button" @click.stop="selected = selected.filter((_, idx) => idx !== i)"
                    title="@lang('file-manager::file-manager.remove')"
                    class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center hover:bg-red-600 shadow-sm">&times;</button>
            </div>
        </template>
    </div>
    @endif

    {{-- Inputs ocultos para o formulário --}}
    @if ($isArrayInput || $allowMultiple)
        <template x-for="(path, i) in selected" :key="'inp' + i">
            <input type="hidden" name="{{ $isArrayInput ? $inputName : $inputName . '[]' }}" :value="path">
        </template>
    @else
        <input type="hidden" name="{{ $inputName }}" :value="selected[0] ?? ''">
    @endif

    {{-- Modal com o File Manager nativo (sem iframe / sem JWT) --}}
    <div x-show="open" x-cloak
        class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[9999]">
        <div class="bg-white shadow-2xl w-[90vw] h-[90vh] flex flex-col overflow-hidden">
            <div class="flex justify-end items-center border-b px-4 py-3">
                <button type="button" @click="open = false"
                    class="flex items-center justify-center w-10 h-10 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors duration-200"
                    aria-label="Fechar">
                    <x-file-manager::icons.cross class="w-5 h-5" />
                </button>
            </div>
            <div class="flex-1 overflow-hidden">
                {{-- lazy: só carrega quando o modal abre (fica visível), evitando
                     renderizar vários File Managers no carregamento do formulário. --}}
                <livewire:file-manager :picker-mode="true" :multiple="$allowMultiple" :filter="$filter ?? 'all'" :lock-filter="(bool) $filter" lazy
                    wire:key="{{ $pickerId }}" />
            </div>
        </div>
    </div>

    {{-- Lightbox: ver imagem/vídeo selecionado em grande. --}}
    <div x-show="light.open" x-cloak @click="light.open = false" @keydown.escape.window="light.open = false"
        class="fixed inset-0 bg-black/80 flex items-center justify-center z-[10000] p-6 cursor-zoom-out">
        <template x-if="!light.video">
            <img :src="light.url" class="max-w-[92vw] max-h-[92vh] object-contain rounded" @click.stop alt="">
        </template>
        <template x-if="light.video">
            <video :src="light.url" controls autoplay class="max-w-[92vw] max-h-[92vh] rounded" @click.stop></video>
        </template>
    </div>
</div>
