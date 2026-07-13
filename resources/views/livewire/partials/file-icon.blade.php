{{-- Ícone por tipo de ficheiro (cor + etiqueta da extensão), estilo "badge" de documento.
     Vars: $file (com 'extension'), $class (tamanho do SVG, ex.: 'h-12 w-12'). --}}
@php
    $ext = strtolower($file['extension'] ?? '');
    $class = $class ?? 'h-12 w-12';

    // Mapa extensão -> cor da marca do tipo.
    $map = [
        // Word
        'doc' => '#2563eb', 'docx' => '#2563eb', 'odt' => '#2563eb', 'rtf' => '#2563eb',
        // Excel
        'xls' => '#16a34a', 'xlsx' => '#16a34a', 'csv' => '#16a34a', 'ods' => '#16a34a',
        // PowerPoint
        'ppt' => '#ea580c', 'pptx' => '#ea580c', 'odp' => '#ea580c',
        // PDF
        'pdf' => '#dc2626',
        // Arquivos
        'zip' => '#d97706', 'rar' => '#d97706', '7z' => '#d97706', 'tar' => '#d97706', 'gz' => '#d97706',
        // Áudio
        'mp3' => '#7c3aed', 'wav' => '#7c3aed', 'ogg' => '#7c3aed', 'flac' => '#7c3aed', 'm4a' => '#7c3aed',
        // Vídeo (fallback quando não há thumbnail)
        'flv' => '#db2777', 'mkv' => '#db2777', 'avi' => '#db2777', 'mov' => '#db2777', 'webm' => '#db2777', 'wmv' => '#db2777',
        // Código
        'php' => '#4f46e5', 'js' => '#4f46e5', 'ts' => '#4f46e5', 'json' => '#4f46e5', 'html' => '#4f46e5',
        'css' => '#4f46e5', 'xml' => '#4f46e5', 'blade' => '#4f46e5',
        // Livro
        'epub' => '#0d9488', 'mobi' => '#0d9488',
    ];
    $color = $map[$ext] ?? '#6b7280'; // cinza por defeito
    $label = strtoupper($ext);
    // Encolher a etiqueta se for grande (ex.: docx, xlsx, pptx).
    $labelSize = strlen($label) <= 3 ? 6.5 : 5.2;
@endphp
<svg class="{{ $class }}" viewBox="0 0 32 40" fill="none" xmlns="http://www.w3.org/2000/svg">
    {{-- Folha com canto dobrado --}}
    <path d="M4 2h16l8 8v26a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" fill="{{ $color }}" fill-opacity="0.12" />
    <path d="M4 2h16l8 8v26a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" stroke="{{ $color }}" stroke-width="1.5" />
    <path d="M20 2v8h8" stroke="{{ $color }}" stroke-width="1.5" fill="none" />
    {{-- Faixa com a extensão --}}
    @if ($label !== '')
        <rect x="2" y="22" width="24" height="10" rx="1.5" fill="{{ $color }}" />
        <text x="14" y="29.4" text-anchor="middle" fill="#ffffff"
            font-family="ui-sans-serif, system-ui, sans-serif" font-size="{{ $labelSize }}"
            font-weight="700" letter-spacing="0.3">{{ $label }}</text>
    @endif
</svg>
