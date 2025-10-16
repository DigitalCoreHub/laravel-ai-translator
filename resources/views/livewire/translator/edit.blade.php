<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">Manuel Düzenleme</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $file }} | {{ strtoupper($from) }} → {{ strtoupper($to) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="reload" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Yenile</button>
            <button wire:click="saveAll" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-500">Tümünü Kaydet</button>
            <a href="{{ route('ai-translator.dashboard') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Panele Dön</a>
        </div>
    </div>

    @if ($statusMessage)
        <div class="rounded-xl border px-4 py-3 text-sm {{ $statusType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200' }}">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-4 py-3 text-left font-medium">Anahtar</th>
                    <th class="px-4 py-3 text-left font-medium">Kaynak</th>
                    <th class="px-4 py-3 text-left font-medium">Çeviri</th>
                    <th class="px-4 py-3 text-right font-medium">Kaydet</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($entries as $index => $entry)
                    <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs">{{ $entry['key'] }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $entry['source'] }}</td>
                        <td class="px-4 py-3">
                            <textarea wire:model.defer="entries.{{ $index }}.target" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm leading-relaxed shadow-sm focus:border-indigo-400 focus:ring focus:ring-indigo-200/60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></textarea>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="saveEntry({{ $index }})" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-indigo-500">Kaydet</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">Kayıt bulunamadı.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
