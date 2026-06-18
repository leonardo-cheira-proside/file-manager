@props([
    'path' => '',          // caminho relativo no disco (ex.: "conteudos/x.png") ou URL completo
    'alt' => '',
    'hideOnError' => true,  // esconde o <img> se a imagem não existir/carregar
    'lightbox' => false,    // clicar amplia em ecrã inteiro (requer Alpine)
])

@php
    use Illuminate\Support\Str;

    $src = '';
    if (! empty($path)) {
        if (Str::startsWith($path, ['http://', 'https://', '/storage', 'data:'])) {
            $src = $path;
        } else {
            try {
                $src = app(\Proside\FileManager\Support\FileManagerService::class)->mediaUrl($path);
            } catch (\Throwable $e) {
                $src = \Illuminate\Support\Facades\Route::has('file-manager.media')
                    ? route('file-manager.media', ['path' => $path])
                    : '';
            }
        }
    }
@endphp

@if ($src !== '')
    @unless ($lightbox)
        <img src="{{ $src }}" alt="{{ $alt }}"
             {{ $attributes->merge(['class' => 'object-cover']) }}
             @if ($hideOnError) onerror="this.style.display='none'" @endif>
    @else
        <div x-data="{ open: false }" class="inline-block leading-none">
            <img src="{{ $src }}" alt="{{ $alt }}"
                 {{ $attributes->merge(['class' => 'object-cover cursor-zoom-in']) }}
                 @if ($hideOnError) onerror="this.style.display='none'" @endif
                 x-on:click="open = true">

            <template x-teleport="body">
                <div x-show="open" x-cloak x-transition.opacity
                     @click="open = false" @keydown.escape.window="open = false"
                     class="fixed inset-0 z-[1100] flex items-center justify-center bg-black/90 p-4 cursor-zoom-out">
                    <img src="{{ $src }}" alt="{{ $alt }}"
                         class="max-w-[95vw] max-h-[90vh] object-contain rounded shadow-2xl">
                </div>
            </template>
        </div>
    @endunless
@endif
