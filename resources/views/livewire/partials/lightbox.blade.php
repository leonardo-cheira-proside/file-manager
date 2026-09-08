{{-- Lightbox de pré-visualização (imagem / vídeo) --}}
<div x-show="light.open" x-cloak role="dialog" aria-modal="true"
     class="fixed inset-0 z-[1100] flex items-center justify-center bg-black/95 p-4"
     @click.self="light.open = false">
    <button type="button" @click="light.open = false"
            class="fixed top-5 right-5 text-white/60 hover:text-white p-2 z-[1110]">
        <svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>

    <div x-data="fmThumb(() => light.url)" x-effect="light.open, light.url; syncThumb()"
        class="relative w-full h-full max-w-[95vw] max-h-[90vh] flex items-center justify-center">
        <div x-show="!l" class="absolute inset-0 flex items-center justify-center text-proximo-500">
            <x-file-manager::icons.spinner class="h-10 w-10" />
        </div>
        <template x-if="light.open && light.type === 'video'">
            <video :src="light.url" controls autoplay class="max-w-full max-h-full rounded-lg shadow-2xl object-contain"
                x-on:loadedmetadata="markLoaded()" x-on:error="l = true"></video>
        </template>
        <template x-if="light.open && light.type === 'image'">
            <img :src="light.url" class="max-w-full max-h-full rounded shadow-2xl object-contain" alt=""
                x-on:load="markLoaded()" x-on:error="l = true">
        </template>
    </div>
</div>
