<div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto max-w-4xl px-6 py-10 space-y-8">
        <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Watch Logs</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Dosya izleme sistemi tarafından kaydedilen son değişiklik bildirimleri.</p>
            </div>
            <div class="flex flex-col items-end gap-3">
                @include('ai-translator::livewire.translator.partials.nav')
                @include('ai-translator::livewire.translator.partials.auth-info')
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white/70 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="space-y-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                @forelse ($lines as $line)
                    <div class="rounded-lg border border-slate-200 bg-white/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-900/80">{{ $line }}</div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Henüz izleme kaydı oluşturulmadı.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
