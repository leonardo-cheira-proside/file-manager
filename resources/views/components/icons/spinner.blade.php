@props([
    'class' => 'h-5 w-5',
])
{{-- A rotação e as opacidades vão dentro do SVG (SMIL + atributos), não em
     classes utilitárias: se a app não tiver recompilado o Tailwind, o
     "animate-spin"/"opacity-25" não existem e o loader ficava um borrão
     sólido e parado. Os atributos width/height são só o tamanho de recurso —
     qualquer classe de Tailwind (h-8 w-8) ganha-lhes por serem CSS. --}}
<svg class="{{ $class }}" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-opacity=".25"></circle>
    <path fill="currentColor" fill-opacity=".75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
        <animateTransform attributeName="transform" type="rotate" values="0 12 12;360 12 12" dur=".8s"
            repeatCount="indefinite" />
    </path>
</svg>
