<!DOCTYPE html>
<html lang="tr" x-data="{ dark: window.matchMedia('(prefers-color-scheme: dark)').matches }" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel AI Translator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { color-scheme: light dark; }
    </style>
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                <div>
                    <h1 class="text-lg font-semibold">Laravel AI Translator v0.4</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">CLI gücünü Livewire ile birleştiren çeviri paneli</p>
                </div>
                <div class="flex items-center gap-3">
                    <nav class="hidden gap-3 text-sm font-medium md:flex">
                        <a href="{{ route('ai-translator.dashboard') }}" class="rounded px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Panel</a>
                        <a href="{{ route('ai-translator.settings') }}" class="rounded px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Ayarlar</a>
                        <a href="{{ route('ai-translator.logs') }}" class="rounded px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Loglar</a>
                    </nav>
                    <button type="button" @click="dark = ! dark" class="inline-flex items-center gap-2 rounded border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span x-show="!dark">🌙</span>
                        <span x-show="dark">☀️</span>
                        <span>Temayı Değiştir</span>
                    </button>
                </div>
            </div>
        </header>
        <main class="mx-auto w-full max-w-6xl flex-1 px-6 py-8">
            {{ $slot }}
        </main>
        <footer class="border-t border-slate-200 bg-white/60 px-6 py-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
            Laravel AI Translator © {{ date('Y') }}
        </footer>
    </div>
    @livewireScripts
</body>
</html>
