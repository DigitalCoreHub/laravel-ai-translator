<div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto max-w-5xl px-6 py-10 space-y-8">
        <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Queue Status</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Arka plandaki çeviri işlerinin anlık durumunu görüntüleyin.</p>
            </div>
            <div class="flex flex-col items-end gap-3">
                @include('ai-translator::livewire.translator.partials.nav')
                @include('ai-translator::livewire.translator.partials.auth-info')
            </div>
        </header>

        <div wire:poll.2s="refreshStatus" class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white/70 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Aktif Kuyruk</p>
                        <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $summary }}</p>
                    </div>
                    <div class="flex gap-6 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex flex-col items-center">
                            <span class="text-xs uppercase tracking-wide text-slate-400">Bekleyen</span>
                            <span class="text-lg font-semibold">{{ $totals['pending'] ?? 0 }}</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="text-xs uppercase tracking-wide text-slate-400">Tamamlanan</span>
                            <span class="text-lg font-semibold text-emerald-600 dark:text-emerald-300">{{ $totals['completed'] ?? 0 }}</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="text-xs uppercase tracking-wide text-slate-400">Hatalı</span>
                            <span class="text-lg font-semibold text-rose-600 dark:text-rose-300">{{ $totals['failed'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white/70 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-100/80 dark:bg-slate-800/70">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Durum</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Dosya</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Kaynak → Hedef</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Provider</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Çevrilen</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Toplam</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Süre (ms)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @forelse ($jobs as $job)
                                <tr class="bg-white/80 transition hover:bg-slate-50 dark:bg-slate-900/60 dark:hover:bg-slate-900">
                                    <td class="px-4 py-3 text-sm font-medium">
                                        @php
                                            $status = $job['status'] ?? 'queued';
                                            $map = [
                                                'queued' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                                                'running' => 'bg-sky-200 text-sky-700 dark:bg-sky-600/30 dark:text-sky-200',
                                                'completed' => 'bg-emerald-200 text-emerald-700 dark:bg-emerald-500/30 dark:text-emerald-200',
                                                'failed' => 'bg-rose-200 text-rose-700 dark:bg-rose-500/30 dark:text-rose-200',
                                            ];
                                        @endphp
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $map[$status] ?? 'bg-slate-200 text-slate-700' }}">
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $job['file'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ strtoupper($job['from'] ?? '-') }} → {{ strtoupper($job['to'] ?? '-') }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ ucfirst($job['provider'] ?? '-') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-300">{{ $job['translated'] ?? 0 }}</td>
                                    <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ $job['progress_total'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ $job['duration_ms'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">Aktif kuyruk kaydı bulunamadı.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
