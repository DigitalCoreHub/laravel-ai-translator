<div class="grid gap-8 lg:grid-cols-[260px,1fr]">
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dil Ayarları</h2>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Kaynak Dil</label>
                    <select wire:model="sourceLocale" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Hedef Dil</label>
                    <select wire:model="targetLocale" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Sağlayıcı</label>
                    <select wire:model="provider" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        @foreach ($providers as $providerName)
                            <option value="{{ $providerName }}">{{ strtoupper($providerName) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="scan" class="flex-1 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">Eksikleri Tara</button>
                    <button wire:click="swapLocales" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">⇄</button>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dosyalar</h2>
            <div class="mt-4 space-y-2">
                @forelse ($files as $file)
                    <button wire:click="selectFile('{{ $file['name'] }}')" class="w-full rounded-xl border px-4 py-3 text-left text-sm transition {{ $selectedFile === $file['name'] ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/10 dark:text-indigo-200' : 'border-slate-200 hover:border-indigo-400 hover:bg-indigo-50 dark:border-slate-800 dark:hover:border-indigo-400 dark:hover:bg-slate-800' }}">
                        <div class="font-semibold">{{ $file['name'] }}</div>
                        <div class="mt-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>{{ $file['missing'] }} eksik</span>
                            <span>{{ $file['total'] }} anahtar</span>
                        </div>
                    </button>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Henüz tarama yapılmadı.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="space-y-6">
        @if ($statusMessage)
            <div class="rounded-xl border px-4 py-3 text-sm {{ $statusType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200' : ($statusType === 'info' ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200') }}">
                {{ $statusMessage }}
                @if ($progressTotal)
                    <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">{{ $progressCurrent }}/{{ $progressTotal }}</span>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="translateSelectedFile" @disabled(! $selectedFile) class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300">AI ile Çevir</button>
            <button wire:click="gotoEdit" @disabled(! $selectedFile) class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Manuel Düzenle</button>
            <div class="text-xs text-slate-500 dark:text-slate-400">Seçili dosya: {{ $selectedFile ?? '—' }}</div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-3 text-left font-medium">Anahtar</th>
                        <th class="px-4 py-3 text-left font-medium">Kaynak</th>
                        <th class="px-4 py-3 text-left font-medium">Hedef</th>
                        <th class="px-4 py-3 text-left font-medium">Durum</th>
                        <th class="px-4 py-3 text-right font-medium">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($entries as $entry)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3 align-top font-mono text-xs">{{ $entry['key'] }}</td>
                            <td class="px-4 py-3 align-top text-slate-600 dark:text-slate-300">{{ $entry['source'] }}</td>
                            <td class="px-4 py-3 align-top">
                                @if ($entry['target'])
                                    <span class="text-slate-700 dark:text-slate-200">{{ $entry['target'] }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if ($entry['status'] === 'missing')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">Eksik</span>
                                @elseif($entry['status'] === 'empty')
                                    <span class="inline-flex items-center rounded-full bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">Boş</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">Tamamlandı</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="translateEntry('{{ base64_encode($entry['key']) }}', false)" class="rounded-lg bg-indigo-500 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-indigo-400">Çevir</button>
                                    <button wire:click="translateEntry('{{ base64_encode($entry['key']) }}', true)" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Yeniden Çevir</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">Gösterilecek kayıt yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($lastPreview)
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4 text-sm text-indigo-700 shadow-sm dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-200">
                <h3 class="text-xs font-semibold uppercase tracking-wide">Son AI çıktıları</h3>
                <dl class="mt-3 space-y-2">
                    @foreach ($lastPreview as $key => $value)
                        <div>
                            <dt class="font-medium">{{ $key }}</dt>
                            <dd class="text-indigo-600 dark:text-indigo-200">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
</div>
