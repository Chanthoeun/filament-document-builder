<div class="w-full flex flex-col items-center justify-center p-0">
    @if (!empty($error))
        <div class="w-full p-4 mb-4 text-sm text-amber-800 rounded-lg bg-amber-50 dark:bg-gray-800 dark:text-amber-400 border border-amber-200 dark:border-amber-700">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ $error }}</span>
            </div>
        </div>
    @endif

    @if (!empty($base64))
        <div class="w-full bg-gray-100 dark:bg-gray-900 rounded-lg p-1 border border-gray-200 dark:border-gray-700">
            <iframe 
                src="data:application/pdf;base64,{{ $base64 }}#toolbar=1&navpanes=1" 
                class="w-full rounded shadow-sm bg-white" 
                style="width: 100%; height: 75vh; min-height: 650px; border: none;"
            ></iframe>
        </div>
    @endif
</div>
