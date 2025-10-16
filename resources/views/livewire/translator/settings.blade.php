<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">Sağlayıcı Ayarları</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Aktif sağlayıcı: <span class="font-semibold text-indigo-600 dark:text-indigo-300">{{ strtoupper($activeProvider) }}</span></p>
        </div>
        <a href="{{ route('ai-translator.dashboard') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Panele Dön</a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($providerConfig as $name => $config)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-200">{{ strtoupper($name) }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">API Anahtarı: <span class="font-mono">{{ $config['api_key'] ?? '—' }}</span></p>
                    </div>
                    <button wire:click="test('{{ $name }}')" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-slate-800 dark:bg-indigo-500 dark:hover:bg-indigo-400">Test Connection</button>
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    @foreach ($config as $key => $value)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $key }}</dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if (isset($results[$name]))
                    <div class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $results[$name]['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200' }}">
                        {{ $results[$name]['message'] }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
