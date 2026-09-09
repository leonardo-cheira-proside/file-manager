{{-- Modal de confirmação depois de o upload terminar --}}
<div x-show="uploadDone.open" x-cloak role="dialog" aria-modal="true"
    class="fixed inset-0 z-[1000] flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" @click="uploadDone.open = false"></div>

    <div class="relative bg-white p-6 rounded-xl shadow-xl w-96">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 flex items-center justify-center text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="font-bold text-lg">@lang('file-manager::file-manager.upload_success')</h2>
        </div>

        <p class="text-sm text-gray-600 mb-5"
            x-text="@js(__('file-manager::file-manager.upload_success_body')).replace(':count', uploadDone.count)"></p>

        <div class="flex justify-end">
            <button type="button" @click="uploadDone.open = false" x-ref="uploadDoneOk"
                class="px-4 py-2 rounded-lg bg-proximo-600 text-white text-sm hover:bg-proximo-700">
                @lang('file-manager::file-manager.close')
            </button>
        </div>
    </div>
</div>
