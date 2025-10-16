<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">Çeviri Logları</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">storage/logs/ai-translator-report.json</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="refreshLogs" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Yenile</button>
            <a href="{{ route('ai-translator.dashboard') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Panele Dön</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-4 py-3 text-left font-medium">Dosya</th>
                    <th class="px-4 py-3 text-left font-medium">Provider</th>
                    <th class="px-4 py-3 text-left font-medium">Çevrilen</th>
                    <th class="px-4 py-3 text-left font-medium">Eksik</th>
                    <th class="px-4 py-3 text-left font-medium">Süre (ms)</th>
                    <th class="px-4 py-3 text-left font-medium">Tarih</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($entries as $entry)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $entry['file'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $entry['primary_provider'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-emerald-600 dark:text-emerald-300">{{ $entry['translated'] ?? 0 }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $entry['missing'] ?? 0 }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $entry['duration_ms'] ?? 0 }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $entry['timestamp'] ? \Carbon\Carbon::parse($entry['timestamp'])->format('d.m.Y H:i') : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">Henüz log kaydı bulunmuyor.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
