{{-- Ecrã de espera do componente "lazy" (o modal do picker). Sem isto o modal
     abria em branco até a primeira resposta do servidor chegar. Fica fora do
     <style> do componente, por isso só usa classes do Tailwind da aplicação. --}}
<div class="flex h-full w-full flex-col items-center justify-center gap-3 bg-white text-proximo-600">
    <x-file-manager::icons.spinner class="h-8 w-8" />
    <span class="text-sm text-gray-400">@lang('file-manager::file-manager.loading')</span>
</div>
