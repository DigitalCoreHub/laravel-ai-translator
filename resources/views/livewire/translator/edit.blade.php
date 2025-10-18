<div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto max-w-3xl px-6 py-10 space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">Çeviri Düzenle</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Dosya: <span class="font-mono">{{ $path }}</span> · Anahtar: <span class="font-mono">{{ $key }}</span></p>
        </header>

        @if ($message)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                {{ $message }}
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white/70 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="space-y-4">
                <div>
                    <h2 class="text-xs uppercase tracking-wide text-slate-400">Kaynak Metin ({{ strtoupper($from) }})</h2>
                    <div class="mt-2 rounded-xl border border-slate-200 bg-white/70 px-4 py-3 text-sm text-slate-600 shadow-inner dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">{{ $source }}</div>
                </div>
                <div>
                    <h2 class="text-xs uppercase tracking-wide text-slate-400">Çeviri ({{ strtoupper($to) }})</h2>
                    <textarea wire:model="value" rows="6" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400"></textarea>
                </div>
                <div class="flex justify-end">
                    <button wire:click="save" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-300">Kaydet</button>
                </div>
            </div>
        </section>

        <div>
            <a href="{{ route('ai-translator.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-500 dark:text-sky-300 dark:hover:text-sky-200">&larr; Panele dön</a>
        </div>
    </div>
</div>
