<div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto max-w-5xl px-6 py-10 space-y-8">
        <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Sync Merkezi</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tüm dil dosyalarını manuel veya kuyruk destekli şekilde senkronize edin.</p>
            </div>
            <div class="flex flex-col items-end gap-3">
                @include('ai-translator::livewire.translator.partials.nav')
                @include('ai-translator::livewire.translator.partials.auth-info')
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white/70 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="flex flex-col text-sm">
                    <span class="mb-1 font-medium">Kaynak Dil</span>
                    <select wire:model="from" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col text-sm">
                    <span class="mb-1 font-medium">Provider</span>
                    <select wire:model="provider" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($providers as $option)
                            <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col text-sm md:col-span-2">
                    <span class="mb-1 font-medium">Hedef Diller</span>
                    <select wire:model="targets" multiple class="min-h-[140px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:ring focus:ring-sky-200/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400">
                        @foreach ($locales as $locale)
                            @if ($locale !== $from)
                                <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                            @endif
                        @endforeach
                    </select>
                    <span class="mt-1 text-xs text-slate-500 dark:text-slate-400">Boş bırakırsanız tüm diller otomatik seçilir.</span>
                </label>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" wire:model="useQueue" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900" />
                    Kuyrukta çalıştır
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" wire:model="force" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900" />
                    Mevcut çevirileri yenile
                </label>
                <button wire:click="sync" class="ml-auto rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">Senkronizasyonu Başlat</button>
            </div>
        </section>

        @if ($statusMessage)
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 shadow-sm dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200">
                {{ $statusMessage }}
            </div>
        @endif
    </div>
</div>
