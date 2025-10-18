<div class="w-full max-w-md space-y-8 rounded-2xl border border-slate-800 bg-slate-900/60 p-8 shadow-xl">
    <div class="space-y-2 text-center">
        <h1 class="text-2xl font-semibold tracking-tight text-white">AI Translator Panel</h1>
        <p class="text-sm text-slate-400">Yetkili kullanıcılar için güvenli giriş.</p>
    </div>

    <form wire:submit.prevent="login" class="space-y-5">
        <div class="space-y-2">
            <label for="email" class="block text-sm font-medium text-slate-200">E-posta Adresi</label>
            <input
                id="email"
                type="email"
                wire:model="email"
                required
                autofocus
                class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/40 dark:text-black"
            >
            @error('email')
                <p class="text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="password" class="block text-sm font-medium text-slate-200">Parola</label>
            <input
                id="password"
                type="password"
                wire:model="password"
                required
                class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/40 dark:text-black"
            >
            @error('password')
                <p class="text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        @if ($errorMessage)
            <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ $errorMessage }}
            </div>
        @endif

        <button
            type="submit"
            class="w-full rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/60"
        >
            Giriş Yap
        </button>
    </form>

    <p class="text-center text-xs text-slate-500">
        Panel erişimi yalnızca yetkilendirilmiş e-posta adresleri için etkinleştirilmiştir.
    </p>
</div>
