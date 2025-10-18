<div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto max-w-6xl px-6 py-10 space-y-8">
        <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Log &amp; İstatistik</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Son çeviri işlemlerinin raporunu görüntüleyin.</p>
            </div>
            <nav class="flex gap-2">
                <a href="{{ route('ai-translator.dashboard') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500">Panel</a>
                <a href="{{ route('ai-translator.settings') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500">Ayarlar</a>
                <a href="{{ route('ai-translator.logs') }}" class="rounded-full bg-slate-200 px-4 py-2 text-sm font-medium text-slate-900 dark:bg-slate-800 dark:text-slate-100">Log &amp; İstatistik</a>
            </nav>
        </header>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white/70 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-100/80 dark:bg-slate-800/70">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Tarih</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Kaynak → Hedef</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Dosya</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Provider</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Çevrilen</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Eksik</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Süre (ms)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse (($entries ?? []) as $item)
                            <tr class="bg-white/80 transition hover:bg-slate-50 dark:bg-slate-900/60 dark:hover:bg-slate-900">
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $item['executed_at'] ? \Illuminate\Support\Carbon::parse($item['executed_at'])->format('Y-m-d H:i:s') : '-' }}</td>
                                <td class="px-4 py-3 font-medium">{{ strtoupper($item['from']) }} → {{ strtoupper($item['to']) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $item['file'] }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ ucfirst($item['provider']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-300">{{ $item['translated'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ $item['missing'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ $item['duration'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">Henüz raporlanmış çeviri işlemi bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
