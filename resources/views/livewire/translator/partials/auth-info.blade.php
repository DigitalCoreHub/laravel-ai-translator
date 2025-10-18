@php
    $user = Illuminate\Support\Facades\Auth::user();
@endphp

@if ($user)
    <div class="flex flex-col items-end gap-2 text-xs text-slate-600 dark:text-slate-300">
        <span class="inline-flex items-center gap-2 rounded-full bg-slate-200/70 px-3 py-1 font-medium text-slate-700 dark:bg-slate-800/70 dark:text-slate-100">
            <span>👤 Signed in as:</span>
            <span class="font-semibold">{{ $user->email }}</span>
        </span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
            >
                Logout
            </button>
        </form>
    </div>
@endif
