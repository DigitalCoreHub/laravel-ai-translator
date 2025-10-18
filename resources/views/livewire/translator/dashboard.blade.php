<div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="max-w-6xl mx-auto px-6 py-10 space-y-8">
        <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">AI Translation Dashboard</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Çoklu provider desteği ile eksik çevirileri hızlıca tamamlayın.</p>
            </div>
            <nav class="flex gap-2">
                <a href="{{ route('ai-translator.dashboard') }}" class="rounded-full bg-slate-200 px-4 py-2 text-sm font-medium text-slate-900 dark:bg-slate-800 dark:text-slate-100">Panel</a>
                <a href="{{ route('ai-translator.settings') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500">Ayarlar</a>
                <a href="{{ route('ai-translator.logs') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500">Log &amp; İstatistik</a>
            </nav>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white/70 p-6 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/70">
            <div class="grid gap-4 md:grid-cols-4">
                <label class="flex flex-col text-sm">
                    <span class="mb-1 font-medium">Kaynak Dil</span>
                    <select wire:model="from" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col text-sm">
                    <span class="mb-1 font-medium">Hedef Dil</span>
                    <select wire:model="to" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col text-sm">
                    <span class="mb-1 font-medium">Provider</span>
                    <select wire:model="provider" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($providers as $name)
                            <option value="{{ $name }}">{{ ucfirst($name) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button wire:click="scan" class="flex-1 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">Eksikleri Tara</button>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button wire:click="translateMissing" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-300">Çevir</button>
                <button wire:click="retranslateAll" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-300">Yeniden Çevir</button>
            </div>
        </section>

        @if ($progressLabel)
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                {{ $progressLabel }}
            </div>
        @endif

        @if ($statusMessage)
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 shadow-sm dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200">
                {{ $statusMessage }}
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white/70 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-100/80 dark:bg-slate-800/70">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Dosya</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Anahtar</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Kaynak</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Çeviri</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Durum</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($entries as $entry)
                            <tr class="bg-white/80 transition hover:bg-slate-50 dark:bg-slate-900/60 dark:hover:bg-slate-900">
                                <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $entry['file'] }}</td>
                                <td class="px-4 py-3 font-medium">{{ $entry['key'] }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $entry['source'] }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $entry['target'] }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $status = $entry['status'];
                                        $statusMap = [
                                            'missing' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
                                            'empty' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
                                            'translated' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                        ];
                                    @endphp
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusMap[$status] ?? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="translateKey(@js($entry['file']), @js($entry['key']))" class="rounded-lg bg-sky-600 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-300">Çevir</button>
                                        <button wire:click="retranslateKey(@js($entry['file']), @js($entry['key']))" class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-300">Yeniden</button>
                                        <button wire:click="edit(@js($entry['file']), @js($entry['key']))" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-slate-100">Düzenle</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">Listelenecek çeviri kaydı bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
