@php
    $items = [
        ['label' => 'Panel', 'route' => 'ai-translator.dashboard'],
        ['label' => 'Sync', 'route' => 'ai-translator.sync'],
        ['label' => 'Queue Status', 'route' => 'ai-translator.queue'],
        ['label' => 'Watch Logs', 'route' => 'ai-translator.watch'],
        ['label' => 'Log & İstatistik', 'route' => 'ai-translator.logs'],
        ['label' => 'Ayarlar', 'route' => 'ai-translator.settings'],
    ];
@endphp

<nav class="flex flex-wrap gap-2">
    @foreach ($items as $item)
        @php
            $active = request()->routeIs($item['route']);
        @endphp
        <a
            href="{{ route($item['route']) }}"
            class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $active
                ? 'border-slate-900 bg-slate-200 text-slate-900 dark:border-slate-200 dark:bg-slate-800 dark:text-slate-100'
                : 'border-slate-300 text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-slate-100' }}"
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
