<div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto max-w-5xl px-6 py-10 space-y-8">
        <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Ayarlar</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Provider yapılandırmalarını görüntüleyin ve bağlantıları test edin.</p>
            </div>
            <nav class="flex gap-2">
                <a href="{{ route('ai-translator.dashboard') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500">Panel</a>
                <a href="{{ route('ai-translator.settings') }}" class="rounded-full bg-slate-200 px-4 py-2 text-sm font-medium text-slate-900 dark:bg-slate-800 dark:text-slate-100">Ayarlar</a>
                <a href="{{ route('ai-translator.logs') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500">Log &amp; İstatistik</a>
            </nav>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white/70 p-6 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/70">
            <h2 class="text-lg font-semibold">Genel Ayarlar</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">Aktif Provider</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ strtoupper($currentProvider) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">Kaynak Dil</p>
                    <select wire:model="from" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">Hedef Dil</p>
                    <select wire:model="to" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            @foreach ($providers as $name => $config)
                <div class="rounded-2xl border border-slate-200 bg-white/70 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">{{ ucfirst($name) }}</h3>
                            <p class="text-xs uppercase tracking-wide text-slate-400">ENV Değerleri</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (isset($status[$name]))
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ $status[$name]['message'] }}</span>
                            @endif
                            <button wire:click="testConnection({{ @js($name) }})" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-300">Test Connection</button>
                        </div>
                    </div>
                    <dl class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($config as $key => $value)
                            <div class="rounded-xl border border-slate-200 bg-white/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ strtoupper($key) }}</dt>
                                <dd class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $value ?: 'env()' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </section>
    </div>
</div>
